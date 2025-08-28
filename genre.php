<?php

include 'header.php';
require_once 'server.php';
 
$genre = $_GET['name'] ?? '';
$genre = trim($genre);

if (empty($genre)) {
    echo "<p style='text-align:center;'>❌ לא נבחר ז'אנר</p>";
    include 'footer.php';
    exit;
}

// Using a prepared statement to prevent SQL Injection
$stmt = $conn->prepare("SELECT * FROM posters WHERE genres LIKE ? ORDER BY year DESC");
$like_genre = "%" . $genre . "%";
$stmt->bind_param("s", $like_genre);
$stmt->execute();
$result = $stmt->get_result();

// >> Get the number of found posters into a variable
$poster_count = $result->num_rows;
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

  <p style="text-align:center;">נמצאו <strong><?= $poster_count ?></strong> פוסטרים</p>

  <?php if ($poster_count > 0): ?>
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
// Close resources properly
$stmt->close();
$conn->close();
include 'footer.php';
?>