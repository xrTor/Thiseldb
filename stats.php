<?php
include 'header.php';
require_once 'server.php';
require_once 'languages.php';

// require_once 'stats_helpers.php'; // <--- הסרנו את השורה הזו

// === פונקציות עזר מתוך full-info-text.php (מוטמעות כאן) ===
mb_internal_encoding('UTF-8');
function norm_split($s){
  if ($s === null) return [];
  $s = str_replace([';', '/', '|'], ',', (string)$s);
  $parts = array_map('trim', explode(',', $s));
  return array_values(array_filter($parts, fn($x)=>$x!==''));
}
/**
 * אוסף ומקבץ שמות יחודיים מתוך עמודה (כמו 'directors', 'cast')
 * @return array 
 */
function aggFromColumn(mysqli $conn, string $col): array {
  $rs = $conn->query("SELECT `$col` v FROM posters WHERE `$col` IS NOT NULL AND `$col`<>''");
  if (!$rs) return [];
  $m = [];
  while($row=$rs->fetch_assoc()){
    foreach(norm_split($row['v']) as $name){
      $key = mb_strtolower($name,'UTF-8');
      // אנחנו צריכים רק את המפתחות (שמות יחודיים), לא את הספירה שלהם
      if (!isset($m[$key])) $m[$key] = true; 
    }
  }
  // מחזיר מערך שהמפתחות שלו הם השמות היחודיים
  return $m;
}
// ============================================


