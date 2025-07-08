<?php
$host = 'localhost';
$db = 'media';
$user = 'root';
$pass = '123456';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);

if (empty($keyword)) {
    echo "<p style='text-align:center;'>🔍 לא הוזנה מילת חיפוש</p>";
    exit;
}

$sql = "SELECT * FROM posters 
        WHERE title_en LIKE '%$keyword%' 
        OR title_he LIKE '%$keyword%' 
        OR plot LIKE '%$keyword%' 
        OR genre LIKE '%$keyword%' 
        OR actors LIKE '%$keyword%'";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>תוצאות חיפוש עבור <?= htmlspecialchars($keyword) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">🔍 תוצאות עבור: <?= htmlspecialchars($keyword) ?></h2>

  <?php if ($result->num_rows > 0): ?>
    <div style="display:flex; flex-wrap:wrap; justify-content:center;">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div style="width:200px; margin:10px; text-align:center;">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster" style="width:100%; border-radius:6px;">
            <p><?= htmlspecialchars($row['title_en']) ?></p>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p style="text-align:center;">😢 לא נמצאו תוצאות</p>
  <?php endif; ?>

  <div style="text-align:center; margin-top:20px;">
    <form method="get" action="search.php">
      <input type="text" name="q" placeholder="הקלד מילה לחיפוש" style="width:200px;">
      <button type="submit">🔍 חפש</button>
    </form>
    <br>
    <a href="index.php">⬅ חזרה לרשימה</a>
  </div>
</body>
</html>

<?php $conn->close(); ?>
