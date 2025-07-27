<?php
 require_once 'server.php';
 
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = $_GET['id'] ?? null;
if (!$id) die("❌ מזהה חסר");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title_en = $_POST['title_en'];
    $title_he = $_POST['title_he'];
    $year = $_POST['year'];
    $imdb_rating = $_POST['imdb_rating'];
    $imdb_link = $_POST['imdb_link'];
    $image_url = $_POST['image_url'];

    $stmt = $conn->prepare("UPDATE posters SET title_en=?, title_he=?, year=?, imdb_rating=?, imdb_link=?, image_url=? WHERE id=?");
    $stmt->bind_param("ssssssi", $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $id);
    $stmt->execute();
    $stmt->close();

    echo "<p style='color:green;'>✅ הפוסטר עודכן בהצלחה!</p>";
}


$stmt = $conn->prepare("SELECT * FROM posters WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$poster = $result->fetch_assoc();
$stmt->close();

$conn->query("DELETE FROM poster_categories WHERE poster_id = $id");

if (!empty($_POST['categories'])) {
    foreach ($_POST['categories'] as $cat_id) {
        $stmt = $conn->prepare("INSERT INTO poster_categories (poster_id, category_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $id, $cat_id);
        $stmt->execute();
        $stmt->close();
    }
}


?>

<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>עריכת פוסטר</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">✏️ עריכת פוסטר</h2>
  <form method="post" action="" style="max-width:500px; margin:auto;">


<label>קטגוריות:</label><br>
<select name="categories[]" multiple>
<?php
$cat_result = $conn->query("SELECT * FROM categories");
while ($cat = $cat_result->fetch_assoc()):
?>
  <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
<?php endwhile; ?>
</select><br><br>



    <label>שם באנגלית:</label><br>
    <input type="text" name="title_en" value="<?= htmlspecialchars($poster['title_en']) ?>" required><br><br>

    <label>שם בעברית:</label><br>
    <input type="text" name="title_he" value="<?= htmlspecialchars($poster['title_he']) ?>"><br><br>

    <label>שנה:</label><br>
    <input type="text" name="year" value="<?= htmlspecialchars($poster['year']) ?>"><br><br>

    <label>דירוג IMDb:</label><br>
    <input type="text" name="imdb_rating" value="<?= htmlspecialchars($poster['imdb_rating']) ?>"><br><br>

    <label>קישור ל-IMDb:</label><br>
    <input type="url" name="imdb_link" value="<?= htmlspecialchars($poster['imdb_link']) ?>"><br><br>

    <label>שם קובץ תמונה:</label><br>
    <input type="text" name="image_url" value="<?= htmlspecialchars($poster['image_url']) ?>"><br><br>

    <button type="submit">💾 עדכן</button>
  </form>
  <div style="text-align:center;margin-top:20px;">
    <a href="index.php">⬅ חזרה לרשימת הפוסטרים</a>
  </div>

</body>
</html>


