<?php

include 'header.php';

 require_once 'server.php';
 

$genre = $_GET['name'] ?? '';
$genre = trim($genre);

if (empty($genre)) {
    echo "<p style='text-align:center;'>❌ לא נבחר ז'אנר</p>";
    exit;
}

$result = $conn->query("SELECT * FROM posters WHERE genre LIKE '%$genre%'");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים בז'אנר <?= htmlspecialchars($genre) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">🎭 פוסטרים בז'אנר: <?= htmlspecialchars($genre) ?></h2>

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
    <p style="text-align:center;">😢 לא נמצאו פוסטרים בז'אנר זה</p>
  <?php endif; ?>

  <div style="text-align:center; margin-top:20px;">
    <a href="index.php">⬅ חזרה לרשימה</a>
  </div>
</body>
</html>

<?php
include 'footer.php';
?>

<?php $conn->close(); ?>
