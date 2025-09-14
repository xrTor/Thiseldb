<?php
require_once 'server.php';
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<h2 class='text-center my-4'>🏷️ כל התגיות</h2>";

// שליפת תגיות מהטבלה
$res = $conn->query("
    SELECT TRIM(genre) AS genre, COUNT(*) AS count
    FROM user_tags
    WHERE genre IS NOT NULL AND TRIM(genre) != ''
    GROUP BY TRIM(genre)
    ORDER BY count DESC
");

$tags = [];
while ($row = $res->fetch_assoc()) {
    $tags[] = [
        'name'  => $row['genre'],
        'count' => (int)$row['count'],
    ];
}

// צבעים מתחלפים
$colors = [
    "#d1ecf1", "#d4edda", "#fff3cd", "#f8d7da",
    "#e2e3e5", "#fde2ff", "#e0f7fa", "#fce4ec",
    "#f1f8e9", "#fff8e1", "#e3f2fd", "#ede7f6"
];

// עיצוב CSS
echo "<style>
.genre-box {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 13px;
  color: #222;
  min-width: 70px;
  white-space: nowrap;
  transition: all 0.2s ease;
  opacity: 0.92;
  cursor: pointer;
}
.genre-box:hover {
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  transform: scale(1.05);
  opacity: 1;
}
</style>";

if (empty($tags)) {
    echo "<p class='text-center text-muted'>לא נמצאו תגיות.</p>";
} else {
    echo "<table class='mx-auto' style='direction: rtl; max-width: 1000px;'>";
    $cols = 8;
    $i = 0;

    // לשמור עקביות תצוגה עם דף התוצאות
    $limitParam = (int)($_SESSION['limit'] ?? 50);
    $viewParam  = (string)($_SESSION['view_mode'] ?? 'modern_grid');

    foreach ($tags as $tag) {
        if ($i % $cols === 0) echo "<tr>";

        $name  = htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8');
        $count = (int)$tag['count'];

        // הפניה אל דף החיפוש (home.php) עם הפרמטר user_tag
        $url = "home.php?search=&year=&min_rating=&metacritic=&rt_score=&imdb_id=&genre=&user_tag="
             . urlencode($tag['name'])
             . "&actor=&directors=&producers=&writers=&composers=&cinematographers=&lang_code=&country=&runtime=&network="
             . "&search_mode=and&limit={$limitParam}&view=" . urlencode($viewParam) . "&sort=";

        $color = $colors[$i % count($colors)];

        echo "<td style='padding: 4px; text-align: center;'>";
        echo "<a href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' class='text-decoration-none'>";
        echo "<div class='genre-box' style='background: $color;'>";
        echo "$name <span style='color:#555'>($count)</span>";
        echo "</div></a></td>";

        if (++$i % $cols === 0) echo "</tr>";
    }
    if ($i % $cols !== 0) echo str_repeat("<td></td>", $cols - ($i % $cols)) . "</tr>";
    echo "</table>";
}
