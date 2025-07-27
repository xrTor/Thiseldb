<?php include 'header.php'; ?>

<?php
 require_once 'server.php';
 
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST['email'] ?? '');
  $msg = trim($_POST['message'] ?? '');

  if ($email === '' || $msg === '') {
    $message = "⚠️ נא למלא את כל השדות";
  } else {
    $stmt = $conn->prepare("INSERT INTO contact_requests (email, message) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $msg);
    $stmt->execute(); $stmt->close();
    $message = "✅ הפנייה נשמרה בהצלחה";
  }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>צור קשר</title>
  <style>
    body { font-family: Arial; background:#f5f5f5; padding:10px; direction:rtl; }
    .form-box {
      max-width:600px; margin:auto; background:#fff; padding:20px;
      border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1);
    }
    input, textarea {
      width:100%; padding:10px; margin-bottom:12px;
      border:1px solid #ccc; border-radius:6px;
    }
    button {
      padding:10px 20px; background:#007bff; color:#fff;
      border:none; border-radius:6px; cursor:pointer;
    }
    .message {
      background:#ffe; border:1px solid #cc9;
      padding:10px; border-radius:6px;
      margin-bottom:16px; font-weight:bold; color:#444;
    }
  </style>
</head>
<body>

<div class="form-box">
  <h2>📩 צור קשר</h2>

  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="email" name="email" placeholder="📧 אימייל לחזרה" required>
    <textarea name="message" placeholder="✍️ תוכן הפנייה..." rows="5" required></textarea>
    <button type="submit">📤 שלח פנייה</button>
  </form>
</div>

</body>
</html>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>
