<?php
/* ===== סשן + CSRF חייבים לפני כל output ===== */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32)); // אסימון קבוע לסשן (נסובב אחרי פעולה)
}

require_once 'server.php';

/* ===== חסימת מחיקה ב-GET (תאימות אחורה) ===== */
if (isset($_GET['delete'])) {
  http_response_code(405);
  echo 'State-changing actions via GET requests are forbidden for security reasons. Please use POST forms.';
  exit;
}

/* ===== טיפול במחיקה ב-POST (עם CSRF) + PRG ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'], $_POST['id'], $_POST['csrf'])
    && $_POST['action'] === 'delete_contact') {

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    echo 'Invalid CSRF token.';
    exit;
  }

  $did = (int)$_POST['id'];
  if ($did > 0) {
    $stmt = $conn->prepare("DELETE FROM contact_requests WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $did);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash'] = "🗑️ הפנייה #$did נמחקה";
  }

  // מסובבים אסימון למניעת replay, ואז מפנים חזרה (PRG)
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
  header('Location: manage_contacts.php', true, 303);
  exit;
}

/* ===== שליפה ===== */
$res = $conn->query("SELECT * FROM contact_requests ORDER BY created_at DESC");

/* ===== עזר להצגה בטוחה ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

include 'header.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פניות שהתקבלו</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f0f0f0; padding:40px; direction:rtl; }
    h2 { margin-top:0; }
    .entry {
      background:#fff; padding:16px; margin-bottom:20px;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1);
    }
    .message {
      background:#ffe; border:1px solid #cc9; padding:10px;
      border-radius:6px; margin-bottom:16px; font-weight:bold; color:#444;
    }
    .flash {
      background:#e8f5e9; border:1px solid #c8e6c9; color:#1b5e20;
      padding:10px; border-radius:6px; margin-bottom:16px; font-weight:bold;
    }
    .info { color:#666; font-size:14px; margin-bottom:10px; }
    a.btn, button.btn {
      padding:6px 12px; background:#eee; border-radius:6px;
      text-decoration:none; margin-right:10px; color:#333; border:1px solid #ddd; cursor:pointer;
      font: inherit;
    }
    a.btn:hover, button.btn:hover { background:#ddd; }
    form.inline { display:inline; margin:0; }
  </style>
</head>
<body>

<h2>📥 פניות שהתקבלו</h2>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if ($res && $res->num_rows > 0): ?>
  <?php while ($row = $res->fetch_assoc()): ?>
    <div class="entry">
      <div class="info">
        🕓 <?= h($row['created_at']) ?> |
        📧 <?= h($row['email']) ?>
      </div>
      <p><?= nl2br(h($row['message'])) ?></p>

      <!-- מחיקה ב-POST עם CSRF (במקום קישור GET) -->
      <form method="post" action="manage_contacts.php" class="inline" onsubmit="return confirm('למחוק את הפנייה?');">
        <input type="hidden" name="action" value="delete_contact">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <button type="submit" class="btn">🗑️ מחק</button>
      </form>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p>אין פניות להצגה.</p>
<?php endif; ?>

</body>
</html>

<?php include 'footer.php'; ?>
