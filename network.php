<?php
// הצגת שגיאות לצורך אבחון
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'header.php';
require_once 'server.php';

$name = $_GET['name'] ?? '';
$name = trim($name);

if (empty($name)) {
    echo "<p style='text-align:center;'>❌ לא צוינה רשת</p>";
    include 'footer.php';
    exit;
}

// >> שימוש ב-Prepared Statement למניעת SQL Injection
$stmt = $conn->prepare("SELECT * FROM posters WHERE networks LIKE ? ORDER BY year DESC");
$like_network = "%" . $name . "%";
$stmt->bind_param("s", $like_network);
$stmt->execute();
$result = $stmt->get_result();

// >> שמירת מספר התוצאות במשתנה
$num_results = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים מרשת <?= htmlspecialchars($name) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">📡 פוסטרים מרשת: <?= htmlspecialchars($name) ?></h2>
  
  <p style="text-align:center;">נמצאו <strong><?= $num_results ?></strong> פוסטרים</p>

  <?php if ($num_results > 0): ?>
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
    <p style="text-align:center;">😢 לא נמצאו פוסטרים מהרשת הזו</p>
  <?php endif; ?>

  <div style="text-align:center; margin-top:20px;">
    <a href="index.php">⬅ חזרה לרשימה</a>
  </div>
</body>
</html>

<?php
include 'footer.php';

// סגירת ה-statement בלבד (אל תסגור כאן את $conn כדי למנוע "mysqli object is already closed")
if (isset($stmt)) { $stmt->close(); }
?>
