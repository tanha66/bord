<?php
/**
 * Web Push Protocol implementation for Bordkhan.
 * Implements RFC 8030 (Web Push) + RFC 8291 (Message Encryption) + RFC 8292 (VAPID).
 * Requires: PHP 7.1+ with openssl and curl extensions.
 */

function wp_b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function wp_b64url_decode(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Create a VAPID JWT (ES256).
 */
function wp_create_jwt(array $claims, string $privateKeyB64, string $publicKeyB64): string {
    $header = wp_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $body = wp_b64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
    $signingInput = $header . '.' . $body;

    // Decode private key from base64url
    $privKeyBytes = wp_b64url_decode($privateKeyB64);
    // Decode public key from base64url (uncompressed point: 04 + x + y)
    $pubKeyBytes = wp_b64url_decode($publicKeyB64);

    // Build PEM private key (EC, prime256v1)
    // ASN.1 structure for EC private key
    $oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1 OID
    $ecPrivKey = "\x30\x74\x02\x01\x01\x04\x20" . $privKeyBytes . "\xa1\x44\x03\x42\x00" . $pubKeyBytes;
    $seq = "\x30" . chr(0x10 + strlen($ecPrivKey) + strlen($oid) + 4) . "\x02\x01\x01" . $oid . "\x04\x20" . $privKeyBytes . "\xa1\x44\x03\x42\x00" . $pubKeyBytes;
    // Simpler approach: use ASN1 encoder
    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
        . chunk_split(base64_encode(self_asn1_ec_key($privKeyBytes, $pubKeyBytes)), 64, "\n")
        . "-----END EC PRIVATE KEY-----\n";

    $key = openssl_pkey_get_private($pem);
    if (!$key) return '';

    $signature = '';
    if (!openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) return '';

    // Convert DER signature to raw R+S (64 bytes)
    $sig = self_der_sig_to_raw($signature);
    if (!$sig) return '';

    return $signingInput . '.' . wp_b64url_encode($sig);
}

/**
 * Build ASN.1 DER EC private key structure.
 */
function self_asn1_ec_key(string $privKey, string $pubKey): string {
    // ECPrivateKey ::= SEQUENCE {
    //   version INTEGER { ecPrivkeyVer1(1) },
    //   privateKey OCTET STRING,
    //   parameters [0] ECParameters OPTIONAL,
    //   publicKey [1] BIT STRING OPTIONAL
    // }
    $version = "\x02\x01\x01"; // INTEGER 1
    $privOctet = "\x04\x20" . $privKey; // OCTET STRING (32 bytes)
    $params = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // [0] prime256v1
    $pubBit = "\xa1\x44\x03\x42\x00" . $pubKey; // [1] BIT STRING (uncompressed point)

    $inner = $version . $privOctet . $params . $pubBit;
    return "\x30" . self_asn1_length(strlen($inner)) . $inner;
}

function self_asn1_length(int $len): string {
    if ($len < 0x80) return chr($len);
    if ($len < 0x100) return "\x81" . chr($len);
    return "\x82" . pack('n', $len);
}

/**
 * Convert DER-encoded ECDSA signature to raw R||S (64 bytes).
 */
function self_der_sig_to_raw(string $der): ?string {
    if (strlen($der) < 8) return null;
    $pos = 0;
    // SEQUENCE
    if (ord($der[$pos++]) !== 0x30) return null;
    $seqLen = ord($der[$pos++]);
    if ($seqLen & 0x80) { $pos += ($seqLen & 0x7f); $seqLen = 0; }
    // R INTEGER
    if (ord($der[$pos++]) !== 0x02) return null;
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen); $pos += $rLen;
    // S INTEGER
    if (ord($der[$pos++]) !== 0x02) return null;
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);
    // Remove leading zeros, pad to 32 bytes
    $r = ltrim($r, "\x00"); $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

