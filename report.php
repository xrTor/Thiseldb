<?php include 'header.php'; ?>

<?php
require_once 'server.php';
 
$poster_id = isset($_GET['poster_id']) ? intval($_GET['poster_id']) : 0;
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $poster_id = intval($_POST['poster_id'] ?? 0);
  $reason = trim($_POST['reason'] ?? '');
  if ($poster_id > 0 && $reason !== '') {
    $stmt = $conn->prepare("INSERT INTO poster_reports (poster_id, report_reason) VALUES (?, ?)");
    $stmt->bind_param("is", $poster_id, $reason);
    $stmt->execute();
    $stmt->close();
    $success = true;
  }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🚨 דיווח על תקלה</title>
  <style>
    body { font-family:Arial; background:#f2f2f2; padding:40px; direction:rtl; }
    .report-box { max-width:500px; margin:auto; background:white; padding:20px; border-radius:8px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    textarea { width:100%; height:100px; margin-top:10px; margin-bottom:10px; border-radius:4px; border:1px solid #ccc; padding:8px; resize:vertical; }
    button { padding:10px 16px; background:#a00; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer; }
    button:hover { background:#c00; }
    .success { background:#dfd; border:1px solid #6c6; padding:10px; border-radius:6px; margin-bottom:10px; text-align:center; }
    .back-button { text-align:center; margin-top:20px; }
    .back-button a {
      display:inline-block;
      background:#eee;
      color:#333;
      padding:8px 14px;
      border-radius:6px;
      text-decoration:none;
      font-weight:bold;
    }
    .back-button a:hover { background:#ddd; }
  </style>
</head>
<body>

<div class="report-box">
  <?php if ($success): ?>
    <div class="success">✅ הדיווח נשלח בהצלחה! תודה רבה 🙏</div>
  <?php endif; ?>

  <h2>🚨 דיווח על תקלה בפוסטר</h2>
  <p>אם מצאת תקלה — כמו פוסטר שגוי, תמונה שבורה או מידע שגוי — תוכל לדווח לנו כאן:</p>

  <form method="post">
    <input type="hidden" name="poster_id" value="<?= $poster_id ?>">
    <textarea name="reason" placeholder="מה התקלה?"></textarea>
    <button type="submit">שלח דיווח</button>
  </form>

  <div class="back-button">
    <a href="poster.php?id=<?= $poster_id ?>">⬅ חזור לפוסטר</a>
  </div>
</div>

</body>
</html>


<?php include 'footer.php'; ?>