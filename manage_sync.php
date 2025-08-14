<?php include 'header.php'; 
require_once 'server.php';

set_time_limit(3000000);

$conn->set_charset("utf8");

// שליפת כל הפוסטרים עם image_url תקף
$result = $conn->query("SELECT id, image_url, imdb_id FROM posters WHERE image_url LIKE 'http%'");

echo "<h3>📥 סנכרון תמונות מהאינטרנט</h3>";
echo "<center><ul>";

while ($row = $result->fetch_assoc()) {
    $id    = $row['id'];
    $url   = $row['image_url'];
    $imdb  = $row['imdb_id'];

    $extension = pathinfo($url, PATHINFO_EXTENSION);
    if (!$extension || strlen($extension) > 5) $extension = 'jpg'; // סיומת ברירת מחדל

    // אם אין imdb_id נשתמש ב־ID כדי ליצור שם ייחודי
    $fileName = $imdb ?: "poster_" . $id;
    $localPath = "uploads/" . $fileName . "." . strtolower($extension);

    $image = @file_get_contents($url);
    if ($image && file_put_contents($localPath, $image)) {
        // עדכון במסד
        $stmt = $conn->prepare("UPDATE posters SET image_url = ? WHERE id = ?");
        $stmt->bind_param("si", $localPath, $id);
        $stmt->execute();
        $stmt->close();

        echo "<li>✅ [{$id}] נשמר ל־{$localPath}</li>";
    } else {
        echo "<li>❌ [{$id}] שגיאה בהורדה מ־{$url}</li>";
    }
}
echo "</ul>";
?>

<?php include 'footer.php'; ?>