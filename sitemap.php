<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$base = rtrim(SITE_URL, '/');
echo '  <url><loc>' . htmlspecialchars($base . '/') . '</loc><priority>1.0</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/tips') . '</loc><priority>0.9</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/boards') . '</loc><priority>0.8</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/repairs') . '</loc><priority>0.8</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/leaderboard') . '</loc><priority>0.7</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/premium') . '</loc><priority>0.7</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/about') . '</loc><priority>0.6</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/terms') . '</loc><priority>0.5</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/privacy') . '</loc><priority>0.5</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/contact') . '</loc><priority>0.6</priority></url>' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/tour') . '</loc><priority>0.6</priority></url>' . "\n";
try {
    $rows = db()->query("SELECT id, DATE_FORMAT(COALESCE(published_at, created_at), '%Y-%m-%d') d FROM tips WHERE status='published'")->fetchAll();
    foreach ($rows as $r) {
        echo '  <url><loc>' . htmlspecialchars($base . '/tip/' . $r['id']) . '</loc><lastmod>' . $r['d'] . '</lastmod><priority>0.8</priority></url>' . "\n";
    }
    $boards = db()->query("SELECT id, DATE_FORMAT(updated_at, '%Y-%m-%d') d FROM boards WHERE status='approved' AND stock>0")->fetchAll();
    foreach ($boards as $b) {
        echo '  <url><loc>' . htmlspecialchars($base . '/board/' . $b['id']) . '</loc><lastmod>' . $b['d'] . '</lastmod><priority>0.7</priority></url>' . "\n";
    }
} catch (Throwable $e) {
    // ignore: sitemap still returns static URLs
}
echo '</urlset>';
