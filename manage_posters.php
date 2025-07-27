<?php
include 'header.php';
 require_once 'server.php';
 
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// שולפים פוסטרים עם שם ו־imdb_id קיימים, עם חיפוש אם נשלח
$posters = $conn->query("
  SELECT id, title_en, type_id, year, imdb_id 
  FROM posters 
  WHERE title_en IS NOT NULL AND title_en != ''
    " . ($search ? "AND title_en LIKE '%$search%'" : "") . "
  ORDER BY id DESC
");

if (!$posters) {
  echo "<p>שגיאה בשאילתת הפוסטרים: " . $conn->error . "</p>";
  exit;
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 ניהול פוסטרים</title>
  <style>
    body { font-family:Arial; background:#f9f9f9; padding:10px; text-align:center; }
    h1 { margin-bottom:30px; }
    table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 0 8px rgba(0,0,0,0.1); }
    th, td { padding:12px; border-bottom:1px solid #ccc; text-align:right; }
    th { background:#eee; }
    tr:hover td { background:#f7f7f7; }
    a.action { margin:0 8px; color:#007bff; text-decoration:none; }
    a.action:hover { text-decoration:underline; }
    form { margin-bottom:20px; }
    input[type="text"] { padding:8px; width:250px; font-size:14px; }
    button { padding:8px 14px; }
  </style>
</head>
<body>

<h1>📁 ניהול פוסטרים</h1>

<form method="get">
  <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 חפש פוסטר...">
  <button type="submit">חיפוש</button>
  <?php if ($search): ?>
    <a href="manage_posters.php" style="margin-right:10px;">🔄 איפוס</a>
  <?php endif; ?>
</form>

<table>
  <tr>
    <th>#</th>
    <th>שם</th>
    <th>מזהה IMDb</th>
    <th>סוג</th>
    <th>שנה</th>
    <th>פעולות</th>
  </tr>
  <?php while ($row = $posters->fetch_assoc()): ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td>
        <a href="poster.php?id=<?= $row['id'] ?>">
          <?= htmlspecialchars($row['title_en']) ?>
        </a>
      </td>
      <td>
        <?php if (!empty($row['imdb_id'])): ?>
          <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank">
            <?= htmlspecialchars($row['imdb_id']) ?>
          </a>
        <?php else: ?>
          —
        <?php endif; ?>
      </td>
      <td><?= $row['type_id'] ?></td>
      <td><?= $row['year'] ?></td>
      <td>
        <a href="edit.php?id=<?= $row['id'] ?>" class="action">✏️ עריכה</a>
        <a href="delete.php?id=<?= $row['id'] ?>" class="action" onclick="return confirm('האם למחוק פוסטר זה?')">🗑️ מחיקה</a>
      </td>
    </tr>
  <?php endwhile; ?>
</table>

<p style="margin-top:30px;"><a href="add.php">➕ הוסף פוסטר חדש</a></p>

<?php include 'footer.php'; ?>
</body>
</html>
