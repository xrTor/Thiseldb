<?php
include 'header.php';
require_once 'server.php';

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;

// סוגים
$type_res = $conn->query("SELECT id, label_he, icon FROM poster_types ORDER BY sort_order ASC");
$type_options = [];
if ($type_res) {
  while ($row = $type_res->fetch_assoc()) {
    $type_options[$row['id']] = $row;
  }
}

// פוסטרים
$query = "
  SELECT id, title_en, title_he, type_id, year, imdb_id, image_url
  FROM posters
  WHERE title_en IS NOT NULL AND title_en != '' ";

if ($search) {
  $query .= "AND title_en LIKE '%$search%' ";
}
if ($type_filter) {
  $query .= "AND type_id = $type_filter ";
}
$query .= "ORDER BY id DESC";

$posters = $conn->query($query);
$total_count = $posters ? $posters->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 ניהול פוסטרים</title>
  <style>
    body { font-family:Arial; background:#f9f9f9; padding:10px; text-align:center; }
    h1 { margin-bottom:10px; }
    form { margin-bottom:10px; }
    input[type="text"] { padding:8px; width:250px; font-size:14px; }

    .type-buttons { margin:15px 0; }
    .type-button {
      display:inline-block;
      padding:6px 12px;
      margin:0 5px 6px;
      background:#eee;
      border-radius:5px;
      text-decoration:none;
      color:#333;
      border:1px solid #ccc;
      font-size:14px;
    }
    .type-button.active {
      background:#007bff;
      color:white;
      border-color:#007bff;
    }

    table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 0 8px rgba(0,0,0,0.1); }
    th, td { padding:10px; border-bottom:1px solid #ccc; text-align:right; vertical-align:middle; }
    th { background:#eee; }
    tr:hover td { background:#f7f7f7; }

    .poster-title {
      text-align:right;
      line-height:1.6;
    }
    .poster-title .he {
      color:#777;
      font-size:13px;
      display:block;
    }

    .poster-img {
      width:50px;
      height:auto;
      border-radius:4px;
    }

    a.action { margin:0 8px; color:#007bff; text-decoration:none; }
    a.action:hover { text-decoration:underline; }
  </style>
</head>
<body>

<h1>📁 ניהול פוסטרים</h1>

<form method="get">
  <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 חפש פוסטר...">
  <button type="submit">חיפוש</button>
  <?php if ($search || $type_filter): ?>
    <a href="manage_posters.php" style="margin-right:10px;">🔄 איפוס</a>
  <?php endif; ?>
</form>

<div class="type-buttons">
  <a href="?<?= http_build_query(['search' => $search, 'type' => 0]) ?>" class="type-button <?= $type_filter === 0 ? 'active' : '' ?>">📦 כל הסוגים</a>
  <?php foreach ($type_options as $id => $data): ?>
    <a href="?<?= http_build_query(['search' => $search, 'type' => $id]) ?>" class="type-button <?= $type_filter === $id ? 'active' : '' ?>">
      <?= htmlspecialchars($data['icon'] . ' ' . $data['label_he']) ?>
    </a>
  <?php endforeach; ?>
</div>

<p style="margin:20px 0; color:#555;">נמצאו <?= $total_count ?> פוסטרים</p>

<table>
  <tr>
    <th>ID</th>
    <th>תמונה</th>
    <th>שם</th>
    <th>IMDb</th>
    <th>סוג</th>
    <th>שנה</th>
    <th>פעולות</th>
  </tr>
  <?php while ($row = $posters->fetch_assoc()): ?>
    <tr>
      <td>
        <a href="poster.php?id=<?= $row['id'] ?>">
          <strong><?= $row['id'] ?></strong>
        </a>
      </td>
      <td>
        <?php if (!empty($row['image_url'])): ?>
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="poster" class="poster-img">
          </a>
        <?php else: ?> — <?php endif; ?>
      </td>
      <td>
        <div class="poster-title">
          <strong>
            <a href="poster.php?id=<?= $row['id'] ?>">
              <?= htmlspecialchars($row['title_en']) ?>
            </a>
          </strong>
          <span class="he"><?= htmlspecialchars($row['title_he']) ?></span>
        </div>
      </td>
      <td>
        <?php if (!empty($row['imdb_id'])): ?>
          <a href="https://www.imdb.com/title/<?= htmlspecialchars($row['imdb_id']) ?>" target="_blank">
            <?= htmlspecialchars($row['imdb_id']) ?>
          </a>
        <?php else: ?> — <?php endif; ?>
      </td>
      <td>
        <?= isset($type_options[$row['type_id']])
          ? htmlspecialchars($type_options[$row['type_id']]['icon'] . ' ' . $type_options[$row['type_id']]['label_he'])
          : '<span style="color:red;">⛔ לא מוכר</span>' ?>
      </td>
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