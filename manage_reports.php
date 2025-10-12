<?php
/* ===== סשן + CSRF חייבים לבוא לפני כל output/כולל header.php ===== */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32)); // יישאר קבוע לאורך הסשן עד שנבחר לסובב
}

require_once 'server.php';

/* ===== חסימת פעולות ב-GET (תאימות אחורה) ===== */
if (isset($_GET['delete']) || isset($_GET['handle'])) {
  http_response_code(405);
  echo 'State-changing actions via GET requests are forbidden for security reasons. Please use POST forms.';
  exit;
}

/* ===== טיפול ב-POST לפני כל הדפסה ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf   = $_POST['csrf']   ?? '';

  // אימות CSRF – לא מייצרים אסימון חדש כאן! משתמשים במה שקיים בסשן.
  if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    http_response_code(403);
    echo 'Invalid CSRF token.';
    exit;
  }

  if ($action === 'delete_report' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id > 0) {
      $stmt = $conn->prepare('DELETE FROM poster_reports WHERE id = ? LIMIT 1');
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
      $_SESSION['flash'] = "הדיווח #$id נמחק.";
    }
    // אופציונלי: לסובב אסימון לאחר פעולה מוצלחת (מניעת replay)
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    header('Location: manage_reports.php', true, 303);
    exit;
  }

  if ($action === 'handle_report' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id > 0) {
      $stmt = $conn->prepare('UPDATE poster_reports SET handled_at = NOW() WHERE id = ?');
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
      $_SESSION['flash'] = "הדיווח #$id סומן כטופל.";
    }
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    header('Location: manage_reports.php', true, 303);
    exit;
  }
}

include 'header.php';

/* ===== שליפת דיווחים ===== */
$sql = "
  SELECT r.id, r.report_reason, r.created_at, r.handled_at, p.title_en, p.id AS poster_id
  FROM poster_reports r
  JOIN posters p ON r.poster_id = p.id
  ORDER BY r.created_at DESC
";
$result = $conn->query($sql);

/* ===== עזר להצגה בטוחה ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🛠️ ניהול דיווחים</title>
  <style>
    body { font-family:Arial, sans-serif; background:#f2f2f2; padding:10px; direction:rtl; }
    h2 { margin-bottom:12px; }
    .flash { background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9; border-radius:8px; padding:10px; margin:10px 0; }
    table { width:100%; border-collapse:collapse; background:#fff; }
    th, td { padding:12px; border-bottom:1px solid #ccc; text-align:right; vertical-align: top; }
    th { background:#eee; }
    tr:hover td { background:#f9f9f9; }
    .actions { white-space:nowrap; }
    .linklike { background:none; border:none; padding:0; color:#0b5ed7; cursor:pointer; font:inherit; }
    .linklike:hover { text-decoration:underline; }
    .inline { display:inline; margin:0; }
    .handled { color:#090; font-weight:bold; }
    .note { color:#777; margin-top:10px; }
    .reason { white-space: pre-wrap; }
  </style>
</head>
<body>

<h2>🛠️ דיווחים שהתקבלו</h2>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if ($result && $result->num_rows > 0): ?>
  <table>
    <tr>
      <th>פוסטר</th>
      <th>תקלה</th>
      <th>נשלח</th>
      <th>טופל</th>
      <th>פעולות</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td>
          <a href="poster.php?id=<?= (int)$row['poster_id'] ?>">
            <?= h($row['title_en']) ?>
          </a>
        </td>
        <td class="reason"><?= nl2br(h($row['report_reason'])) ?></td>
        <td><?= h($row['created_at']) ?></td>
        <td>
          <?php if (!empty($row['handled_at'])): ?>
            <span class="handled">✅ <?= h($row['handled_at']) ?></span>
          <?php else: ?>
            ❌ טרם טופל
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if (empty($row['handled_at'])): ?>
            <form method="post" action="manage_reports.php" class="inline" onsubmit="return confirm('לסמן את הדיווח #<?= (int)$row['id'] ?> כטופל?');">
              <input type="hidden" name="action" value="handle_report">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
              <button type="submit" class="linklike">סמן כטופל</button>
            </form>
            &nbsp;|&nbsp;
          <?php endif; ?>
          <form method="post" action="manage_reports.php" class="inline" onsubmit="return confirm('למחוק את הדיווח #<?= (int)$row['id'] ?>?');">
            <input type="hidden" name="action" value="delete_report">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
            <button type="submit" class="linklike">🗑️ מחק</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
<?php else: ?>
  <p class="note">אין דיווחים כרגע</p>
<?php endif; ?>

</body>
</html>

<?php include 'footer.php'; ?>
