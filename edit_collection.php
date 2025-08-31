<?php
require_once 'server.php';
include 'header.php';
require_once 'bbcode.php';

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

$descValue = trim($collection['description'] ?? '');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>✏️ עריכת אוסף</title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:40px; }
    .form-box { max-width:650px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    input, textarea { width:100%; padding:8px; margin:10px 0; border:1px solid #ccc; border-radius:4px; }
    button { padding:10px 16px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#0056b3; }
    .message { background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #ddc; color:#333; }
    .details { margin-bottom:20px; font-size:15px; line-height:1.5; color:#555; }
    #descBox {
      width: 100%;
      min-height: 80px;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
      resize: vertical;
      line-height: 1.5;
        direction: ltr;
  text-align: left;
  font-family: monospace;

    }
    #previewBox {
      border:1px solid #ddd;
      background:#fafafa;
      padding:10px;
      border-radius:6px;
      margin-top:10px;
      min-height:40px;
      font-size:14px;
    }
    #previewTitle {
      font-weight:bold;
      margin-top:15px;
      margin-bottom:5px;
    }
  </style>
</head>
<body>
<div class="form-box">
  <h2>✏️ עריכת אוסף</h2>

  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
  <?php endif; ?>

  <div class="details">
    <p>📁 שם נוכחי: <strong><?= htmlspecialchars($collection['name']) ?></strong></p>
    <p>📝 תיאור נוכחי:<br>
       <?= $descValue !== '' ? bbcode_to_html($descValue) : '<em>אין תיאור</em>' ?></p>
    <?php if (!empty(trim($collection['image_url']))): ?>
      <p>🖼️ תמונה: <a href="<?= htmlspecialchars($collection['image_url']) ?>" target="_blank">צפייה</a></p>
    <?php endif; ?>
  </div>

  <form method="post">
    <label>📁 שם האוסף</label>
    <input type="text" name="name" value="<?= htmlspecialchars($collection['name']) ?>" required>

    <label>📝 תיאור האוסף</label><br>
    <small><a href="bbcode_guide.php">התיאור תומך בקוד BBCode (כאן מדריך מלא לשימוש)</a></small>
    <textarea id="descBox" name="description" rows="8"><?= htmlspecialchars($descValue) ?></textarea>

    <div id="previewTitle">🔍 תצוגה מקדימה:</div>
    <div id="previewBox"><?= $descValue !== '' ? bbcode_to_html($descValue) : '— ריק —' ?></div>

    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" value="<?= htmlspecialchars($collection['image_url']) ?>" placeholder="(אפשר להשאיר ריק — לא תוצג תמונה)">

    <button type="submit" name="update_collection">💾 שמור שינויים</button>
  </form>

  <br><a href="manage_collections.php">⬅ חזרה לניהול</a>
</div>

<script>
// AJAX קטן כדי לפרסר BBCode ל־HTML בזמן אמת
document.getElementById('descBox').addEventListener('input', function() {
  let text = this.value;
  // שליחה לשרת כדי לרנדר עם bbcode_to_html (כמו ב־PHP)
  fetch('preview_bbcode.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'text=' + encodeURIComponent(text)
  })
  .then(res => res.text())
  .then(html => {
    document.getElementById('previewBox').innerHTML = html || '— ריק —';
  });
});
</script>
</body>
</html>

<?php include 'footer.php'; ?>
