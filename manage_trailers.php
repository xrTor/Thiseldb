<?php
include 'header.php';
require_once 'server.php';
$conn->set_charset("utf8");

// טעינת הפוסטרים מהחדש לישן
$result = $conn->query("SELECT id, title_en, title_he, youtube_trailer FROM posters ORDER BY id DESC");

// פונקציה להוצאת מזהה וידאו מ-YouTube
function extractYoutubeId($url) {
  parse_str(parse_url($url, PHP_URL_QUERY), $vars);
  return $vars['v'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>ניהול טריילרים</title>
  <style>
    body { font-family: Arial, sans-serif; direction: rtl; margin: 20px; background-color: white; }
    h2 { color: #333; margin-top: 40px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 10px; text-align: center; vertical-align: middle; }
    iframe { border-radius: 4px; }
    a { text-decoration: none; color: #007BFF; }
    a:hover { text-decoration: underline; }
    input[type="url"] { width: 90%; padding: 6px; }
    button { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
    .update-btn { background-color: #28a745; color: white; }
    .update-btn:hover { background-color: #218838; }
    .delete-btn { background-color: #dc3545; color: white; margin-top: 6px; }
    .delete-btn:hover { background-color: #c82333; }
    .group-title { margin-top: 50px; font-size: 18px; }
  </style>
</head>
<body>

<h2>🎬 ניהול טריילרים לפי פוסטר</h2>

<!-- 🟢 עם טריילר -->
<h3 class="group-title">🟢 פוסטרים עם טריילר</h3>
<table>
  <tr>
    <th>ID</th>
    <th>שם הפוסטר</th>
    <th>טריילר נוכחי</th>
    <th>פעולות</th>
  </tr>

  <?php $result->data_seek(0); while ($row = $result->fetch_assoc()) {
    if (!empty($row['youtube_trailer'])) { ?>
      <tr style="background-color:white;">
        <td><?= $row['id'] ?></td>
        <td>
          <a href="poster.php?id=<?= $row['id'] ?>">
            <strong><?= htmlspecialchars($row['title_en']) ?></strong>
          </a><br>
          <small style="color: gray;"><?= htmlspecialchars($row['title_he']) ?></small>
        </td>
        <td>
          <iframe width="220" height="120"
            src="https://www.youtube.com/embed/<?= htmlspecialchars(extractYoutubeId($row['youtube_trailer'])) ?>"
            frameborder="0" allowfullscreen></iframe>
        </td>
        <td>
          <form action="update_trailer.php" method="POST">
            <input type="hidden" name="poster_id" value="<?= $row['id'] ?>">
            <input type="url" name="youtube_trailer" placeholder="https://www.youtube.com/watch?v=..." required>
            <button type="submit" class="update-btn">עדכן טריילר</button>
          </form>
          <form action="delete_trailer.php" method="POST">
            <input type="hidden" name="poster_id" value="<?= $row['id'] ?>">
            <button type="submit" class="delete-btn">🗑️ הסר טריילר</button>
          </form>
        </td>
      </tr>
  <?php }} ?>
</table>

<!-- 🔴 בלי טריילר -->
<h3 class="group-title">🔴 פוסטרים ללא טריילר</h3>
<table>
  <tr>
    <th>ID</th>
    <th>שם הפוסטר</th>
    <th>טריילר נוכחי</th>
    <th>עדכון קישור</th>
  </tr>

  <?php $result->data_seek(0); while ($row = $result->fetch_assoc()) {
    if (empty($row['youtube_trailer'])) { ?>
      <tr style="background-color:white;">
        <td><?= $row['id'] ?></td>
        <td>
          <a href="poster.php?id=<?= $row['id'] ?>">
            <strong><?= htmlspecialchars($row['title_en']) ?></strong>
          </a><br>
          <small style="color: gray;"><?= htmlspecialchars($row['title_he']) ?></small>
        </td>
        <td><span style="color: gray;">אין טריילר</span></td>
        <td>
          <form action="update_trailer.php" method="POST">
            <input type="hidden" name="poster_id" value="<?= $row['id'] ?>">
            <input type="url" name="youtube_trailer" placeholder="https://www.youtube.com/watch?v=..." required>
            <button type="submit" class="update-btn">עדכן טריילר</button>
          </form>
        </td>
      </tr>
  <?php }} ?>
</table>

</body>
</html>

<?php include 'footer.php'; ?>