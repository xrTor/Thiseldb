<?php
require_once 'server.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$poster_id     = isset($_GET['poster_id']) ? (int)$_GET['poster_id'] : 0;
$collection_id = isset($_GET['collection_id']) ? (int)$_GET['collection_id'] : 0;

$mode = '';
if ($collection_id > 0) $mode = 'collection';
elseif ($poster_id > 0) $mode = 'poster';

$success = false;
$error   = '';

/* ===== ודא טבלת אוספים קיימת + handled_at ===== */
try {
  $conn->query("
    CREATE TABLE IF NOT EXISTS collection_reports (
      id INT AUTO_INCREMENT PRIMARY KEY,
      collection_id INT NOT NULL,
      report_reason TEXT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      handled_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
} catch (Throwable $e) {
  // לא מפילים את העמוד
}

/* ===== POST לפני include header ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mode_post = (string)($_POST['mode'] ?? '');
  $reason = trim((string)($_POST['reason'] ?? ''));

  if ($reason === '') {
    $error = 'נא לכתוב מה התקלה.';
  } else {
    if ($mode_post === 'poster') {
      $pid = (int)($_POST['poster_id'] ?? 0);
      if ($pid <= 0) {
        $error = 'Poster ID לא תקין.';
      } else {
        $stmt = $conn->prepare("INSERT INTO poster_reports (poster_id, report_reason) VALUES (?, ?)");
        $stmt->bind_param("is", $pid, $reason);
        $stmt->execute();
        $stmt->close();

        header("Location: index.php?report_submitted=1");
        exit;
      }
    } elseif ($mode_post === 'collection') {
      $cid = (int)($_POST['collection_id'] ?? 0);
      if ($cid <= 0) {
        $error = 'Collection ID לא תקין.';
      } else {
        $stmt = $conn->prepare("INSERT INTO collection_reports (collection_id, report_reason) VALUES (?, ?)");
        $stmt->bind_param("is", $cid, $reason);
        $stmt->execute();
        $stmt->close();

        header("Location: index.php?report_submitted=1");
        exit;
      }
    } else {
      $error = 'מצב דיווח לא תקין.';
    }
  }
}

include 'header.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🚨 דיווח</title>
  <style>
    body { font-family:Arial; background:#f2f2f2; padding:40px; direction:rtl; }
    .report-box { max-width:520px; margin:auto; background:white; padding:20px; border-radius:8px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    textarea { width:100%; height:110px; margin-top:10px; margin-bottom:10px; border-radius:6px; border:1px solid #ccc; padding:10px; resize:vertical; }
    button { padding:10px 16px; background:#a00; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer; }
    button:hover { background:#c00; }
    .err { background:#ffe3e3; border:1px solid #ffa8a8; padding:10px; border-radius:6px; margin-bottom:10px; color:#c92a2a; }
    .back-button { text-align:center; margin-top:20px; }
    .back-button a { display:inline-block; background:#eee; color:#333; padding:8px 14px; border-radius:6px; text-decoration:none; font-weight:bold; }
    .back-button a:hover { background:#ddd; }
  </style>
</head>
<body>

<div class="report-box">
  <h2>
    <?php if ($mode === 'collection'): ?>
      🚨 דיווח על האוסף
    <?php else: ?>
      🚨 דיווח על הפוסטר
    <?php endif; ?>
  </h2>

  <?php if ($error !== ''): ?>
    <div class="err"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($mode === 'collection' && $collection_id > 0): ?>
    <form method="post">
      <input type="hidden" name="mode" value="collection">
      <input type="hidden" name="collection_id" value="<?= (int)$collection_id ?>">
      <textarea name="reason" placeholder="מה התקלה באוסף?"></textarea>
      <button type="submit">שלח דיווח</button>
    </form>

    <div class="back-button">
      <a href="collection.php?id=<?= (int)$collection_id ?>">⬅ חזור לאוסף</a>
    </div>

  <?php elseif ($mode === 'poster' && $poster_id > 0): ?>
    <form method="post">
      <input type="hidden" name="mode" value="poster">
      <input type="hidden" name="poster_id" value="<?= (int)$poster_id ?>">
      <textarea name="reason" placeholder="מה התקלה בפוסטר?"></textarea>
      <button type="submit">שלח דיווח</button>
    </form>

    <div class="back-button">
      <a href="poster.php?id=<?= (int)$poster_id ?>">⬅ חזור לפוסטר</a>
    </div>

  <?php else: ?>
    <div class="err">❌ לא התקבל poster_id או collection_id.</div>
    <div class="back-button">
      <a href="javascript:history.back()">⬅ חזור</a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
<?php include 'footer.php'; ?>
