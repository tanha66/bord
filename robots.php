<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin\n";
echo "Disallow: /settings\n";
echo "Disallow: /wallet\n";
echo "Disallow: /my-tips\n";
echo "Disallow: /bookmarks\n";
echo "Disallow: /favorites\n";
echo "Disallow: /notifications\n";
echo "Disallow: /upload\n";
echo "Disallow: /serve.php\n";
echo "Disallow: /install.php\n";
echo "Sitemap: " . rtrim(SITE_URL, '/') . "/sitemap.xml\n";
