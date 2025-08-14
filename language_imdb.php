<?php
include 'header.php';
require_once 'server.php';

$lang = trim($_GET['lang_code'] ?? '');

if (empty($lang)) {
    echo "<p style='text-align:center;'>❌ שפה לא צוינה</p>";
    include 'footer.php';
    exit;
}

// חיפוש בשדה languages (IMDB/TMDB)
$result = $conn->query("
    SELECT * FROM posters
    WHERE languages LIKE '%" . $conn->real_escape_string($lang) . "%'
");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים בשפה <?= htmlspecialchars($lang) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">
    🈯 פוסטרים בשפה: <?= htmlspecialchars($lang) ?>
  </h2>

  <?php if ($result->num_rows > 0): ?>
    <div style="display:flex; flex-wrap:wrap; justify-content:center;">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div style="width:200px; margin:10px; text-align:center;">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="Poster" style="width:100%; border-radius:6px;">
            <div style="margin-top:4px;">
              <div><?= htmlspecialchars($row['title_en']) ?></div>
              <?php if (!empty($row['title_he'])): ?>
                <div style="font-size:13px;color:#666;"><?= htmlspecialchars($row['title_he']) ?></div>
              <?php endif; ?>
            </div>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p style="text-align:center;">😢 לא נמצאו פוסטרים בשפה זו</p>
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
