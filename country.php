<?php
include 'header.php';
require_once 'server.php';

$country = $_GET['country'] ?? '';
$country = trim($country);

if (empty($country)) {
    echo "<p style='text-align:center;'>❌ לא צוינה מדינה</p>";
    exit;
}

$result = $conn->query("SELECT * FROM posters WHERE countries LIKE '%$country%'");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים ממדינה <?= htmlspecialchars($country) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">🌍 פוסטרים ממדינה: <?= htmlspecialchars($country) ?></h2>

  <?php if ($result->num_rows > 0): ?>
    <div style="display:flex; flex-wrap:wrap; justify-content:center;">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div style="width:200px; margin:10px; text-align:center;">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="Poster" style="width:100%; border-radius:6px;">
            <p><?= htmlspecialchars($row['title_en']) ?></p>
            <?php if (!empty($row['title_he'])): ?>
              <div style="color:#666;font-size:13px"><?= htmlspecialchars($row['title_he']) ?></div>
            <?php endif; ?>
            <?php if (!empty($row['year'])): ?>
              <div style="color:#999;font-size:12px"><?= htmlspecialchars($row['year']) ?></div>
            <?php endif; ?>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p style="text-align:center;">😢 לא נמצאו פוסטרים מהמדינה הזו</p>
  <?php endif; ?>

  <div style="text-align:center; margin-top:20px;">
    <a href="index.php">⬅ חזרה לרשימה</a>
  </div>
</body>
</html>

<?php
include 'footer.php';
$conn->close();
?>
