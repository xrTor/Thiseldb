<?php
// sitemap.php — מייצר XML דינמי לכל האתר

header("Content-Type: application/xml; charset=UTF-8");
require_once __DIR__ . "/server.php";

// בסיס הכתובת הראשי
$base = "https://thiseldb.me/";

// הדפס כותרת XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

<?php
// === עמודים ראשיים (ללא manage) ===
$staticPages = [
    "home.php",
    "bar.php",
    "about.php",
    "contact.php",
    "poster.php",
    "collections.php",
    "connections.php",
    "similar_all.php",
    "connections_stats.php",
    "stats.php",
    "imdb.php",
    "tmdb.php",
    "tvdb.php",
    "full-info.php",
    "full-info-text.php",
    "full-info-he.php",
    "full-info-text-he.php"
];

foreach ($staticPages as $page) {
    echo "  <url><loc>{$base}{$page}</loc></url>\n";
}

// === פוסטרים ===
$res = $conn->query("SELECT id FROM posters ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    echo "  <url><loc>{$base}poster.php?id={$id}</loc></url>\n";
}

// === אוספים ===
$res = $conn->query("SELECT id FROM collections ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    echo "  <url><loc>{$base}collection.php?id={$id}</loc></url>\n";
}
?>
</urlset>
