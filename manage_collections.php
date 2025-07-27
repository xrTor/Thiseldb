<?php
include 'header.php';
require_once 'server.php';

$message = '';

// יצירת אוסף חדש
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_collection'])) {
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $img = trim($_POST['image_url'] ?? '');

  if ($name !== '') {
    $stmt = $conn->prepare("INSERT INTO collections (name, description, image_url) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $desc, $img);
    $stmt->execute(); $stmt->close();
    $message = "✅ האוסף נוצר בהצלחה";
  } else {
    $message = "⚠️ שם האוסף הוא חובה";
  }
}

// מחיקה
if (isset($_GET['delete'])) {
  $did = intval($_GET['delete']);
  $conn->query("DELETE FROM collections WHERE id = $did");
  $message = "🗑️ אוסף נמחק";
}

// הודעות מעבר
if (isset($_GET['msg'])) {
  if ($_GET['msg'] === 'linked') $message = "📥 הפוסטר קושר לאוסף";
  elseif ($_GET['msg'] === 'exists') $message = "⚠️ הפוסטר כבר מקושר";
  elseif ($_GET['msg'] === 'error') $message = "❌ שגיאה בקישור";
}

// שליפת כל האוספים
$result = $conn->query("SELECT * FROM collections ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📦 ניהול אוספים</title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:40px; }
    .form-box, .collection-box { max-width:600px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); margin-bottom:30px; }
    input, textarea { width:100%; padding:8px; margin:10px 0; border:1px solid #ccc; border-radius:4px; }
    button { padding:10px 16px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#0056b3; }
    .message { background:#ffe; padding:10px; border-radius:6px; border:1px solid #ddc; color:#333; margin-bottom:10px; }
    .link-btn { display:inline-block; background:#eee; padding:6px 12px; border-radius:6px; text-decoration:none; margin:6px 6px 0 0; }
    .link-btn:hover { background:#ddd; }
    .collection-box img { max-width:100%; border-radius:6px; margin-bottom:10px; }
  </style>
</head>
<body>

<h2>📦 ניהול אוספים</h2>

<?php if (!empty($message)): ?>
  <div class="form-box">
    <div class="message"><?= $message ?></div>
  </div>
<?php endif; ?>

<div class="form-box">
  <h2>➕ יצירת אוסף חדש</h2>
  <form method="post">
    <label>📁 שם האוסף</label>
    <input type="text" name="name" required>

    <label>📝 תיאור האוסף</label>
    <textarea name="description" rows="4"></textarea>

    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" placeholder="https://example.com/image.jpg">

    <button type="submit" name="create_collection">📥 צור</button>
  </form>
</div>

<div class="form-box">
  <h2>📚 כל האוספים</h2>
  <?php while ($row = $result->fetch_assoc()): ?>
    <div class="collection-box">
      <?php if (!empty($row['image_url'])): ?>
        <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Collection">
      <?php endif; ?>
      <h3>📁 <?= htmlspecialchars($row['name']) ?></h3>
      <p>📝 <?= nl2br(htmlspecialchars($row['description'])) ?></p>
      <small>🕓 נוצר ב־<?= htmlspecialchars($row['created_at']) ?></small><br>

      <a href="edit_collection.php?id=<?= $row['id'] ?>" class="link-btn">✏️ ערוך</a>
      <a href="manage_collections.php?delete=<?= $row['id'] ?>" onclick="return confirm('למחוק את האוסף?')" class="link-btn">🗑️ מחק</a>
<a href="collection.php?id=<?= $row['id'] ?>" class="link-btn">📦 צפייה ציבורית</a>

      <form method="post" action="add_to_collection.php" style="margin-top:10px;">
        <input type="hidden" name="collection_id" value="<?= $row['id'] ?>">
        <label>🔗 מזהה פוסטר לקישור</label>
        <input type="number" name="poster_id" required>
        <button type="submit">📥 קישור פוסטר</button>
      </form>
    </div>
  <?php endwhile; ?>
</div>

</body>
</html>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>