/**
 * HKDF-SHA256 extract + expand (RFC 5869).
 */
function wp_hkdf(string $ikm, string $salt, string $info, int $length): string {
    if (function_exists('hash_hkdf')) {
        return hash_hkdf('sha256', $ikm, $length, $info, $salt);
    }
    // Manual HKDF
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t = '';
    $lastBlock = '';
    for ($i = 1; strlen($t) < $length; $i++) {
        $lastBlock = hash_hmac('sha256', $lastBlock . $info . chr($i), $prk, true);
        $t .= $lastBlock;
    }
    return substr($t, 0, $length);
}

/**
 * Encrypt payload for Web Push (RFC 8291).
 */
function wp_encrypt(string $payload, string $userPubKeyB64, string $userAuthB64, string $serverPubKeyB64, string $serverPrivKeyB64): ?array {
    $userPubKey = wp_b64url_decode($userPubKeyB64); // 65 bytes uncompressed point
    $userAuth = wp_b64url_decode($userAuthB64);     // 16 bytes
    $serverPubKey = wp_b64url_decode($serverPubKeyB64);
    $serverPrivKey = wp_b64url_decode($serverPrivKeyB64);

    // 1. ECDH shared secret
    $sharedSecret = self_ecdh($serverPrivKey, $userPubKey);
    if (!$sharedSecret) return null;

    // 2. Derive keys using HKDF
    // auth_info = "Content-Encoding: auth\0"
    $authInfo = "Content-Encoding: auth\x00";
    $ikm = wp_hkdf($userAuth, $sharedSecret, $authInfo, 32);

    // key_info = "Content-Encoding: aesgcm\0" || 0x00 || context
    // context = "P-256\0" || len(client_pub) || client_pub || len(server_pub) || server_pub
    $context = "P-256\x00"
        . pack('n', strlen($userPubKey)) . $userPubKey
        . pack('n', strlen($serverPubKey)) . $serverPubKey;
    $keyInfo = "Content-Encoding: aesgcm\x00" . $context;
    $nonceInfo = "Content-Encoding: nonce\x00" . $context;

    $cek = wp_hkdf($ikm, '', $keyInfo, 16);    // Content Encryption Key
    $nonce = wp_hkdf($ikm, '', $nonceInfo, 12);  // Nonce

    // 3. Add padding (2-byte record size prefix + padding)
    $padLen = 0; // No padding for simplicity (could add for privacy)
    $paddedPayload = pack('n', $padLen) . str_repeat("\x00", $padLen) . $payload;

    // 4. AES-128-GCM encrypt
    $tag = '';
    $encrypted = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($encrypted === false) return null;

    return [
        'ciphertext' => $encrypted . $tag,
        'nonce' => $nonce,
        'server_pub' => $serverPubKey,
    ];
}

/**
 * ECDH: compute shared secret using server private key and user public key.
 */
function self_ecdh(string $privKey, string $pubKey): ?string {
    // Build server EC private key PEM
    $pubKeyFromPriv = self_ec_public_from_private($privKey);
    if (!$pubKeyFromPriv) return null;

    $serverPem = "-----BEGIN EC PRIVATE KEY-----\n"
        . chunk_split(base64_encode(self_asn1_ec_key($privKey, $pubKeyFromPriv)), 64, "\n")
        . "-----END EC PRIVATE KEY-----\n";

    $serverKey = openssl_pkey_get_private($serverPem);
    if (!$serverKey) return null;

    // Build user public key PEM
    $userPubPem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode(self_asn1_ec_pub($pubKey)), 64, "\n")
        . "-----END PUBLIC KEY-----\n";

    $userKey = openssl_pkey_get_public($userPubPem);
    if (!$userKey) return null;

    $shared = openssl_pkey_derive($userKey, $serverKey);
    return $shared ?: null;
}

/**
 * Extract public key point from private key bytes.
 */
