<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$lines = [
    'User-agent: *',
    'Allow: /',
    // صفحات خصوصی و فرم‌ها
    'Disallow: /admin',
    'Disallow: /settings',
    'Disallow: /wallet',
    'Disallow: /wallet-plus',
    'Disallow: /profile',
    'Disallow: /profile-edit',
    'Disallow: /tickets',
    'Disallow: /my-tips',
    'Disallow: /my-boards',
    'Disallow: /bookmarks',
    'Disallow: /favorites',
    'Disallow: /notifications',
    'Disallow: /upload',
    'Disallow: /seller-apply',
    'Disallow: /login',
    'Disallow: /register',
    'Disallow: /verify',
    'Disallow: /forgot',
    // زیرساخت
    'Disallow: /serve',
    'Disallow: /serve.php',
    'Disallow: /install.php',
    'Disallow: /migrate.php',
    'Disallow: /php-extended/',
    'Disallow: /config.php',
    'Disallow: /sql/',
    '',
    'Sitemap: ' . rtrim(SITE_URL, '/') . '/sitemap.xml',
];
echo implode("\n", $lines) . "\n";
