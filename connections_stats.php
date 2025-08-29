<?php
require_once 'server.php';
include 'header.php';

$tot = $conn->query("SELECT COUNT(*) AS c FROM poster_connections")->fetch_assoc()['c'] ?? 0;
$tot_imdb = $conn->query("SELECT COUNT(*) AS c FROM poster_connections WHERE source='imdb'")->fetch_assoc()['c'] ?? 0;

/* COUNT(DISTINCT col1, col2) (MySQL 5.7+) עם נפילה אחורה במקרה צורך */
$unique_pairs = 0;
$q = $conn->query("SELECT COUNT(DISTINCT poster_id, conn_imdb_id) AS c FROM poster_connections");
if ($q) {
  $unique_pairs = $q->fetch_assoc()['c'] ?? 0;
} else {
  $q = $conn->query("SELECT COUNT(*) AS c FROM (SELECT poster_id, conn_imdb_id FROM poster_connections GROUP BY poster_id, conn_imdb_id) t");
  $unique_pairs = $q ? ($q->fetch_assoc()['c'] ?? 0) : 0;
}

$by_label = $conn->query("
  SELECT relation_label, COUNT(*) AS cnt
  FROM poster_connections
  GROUP BY relation_label
  ORDER BY cnt DESC
");

$top_titles = $conn->query("
  SELECT p.id, p.title_en, p.title_he, COUNT(*) AS cnt
  FROM poster_connections pc
  JOIN posters p ON p.id = pc.poster_id
  GROUP BY p.id
  ORDER BY cnt DESC
  LIMIT 30
");

$no_conn = $conn->query("
  SELECT COUNT(*) AS c
  FROM posters p
  LEFT JOIN poster_connections pc ON pc.poster_id = p.id
  WHERE pc.poster_id IS NULL
")->fetch_assoc()['c'] ?? 0;
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>סטטיסטיקות IMDb Connections</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --line:#22283a; --accent:#8ab4ff; }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;background:#0f1115;color:#e7ecff;margin:0}
    .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px}
    .card{background:#151924;border:1px solid #22283a;border-radius:14px;padding:16px}
    .card h3{margin:0 0 8px;font-size:14px;color:#8a90a2}
    .big{font-size:28px;font-weight:800}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #22283a;text-align:right}
    th{background:#131826}
    a{color:#8ab4ff;text-decoration:none}
    a:hover{text-decoration:underline}
  </style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 12px">סטטיסטיקות IMDb Connections</h1>

  <div class="cards">
    <div class="card"><h3>סה״כ קשרים</h3><div class="big"><?=number_format($tot)?></div></div>
    <div class="card"><h3>קשרים ממקור IMDb</h3><div class="big"><?=number_format($tot_imdb)?></div></div>
    <div class="card"><h3>זוגות ייחודיים (Poster↔Conn)</h3><div class="big"><?=number_format($unique_pairs)?></div></div>
    <div class="card"><h3>פוסטרים ללא קשרים</h3><div class="big"><?=number_format($no_conn)?></div></div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <h3 style="margin:0 0 8px">לפי סוג קשר</h3>
    <table>
      <thead><tr><th>סוג קשר</th><th>כמות</th></tr></thead>
      <tbody>
        <?php while($r=$by_label->fetch_assoc()): ?>
          <tr>
            <td><?=htmlspecialchars($r['relation_label']??'',ENT_QUOTES,'UTF-8')?></td>
            <td><?=number_format((int)$r['cnt'])?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px">טייטלים עם הכי הרבה קשרים (טופ 30)</h3>
    <table>
      <thead><tr><th>#</th><th>כותרת</th><th>קשרים</th></tr></thead>
      <tbody>
        <?php while($r=$top_titles->fetch_assoc()): ?>
          <tr>
            <td><a href="poster.php?id=<?=$r['id']?>" target="_blank"><?= (int)$r['id'] ?></a></td>
            <td>
              <a href="poster.php?id=<?=$r['id']?>" target="_blank">
                <?= htmlspecialchars($r['title_en'] ?: $r['title_he'] ?: ('#'.$r['id']), ENT_QUOTES, 'UTF-8') ?>
              </a>
              <?php if(!empty($r['title_he'])): ?>
                <div style="color:#8a90a2;font-size:12px"><?=htmlspecialchars($r['title_he'],ENT_QUOTES,'UTF-8')?></div>
              <?php endif; ?>
            </td>
            <td><?= number_format((int)$r['cnt']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