function self_ec_public_from_private(string $privKey): ?string {
    // We need to compute the public key from private key
    // Use openssl to do this
    $oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $ecKey = "\x30" . self_asn1_length(3 + 34 + strlen($oid)) . "\x02\x01\x01" . "\x04\x20" . $privKey . $oid;
    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
        . chunk_split(base64_encode($ecKey), 64, "\n")
        . "-----END EC PRIVATE KEY-----\n";

    $key = openssl_pkey_get_private($pem);
    if (!$key) return null;

    $details = openssl_pkey_get_details($key);
    if (!$details || !isset($details['ec'])) return null;

    $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    return "\x04" . $x . $y;
}

/**
 * Build ASN.1 DER for EC public key (uncompressed point).
 */
function self_asn1_ec_pub(string $point): string {
    // SubjectPublicKeyInfo ::= SEQUENCE {
    //   algorithm AlgorithmIdentifier,
    //   subjectPublicKey BIT STRING
    // }
    $oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1
    $ecOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";   // id-ecPublicKey
    $algo = "\x30" . self_asn1_length(strlen($ecOid) + strlen($oid) + 4)
        . $ecOid . $oid;
    $bitString = "\x03" . self_asn1_length(strlen($point) + 1) . "\x00" . $point;
    $inner = $algo . $bitString;
    return "\x30" . self_asn1_length(strlen($inner)) . $inner;
}

/**
 * Generate an ephemeral EC key pair for encryption.
 * Returns ['private' => base64url, 'public' => base64url]
 */
function wp_generate_ephemeral_key(): ?array {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) return null;

    $details = openssl_pkey_get_details($key);
    $d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
    $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $pubPoint = "\x04" . $x . $y;

    return [
        'private' => wp_b64url_encode($d),
        'public' => wp_b64url_encode($pubPoint),
        'private_raw' => $d,
        'public_raw' => $pubPoint,
    ];
}

/**
 * Send a Web Push notification.
 * Full implementation: VAPID JWT + ECDH encryption + HTTP POST.
 */
function wp_send_push(array $subscription, string $payloadJson, array $vapidKeys): bool {
    $endpoint = $subscription['endpoint'] ?? '';
    $p256dh = $subscription['p256dh'] ?? '';
    $auth = $subscription['auth'] ?? '';
    if (!$endpoint || !$p256dh || !$auth) return false;

    // Generate ephemeral key pair for this message
    $eph = wp_generate_ephemeral_key();
    if (!$eph) return false;

    // Encrypt payload
    $enc = wp_encrypt($payloadJson, $p256dh, $auth, $eph['public'], $eph['private']);
    if (!$enc) return false;

    // Create VAPID JWT
    $aud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $claims = [
        'aud' => $aud,
        'exp' => time() + 86400,
        'sub' => 'mailto:push@' . parse_url(SITE_URL, PHP_URL_HOST),
    ];
    $jwt = wp_create_jwt($claims, $vapidKeys['private'], $vapidKeys['public']);
    if (!$jwt) return false;

    // Build Crypto-Key header
    $cryptoKey = 'dh=' . wp_b64url_encode($enc['server_pub']) . ';p256ecdsa=' . wp_b64url_encode(wp_b64url_decode($vapidKeys['public']));

    // HTTP POST
    $headers = [
        'TTL: 86400',
        'Content-Type: application/octet-stream',
        'Content-Encoding: aesgcm',
        'Encryption: ' . wp_b64url_encode($enc['nonce']),
        'Crypto-Key: ' . $cryptoKey,
        'Authorization: vapid t=' . $jwt . ', k=' . wp_b64url_encode(wp_b64url_decode($vapidKeys['public'])),
    ];

    if (!function_exists('curl_init')) return false;
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $enc['ciphertext'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 410 || $httpCode === 404) {
        // Subscription expired - caller should remove it
        return false;
    }

    return $httpCode >= 200 && $httpCode < 300;
}
