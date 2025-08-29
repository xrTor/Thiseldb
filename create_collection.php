<?php
include 'header.php';
require_once 'server.php';

$message = '';
$new_id  = null; // ← יזהה את האוסף שנשמר כדי להציג כפתור דילוג

if (!function_exists('slugify_collection')) {
  function slugify_collection(string $name): string {
    $slug = mb_strtolower($name, 'UTF-8');
    $slug = preg_replace('~[^\p{L}\p{N}]+~u', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'collection-'.time();
    return $slug;
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $img  = trim($_POST['image_url'] ?? '');

  // לא מייצרים נתיב דיפולטי שלא קיים. אם אין תמונה → נשמור NULL.
  // אם הוזן URL חיצוני (http/https) נשמור אותו כמות שהוא.
  // אם הוזן שם קובץ בלבד, נוסיף images/logos/ רק אם הקובץ קיים.
  $imgToSave = null;
  if ($img !== '') {
    if (preg_match('~^https?://~i', $img)) {
      $imgToSave = $img;
    } else {
      if (strpos($img, '/') === false && strpos($img, '\\') === false) {
        $candidate = 'images/logos/' . $img;
      } else {
        $candidate = $img;
      }
      $fsPath = __DIR__ . '/' . ltrim($candidate, '/');
      $imgToSave = is_file($fsPath) ? $candidate : null;
    }
  }

  if ($name !== '') {
    $stmt = $conn->prepare("INSERT INTO collections (name, description, image_url) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $desc, $imgToSave);
    $stmt->execute();
    $new_id = $conn->insert_id; // ← נשמור את ה-ID החדש
    $stmt->close();
    $message = "✅ האוסף נוסף בהצלחה!" . ($imgToSave === null ? " (ללא תמונה — יוצג פלייסהולדר בעמודי התצוגה)" : "");
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
    .hint { font-size:12px; color:#666; margin-top:-6px; }
    .goto-wrap { text-align:center; margin-top:10px; }
    .goto-btn { display:inline-block; padding:10px 18px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
    .goto-btn:hover { background:#0866cc; }
  </style>
</head>
<body>
<br>
<div class="form-box">
  <h2>➕ יצירת אוסף חדש</h2>

  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php if (!empty($new_id)): ?>
      <div class="goto-wrap">
        <a class="goto-btn" href="collection.php?id=<?= (int)$new_id ?>">➡ עבור לאוסף שנשמר</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post">
    <label>📁 שם האוסף</label>
    <input type="text" name="name" required>

    <label>📝 תיאור האוסף</label><br>
     <label>יש למקם את התקציר העברי מעל לאנגלי עם 2 ירידות שורה</label>


    <textarea name="description" rows="20"></textarea>
    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" placeholder="אפשר להשאיר ריק. אפשר להזין URL מלא או שם קובץ (logo.png)">
    <div class="hint">
      אם תזין שם קובץ בלבד, ייבדק קובץ <code>images/logos/&lt;השם&gt;</code> בשרת. אם אינו קיים — לא תישמר כתובת כדי למנוע תמונה שבורה.
    </div>

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
