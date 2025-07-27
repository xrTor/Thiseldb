<?php include 'header.php'; ?>

<?php
require_once 'server.php';
 
// טיפול בבקשת מחיקה
if (isset($_GET['delete'])) {
  $del_id = intval($_GET['delete']);
  $conn->query("DELETE FROM poster_reports WHERE id = $del_id");
}

// סימון כטופל
if (isset($_GET['handle'])) {
  $handle_id = intval($_GET['handle']);
  $conn->query("UPDATE poster_reports SET handled_at = NOW() WHERE id = $handle_id");
}

// שליפת דיווחים עם פרטי הפוסטר
$sql = "
  SELECT r.id, r.report_reason, r.created_at, r.handled_at, p.title_en, p.id AS poster_id
  FROM poster_reports r
  JOIN posters p ON r.poster_id = p.id
  ORDER BY r.created_at DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🛠️ ניהול דיווחים</title>
  <style>
    body { font-family:Arial; background:#f2f2f2; padding:10px; direction:rtl; }
    h2 { margin-bottom:20px; }
    table { width:100%; border-collapse:collapse; background:white; }
    th, td { padding:12px; border-bottom:1px solid #ccc; text-align:right; }
    th { background:#eee; }
    tr:hover td { background:#f9f9f9; }
    .actions a {
      margin-left:10px;
      text-decoration:none;
      padding:4px 8px;
      border-radius:6px;
      font-size:13px;
      background:#eee;
      color:#333;
    }
    .actions a:hover { background:#ddd; }
    .handled { color:#090; font-weight:bold; }
    .note { color:#999; margin-top:10px; }
  </style>
</head>
<body>

<h2>🛠️ דיווחים שהתקבלו</h2>

<?php if ($result->num_rows > 0): ?>
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
          <a href="poster.php?id=<?= $row['poster_id'] ?>">
            <?= htmlspecialchars($row['title_en']) ?>
          </a>
        </td>
        <td><?= nl2br(htmlspecialchars($row['report_reason'])) ?></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
        <td>
          <?php if ($row['handled_at']): ?>
            <span class="handled">✅ <?= htmlspecialchars($row['handled_at']) ?></span>
          <?php else: ?>
            ❌ טרם טופל
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if (!$row['handled_at']): ?>
            <a href="?handle=<?= $row['id'] ?>">סמן כטופל</a>
          <?php endif; ?>
          <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('למחוק דיווח זה?')">🗑️ מחק</a>
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