<?php
/* ==========================================================
   Session + CSRF
   ========================================================== */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

require_once 'server.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ==========================================================
   לפני X זמן (ללא שבירת עיצוב)
   ========================================================== */
function time_ago(string $datetime): string {
  $ts = strtotime($datetime);
  if (!$ts) return '';
  $diff = time() - $ts;

  if ($diff < 60) return 'לפני רגע';
  if ($diff < 3600) return 'לפני ' . floor($diff/60) . ' דקות';
  if ($diff < 86400) return 'לפני ' . floor($diff/3600) . ' שעות';
  if ($diff < 2592000) return 'לפני ' . floor($diff/86400) . ' ימים';
  if ($diff < 31536000) return 'לפני ' . floor($diff/2592000) . ' חודשים';
  return 'לפני ' . floor($diff/31536000) . ' שנים';
}


/* ==========================================================
   Helper: check column exists
   ========================================================== */
function column_exists(mysqli $conn, string $table, string $column): bool {
  $sql = "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = ($res && $res->num_rows > 0);
  $stmt->close();
  return $ok;
}

/* ==========================================================
   Ensure tables + columns
   ========================================================== */
$conn->query("
  CREATE TABLE IF NOT EXISTS poster_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poster_id INT NOT NULL,
    report_reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
  CREATE TABLE IF NOT EXISTS collection_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    report_reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

foreach (['poster_reports','collection_reports'] as $t) {
  if (!column_exists($conn, $t, 'handled_at')) {
    $conn->query("ALTER TABLE {$t} ADD COLUMN handled_at DATETIME NULL");
  }
  if (!column_exists($conn, $t, 'deleted_at')) {
    $conn->query("ALTER TABLE {$t} ADD COLUMN deleted_at DATETIME NULL");
  }
}

/* ==========================================================
   POST actions
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    exit('Bad CSRF');
  }

  $action = $_POST['action'] ?? '';

  
  // ---- soft delete ----
  if ($action === 'soft_delete_one') {
    $table = ($_POST['type'] === 'collection') ? 'collection_reports' : 'poster_reports';
    $id = (int)$_POST['id'];
    $conn->query("UPDATE {$table} SET deleted_at = NOW() WHERE id = {$id} LIMIT 1");
  }

  if ($action === 'soft_delete_all') {
    $conn->query("UPDATE poster_reports SET deleted_at = NOW() WHERE deleted_at IS NULL");
    $conn->query("UPDATE collection_reports SET deleted_at = NOW() WHERE deleted_at IS NULL");
  }

  if ($action === 'soft_delete_handled') {
    $conn->query("UPDATE poster_reports SET deleted_at = NOW() WHERE handled_at IS NOT NULL AND deleted_at IS NULL");
    $conn->query("UPDATE collection_reports SET deleted_at = NOW() WHERE handled_at IS NOT NULL AND deleted_at IS NULL");
  }

  if ($action === 'soft_delete_posters') {
    $conn->query("UPDATE poster_reports SET deleted_at = NOW() WHERE deleted_at IS NULL");
  }

  if ($action === 'soft_delete_collections') {
    $conn->query("UPDATE collection_reports SET deleted_at = NOW() WHERE deleted_at IS NULL");
  }

  // ---- handle / unhandle ----
  if ($action === 'handle_one') {
    $table = ($_POST['type'] === 'collection') ? 'collection_reports' : 'poster_reports';
    $id = (int)$_POST['id'];
    $conn->query("UPDATE {$table} SET handled_at = NOW() WHERE id = {$id}");
  }

  if ($action === 'unhandle_one') {
    $table = ($_POST['type'] === 'collection') ? 'collection_reports' : 'poster_reports';
    $id = (int)$_POST['id'];
    $conn->query("UPDATE {$table} SET handled_at = NULL WHERE id = {$id}");
  }

// ---- undo (פריט אחד בלבד) ----
if ($action === 'undo_one') {
  $table = ($_POST['type'] === 'collection') ? 'collection_reports' : 'poster_reports';
  $id = (int)$_POST['id'];
  $conn->query("UPDATE {$table} SET deleted_at = NULL WHERE id = {$id} LIMIT 1");
}

  // ---- 🔥 EMPTY TRASH (HARD DELETE) ----
  if ($action === 'empty_trash') {
    $conn->query("DELETE FROM poster_reports WHERE deleted_at IS NOT NULL");
    $conn->query("DELETE FROM collection_reports WHERE deleted_at IS NOT NULL");
  }

  if ($action === 'hard_delete_one') {
  $table = ($_POST['type'] === 'collection') ? 'collection_reports' : 'poster_reports';
  $id = (int)$_POST['id'];
  $conn->query("DELETE FROM {$table} WHERE id = {$id} LIMIT 1");
}


  $_SESSION['csrf'] = bin2hex(random_bytes(32));
  header("Location: manage_reports.php?view=" . ($_GET['view'] ?? 'active'), true, 303);
  exit;
}

/* ==========================================================
   View mode
   ========================================================== */
$view = ($_GET['view'] ?? 'active') === 'trash' ? 'trash' : 'active';
$where = $view === 'trash' ? 'IS NOT NULL' : 'IS NULL';

include 'header.php';

/* ==========================================================
   Fetch reports
   ========================================================== */
$sql = "
  SELECT
    'poster' AS rtype,
    r.id,
    r.report_reason,
    r.created_at,
    r.handled_at,
    r.deleted_at,
    p.title_en AS subject_title,
    p.id AS subject_id
  FROM poster_reports r
  JOIN posters p ON p.id = r.poster_id
  WHERE r.deleted_at {$where}

  UNION ALL

  SELECT
    'collection' AS rtype,
    r.id,
    r.report_reason,
    r.created_at,
    r.handled_at,
    r.deleted_at,
    c.name AS subject_title,
    c.id AS subject_id
  FROM collection_reports r
  JOIN collections c ON c.id = r.collection_id
  WHERE r.deleted_at {$where}

  ORDER BY created_at DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<title>🚨 ניהול דיווחים</title>
<style>
body { font-family:Arial; background:#f2f2f2; padding:10px; }
.topbar { display:flex; gap:8px; flex-wrap:wrap; margin:10px 0; }
.btn { padding:7px 12px; border-radius:8px; border:1px solid #ccc; background:#fff; cursor:pointer; font-weight:bold; }
.btn-danger { background:#fff5f5; color:#c92a2a; border-color:#ffa8a8; }
.badge { padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid #ccc; }
table { width:100%; border-collapse:collapse; background:#fff; }
th,td { padding:10px; border-bottom:1px solid #ccc; text-align:center; vertical-align:middle; }
th { background:#eee; }
.handled { color:green; font-weight:bold; }
.not-handled { color:#a00; font-weight:bold; }
</style>
</head>
<body>

<h2>🚨 ניהול דיווחים</h2>

<div class="topbar">
  <a class="btn" href="?view=active">📋 פעילים</a>
  <a class="btn" href="?view=trash">🗑️ אשפה</a>

  <?php if ($view === 'active'): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="soft_delete_handled">
      <button class="btn btn-danger">🚨 מחק מטופלים</button>
    </form>
    <form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="soft_delete_all">
      <button class="btn btn-danger">🚨 מחק הכל</button>
    </form>
   
  <?php else: ?>
    <form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="action" value="empty_trash">
      <button class="btn btn-danger">🔥 רוקן סל אשפה</button>
    </form>
  <?php endif; ?>
</div>

<table>
<thead>
<tr>
  <th>#</th><th>סוג</th><th>על מה</th><th>דיווח</th><th>תאריך</th><?php if ($view==='trash'): ?><th>נמחק</th><?php endif; ?><th>סטטוס</th><th>פעולות</th>
</tr>
</thead>
<tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= (int)$row['id'] ?></td>
  <td><span class="badge"><?= $row['rtype']==='collection'?'אוסף':'פוסטר' ?></span></td>
  <td>
    <?php
      $link = ($row['rtype']==='collection')
        ? 'collection.php?id='.$row['subject_id']
        : 'poster.php?id='.$row['subject_id'];
    ?>
    <a href="<?=h($link)?>" target="_blank"><?=h($row['subject_title'])?></a>
  </td>
  <td><?= nl2br(h($row['report_reason'])) ?></td>
  <td style="text-align:center; white-space:nowrap;">
    <?= date('H:i d.m.Y', strtotime($row['created_at'])) ?>
    <span style="font-size:12px; opacity:0.6; margin-right:6px;">⌛ <?= time_ago($row['created_at']) ?></span>
  </td>
  <?php if ($view==='trash'): ?>
  <td style="text-align:center; white-space:nowrap;">
    <?= date('H:i d.m.Y', strtotime($row['deleted_at'])) ?>
    <span style="font-size:12px; opacity:0.6; margin-right:6px;">⌛ <?= time_ago($row['deleted_at']) ?></span>
  </td>
  <?php endif; ?>
  <td><?= $row['handled_at'] ? '<span class="handled">מטופל</span>' : '<span class="not-handled">לא טופל</span>' ?></td>
  <td>
    <?php if ($view==='active'): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
        <input type="hidden" name="action" value="<?= $row['handled_at']?'unhandle_one':'handle_one' ?>">
        <input type="hidden" name="type" value="<?=h($row['rtype'])?>">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <button class="btn"><?= $row['handled_at']?'↩️ בטל טופל':'🚨 סמן כטופל' ?></button>
      </form>

      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
        <input type="hidden" name="action" value="soft_delete_one">
        <input type="hidden" name="type" value="<?=h($row['rtype'])?>">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <button class="btn btn-danger">🗑️ מחק</button>
      </form>
    <?php else: ?>

  <!-- שחזור -->
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
    <input type="hidden" name="action" value="undo_one">
    <input type="hidden" name="type" value="<?=h($row['rtype'])?>">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <button class="btn">↩️ שחזר</button>
  </form>

  <!-- מחיקה סופית -->
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
    <input type="hidden" name="action" value="hard_delete_one">
    <input type="hidden" name="type" value="<?=h($row['rtype'])?>">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <button class="btn btn-danger">🔥 מחק סופית</button>
  </form>

<?php endif; ?>

  </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
<?php include 'footer.php'; ?>
