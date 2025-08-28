<?php
require_once 'server.php';
include 'header.php';

/* --- שליפת סוגים --- */
$types = [];
$type_result = $conn->query("SELECT id, label_he, icon FROM poster_types ORDER BY id ASC");
while ($row = $type_result->fetch_assoc()) {
    $types[$row['id']] = ['label' => $row['label_he'], 'icon' => $row['icon']];
}

$selected_type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;

/* --- פרמטר סינון חסר: missing=all|title_he|title_en|overview|imdb|image|year|genres|mc|rt --- */
$missing_key = $_GET['missing'] ?? 'all';
$allowed_missing = [
  'all'      => 'הכול',
  'title_he' => 'שם בעברית',
  'title_en' => 'שם באנגלית',
  'overview' => 'תקציר',
  'imdb'     => 'IMDb Rating',
  'image'    => 'תמונה',
  'year'     => 'שנה',
  'genres'   => 'ז׳אנרים',
  'mc'       => 'Metacritic',
  'rt'       => 'Rotten Tomatoes',
];
if (!isset($allowed_missing[$missing_key])) $missing_key = 'all';

/* --- תנאים --- */
$cond_map = [
  'title_he' => "(title_he IS NULL OR title_he='')",
  'title_en' => "(title_en IS NULL OR title_en='')",
  // תקציר חסר: בלי overview_en
  'overview' => "(COALESCE(NULLIF(plot,''), NULLIF(plot_he,''), NULLIF(overview_he,'')) IS NULL)",
  'imdb'     => "(imdb_rating IS NULL OR imdb_rating=0)",
  'image'    => "((image_url IS NULL OR image_url='' OR image_url='N/A') AND (poster_url IS NULL OR poster_url='' OR poster_url='N/A'))",
  'year'     => "(year IS NULL OR year='')",
  'genres'   => "((genre IS NULL OR genre='') AND (genres IS NULL OR genres=''))",
  'mc'       => "(mc_score IS NULL OR mc_score='' OR mc_score=0)",
  'rt'       => "(rt_score IS NULL OR rt_score='' OR rt_score=0)",
];
$cond_all = implode(" OR ", array_values($cond_map));

/* WHERE סופי לפי סינון */
$where = ($missing_key === 'all') ? $cond_all : $cond_map[$missing_key];
if ($selected_type_id > 0) {
  $where = "(type_id = $selected_type_id) AND ($where)";
}

/* --- מונה לכל כפתור (במכה אחת) --- */
$counts_sql =
  "SELECT
     SUM(CASE WHEN {$cond_map['title_he']} THEN 1 ELSE 0 END) AS c_title_he,
     SUM(CASE WHEN {$cond_map['title_en']} THEN 1 ELSE 0 END) AS c_title_en,
     SUM(CASE WHEN {$cond_map['overview']} THEN 1 ELSE 0 END) AS c_overview,
     SUM(CASE WHEN {$cond_map['imdb']} THEN 1 ELSE 0 END)     AS c_imdb,
     SUM(CASE WHEN {$cond_map['image']} THEN 1 ELSE 0 END)    AS c_image,
     SUM(CASE WHEN {$cond_map['year']} THEN 1 ELSE 0 END)     AS c_year,
     SUM(CASE WHEN {$cond_map['genres']} THEN 1 ELSE 0 END)   AS c_genres,
     SUM(CASE WHEN {$cond_map['mc']} THEN 1 ELSE 0 END)       AS c_mc,
     SUM(CASE WHEN {$cond_map['rt']} THEN 1 ELSE 0 END)       AS c_rt,
     SUM(CASE WHEN ($cond_all) THEN 1 ELSE 0 END)             AS c_all
   FROM posters" . ($selected_type_id > 0 ? " WHERE type_id = $selected_type_id" : "");

