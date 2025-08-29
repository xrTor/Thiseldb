<?php
include 'header.php';
require_once 'server.php';
require_once 'languages.php';

// 🔢 סטטיסטיקה לפי סוג עם אייקונים
$types_data = [];
$types_result = $conn->query("SELECT pt.code, pt.label_he, pt.icon, COUNT(p.id) AS total
  FROM poster_types pt
  LEFT JOIN posters p ON p.type_id = pt.id
  GROUP BY pt.code, pt.label_he, pt.icon
  ORDER BY pt.sort_order ASC");

while ($row = $types_result->fetch_assoc()) {
    $row['label_with_icon'] = trim(($row['icon'] ?? '') . ' ' . ($row['label_he'] ?? ''));
    $types_data[] = $row;
}

// 🔗 לפי סוג קשר (לטבלה)
$connections_by_label = $conn->query("
  SELECT relation_label, COUNT(*) AS cnt
  FROM poster_connections
  GROUP BY relation_label
  ORDER BY cnt DESC
");

// 🔗 אותו פילוח עבור הגרף (שניהל סבב נפרד כדי לא "לצרוך" את תוצאת ה-query של הטבלה)
$connections_for_chart = $conn->query("
  SELECT relation_label, COUNT(*) AS cnt
  FROM poster_connections
  GROUP BY relation_label
  ORDER BY cnt DESC
");

$conn_labels = [];
$conn_counts = [];
if ($connections_for_chart) {
  while ($r = $connections_for_chart->fetch_assoc()) {
    $conn_labels[] = $r['relation_label'] !== null && $r['relation_label'] !== '' ? $r['relation_label'] : 'ללא תווית';
    $conn_counts[] = (int)($r['cnt'] ?? 0);
  }
}

// סה״כ קשרים (לשורה בראש הטבלה)
$total_conn_sum_row = $conn->query("SELECT COUNT(*) AS c FROM poster_connections")->fetch_assoc();
$total_conn_sum = (int)($total_conn_sum_row['c'] ?? 0);

// ❤️ אהדה
$count_likes_row    = $conn->query("SELECT COUNT(*) AS c FROM poster_votes WHERE vote_type='like'")->fetch_assoc();
$count_dislikes_row = $conn->query("SELECT COUNT(*) AS c FROM poster_votes WHERE vote_type='dislike'")->fetch_assoc();
$count_likes    = (int)($count_likes_row['c'] ?? 0);
$count_dislikes = (int)($count_dislikes_row['c'] ?? 0);
$total_votes = $count_likes + $count_dislikes;
$percent_likes = $total_votes ? round(($count_likes / $total_votes) * 100) : 0;
$percent_dislikes = $total_votes ? round(($count_dislikes / $total_votes) * 100) : 0;

// 🔝 אהובים
$top_posters = $conn->query("
  SELECT p.id, p.title_en,
    SUM(CASE WHEN pv.vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN pv.vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes pv ON pv.poster_id = p.id
  GROUP BY p.id
  ORDER BY likes DESC
  LIMIT 10
");

// 🌐 שפה + שנה
$languages_count = $conn->query("SELECT lang_code, COUNT(*) AS total FROM poster_languages GROUP BY lang_code ORDER BY total DESC");
$years = $conn->query("SELECT year, COUNT(*) AS total FROM posters WHERE year IS NOT NULL GROUP BY year ORDER BY year ASC");
$year_data = []; while ($row = $years->fetch_assoc()) $year_data[] = $row;

// ספירה כללית
$total_query = $conn->query("SELECT COUNT(*) AS total FROM posters");
$total = (int)($total_query->fetch_assoc()['total'] ?? 0);

// 📦 אוספים
$count_collections_row = $conn->query("SELECT COUNT(*) AS c FROM collections")->fetch_assoc();
$count_collections = (int)($count_collections_row['c'] ?? 0);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📊 סטטיסטיקות כלליות</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Arial; background:#f4f4f4; padding:10px; text-align:center; direction:rtl; max-width:1000px; margin:auto; }
    .box { background:#fff; padding:30px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.1); margin:30px 0; text-align:right; }
    .box h2 { text-align:center; margin-top:0; }
    table { width:100%; border-collapse:collapse; background:white; margin-top:20px; }
    th, td { padding:12px; border-bottom:1px solid #ccc; text-align:right; }
    th { background:#eee; }
    canvas { max-width:1000px; margin:20px auto; }
    .bar { height:20px; background:#ddd; border-radius:10px; overflow:hidden; margin:10px 0; }
    .bar-inner { height:100%; text-align:right; color:#fff; padding-right:10px; line-height:20px; font-size:13px; }
    .like-bar { background:#28a745; width:<?= $percent_likes ?>%; }
    .dislike-bar { background:#dc3545; width:<?= $percent_dislikes ?>%; }
    a { color:#007bff; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .yearChart {width: 100% !important;}
    .summary-row { font-weight:bold; background:#f9f9f9; }
    .box.small {
  padding: 15px;
}
#voteChart {
  max-width: 250px;
  max-height: 250px;
  margin: 10px auto;
  display: block;
}
/* אהדה כללית – בלוק קטן ומרוכז */
.box.votes{
  max-width: 480px;   /* רוחב מקסימלי לבלוק */
  margin: 20px auto;  /* ממרכז את הבלוק */
  padding: 16px;      /* פחות ריווח פנימי */
}

/* הגרף עצמו – קטן */
#voteChart{  width: 220px !important;
  height: 220px !important;
  max-width: 220px;
  display: block;
  margin: 8px auto;   /* מרכז את הקנבס */
}

/* הפסים והטקסטים בבלוק אהדה – שגם הם לא יתפרשו לרוחב מלא */
.box.votes p,
.box.votes .bar{
  max-width: 420px;
  margin-left: auto;
  margin-right: auto;
}

  </style>
</head>
<body>

<h1>📊 סטטיסטיקות כלליות</h1>

<div class="box">
  <h2>🔢 לפי סוג</h2>
  <table>
    <thead>
      <tr><th>סוג</th><th>מספר פוסטרים</th></tr>
    </thead>
    <tbody>
      <!-- שורת סיכום בתוך הטבלה, מעל כל הסוגים -->
      <tr class="summary-row">
        <td><img src="images/types/posters.png" alt="Poster" width="64px" style="vertical-align: middle;"> סך הכול פוסטרים</td>
        <td><?= number_format($total) ?></td>
      </tr>
      <?php foreach ($types_data as $type): ?>
        <tr>
          <td><?= htmlspecialchars($type['label_with_icon'] ?? '') ?></td>
          <td><?= number_format((int)($type['total'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- 🔗 פילוח קשרים לפי סוג: ממוקם מעל "אהדה כללית" -->
<div class="box">
  <h2>🔗 פילוח קשרים לפי סוג (IMDb Connections)</h2>
  <table>
    <thead>
      <tr>
        <th>סוג קשר</th>
        <th>כמות</th>
      </tr>
    </thead>
    <tbody>
      <!-- שורת "סה״כ קשרים" בראש הטבלה -->
      <tr class="summary-row">
        <td>סה״כ קשרים</td>
        <td><?= number_format($total_conn_sum) ?></td>
      </tr>

      <?php if ($connections_by_label): ?>
        <?php while($r = $connections_by_label->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['relation_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int)($r['cnt'] ?? 0)) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="2">אין נתונים להצגה.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- גרף עוגה קטן להתפלגות סוגי הקשרים -->
  <canvas id="connTypeChart"></canvas>
</div>

<div class="box small">
  <h2>❤️ אהדה כללית</h2>
  <canvas id="voteChart"></canvas>

  <p>❤️ אהבתי: <?= $count_likes ?> (<?= $percent_likes ?>%)</p>
  <div class="bar"><div class="bar-inner like-bar"><?= $percent_likes ?>%</div></div>
  <p>💔 לא אהבתי: <?= $count_dislikes ?> (<?= $percent_dislikes ?>%)</p>
  <div class="bar"><div class="bar-inner dislike-bar"><?= $percent_dislikes ?>%</div></div>
  <p>סה"כ הצבעות: <strong><?= $total_votes ?></strong></p>
</div>

<div class="box">
  <h2>🔥 עשרת הפוסטרים הכי אהובים</h2>
  <table>
    <thead>
      <tr><th>שם פוסטר</th><th>❤️ אהבתי</th><th>💔 לא אהבתי</th></tr>
    </thead>
    <tbody>
    <?php while ($row = $top_posters->fetch_assoc()): ?>
      <tr>
        <td><a href="poster.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['title_en'] ?? '#'.(int)$row['id']) ?></a></td>
        <td><?= (int)($row['likes'] ?? 0) ?></td>
        <td><?= (int)($row['dislikes'] ?? 0) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="box">
  <h2>🌐 פילוח לפי שפה</h2>
  <table>
    <thead>
      <tr><th>שפה</th><th>מספר פוסטרים</th></tr>
    </thead>
    <tbody>
    <?php while ($row = $languages_count->fetch_assoc()): ?>
      <?php
      $lang_code = $row['lang_code'] ?? '';
      $lang_label = $lang_code;
      $lang_flag = '';

      foreach ($languages as $lang) {
        if (($lang['code'] ?? '') === $lang_code) {
          $lang_label = $lang['label'] ?? $lang_code;
          $lang_flag = $lang['flag'] ?? '';
          break;
        }
      }
      ?>
      <tr>
        <td>
          <?php if ($lang_flag): ?>
            <img src="<?= htmlspecialchars($lang_flag) ?>" alt="<?= htmlspecialchars($lang_label) ?>" style="height:16px; vertical-align:middle; margin-left: 5px;">
          <?php endif; ?>
          <?= htmlspecialchars($lang_label) ?>
        </td>
        <td><?= number_format((int)($row['total'] ?? 0)) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="box">
  <h2>📅 פילוח לפי שנה</h2>
  <table>
    <thead>
      <tr><th>שנה</th><th>מספר פוסטרים</th></tr>
    </thead>
    <tbody>
    <?php foreach ($year_data as $row): ?>
      <tr>
        <td><?= htmlspecialchars((string)($row['year'] ?? '')) ?></td>
        <td><?= number_format((int)($row['total'] ?? 0)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <canvas id="yearChart" class="yearChart"></canvas>
</div>

<div class="box">
  <h2>📊 גרף התפלגות לפי סוג</h2>
  <canvas id="typeChart"></canvas>
</div>

<p><a href="index.php">⬅ חזרה לדף הבית</a></p>

<script>
  // גרף אהדה
  new Chart(document.getElementById('voteChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['אהבתי', 'לא אהבתי'],
      datasets: [{
        data: [<?= $count_likes ?>, <?= $count_dislikes ?>],
        backgroundColor: ['#28a745', '#dc3545'],
        borderWidth: 1
      }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
  });

  // גרף לפי שנה
  const yearLabels = <?= json_encode(array_column($year_data, 'year')) ?>;
  const yearCounts = <?= json_encode(array_column($year_data, 'total')) ?>;
  new Chart(document.getElementById('yearChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: yearLabels,
      datasets: [{
        label: 'פוסטרים לפי שנה',
        data: yearCounts,
        backgroundColor: '#FF9800'
      }]
    },
    options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
  });

  // גרף לפי סוג עם אייקונים (פוסטרים)
  const typeLabels = <?= json_encode(array_column($types_data, 'label_with_icon'), JSON_UNESCAPED_UNICODE) ?>;
  const typeCounts = <?= json_encode(array_column($types_data, 'total')) ?>;
  new Chart(document.getElementById('typeChart').getContext('2d'), {
    type: 'pie',
    data: {
      labels: typeLabels,
      datasets: [{
        data: typeCounts,
        backgroundColor: ['#4CAF50', '#2196F3', '#9C27B0', '#FF9800','#FFC107', '#795548', '#00BCD4', '#607D8B','#FF5722', '#E91E63']
      }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
  });

  // 🔗 גרף עוגה: התפלגות סוגי הקשרים (IMDb Connections)
  const connLabels = <?= json_encode($conn_labels, JSON_UNESCAPED_UNICODE) ?>;
  const connCounts = <?= json_encode($conn_counts) ?>;
  if (connLabels.length > 0 && connCounts.length > 0) {
    new Chart(document.getElementById('connTypeChart').getContext('2d'), {
      type: 'pie',
      data: {
        labels: connLabels,
        datasets: [{
          data: connCounts,
          backgroundColor: ['#8E24AA', '#3949AB', '#039BE5', '#00897B', '#7CB342', '#FDD835', '#FB8C00', '#E53935', '#6D4C41', '#546E7A']
        }]
      },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  }
</script>

<?php include 'footer.php'; ?>
</body>
</html>
