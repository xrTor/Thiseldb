<?php include 'header.php'; ?>

<?php
 require_once 'server.php';
 
$message = '';

// מחיקה
if (isset($_GET['delete'])) {
  $did = intval($_GET['delete']);
  $conn->query("DELETE FROM contact_requests WHERE id = $did");
  $message = "🗑️ הפנייה נמחקה";
}

$res = $conn->query("SELECT * FROM contact_requests ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פניות שהתקבלו</title>
  <style>
    body { font-family: Arial; background:#f0f0f0; padding:40px; direction:rtl; }
    .entry {
      background:#fff; padding:16px; margin-bottom:20px;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1);
    }
    .message {
      background:#ffe; border:1px solid #cc9; padding:10px;
      border-radius:6px; margin-bottom:16px; font-weight:bold; color:#444;
    }
    .info { color:#666; font-size:14px; margin-bottom:10px; }
    a.btn {
      padding:6px 12px; background:#eee; border-radius:6px;
      text-decoration:none; margin-right:10px;
    }
    a.btn:hover { background:#ddd; }
  </style>
</head>
<body>

<h2>📥 פניות שהתקבלו</h2>

<?php if ($message): ?>
  <div class="message"><?= $message ?></div>
<?php endif; ?>

<?php if ($res && $res->num_rows > 0): while ($row = $res->fetch_assoc()): ?>
  <div class="entry">
    <div class="info">
      🕓 <?= htmlspecialchars($row['created_at']) ?> |
      📧 <?= htmlspecialchars($row['email']) ?>
    </div>
    <p><?= nl2br(htmlspecialchars($row['message'])) ?></p>
    <a href="manage_contacts.php?delete=<?= $row['id'] ?>" class="btn" onclick="return confirm('למחוק את הפנייה?')">🗑️ מחק</a>
  </div>
<?php endwhile; else: ?>
  <p>אין פניות להצגה.</p>
<?php endif; ?>

</body>
</html>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>
