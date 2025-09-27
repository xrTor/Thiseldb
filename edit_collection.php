<?php
require_once 'server.php';
include 'header.php';
require_once 'bbcode.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_collection'])) {
  $name = trim($_POST['name'] ?? '');
  $desc_shared = trim($_POST['description_shared'] ?? '');
  $desc_he = trim($_POST['description_he'] ?? '');
  $desc_en = trim($_POST['description_en'] ?? '');
  $img  = trim($_POST['image_url'] ?? '');
  $poster_img = trim($_POST['poster_image_url'] ?? '');
  
  $desc = '';
  if (!empty($desc_shared)) {
      $desc .= "[משותף]\n" . $desc_shared . "\n[/משותף]\n\n\n";
  }
  if (!empty($desc_he) || !empty($desc_en)) {
      $desc .= "[עברית]\n" . $desc_he . "\n[/עברית]\n\n\n[אנגלית]\n" . $desc_en . "\n[/אנגלית]";
  }
  $desc = trim($desc);


  if ($img !== '') {
    if (!preg_match('~^https?://~i', $img) && strpos($img, '/') === false && strpos($img, '\\') === false) {
      $img = 'images/logos/' . $img;
    }
  }

  $posterImgToSave = null;
  if ($poster_img !== '') {
    if (strpos($poster_img, '/') === false && strpos($poster_img, '\\') === false) {
      $posterImgToSave = 'images/stickers/' . $poster_img;
    } else { 
      $posterImgToSave = $poster_img;
    }
  }

  if ($name !== '') {
    $imgToSave = ($img === '') ? null : $img;
    
    $stmt = $conn->prepare("UPDATE collections SET name=?, description=?, image_url=?, poster_image_url=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssssi", $name, $desc, $imgToSave, $posterImgToSave, $id);
    $stmt->execute();
    $stmt->close();

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
$desc_shared_val = '';
$desc_he_val = '';
$desc_en_val = '';

if (preg_match('/\[משותף\](.*?)\[\/משותף\]/is', $descValue, $matches_shared)) {
    $desc_shared_val = trim($matches_shared[1]);
}
if (preg_match('/\[עברית\](.*?)\[\/עברית\]/is', $descValue, $matches_he)) {
    $desc_he_val = trim($matches_he[1]);
}
if (preg_match('/\[אנגלית\](.*?)\[\/אנגלית\]/is', $descValue, $matches_en)) {
    $desc_en_val = trim($matches_en[1]);
}

if (empty($desc_he_val) && empty($desc_en_val) && !empty($descValue) && strpos($descValue, '[עברית]') === false) {
    if (preg_match('/\p{Hebrew}/u', $descValue)) {
        $desc_he_val = $descValue;
    } else {
        $desc_en_val = $descValue;
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>✏️ עריכת אוסף</title>
    
  <link rel="stylesheet" href="bbcode.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    body { font-family: Arial; direction: rtl; background: #f0f0f0; padding: 10px; }
    .form-box { max-width: 750px; margin: 20px auto; background: white; padding: 8px 12px; border-radius: 6px; box-shadow: 0 0 4px rgba(0,0,0,0.08); }
    input, textarea { width: 100%; padding: 6px 8px; margin: 6px 0; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
    button { padding: 8px 14px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background:#0056b3; }
    .message { background:#ffe; padding:8px; border-radius:6px; margin-bottom:8px; border:1px solid #ddc; color:#333; }
    .details { margin-bottom:8px; font-size:15px; line-height:1.4; color:#555; }
    .hint { font-size: 12px; color: #666; margin-top: -5px; margin-bottom: 6px; }
    .inline-image-preview img { max-width: 100%; height: auto; border-radius: 4px; margin-top: 6px; border: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .description-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 600px) { .description-grid { grid-template-columns: 1fr; } }
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
    <div class="details-desc">
      <strong class="details-desc-title">📝 תיאור נוכחי:</strong>
      <div class="bbcode">
         <?= $descValue !== '' ? bbcode_to_html($descValue) : '<em>אין תיאור</em>' ?>
      </div>
    </div>
    <?php if (!empty(trim($collection['image_url']))): ?>
      <p style="margin-top: 6px;">🖼️ תמונה: <a href="javascript:void(0);" onclick="toggleImagePreview(this, '<?= htmlspecialchars($collection['image_url']) ?>')">צפייה</a></p>
    <?php endif; ?>
  </div>

  <form method="post">
    
    <label>📁 שם האוסף</label>
    <input type="text" name="name" value="<?= htmlspecialchars($collection['name']) ?>" required>
    <a href="bbcode_guide.php" target="_blank">📜 מדריך BBCode</a><br><br>
    <div class="bb-editor">
        <div>
          <label for="descBoxShared">תיאור משותף (חלק עליון, לתמונות/באנרים)</label>
          <textarea class="bb-textarea" name="description_shared" id="descBoxShared" rows="5"><?= htmlspecialchars($desc_shared_val) ?></textarea>
        </div>
        <br>
        <div class="description-grid">
            <div>
              <label for="descBoxHe">תיאור (עברית)</label>
              <textarea class="bb-textarea" name="description_he" id="descBoxHe" rows="8"><?= htmlspecialchars($desc_he_val) ?></textarea>
            </div>
            <div>
              <label for="descBoxEn">Description (English)</label>
              <textarea class="bb-textarea" name="description_en" id="descBoxEn" rows="8" style="direction: ltr; text-align: left;"><?= htmlspecialchars($desc_en_val) ?></textarea>
            </div>
        </div>
        🔍 תצוגה מקדימה:
        <div class="bb-preview bbcode" id="previewBox"></div>
    </div>

    <label>🖼️ כתובת לתמונה</label>
    <input type="text" name="image_url" value="<?= htmlspecialchars($collection['image_url']) ?>" placeholder="(אפשר להשאיר ריק — לא תוצג תמונה)">
    
    <label>🖼️ תמונת סטיקר לפוסטרים</label>
    <input type="text" name="poster_image_url" value="<?= htmlspecialchars(basename($collection['poster_image_url'] ?? '')) ?>" placeholder="שם קובץ בלבד (לדוגמה: sticker.png)">
    <div class="hint">
      הזן שם קובץ בלבד. המערכת תחפש אותו בתיקייה <code>images/stickers/</code>.
    </div>
    
    <button type="submit" name="update_collection">💾 שמור שינויים</button>
  </form>

  <br><a href="collections.php">⬅ חזרה לרשימת האוספים</a>
</div>

<script>
function toggleImagePreview(linkElement, imageUrl) {
  const parentP = linkElement.parentNode;
  const existingPreview = parentP.nextElementSibling;
  if (existingPreview && existingPreview.classList.contains('inline-image-preview')) {
    existingPreview.remove();
  } else {
    const previewContainer = document.createElement('div');
    previewContainer.className = 'inline-image-preview';
    const img = document.createElement('img');
    img.src = imageUrl;
    previewContainer.appendChild(img);
    parentP.parentNode.insertBefore(previewContainer, parentP.nextSibling);
  }
}
</script>
</body>
</html>

<?php include 'footer.php'; ?>
<?php include 'bbcode_editor.php'; ?>