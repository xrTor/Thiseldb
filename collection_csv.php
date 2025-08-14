<?php
require_once 'server.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) die("❌ אוסף לא צוין");

$stmt = $conn->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->bind_param("i", $id); $stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("❌ האוסף לא נמצא");
$collection = $result->fetch_assoc();
$stmt->close();

function safe_filename($name) {
    // הסרת תווים אסורים (שומרים על מספרים, אותיות, עברית ואנגלית, שאר תווים אסורים ימחקו)
    $name = preg_replace('/[\/\\\\\?\%\*\:\|\"\'<>]/u', '', $name);
    // רווחים ל-_
    $name = str_replace(' ', '_', $name);
    return $name;
}

$filename = safe_filename($collection['name']) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // BOM ל־UTF-8

// כאן תבנה את שורת הכותרות
echo "English Title,Hebrew Title,Year,IMDb ID\n";

// שליפת הפוסטרים באוסף (לפי הסדר שאתה רוצה)
$stmt = $conn->prepare("
    SELECT p.title_en, p.title_he, p.year, p.imdb_id
    FROM poster_collections pc
    JOIN posters p ON p.id = pc.poster_id
    WHERE pc.collection_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    // שים לב: אם יש פסיקים בטקסט, סוגר במרכאות
    echo '"' . str_replace('"', '""', $row['title_en']) . '",';
    echo '"' . str_replace('"', '""', $row['title_he']) . '",';
    echo '"' . str_replace('"', '""', $row['year']) . '",';
    echo '"' . str_replace('"', '""', $row['imdb_id']) . '"' . "\n";
}
$stmt->close();
$conn->close();
?>
