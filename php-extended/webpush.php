<?php
/**
 * Web Push — simplified implementation.
 * Implements RFC 8030 + RFC 8291 + RFC 8292.
 * Requires: PHP 7.1+ with openssl, curl, gmp extensions.
 */

function wp_b64url(string $data, bool $encode = true): string {
    if ($encode) return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Build PEM from raw EC private + public key bytes (P-256). */
function wp_ec_key_to_pem(string $d, string $x, string $y): string {
    // Use openssl to derive the proper key format
    // Build a minimal PKCS8 EC key
    $pubPoint = "\x04" . $x . $y;
    // ECPrivateKey DER
    $version = "\x02\x01\x01";
    $privOctet = "\x04\x20" . $d;
    $params = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $pubBits = "\xa1\x44\x03\x42\x00" . $pubPoint;
    $inner = $version . $privOctet . $params . $pubBits;
    $der = "\x30" . self_asn1_len(strlen($inner)) . $inner;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END EC PRIVATE KEY-----\n";
}

/** Build PEM for EC public key (uncompressed point). */
function wp_ec_pub_to_pem(string $point): string {
    $algo = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $bits = "\x03" . self_asn1_len(strlen($point) + 1) . "\x00" . $point;
    $inner = $algo . $bits;
    $der = "\x30" . self_asn1_len(strlen($inner)) . $inner;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END PUBLIC KEY-----\n";
}

function self_asn1_len(int $len): string {
    if ($len < 128) return chr($len);
    if ($len < 256) return "\x81" . chr($len);
    return "\x82" . pack('n', $len);
}

/** Create ES256 JWT. */
function wp_jwt(array $claims, string $privD, string $pubX, string $pubY): string {
    $header = wp_b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $body = wp_b64url(json_encode($claims));
    $input = $header . '.' . $body;
    $pem = wp_ec_key_to_pem($privD, $pubX, $pubY);
    $key = openssl_pkey_get_private($pem);
    if (!$key) return '';
    $sig = '';
    if (!openssl_sign($input, $sig, $key, OPENSSL_ALGO_SHA256)) return '';
    // DER → raw R||S (64 bytes)
    $pos = 2; // skip SEQUENCE tag + length
    $r = self_asn1_int($sig, $pos);
    $s = self_asn1_int($sig, $pos);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $input . '.' . wp_b64url($r . $s);
}

function self_asn1_int(string $der, int &$pos): string {
    if (ord($der[$pos]) !== 0x02) return '';
    $pos++;
    $len = ord($der[$pos]); $pos++;
    $val = substr($der, $pos, $len);
    $pos += $len;
    return $val;
}

/** HKDF-SHA256 */
function wp_hkdf(string $ikm, string $salt, string $info, int $len): string {
    if (function_exists('hash_hkdf')) return hash_hkdf('sha256', $ikm, $len, $info, $salt);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $out = ''; $block = '';
    for ($i = 1; strlen($out) < $len; $i++) {
        $block = hash_hmac('sha256', $block . $info . chr($i), $prk, true);
        $out .= $block;
    }
    return substr($out, 0, $len);
}

/** Generate ephemeral EC key pair. Returns {d, x, y, d_b64, xy_b64} */
function wp_gen_key(): ?array {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) return null;
    $det = openssl_pkey_get_details($key);
    if (!$det || !isset($det['ec'])) return null;
    $d = str_pad($det['ec']['d'], 32, "\x00", STR_PAD_LEFT);
    $x = str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    return ['d' => $d, 'x' => $x, 'y' => $y, 'pub_point' => "\x04" . $x . $y];
}

/** ECDH shared secret. */
function wp_ecdh(string $myD, string $myX, string $myY, string $theirPoint): ?string {
    $myPem = wp_ec_key_to_pem($myD, $myX, $myY);
    $myKey = openssl_pkey_get_private($myPem);
    if (!$myKey) return null;
    $theirPem = wp_ec_pub_to_pem($theirPoint);
    $theirKey = openssl_pkey_get_public($theirPem);
    if (!$theirKey) return null;
    $shared = openssl_pkey_derive($theirKey, $myKey, 32);
    return $shared ?: null;
}

