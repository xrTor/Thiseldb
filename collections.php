<?php
require_once 'server.php';
include 'header.php';

$message = '';

// מחיקת אוסף
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_collection'])) {
  $cid = (int)$_POST['delete_collection'];
  $conn->query("DELETE FROM collections WHERE id = $cid");
  $conn->query("DELETE FROM poster_collections WHERE collection_id = $cid");
  $message = "🗑️ האוסף נמחק בהצלחה";
}

// פאגינציה
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// שליפה עם ספירת פוסטרים לכל אוסף
$res = $conn->query("
  SELECT c.*, COUNT(pc.poster_id) as total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  GROUP BY c.id
  ORDER BY c.created_at DESC
  LIMIT $per_page OFFSET $offset
");

// ספירת כל האוספים
$total_rows = $conn->query("SELECT COUNT(*) FROM collections")->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 רשימת אוספים</title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:10px; }
    .collection-card {
      background:white; padding:20px; margin:10px auto;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); max-width:1100px; position:relative; text-align: right;
    }
    .collection-card h3 { margin:0 0 10px 0; font-size:20px; }
    .collection-card .description { color:#555; margin-bottom:10px; }
    .collection-card .count { color:#999; font-size:14px; }
    .collection-card .actions { position:absolute; top:20px; left:20px; }
    .collection-card .actions a, .collection-card .actions button {
      margin-right:6px; text-decoration:none; font-size:14px;
      background:none; border:none; color:#007bff; cursor:pointer;
    }
    .collection-card .actions a:hover, .collection-card .actions button:hover {
      text-decoration:underline;
    }
    .message {
      background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px;
      border:1px solid #ddc; color:#333; max-width:600px; margin:auto;
    }
    .add-new { text-align:center; margin-top:30px; }
    .add-new a {
      background:#007bff; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;
    }
    .pagination {
      text-align:center; margin-top:20px;
    }
    .pagination a {
      margin:0 6px; padding:6px 10px; background:#eee; border-radius:4px;
      text-decoration:none; color:#333;
    }
    .pagination a.active {
      font-weight:bold; background:#ccc;
    }
  </style>
</head>
<body>

<h2 style="text-align:center;">📁 רשימת האוספים</h2>

<div class="add-new">
  <a href="create_collection.php">➕ צור אוסף חדש</a>
</div><br>

<?php if ($message): ?>
  <div class="message"><?= $message ?></div>
<?php endif; ?>
<?php if ($res->num_rows > 0): ?>
  <?php while ($c = $res->fetch_assoc()): ?>
    <div class="collection-card">
      <h3><a href="collection.php?id=<?= $c['id'] ?>">📁 <?= htmlspecialchars($c['name']) ?></a></h3>
      <div class="description"><?= htmlspecialchars($c['description']) ?></div>
      <div class="count">🎞️ <?= $c['total_items'] ?> פוסטרים</div>
      <div class="actions">
        <a href="edit_collection.php?id=<?= $c['id'] ?>">✏️ ערוך</a>
        <form method="post" style="display:inline;">
          <button type="submit" name="delete_collection" value="<?= $c['id'] ?>" onclick="return confirm('למחוק את האוסף?')">🗑️ מחק</button>
        </form>
        <a href="add_to_collection.php?id=<?= $c['id'] ?>">➕ הוסף פוסטר</a>
      </div>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p style="text-align:center;">😢 לא קיימים אוספים כרגע</p>
<?php endif; ?>

<!-- פאגינציה -->
<?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

</body>
</html>

<?php include 'footer.php'; ?>
