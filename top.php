<?php
/*
include 'header.php';
*/
$conn = new mysqli('localhost','root','123456','media');
if ($conn->connect_error) die("Connection failed");

$res = $conn->query("
  SELECT * FROM posters
  WHERE imdb_rating IS NOT NULL AND imdb_rating != ''
  ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC
  LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🏆 Top Rated</title>
  <style>
    body { font-family:sans-serif; max-width:800px; margin:30px auto; }
    .poster { margin:15px 0; padding:10px; border-bottom:1px solid #ccc; }
    img { height:120px; vertical-align:middle; border-radius:6px; margin-right:10px; }
  </style>
</head>
<body>
<h2>🏆 10 הפוסטרים עם הדירוג הגבוה ביותר</h2>
<?php while ($row = $res->fetch_assoc()): ?>
  <div class="poster">
    <a href="poster.php?id=<?= $row['id'] ?>">
      <img src="<?= $row['image_url'] ?>" alt="">
    </a>
    <strong><?= htmlspecialchars($row['title_en']) ?></strong>
    [<?= $row['year'] ?>]
    ⭐ <?= $row['imdb_rating'] ?> / 10
  </div>
<?php endwhile; ?>
</body>
</html>
