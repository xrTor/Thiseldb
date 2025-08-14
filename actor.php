<?php
include 'header.php';
require_once 'server.php';

$name = trim($_GET['name'] ?? '');

if ($name == '') {
    echo "<p>❌ שם לא סופק</p>";
    include 'footer.php';
    exit;
}

function safe($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// מיפוי בין שדה למסד לתפקיד
$roles_map = [
    'actors'           => 'שחקן/ית',
    'directors'        => 'במאי/ת',
    'writers'          => 'תסריטאי/ת',
    'producers'        => 'מפיק/ה',
    'cinematographers' => 'צלם/ת',
    'composers'        => 'מלחין/ה'
];

// בודקים אילו שדות קיימים בפועל במסד
$columns = [];
$res_cols = $conn->query("SHOW COLUMNS FROM posters");
while ($col = $res_cols->fetch_assoc()) {
    $columns[] = $col['Field'];
}
// משאירים במיפוי רק את אלו שבפועל קיימים
$roles_map = array_filter($roles_map, fn($field) => in_array($field, $columns), ARRAY_FILTER_USE_KEY);

// בונים WHERE על פי השדות הקיימים
$where = [];
$params = [];
foreach (array_keys($roles_map) as $field) {
    $where[] = "$field LIKE ?";
    $params[] = "%$name%";
}
$where_sql = implode(' OR ', $where);

$stmt = $conn->prepare("SELECT * FROM posters WHERE $where_sql ORDER BY year DESC");
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$res = $stmt->get_result();

echo "<h2>דף יוצר: " . safe($name) . "</h2>";

if ($res->num_rows == 0) {
    echo "<p>לא נמצאו פוסטרים עבור שם זה.</p>";
    include 'footer.php';
    exit;
}

// עיצוב מודרני עם פוסטר, שמות, לינק IMDb
echo '<div style="display:flex;flex-direction:column;gap:26px;max-width:750px;margin:auto">';
while ($row = $res->fetch_assoc()) {
    $poster_roles = [];
    foreach ($roles_map as $field => $label) {
        $names_arr = array_map('trim', explode(',', $row[$field] ?? ''));
        foreach ($names_arr as $n) {
            if (strcasecmp($n, $name) == 0) {
                $poster_roles[] = $label;
                break;
            }
        }
    }
    $title_he = $row['title_he'] ?: '';
    $title_en = $row['title_en'] ?: '';
    $year = $row['year'] ?: '';
    $img = $row['image_url'] ?: 'no-poster.png';
    $imdb = $row['imdb_link'] ?: '';
    $p_link = "poster.php?id=" . intval($row['id']);
    echo '<div style="display:flex;align-items:center;background:#fff;border-radius:16px;padding:18px 14px;box-shadow:0 2px 10px #e4e4e4">';
    echo '  <a href="'.safe($p_link).'" target="_blank"><img src="'.safe($img).'" alt="" style="width:90px;height:130px;object-fit:cover;border-radius:12px;margin-left:16px"></a>';
    echo '  <div style="flex:1">';
    echo '    <div style="font-size:20px;font-weight:bold;color:#1a237e">';
    if ($imdb) {
        echo '<a href="'.safe($imdb).'" target="_blank" style="color:#1a237e;text-decoration:none">';
    }
    echo safe($title_he);
    if ($title_he && $title_en) echo " | ";
    echo safe($title_en);
    if ($imdb) echo '</a>';
    echo '    </div>';
    if ($year) echo '<div style="font-size:15px;color:#777">שנה: '.safe($year).'</div>';
    echo '    <div style="margin-top:5px;"><b>תפקידים:</b> <span style="color:#0d47a1">'.implode(', ', $poster_roles).'</span></div>';
    echo '  </div>';
    echo '</div>';
}
echo '</div>';

include 'footer.php';
?>
