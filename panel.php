<?php
include 'header.php';

require_once 'server.php';
 
// פונקציה בטוחה לספירה
function safeCount($conn, $table) {
  $res = $conn->query("SELECT COUNT(*) as c FROM $table");
  return ($res && $res->num_rows > 0) ? $res->fetch_assoc()['c'] : 0;
}

// סטטיסטיקות
$stats = [
  'posters'     => safeCount($conn, 'posters'),
  'collections' => safeCount($conn, 'collections'),
  'contacts'    => safeCount($conn, 'contact_requests'),
  'votes'       => safeCount($conn, 'poster_votes'),
  'reports'     => safeCount($conn, 'poster_reports') // ✅ תיקון לשם הטבלה
];

// פעילות אחרונה
$latest_contacts = $conn->query("SELECT * FROM contact_requests ORDER BY created_at DESC LIMIT 5");
$latest_votes = $conn->query("
  SELECT pv.*, p.title_en 
  FROM poster_votes pv 
  JOIN posters p ON p.id = pv.poster_id 
  ORDER BY pv.created_at DESC LIMIT 5
");
$latest_posters = $conn->query("SELECT * FROM posters ORDER BY created_at DESC LIMIT 5");
$latest_reports = $conn->query("
  SELECT pr.*, po.title_en 
  FROM poster_reports pr 
  JOIN posters po ON po.id = pr.poster_id 
  ORDER BY pr.created_at DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>מרכז ניהול</title>
  <style>
    body {
      font-family: Arial;
      background:#f4f4f4;
      padding:40px;
      direction:rtl;
    }
    .box-grid {
      display:flex;
      flex-wrap:wrap;
      gap:20px;
      margin-bottom:40px;
    }
    .stat-box, .nav-box {
      background:#fff;
      padding:20px;
      border-radius:8px;
      box-shadow:0 0 6px rgba(0,0,0,0.1);
      flex:1; min-width:200px;
    }
    h2 { margin-bottom:20px; }
    .nav-box a {
      display:block;
      margin-bottom:10px;
      padding:10px;
      background:#007bff;
      color:#fff;
      text-decoration:none;
      border-radius:6px;
    }
    .nav-box a:hover {
      background:#0056b3;
    }
    .recent-box { margin-bottom:30px; }
    .entry {
      background:#fff;
      padding:10px;
      border-radius:6px;
      margin-bottom:10px;
      box-shadow:0 0 4px rgba(0,0,0,0.05);
    }
    .entry small {
      color:#888;
      font-size:12px;
      display:block;
      margin-top:6px;
    }
  </style>
</head>
<body>

<h1>📋 מרכז ניהול מערכת</h1>

<div class="box-grid">
  <div class="stat-box">
    <h2>📊 סטטיסטיקות</h2>
    <p>🎬 פוסטרים: <?= $stats['posters'] ?></p>
    <p>📦 אוספים: <?= $stats['collections'] ?></p>
    <p>📩 פניות צור קשר: <?= $stats['contacts'] ?></p>
    <p>❤️/💔 הצבעות: <?= $stats['votes'] ?></p>
    <p>🚨 דיווחים: <?= $stats['reports'] ?></p>
  </div>

  <div class="nav-box">
    <h2>🧭 ניווט מהיר</h2>
    <a href="manage_posters.php">ניהול פוסטרים</a>
    <a href="manage_collections.php">ניהול אוספים</a>
    <a href="manage_contacts.php">ניהול פניות</a>
    <a href="manage_reports.php">ניהול דיווחים</a>
    <a href="poster_user_tag.php">ניהול תגיות</a>
    <a href="manage_languages.php">ניהול שפות</a>
    <a href="type_admin.php">ניהול סוגים</a>
    <a href="poster_trailers.php">ניהול טריילים</a>
    <a href="mange_types.php">שיוך סוגים</a>
    <a href="sync.php">סנכרון תמונות מהאינטרנט</a>

     
    
  </div>
</div>

<div class="recent-box">
  <h2>🕓 פעילות אחרונה</h2>

  <h3>📩 פניות אחרונות</h3>
  <?php if ($latest_contacts && $latest_contacts->num_rows > 0): while ($row = $latest_contacts->fetch_assoc()): ?>
    <div class="entry">
      <strong><?= htmlspecialchars($row['message']) ?></strong>
      <small><?= htmlspecialchars($row['created_at']) ?></small>
    </div>
  <?php endwhile; else: ?>
    <p>אין פניות זמינות.</p>
  <?php endif; ?>

  <h3>🗳️ הצבעות אחרונות</h3>
  <?php if ($latest_votes && $latest_votes->num_rows > 0): while ($row = $latest_votes->fetch_assoc()): ?>
    <div class="entry">
      <strong><?= $row['vote_type'] === 'like' ? '❤️ אהבתי' : '💔 לא אהבתי' ?>
        על <?= htmlspecialchars($row['title_en']) ?></strong>
      <small><?= htmlspecialchars($row['created_at']) ?> | מזהה: <?= htmlspecialchars($row['visitor_token']) ?></small>
    </div>
  <?php endwhile; else: ?>
    <p>אין הצבעות זמינות.</p>
  <?php endif; ?>

  <h3>🆕 פוסטרים שנוספו</h3>
  <?php if ($latest_posters && $latest_posters->num_rows > 0): while ($row = $latest_posters->fetch_assoc()): ?>
    <div class="entry">
      <strong><?= htmlspecialchars($row['title_en']) ?></strong>
      <small><?= htmlspecialchars($row['created_at']) ?> | ID: <?= $row['id'] ?></small>
    </div>
  <?php endwhile; else: ?>
    <p>אין פוסטרים חדשים.</p>
  <?php endif; ?>

  <h3>🚨 דיווחים אחרונים</h3>
  <?php if ($latest_reports && $latest_reports->num_rows > 0): while ($row = $latest_reports->fetch_assoc()): ?>
    <div class="entry">
      <strong><?= htmlspecialchars($row['reason'] ?? 'דיווח ללא סיבה') ?></strong><br>
      <small>
        <?= htmlspecialchars($row['created_at']) ?> |
        מזהה מדווח: <?= htmlspecialchars($row['reporter_token'] ?? 'לא ידוע') ?> |
        על <a href="poster.php?id=<?= $row['poster_id'] ?>" target="_blank">
          <?= htmlspecialchars($row['title_en']) ?>
        </a>
      </small>
    </div>
  <?php endwhile; else: ?>
    <p>אין דיווחים זמינים.</p>
  <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>