/** Encrypt payload per RFC 8291. */
function wp_encrypt(string $payload, string $subPubKey, string $subAuth, string $srvD, string $srvX, string $srvY): ?array {
    $srvPub = "\x04" . $srvX . $srvY;
    // 1. ECDH
    $shared = wp_ecdh($srvD, $srvX, $srvY, $subPubKey);
    if (!$shared) return null;
    // 2. auth secret → IKM
    $ikm = wp_hkdf($subAuth, $shared, "Content-Encoding: auth\x00", 32);
    // 3. context
    $ctx = "P-256\x00" . pack('n', strlen($subPubKey)) . $subPubKey . pack('n', strlen($srvPub)) . $srvPub;
    // 4. Derive CEK and nonce
    $cek = wp_hkdf($ikm, '', "Content-Encoding: aesgcm\x00" . $ctx, 16);
    $nonce = wp_hkdf($ikm, '', "Content-Encoding: nonce\x00" . $ctx, 12);
    // 5. Pad + encrypt
    $padded = pack('n', 0) . $payload; // 2-byte padding length = 0
    $tag = '';
    $ct = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ct === false) return null;
    return ['body' => $ct . $tag, 'nonce' => $nonce, 'pub' => $srvPub];
}

/** Send push notification. Returns true on success. */
function wp_send_push(array $sub, string $payloadJson, array $vapid): bool {
    $endpoint = $sub['endpoint'] ?? '';
    $p256dh = $sub['p256dh'] ?? '';
    $auth = $sub['auth'] ?? '';
    if (!$endpoint || !$p256dh || !$auth) return false;

    $vapidPub = wp_b64url($vapid['public_raw'] ?? wp_b64url($vapid['public'], false));
    $vapidPrv = $vapid['private_raw'] ?? wp_b64url($vapid['private'], false);

    // Generate ephemeral key
    $eph = wp_gen_key();
    if (!$eph) return false;

    // Decode subscriber keys
    $subPubRaw = wp_b64url($p256dh, false);
    $subAuthRaw = wp_b64url($auth, false);
    $vapidPubRaw = wp_b64url($vapid['public'], false);
    $vapidPrvRaw = wp_b64url($vapid['private'], false);

    // Extract x,y from VAPID public key for JWT
    $vx = substr($vapidPubRaw, 1, 32);
    $vy = substr($vapidPubRaw, 33, 32);

    // Encrypt
    $enc = wp_encrypt($payloadJson, $subPubRaw, $subAuthRaw, $vapidPrvRaw, $vx, $vy);
    if (!$enc) {
        // Fallback: try with ephemeral key
        $shared = wp_ecdh($eph['d'], $eph['x'], $eph['y'], $subPubRaw);
        if (!$shared) return false;
        $ikm = wp_hkdf($subAuthRaw, $shared, "Content-Encoding: auth\x00", 32);
        $ctx = "P-256\x00" . pack('n', strlen($subPubRaw)) . $subPubRaw . pack('n', strlen($eph['pub_point'])) . $eph['pub_point'];
        $cek = wp_hkdf($ikm, '', "Content-Encoding: aesgcm\x00" . $ctx, 16);
        $nonce = wp_hkdf($ikm, '', "Content-Encoding: nonce\x00" . $ctx, 12);
        $padded = pack('n', 0) . $payloadJson;
        $tag = '';
        $ct = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ct === false) return false;
        $enc = ['body' => $ct . $tag, 'nonce' => $nonce, 'pub' => $eph['pub_point']];
        $useEph = true;
    } else {
        $useEph = false;
    }

    // VAPID JWT
    $aud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $jwt = wp_jwt([
        'aud' => $aud,
        'exp' => time() + 43200,
        'sub' => 'mailto:push@' . parse_url(SITE_URL, PHP_URL_HOST),
    ], $vapidPrvRaw, $vx, $vy);
    if (!$jwt) return false;

    $pubKeyB64 = wp_b64url($vapidPubRaw);
    $dhKeyB64 = wp_b64url($enc['pub']);
    $saltB64 = wp_b64url($enc['nonce']);

    $headers = [
        'TTL: 86400',
        'Content-Encoding: aesgcm',
        'Encryption: ' . $saltB64,
        'Crypto-Key: dh=' . $dhKeyB64,
        'Authorization: WebPush ' . $jwt,
    ];

    if (!function_exists('curl_init')) return false;
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $enc['body'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}
