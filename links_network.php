<?php
require_once 'server.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// שליפת רשתות - שימוש בעמודה "networks"
$res = $conn->query("SELECT networks FROM posters WHERE networks IS NOT NULL AND networks != ''");

$networks = [];

while ($row = $res->fetch_assoc()) {
    // תמיכה גם במחרוזת וגם ב-JSON
    $raw = $row['networks'];
    $list = [];

    if (is_string($raw) && strlen($raw)) {
        $tryJson = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tryJson)) {
            $list = $tryJson;
        } else {
            $list = explode(',', $raw);
        }
    }

    foreach ($list as $g) {
        $g = trim((string)$g);
        if ($g !== '') {
            $key = mb_strtolower($g, 'UTF-8');
            if (!isset($networks[$key])) {
                $networks[$key] = ['label' => $g, 'count' => 1];
            } else {
                $networks[$key]['count']++;
            }
        }
    }
}

// מיון לפי כמות יורדת
usort($networks, fn($a, $b) => $b['count'] <=> $a['count']);

$colors = [
    "#d1ecf1", "#d4edda", "#fff3cd", "#f8d7da",
    "#e2e3e5", "#fde2ff", "#e0f7fa", "#fce4ec",
    "#f1f8e9", "#fff8e1", "#e3f2fd", "#ede7f6"
];

// כותרת
echo "<h2 class='text-center my-4'>📡 כל הרשתות</h2>";

// עיצוב (כמו בקובץ המקורי)
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

echo "<table class='mx-auto' style='direction: rtl; max-width: 1000px;'>";
$cols = 8;
$i = 0;

$limitParam = (int)($_SESSION['limit'] ?? 50);
$viewParam  = (string)($_SESSION['view_mode'] ?? 'modern_grid');

foreach ($networks as $g) {
    if ($i % $cols === 0) echo "<tr>";

    $name  = htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8');
    $count = (int)$g['count'];

    // 🔗 שינוי יחיד: הפניה ל-home.php עם פרמטרים של החיפוש
    $url = "home.php?search=&year=&min_rating=&metacritic=&rt_score=&imdb_id=&genre=&user_tag=&actor=&directors=&producers=&writers=&composers=&cinematographers=&lang_code=&country=&runtime=&network="
         . urlencode($g['label'])
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
