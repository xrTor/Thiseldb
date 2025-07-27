<?php include 'header.php'; 
require_once 'server.php';
?>

<?php
require_once 'server.php';
require_once 'languages.php';

$lang_code = $_GET['lang_code'] ?? '';
$lang_label = '';
$lang_flag = '';

foreach ($languages as $lang) {
  if ($lang['code'] === $lang_code) {
    $lang_label = $lang['label'];
    $lang_flag = $lang['flag'];
    break;
  }
}

if ($lang_code === '') {
  echo "<p>❌ לא נבחרה שפה</p>";
  exit;
}

$stmt = $conn->prepare("
  SELECT p.id, p.title_en, p.title_he, p.image_url, p.year
  FROM posters p
  JOIN poster_languages pl ON p.id = pl.poster_id
  WHERE pl.lang_code = ?
  ORDER BY p.id DESC
");
$stmt->bind_param("s", $lang_code);
$stmt->execute();
$result = $stmt->get_result();


$lang_code = $_GET['lang_code'] ?? '';
$lang_code = trim($lang_code);

if (empty($lang_code)) {
    echo "<p style='text-align:center;'>❌ לא נבחרה שפה</p>";
    exit;
}

// שליפת פוסטרים לפי השפה
$stmt = $conn->prepare("
  SELECT posters.*
  FROM posters
  JOIN poster_languages ON posters.id = poster_languages.poster_id
  WHERE poster_languages.lang_code = ?
  ORDER BY posters.year DESC
");
$stmt->bind_param("s", $lang_code);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>

<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים בשפה <?= htmlspecialchars($lang_code) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  
<h1>🌐 פוסטרים בשפה <?= htmlspecialchars($lang_label) ?>
<?php if ($lang_flag): ?>
  <img src="<?= htmlspecialchars($lang_flag) ?>" alt="<?= htmlspecialchars($lang_label) ?>" style="height:20px; vertical-align:middle;">
<?php endif; ?>
</h1>

  <?php if ($result->num_rows > 0): ?>
    <div style="display:flex; flex-wrap:wrap; justify-content:center;">
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $img = (!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png';
        ?>
        <div style="width:200px; margin:10px; text-align:center;">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($img) ?>" alt="Poster" style="width:100%; border-radius:6px;">
            <p><?= htmlspecialchars($row['title_en']) ?></p>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p style="text-align:center;">😢 לא נמצאו פוסטרים בשפה <?= htmlspecialchars($lang_code) ?></p>
  <?php endif; ?>

  <div style="text-align:center; margin-top:20px;">
    <a href="index.php">⬅ חזרה לרשימה</a>
  </div>
</body>
</html>

<?php include 'footer.php'; ?>