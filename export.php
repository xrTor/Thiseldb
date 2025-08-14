<?php
require_once 'server.php';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="Thiseldb.csv"');

// פתרון לבעיות עברית ב־Excel
echo "\xEF\xBB\xBF";

$output = fopen("php://output", "w");

// כותרות העמודות
fputcsv($output, ['ID', 'Title (EN)', 'Title (HE)', 'Year', 'IMDb Rating', 'Type (HE)', 'IMDb ID']);

// שאילתה עם חיבור לטבלת הסוגים
$res = $conn->query("
  SELECT 
    p.id, p.title_en, p.title_he, p.year, p.imdb_rating, p.imdb_id,
    t.label_he AS type_label_he
  FROM posters p
  LEFT JOIN poster_types t ON p.type_id = t.id
");

while ($row = $res->fetch_assoc()) {
  $id       = $row['id'];
  $title_en = $row['title_en'] ?: '—';
  $title_he = $row['title_he'] ?: '—';
  $year     = $row['year'] ?: '—';
  $rating   = $row['imdb_rating'] ?: '—';
  $type_he  = $row['type_label_he'] ?: '⁉️ לא ידוע';
  $imdb_id  = $row['imdb_id'] ?: '—';

  fputcsv($output, [$id, $title_en, $title_he, $year, $rating, $type_he, $imdb_id]);
}

fclose($output);
$conn->close();
?>