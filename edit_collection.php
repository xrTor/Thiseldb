<?php
require_once 'server.php';
include 'header.php';

if (!function_exists('slugify_collection')) {
  function slugify_collection(string $name): string {
    $slug = mb_strtolower($name, 'UTF-8');
    $slug = preg_replace('~[^\p{L}\p{N}]+~u', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'collection-'.time();
    return $slug;
  }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// שליפת נתוני האוסף
$stmt = $conn->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<p>❌ אוסף לא נמצא</p>";
  include 'footer.php';
  exit;
}
$collection = $result->fetch_assoc();
$stmt->close();

// עדכון נתונים
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_collection'])) {
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $img  = trim($_POST['image_url'] ?? '');

  // אל תייצר נתיב אוטומטי כששדה התמונה ריק; תקן נתיב רק אם המשתמש הזין ערך
  if ($img !== '') {
    // אם הוזן רק שם קובץ (ללא URL/נתיב) – הוסף images/logos/
    if (!preg_match('~^https?://~i', $img) && strpos($img, '/') === false && strpos($img, '\\') === false) {
      $img = 'images/logos/' . $img;
    }
  }

  if ($name !== '') {
    if ($img === '') {
      // שמור NULL כדי לא להציג תמונה שבורה
      $stmt = $conn->prepare("UPDATE collections SET name=?, description=?, image_url=NULL WHERE id=?");
      $stmt->bind_param("ssi", $name, $desc, $id);
    } else {
      $stmt = $conn->prepare("UPDATE collections SET name=?, description=?, image_url=? WHERE id=?");
      $stmt->bind_param("sssi", $name, $desc, $img, $id);
    }
    $stmt->execute();
    $stmt->close();

    // ניתוב חזרה לעמוד האוסף שנערך
    $target = "collection.php?id=" . $id;
    if (!headers_sent()) {
      header("Location: " . $target);
      exit;
    } else {
      echo "<script>window.location.href=" . json_encode($target) . ";</script>";
      exit;
    }
  } else {
    $message = "❌ יש למלא שם לאוסף";
  }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>✏️ עריכת אוסף</title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:40px; }
    .form-box { max-width:500px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    input, textarea { width:100%; padding:8px; margin:10px 0; border:1px solid #ccc; border-radius:4px; }
    button { padding:10px 16px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#0056b3; }
    .message { background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #ddc; color:#333; }
    .details { margin-bottom:20px; font-size:15px; line-height:1.5; color:#555; }
  </style>
</head>
<body>
<p>
<div class="form-box">
  <h2>✏️ עריכת אוסף</h2>

  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
  <?php endif; ?>

  <div class="details">
    <p>📁 שם נוכחי: <strong><?= htmlspecialchars($collection['name']) ?></strong></p>
    <p>📝 תיאור נוכחי: <?= $collection['description'] ? nl2br(htmlspecialchars($collection['description'], ENT_QUOTES)) : '<em>אין תיאור</em>' ?></p>
    <?php if (!empty(trim($collection['image_url']))): ?>
      <p>🖼️ תמונה: <a href="<?= htmlspecialchars($collection['image_url']) ?>" target="_blank">צפייה</a></p>
    <?php endif; ?>
  </div>

  <form method="post">
    <label>📁 שם האוסף</label>
    <input type="text" name="name" value="<?= htmlspecialchars($collection['name']) ?>" required>

    <label>📝 תיאור האוסף</label><br>
     <label>יש למקם את התקציר העברי מעל לאנגלי עם 2 ירידות שורה</label>
    <textarea name="description" rows="20"><?= htmlspecialchars($collection['description']) ?></textarea>

    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" value="<?= htmlspecialchars($collection['image_url']) ?>" placeholder="(אפשר להשאיר ריק — לא תוצג תמונה; או להקליד רק filename.png לשמירה תחת images/logos/)">

    <button type="submit" name="update_collection">💾 שמור שינויים</button>
  </form>

  <br><a href="manage_collections.php">⬅ חזרה לניהול</a>
</div>

</body>
</html>

<?php include 'footer.php'; ?>
