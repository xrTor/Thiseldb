<?php include 'header.php';
require_once 'server.php';
 
$name = $conn->real_escape_string($_GET['name'] ?? '');

$sql = "
  SELECT p.* FROM user_tags g
  JOIN posters p ON g.poster_id = p.id
  WHERE g.genre LIKE '%$name%' AND p.pending = 0
  GROUP BY p.id
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📝 ז׳אנר קהילתי: <?= htmlspecialchars($name) ?></title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f0f0f0; padding:40px; }
    .poster-card {
      width:200px;
      margin:10px;
      text-align:center;
      background:white;
      padding:10px;
      border-radius:6px;
      box-shadow:0 0 4px rgba(0,0,0,0.1);
    }
    .poster-card img {
      width:100%;
      border-radius:6px;
      box-shadow:0 0 6px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body>

<h2 style="text-align:center;">📝 פוסטרים עם ז׳אנר קהילתי: <?= htmlspecialchars($name) ?></h2>

<?php if ($result->num_rows > 0): ?>
  <div style="display:flex; flex-wrap:wrap; justify-content:center;">
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="poster-card">
        <a href="poster.php?id=<?= $row['id'] ?>">
          <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster">
          <p><?= htmlspecialchars($row['title_en']) ?></p>
        </a>
      </div>
    <?php endwhile; ?>
  </div>
<?php else: ?>
  <p style="text-align:center; color:#666;">😢 לא נמצאו פוסטרים עם ז׳אנר זה מתוך הקהילה</p>
<?php endif; ?>

<div style="text-align:center; margin-top:20px;">
  <a href="index.php">⬅ חזרה לרשימה</a>
</div>

</body>
</html>

<?php include 'footer.php'; ?>
