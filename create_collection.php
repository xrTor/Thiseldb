<?php
include 'header.php';
require_once 'server.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $img  = trim($_POST['image_url'] ?? '');

  if ($name !== '') {
    $stmt = $conn->prepare("INSERT INTO collections (name, description, image_url) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $desc, $img);
    $stmt->execute();
    $stmt->close();
    $message = "✅ האוסף נוסף בהצלחה!";
  } else {
    $message = "❌ יש למלא שם לאוסף";
  }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>➕ יצירת אוסף חדש</title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:40px; }
    .form-box { max-width:500px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    input, textarea { width:100%; padding:8px; margin:10px 0; border:1px solid #ccc; border-radius:4px; }
    button { padding:10px 16px; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#218838; }
    .message { background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #ddc; color:#333; }
  </style>
</head>
<body>
<br>
<div class="form-box">
  <h2>➕ יצירת אוסף חדש</h2>

  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
  <?php endif; ?>

  <form method="post">
    <label>📁 שם האוסף</label>
    <input type="text" name="name" required>

    <label>📝 תיאור האוסף</label>
    <textarea name="description" rows="4"></textarea>

    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" placeholder="https://example.com/image.jpg">

    <button type="submit">📥 שמור אוסף</button>
  </form>
</div>
<form method="post">
  <!-- כל שדות הטופס -->
</form>

<!-- כפתור חזרה -->
<div style="text-align:center; margin-top:20px;">
  <a href="manage_collections.php" style="background:#007bff; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;">
    ⬅ חזרה לרשימת האוספים
  </a>
</div>

</body>
</html>

<?php include 'footer.php'; ?>
