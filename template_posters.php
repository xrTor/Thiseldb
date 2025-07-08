<?php
$stmt = $conn->prepare("SELECT * FROM posters WHERE type = ? ORDER BY year DESC");
$stmt->bind_param("s", $type);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= $title ?> | Thiseldb</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1 style="text-align:center;"><?= $title ?></h1>
  <p style="text-align:center;"><a href="index.php">⬅ חזרה לכל הפוסטרים</a></p>

  <div class="poster-wall">
    <?php if (empty($rows)): ?>
      <p style="text-align:center;">😢 לא נמצאו פוסטרים מהסוג הזה</p>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <div class="poster">
          <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="">
          <div class="poster-title">
            <b><?= htmlspecialchars($row['title_en']) ?></b><br>
            <?= htmlspecialchars($row['title_he']) ?><br>
            [<?= htmlspecialchars($row['year']) ?>]
          </div>

          <!-- תגיות -->
          <div class="poster-tags">
            🏷️
            <?php
            $poster_id = $row['id'];
            $cat_result = $conn->query("SELECT c.id, c.name FROM categories c
              JOIN poster_categories pc ON c.id = pc.category_id
              WHERE pc.poster_id = $poster_id");
            if ($cat_result->num_rows > 0) {
              while ($c = $cat_result->fetch_assoc()) {
                echo "<a href='index.php?tag={$c['id']}' class='tag-link'>" . htmlspecialchars($c['name']) . "</a> ";
              }
            } else {
              echo "<span style='color:#888;'>אין תגיות</span>";
            }
            ?>
          </div>

          <div class="poster-actions">
            <a href="poster.php?id=<?= $poster_id ?>">📄 צפה</a> |
            <a href="edit.php?id=<?= $poster_id ?>">✏️ ערוך</a> |
            <a href="delete.php?id=<?= $poster_id ?>" onclick="return confirm('למחוק?')">🗑️</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