$counts_res = $conn->query($counts_sql);
$counts_row = $counts_res ? $counts_res->fetch_assoc() : [];
$counts = [
  'all'      => (int)($counts_row['c_all']      ?? 0),
  'title_he' => (int)($counts_row['c_title_he'] ?? 0),
  'title_en' => (int)($counts_row['c_title_en'] ?? 0),
  'overview' => (int)($counts_row['c_overview'] ?? 0),
  'imdb'     => (int)($counts_row['c_imdb']     ?? 0),
  'image'    => (int)($counts_row['c_image']    ?? 0),
  'year'     => (int)($counts_row['c_year']     ?? 0),
  'genres'   => (int)($counts_row['c_genres']   ?? 0),
  'mc'       => (int)($counts_row['c_mc']       ?? 0),
  'rt'       => (int)($counts_row['c_rt']       ?? 0),
];

/* --- ספירה לסיכום + שליפה --- */
$count_result = $conn->query("SELECT COUNT(*) AS total FROM posters WHERE $where");
$total_missing = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

$query = "SELECT * FROM posters WHERE $where ORDER BY id DESC LIMIT 1000000";
$result = $conn->query($query);

/* --- סטייל --- */
echo <<<STYLE
<style>
  body { font-family: 'Segoe UI', sans-serif; }
  .styled-table {
    width: 95%; margin: 14px auto 20px; border-collapse: collapse; direction: rtl;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .styled-table th {
    background-color: #F2F2F3; color: black; padding: 10px; text-align: center;
  }
  .styled-table td {
    padding: 10px; text-align: right; border-bottom: 1px solid #ccc;
  }
  .styled-table td:nth-child(1),
  .styled-table td:nth-child(3),
  .styled-table td:nth-child(4),
  .styled-table td:nth-child(5),
  .styled-table td:nth-child(6),
  .styled-table td:nth-child(7),
  .styled-table td:nth-child(8) {
    text-align: center;
  }
  .styled-table tr:nth-child(even) { background-color: #f9f9f9; }

  .btn { text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
  .btn-edit { background: /* #FF9800 */; color: white; }

  .filters { text-align: center; margin: 10px 0 6px; font-size: 16px; }
  .filters select { padding: 6px; font-size: 15px; }

  /* פס כפתורי-חסר למעלה */
  .missing-bar { text-align:center; margin: 10px 0 10px; }
  .missing-bar a {
    display:inline-block; margin:4px 5px; padding:6px 10px; border-radius:999px;
    border:1px solid #d0d7e2; text-decoration:none; color:#123; background:#fff; font-size:14px;
  }
  .missing-bar a .count { color:#555; margin-right:4px; }
  .missing-bar a.active { background:#e7f1ff; border-color:#9dbcf5; }
  .missing-summary { text-align:center; font-weight:bold; margin:6px 0; }
</style>
STYLE;

echo '<h2 style="text-align:center; margin:10px;">🎯 פוסטרים עם מידע חסר</h2>';

/* --- פס כפתורים עם מונים --- */
echo '<div class="missing-bar">';
$base_qs = $_GET; unset($base_qs['missing']);
foreach ($allowed_missing as $key => $label) {
  $qs = $base_qs; $qs['missing'] = $key;
  $url = htmlspecialchars($_SERVER['PHP_SELF'].'?'.http_build_query($qs), ENT_QUOTES, 'UTF-8');
  $cls = ($missing_key === $key) ? 'active' : '';
  $num = (int)($counts[$key] ?? 0);
  echo '<a class="'.$cls.'" href="'.$url.'">'.$label.' <span class="count">('.$num.')</span></a>';
}
echo '</div>';

/* --- טופס סינון סוג (שומר missing) --- */
echo '<form method="get" class="filters">';
echo '  <label>סוג פוסטר:</label> ';
echo '  <select name="type_id" onchange="this.form.submit()">';
echo '    <option value="0">— כל הסוגים —</option>';
foreach ($types as $id => $data) {
  $sel = ($selected_type_id === $id) ? 'selected' : '';
  $icon = $data['icon'] ? $data['icon'] . ' ' : '';
  echo "<option value=\"$id\" $sel>{$icon}{$data['label']}</option>";
}
echo '  </select>';
echo '  <input type="hidden" name="missing" value="'.htmlspecialchars($missing_key, ENT_QUOTES, 'UTF-8').'">';
echo '</form>';

/* --- סיכום --- */
$sel_label = $allowed_missing[$missing_key] ?? 'הכול';
echo "<p class='missing-summary'>מציג: <u>$sel_label</u> — נמצאו <span style='color:darkred;'>$total_missing</span> פריטים</p>";

/* --- טבלה/אין נתונים --- */
if ($total_missing === 0) {
  echo "<p style='text-align:center;'>✅ אין פריטים חסרים לפי הסינון.</p>";
} else {
  echo '<table class="styled-table">
    <tr>
      <th>#</th>
      <th>כותרת</th>
      <th>שנה</th>
      <th>IMDb</th>
      <th>Metacritic</th>
      <th>Rotten<br>Tomatoes</th>
      <th>IMDb ID</th>
      <th>חסרים</th>
      <th>עריכה</th>
    </tr>';

  while ($row = $result->fetch_assoc()) {
    $missing = [];

    if (empty($row['title_he'])) $missing[] = 'שם בעברית';
    if (empty($row['title_en'])) $missing[] = 'שם באנגלית';

    $has_overview = !empty($row['plot']) || !empty($row['plot_he']) || !empty($row['overview_he']);
    if (!$has_overview) $missing[] = 'תקציר';

    $imdb_rating_val = (float)($row['imdb_rating'] ?? 0);
    if ($imdb_rating_val <= 0) $missing[] = 'דירוג IMDb';

    $img_empty =
      (empty($row['image_url']) || $row['image_url'] === 'N/A') &&
      (empty($row['poster_url']) || $row['poster_url'] === 'N/A');
    if ($img_empty) $missing[] = 'תמונה';

    if (empty($row['year'])) $missing[] = 'שנה';

    $has_genres = !(empty(trim((string)($row['genre'] ?? ''))) && empty(trim((string)($row['genres'] ?? ''))));
    if (!$has_genres) $missing[] = 'ז׳אנרים';

    $mc = $row['mc_score'] ?? '';
    if ($mc === '' || (string)$mc === '0') $missing[] = 'Metacritic';

    $rt = $row['rt_score'] ?? '';
    if ($rt === '' || (string)$rt === '0') $missing[] = 'Rotten Tomatoes';

    $id_link = '<a href="poster.php?id=' . (int)$row['id'] . '" target="_blank">' . (int)$row['id'] . '</a>';

    $title_raw = $row['title_he'] ?: $row['title_en'];
    $title = mb_strlen($title_raw) > 60 ? mb_substr($title_raw, 0, 60) . '…' : $title_raw;
    $title_link = '<a href="poster.php?id=' . (int)$row['id'] . '" target="_blank">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a>';

    $imdb_id = htmlspecialchars((string)($row['imdb_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $imdb_link = $imdb_id ? '<a href="https://www.imdb.com/title/' . $imdb_id . '" target="_blank">' . $imdb_id . '</a>' : '—';

    $imdb_show = ($imdb_rating_val > 0) ? htmlspecialchars($row['imdb_rating'], ENT_QUOTES, 'UTF-8') : '—';
    $mc_show   = (!empty($mc) && (string)$mc !== '0') ? htmlspecialchars((string)$mc, ENT_QUOTES, 'UTF-8') : '—';
    $rt_show   = (!empty($rt) && (string)$rt !== '0') ? htmlspecialchars((string)$rt, ENT_QUOTES, 'UTF-8') : '—';

    echo '<tr>
      <td>' . $id_link . '</td>
      <td>' . $title_link . '</td>
      <td>' . htmlspecialchars((string)($row['year'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
      <td>' . $imdb_show . '</td>
      <td>' . $mc_show . '</td>
      <td>' . $rt_show . '</td>
      <td>' . $imdb_link . '</td>
      <td style="color:red;">' . htmlspecialchars(implode(', ', $missing), ENT_QUOTES, 'UTF-8') . '</td>
      <td><a class="btn btn-edit" href="edit.php?id=' . (int)$row['id'] . '" target="_blank">✏ ערוך</a></td>
    </tr>';
  }

  echo '</table>';
}

include 'footer.php';
?>
