<?php
$host = 'localhost';
$db = 'media';
$user = 'root';
$pass = '123456';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ספירה לפי סוג
$count_series = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE type='series'")->fetch_assoc()['c'];
$count_movies = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE type='movie'")->fetch_assoc()['c'];

// ספירה לפי תגית
$tags = $conn->query("
  SELECT c.name, COUNT(pc.poster_id) AS total
  FROM categories c
  JOIN poster_categories pc ON c.id = pc.category_id
  GROUP BY c.id
  ORDER BY total DESC
");
?>

<footer style="text-align:center; margin-top:30px; font-size:14px;">
  <p>&copy; <?= date("Y") ?> Thiseldb </p>

  סטטיסטיקה:

  <div class="box">
 <span><a href="movies.php">🎬 סרטים: <strong><?= $count_movies ?></a></strong></span> |
  <span><a href="series.php">📺 סדרות: <strong><?= $count_series ?></a></strong></span>
 </div>

</footer>
</body>
</html>
