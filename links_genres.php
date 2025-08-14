<?php
require_once 'server.php';

// שליפת ז'אנרים
$res = $conn->query("SELECT genre FROM posters WHERE genre IS NOT NULL AND genre != ''");

$genres = [];

while ($row = $res->fetch_assoc()) {
    $list = explode(',', $row['genre']);
    foreach ($list as $g) {
        $g = trim($g);
        if ($g !== '') {
            $key = mb_strtolower($g);
            if (!isset($genres[$key])) {
                $genres[$key] = ['label' => $g, 'count' => 1];
            } else {
                $genres[$key]['count']++;
            }
        }
    }
}

usort($genres, fn($a, $b) => $b['count'] - $a['count']);

$colors = [
    "#d1ecf1", "#d4edda", "#fff3cd", "#f8d7da",
    "#e2e3e5", "#fde2ff", "#e0f7fa", "#fce4ec",
    "#f1f8e9", "#fff8e1", "#e3f2fd", "#ede7f6"
];

// כותרת
echo "<h2 class='text-center my-4'>🎨 כל הז'אנרים</h2>";

// עיצוב
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

foreach ($genres as $g) {
    if ($i % $cols === 0) echo "<tr>";

    $name = htmlspecialchars($g['label']);
    $count = $g['count'];
    $url = "genre.php?name=" . urlencode($g['label']);
    $color = $colors[$i % count($colors)];

    echo "<td style='padding: 4px; text-align: center;'>";
    echo "<a href='$url' class='text-decoration-none'>";
    echo "<div class='genre-box' style='background: $color;'>";
    echo "$name <span style='color:#555'>($count)</span>";
    echo "</div></a></td>";

    if (++$i % $cols === 0) echo "</tr>";
}
if ($i % $cols !== 0) echo str_repeat("<td></td>", $cols - ($i % $cols)) . "</tr>";
echo "</table>";

?>
