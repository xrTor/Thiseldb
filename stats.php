<?php
include 'header.php';
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
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📊 סטטיסטיקות</title>
  <style>
    body { font-family:sans-serif; max-width:800px; margin:30px auto; }
    h1 { text-align:center; }
    .box { background:#f9f9f9!important; padding:15px; margin:10px 0; border-radius:6px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:8px; border-bottom:1px solid #ccc; text-align:right; }
  </style>
</head>
<body>

<h1>📊 סטטיסטיקות כלליות</h1>

<div class="box">
  <h3>🔢 לפי סוג:</h3>
  <p>📺 סדרות: <strong><?= $count_series ?></strong></p>
  <p>🎬 סרטים: <strong><?= $count_movies ?></strong></p>
</div>

<div class="box">
  <h3>🏷️ פוסטרים לפי תגית:</h3>
  <table>
    <tr><th>תגית</th><th>מספר פוסטרים</th></tr>
    <?php while ($row = $tags->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= $row['total'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<p style="text-align:center;"><a href="index.php">⬅ חזרה</a></p>

</body>
</html>