// 🔢 סטטיסטיקה לפי סוג עם אייקונים
$types_data = [];
$types_result = $conn->query("SELECT pt.code, pt.label_he, pt.icon, pt.image, COUNT(p.id) AS total
  FROM poster_types pt
  LEFT JOIN posters p ON p.type_id = pt.id
  GROUP BY pt.code, pt.label_he, pt.icon, pt.image
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

// 🔗 אותו פילוח עבור הגרף
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

// סה״כ קשרים
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

// 🔝 פוסטרים אהובים (חדש)
$liked_posters = $conn->query("
  SELECT p.id, p.title_en,
    SUM(CASE WHEN pv.vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN pv.vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes pv ON pv.poster_id = p.id
  GROUP BY p.id
  HAVING likes > 0
  ORDER BY likes DESC
");

// 🔝 פוסטרים לא אהובים (חדש)
$disliked_posters = $conn->query("
  SELECT p.id, p.title_en,
    SUM(CASE WHEN pv.vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN pv.vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes pv ON pv.poster_id = p.id
  GROUP BY p.id
  HAVING dislikes > 0
  ORDER BY dislikes DESC
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


// --- לוגיקה עבור 3 בלוקי הצוות ---

// בלוק 1: ספירת פוסטרים שיש להם מידע
$count_actors_row = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE cast IS NOT NULL AND cast != ''")->fetch_assoc(); 
$count_actors = (int)($count_actors_row['c'] ?? 0); 
$count_directors_row = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE directors IS NOT NULL AND directors != ''")->fetch_assoc();
$count_directors = (int)($count_directors_row['c'] ?? 0);
$count_writers_row = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE writers IS NOT NULL AND writers != ''")->fetch_assoc();
$count_writers = (int)($count_writers_row['c'] ?? 0);
$count_producers_row = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE producers IS NOT NULL AND producers != ''")->fetch_assoc();
$count_producers = (int)($count_producers_row['c'] ?? 0);
$count_cinematographers_row = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE cinematographers IS NOT NULL AND cinematographers != ''")->fetch_assoc();
$count_cinematographers = (int)($count_cinematographers_row['c'] ?? 0);
$count_composers_row = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE composers IS NOT NULL AND composers != ''")->fetch_assoc();
$count_composers = (int)($count_composers_row['c'] ?? 0);

// בלוק 2: ספירת סך כל השמות (כולל כפילויות, לפי פסיקים)
$count_total_actors_row = $conn->query("SELECT SUM(CASE WHEN cast IS NOT NULL AND cast != '' THEN LENGTH(cast) - LENGTH(REPLACE(cast, ',', '')) + 1 ELSE 0 END) AS c FROM posters")->fetch_assoc(); 
$count_total_actors = (int)($count_total_actors_row['c'] ?? 0); 
$count_total_directors_row = $conn->query("SELECT SUM(CASE WHEN directors IS NOT NULL AND directors != '' THEN LENGTH(directors) - LENGTH(REPLACE(directors, ',', '')) + 1 ELSE 0 END) AS c FROM posters")->fetch_assoc();
$count_total_directors = (int)($count_total_directors_row['c'] ?? 0);
$count_total_writers_row = $conn->query("SELECT SUM(CASE WHEN writers IS NOT NULL AND writers != '' THEN LENGTH(writers) - LENGTH(REPLACE(writers, ',', '')) + 1 ELSE 0 END) AS c FROM posters")->fetch_assoc();
$count_total_writers = (int)($count_total_writers_row['c'] ?? 0);
$count_total_producers_row = $conn->query("SELECT SUM(CASE WHEN producers IS NOT NULL AND producers != '' THEN LENGTH(producers) - LENGTH(REPLACE(producers, ',', '')) + 1 ELSE 0 END) AS c FROM posters")->fetch_assoc();
$count_total_producers = (int)($count_total_producers_row['c'] ?? 0);
$count_total_cinematographers_row = $conn->query("SELECT SUM(CASE WHEN cinematographers IS NOT NULL AND cinematographers != '' THEN LENGTH(cinematographers) - LENGTH(REPLACE(cinematographers, ',', '')) + 1 ELSE 0 END) AS c FROM posters")->fetch_assoc();
$count_total_cinematographers = (int)($count_total_cinematographers_row['c'] ?? 0);
$count_total_composers_row = $conn->query("SELECT SUM(CASE WHEN composers IS NOT NULL AND composers != '' THEN LENGTH(composers) - LENGTH(REPLACE(composers, ',', '')) + 1 ELSE 0 END) AS c FROM posters")->fetch_assoc();
$count_total_composers = (int)($count_total_composers_row['c'] ?? 0);

// בלוק 3: ספירת שמות יחודיים (לפי לוגיקת full-info-text.php)
$count_unique_actors_cast = count(aggFromColumn($conn, 'cast'));
$count_unique_directors   = count(aggFromColumn($conn, 'directors'));
$count_unique_writers     = count(aggFromColumn($conn, 'writers'));
$count_unique_producers   = count(aggFromColumn($conn, 'producers'));
$count_unique_composers   = count(aggFromColumn($conn, 'composers'));
$count_unique_cinematog   = count(aggFromColumn($conn, 'cinematographers'));

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
    td:first-child { width: 70%; } /* נותן קצת יותר מקום לתיאור */
    canvas { max-width:1000px; margin:20px auto; }
    .bar { height:20px; background:#ddd; border-radius:10px; overflow:hidden; margin:10px 0; }
    .bar-inner { height:100%; text-align:right; color:#fff; padding-right:10px; line-height:20px; font-size:13px; }
    .like-bar { background:#28a745; width:<?= $percent_likes ?>%; }
    .dislike-bar { background:#dc3545; width:<?= $percent_dislikes ?>%; }
    a { color:#007bff; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .yearChart {width: 100% !important;}
    .summary-row { font-weight:bold; background:#f9f9f9; }
    
    #voteChart {
      max-width: 300px;
      max-height: 300px;
      margin: 20px auto;
      display: block;
    }

    .bar {
      height: 22px;
      background:#ddd;
      border-radius:10px;
      overflow:hidden;
      margin:10px auto; /* auto ימרכז אותו */
      display:flex; 
      max-width: 600px; /* הגבלת רוחב הפסים */
    }
    .box p {
        text-align: center; /* ממשיך למרכז את הטקסט בבלוק האהדה */
    }

    footer .box {
      background: transparent !important;
      box-shadow: none !important;
      border-radius: 0 !important;
      padding: 0 !important;
      margin: 0 !important;
      text-align: center !important;
    }

    /* עיצוב לבלוק השנים המתקפל */
    summary {
        font-size: 1.5em; 
        font-weight: bold; 
        text-align: center; 
        margin-top: 0; 
        padding-bottom: 10px; 
        cursor: pointer; 
        list-style: none; /* מסתיר את החץ הדיפולטיבי בחלק מהדפדפנים */
    }
    /* מוסיף חץ יפה יותר שגם מסתובב */
    summary::marker {
        content: ''; /* מסתיר ב-Firefox */
    }
    summary {
        list-style-type: none; /* מסתיר ב-Safari/Chrome */
        position: relative;
        padding-right: 20px; /* נותן מקום לחץ */
    }
    summary::before {
        content: '►';
        position: absolute;
        right: 0;
        font-size: 0.8em;
        transition: transform 0.2s;
        transform: rotate(0deg); /* מצב סגור */
    }
    details[open] > summary::before {
        transform: rotate(90deg); /* מצב פתוח */
    }

    /* עיצוב לכפתור סיכום בתוך בלוק האהדה (גודל רגיל) */
    .box details summary {
        font-size: 1.2em; /* קצת יותר גדול */
        font-weight: bold;
        cursor: pointer;
        text-align: right;
        padding-right: 20px; /* מקום לחץ */
        margin-top: 25px; /* רווח */
        padding-bottom: 5px;
    }
    .box details[open] summary {
        margin-bottom: 15px;
    }

  </style>
</head>
<body>

<h1><br> סטטיסטיקות כלליות</h1> <img src="images/spreadsheet.png" height="70px" alt="">

<div class="box">
  <h2>🔢 לפי סוג</h2>
  <table>
    <thead>
      <tr><th>סוג</th><th>מספר פוסטרים</th></tr>
    </thead>
    <tbody>
      <tr class="summary-row">
        <td><img src="images/types/posters.png" alt="Poster" width="64px" style="vertical-align: middle;"> סך הכול פוסטרים</td>
        <td><?= number_format($total) ?></td>
      </tr>
      <?php foreach ($types_data as $type): ?>
        <tr>
          <td>
            <?php if (!empty($type['image'])): ?>
              <img src="images/types/<?= htmlspecialchars($type['image']) ?>" alt="<?= htmlspecialchars($type['label_he']) ?>" width="64px" style="vertical-align: middle;">
            <?php else: ?>
              <?= htmlspecialchars($type['icon'] ?? '') ?>
            <?php endif; ?>
            <?= htmlspecialchars($type['label_he'] ?? '') ?>
          </td>
          <td><?= number_format((int)($type['total'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

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
      <tr class="summary-row">
        <td>סה״כ קשרים</td>
        <td><?= number_format($total_conn_sum) ?></td>
      </tr>

      <?php if ($connections_by_label): ?>
        <?php while($r = $connections_by_label->fetch_assoc()): ?>
          <?php $label = $r['relation_label'] ?? ''; ?>
          <tr>
            <td>
              <a href="connections.php?label=<?= urlencode($label) ?>">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
              </a>
            </td>
            <td><?= number_format((int)($r['cnt'] ?? 0)) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="2">אין נתונים להצגה.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <canvas id="connTypeChart"></canvas>
</div>
<?php include 'watch_totals.php';?>

<div class="box">
  <h2>❤️ אהדה כללית</h2>
  <canvas id="voteChart"></canvas>

  <p>
  ❤️ אהבתי: <?= $count_likes ?> (<?= $percent_likes ?>%) |
  💔 לא אהבתי: <?= $count_dislikes ?> (<?= $percent_dislikes ?>%)
</p>

<div class="bar">
  <div class="bar-inner like-bar" style="width:<?= $percent_likes ?>%"></div>
  <div class="bar-inner dislike-bar" style="width:<?= $percent_dislikes ?>%"></div>
</div>

<p>סה"כ הצבעות: <strong><?= $total_votes ?></strong></p>

  <details>
    <summary>
      ❤️ הצג פוסטרים שאהבתי (<?= $liked_posters->num_rows ?>)
    </summary>
    <table style="margin-top: 10px;">
      <thead>
        <tr><th>שם פוסטר</th><th>❤️</th><th>💔</th></tr>
      </thead>
      <tbody>
      <?php while ($row = $liked_posters->fetch_assoc()): ?>
        <tr>
          <td><a href="poster.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['title_en'] ?? '#'.(int)$row['id']) ?></a></td>
          <td><?= (int)($row['likes'] ?? 0) ?></td>
          <td><?= (int)($row['dislikes'] ?? 0) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </details>
  
  <details style="margin-top: 15px;"> <summary>
      💔 הצג פוסטרים שלא אהבתי (<?= $disliked_posters->num_rows ?>)
    </summary>
    <table style="margin-top: 10px;">
      <thead>
        <tr><th>שם פוסטר</th><th>❤️</th><th>💔</th></tr>
      </thead>
      <tbody>
      <?php while ($row = $disliked_posters->fetch_assoc()): ?>
        <tr>
          <td><a href="poster.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['title_en'] ?? '#'.(int)$row['id']) ?></a></td>
          <td><?= (int)($row['likes'] ?? 0) ?></td>
          <td><?= (int)($row['dislikes'] ?? 0) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </details>
  
</div>
<div class="box">
  <h2>📦 אוספים</h2>
  <table>
    <tbody>
      <tr>
        <td><img src="images/types/collection.png" alt="Collection" width="64px" style="vertical-align: middle;"> סה"כ אוספים</td>
        <td><?= number_format($count_collections) ?></td>
      </tr>
    </tbody>
  </table>
</div>


<div class="box">
  <h2>👩‍🎨 ספירת אנשי צוות יחודיים</h2>
  <p style="text-align:center; font-style:italic; color:#555; font-size: 14px;">
    הספירה מציגה כמה שמות יחודיים (case-insensitive) קיימים בכל תפקיד
  </p>
  <table>
    <thead>
      <tr>
        <th>תיאור</th>
        <th>כמות שמות יחודיים</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>👤 סה"כ שחקנים יחודיים</td>
        <td><?= number_format($count_unique_actors_cast) ?></td>
      </tr>
      <tr>
        <td>🎬 סה"כ במאים יחודיים</td>
        <td><?= number_format($count_unique_directors) ?></td>
      </tr>
      <tr>
        <td>✍️ סה"כ תסריטאים יחודיים</td>
        <td><?= number_format($count_unique_writers) ?></td>
      </tr>
      <tr>
        <td>💼 סה"כ מפיקים יחודיים</td>
        <td><?= number_format($count_unique_producers) ?></td>
      </tr>
      <tr>
        <td>🎥 סה"כ צלמים יחודיים</td>
        <td><?= number_format($count_unique_cinematog) ?></td>
      </tr>
      <tr>
        <td>🎵 סה"כ מלחינים יחודיים</td>
        <td><?= number_format($count_unique_composers) ?></td>
      </tr>
    </tbody>
  </table>
</div>
<div class="box">
  <h2>👥 צוות ושחקנים (לפי פוסטרים)</h2>
  <p style="text-align:center; font-style:italic; color:#555; font-size: 14px;">
    הספירה מציגה כמה פוסטרים מכילים מידע (שדה לא ריק) בכל קטגוריה.
  </p>
  <table>
    <thead>
      <tr>
        <th>תיאור</th>
        <th>כמות פוסטרים</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>👤 פוסטרים עם שחקנים משויכים</td>
        <td><?= number_format($count_actors) ?></td>
      </tr>
      <tr>
        <td>🎬 פוסטרים עם במאים משויכים</td>
        <td><?= number_format($count_directors) ?></td>
      </tr>
      <tr>
        <td>✍️ פוסטרים עם תסריטאים משויכים</td>
        <td><?= number_format($count_writers) ?></td>
      </tr>
      <tr>
        <td>💼 פוסטרים עם מפיקים משויכים</td>
        <td><?= number_format($count_producers) ?></td>
      </tr>
      <tr>
        <td>🎥 פוסטרים עם צלמים משויכים</td>
        <td><?= number_format($count_cinematographers) ?></td>
      </tr>
      <tr>
        <td>🎵 פוסטרים עם מלחינים משויכים</td>
        <td><?= number_format($count_composers) ?></td>
      </tr>
    </tbody>
  </table>
</div>
<div class="box">
  <h2>📊 סך כל שיוכי הצוות (לפי שמות)</h2>
  <p style="text-align:center; font-style:italic; color:#555; font-size: 14px;">
    הספירה מציגה כמה שמות משויכים בסך הכל (כולל כפילויות, לפי ספירת פסיקים).
  </p>
  <table>
    <thead>
      <tr>
        <th>תפקיד</th>
        <th>סך כל השמות ששויכו</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>👤 סך כל שיוכי השחקנים</td>
        <td><?= number_format($count_total_actors) ?></td>
      </tr>
      <tr>
        <td>🎬 סך כל שיוכי הבמאים</td>
        <td><?= number_format($count_total_directors) ?></td>
      </tr>
      <tr>
        <td>✍️ סך כל שיוכי התסריטאים</td>
        <td><?= number_format($count_total_writers) ?></td>
      </tr>
      <tr>
        <td>💼 סך כל שיוכי המפיקים</td>
        <td><?= number_format($count_total_producers) ?></td>
      </tr>
      <tr>
        <td>🎥 סך כל שיוכי הצלמים</td>
        <td><?= number_format($count_total_cinematographers) ?></td>
      </tr>
      <tr>
        <td>🎵 סך כל שיוכי המלחינים</td>
        <td><?= number_format($count_total_composers) ?></td>
      </tr>
    </tbody>
  </table>
</div>
<div class="box">
  <h2>🌐 פילוח לפי שפה (דגל)</h2>
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
          <a href="language.php?lang_code=<?= htmlspecialchars($lang_code) ?>">
            <?php if ($lang_flag): ?>
              <img src="<?= htmlspecialchars($lang_flag) ?>" alt="<?= htmlspecialchars($lang_label) ?>" style="height:16px; vertical-align:middle; margin-left: 5px;">
            <?php endif; ?>
            <?= htmlspecialchars($lang_label) ?>
          </a>
        </td>
        <td><?= number_format((int)($row['total'] ?? 0)) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>





<div class="box">
  <h2>📊 גרף התפלגות לפי סוג</h2>
  <canvas id="typeChart"></canvas>
</div>


<div class="box">
  
  <details>
    <summary>
      📅 פילוח לפי שנה (לחץ להצגה/הסתרת הטבלה)
    </summary>
    
    <table style="margin-top: 15px;">
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
  </details>

  <canvas id="yearChart" class="yearChart" style="margin-top: 20px;"></canvas>

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