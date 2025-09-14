<?php
ob_start();
include 'header.php';
require_once 'server.php';
require_once 'bbcode.php';

$message = '';
$new_id  = null; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name       = trim($_POST['name'] ?? '');
  $desc_he    = trim($_POST['description_he'] ?? '');
  $desc_en    = trim($_POST['description_en'] ?? '');
  $img        = trim($_POST['image_url'] ?? '');
  $poster_img = trim($_POST['poster_image_url'] ?? '');
  
  $desc = '';
  if (!empty($desc_he) || !empty($desc_en)) {
      $desc = "[עברית]\n" . $desc_he . "\n[/עברית]\n\n\n[אנגלית]\n" . $desc_en . "\n[/אנגלית]";
  }

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

  $posterImgToSave = null;
  if ($poster_img !== '') {
    if (strpos($poster_img, '/') === false && strpos($poster_img, '\\') === false) {
      $posterImgToSave = 'images/stickers/' . $poster_img;
    }
  }

  if ($name !== '') {
    // Assuming the SQL column error is fixed by the user
    $stmt = $conn->prepare("INSERT INTO collections (name, description, image_url, poster_image_url, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssss", $name, $desc, $imgToSave, $posterImgToSave);
    $stmt->execute();
    $new_id = $conn->insert_id;
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
  
  <link rel="stylesheet" href="bbcode.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:40px; }
    .form-box { max-width:750px; margin:auto; background:white; padding:20px; border-radius:8px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    input, textarea { width:100%; padding:8px; margin:10px 0; border:1px solid #ccc; border-radius:4px; }
    button { padding:6px 12px; margin:2px; border-radius:4px; cursor:pointer; background:#28a745; color:white; border:none; font-weight:bold;}
    button:hover { background:#0056b3; }
    .message { background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #ddc; color:#333; }
    .goto-wrap { text-align:center; margin-top:10px; }
    .goto-btn { display:inline-block; padding:10px 18px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
    .goto-btn:hover { background:#0866cc; }
    .description-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 600px) { .description-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
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

    <div class="bb-editor">
      <div class="description-grid">
        <div>
          <label for="descBoxHe">תיאור (עברית)</label>
          <textarea class="bb-textarea" name="description_he" id="descBoxHe" rows="8"></textarea>
        </div>
        <div>
          <label for="descBoxEn">Description (English)</label>
          <textarea class="bb-textarea" name="description_en" id="descBoxEn" rows="8" style="direction: ltr; text-align: left;"></textarea>
        </div>
      </div>
      
      🔍 תצוגה מקדימה:
      <div class="bb-preview bbcode" id="previewBox">— ריק —</div>
    </div>

    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" placeholder="אפשר להזין URL מלא או שם קובץ (logo.png)">

    <label>🖼️ תמונת סטיקר לפוסטרים</label>
    <input type="text" name="poster_image_url" placeholder="sticker.png">

    <button type="submit">📥 שמור אוסף</button>
  </form>
</div>

<div style="text-align:center; margin-top:20px;">
  <a href="collections.php" style="background:#007bff; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;">
    ⬅ חזרה לרשימת האוספים
  </a>
</div>

<?php include 'footer.php'; ?>
<?php include 'bbcode_editor.php'; ?>
<?php ob_end_flush(); ?>