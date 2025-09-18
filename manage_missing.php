<?php
require_once 'server.php';
include 'header.php';

// הגדרת נתיב לתמונות הסוגים
if (!defined('IMAGE_BASE_PATH')) {
    define('IMAGE_BASE_PATH', 'images/types/');
}

/* --- שליפת סוגים (עם עמודת התמונה) --- */
$types = [];
$type_result = $conn->query("SELECT id, label_he, icon, image FROM poster_types ORDER BY id ASC");
while ($row = $type_result->fetch_assoc()) {
    $types[$row['id']] = [
        'label' => $row['label_he'],
        'icon'  => $row['icon'],
        'image' => $row['image']
    ];
}

$selected_type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;

/* --- פרמטר סינון חסר --- */
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
  'tmdb'     => 'TMDb קישור',
];
if (!isset($allowed_missing[$missing_key])) $missing_key = 'all';

/* --- תנאים --- */
$cond_map = [
  'title_he' => "(title_he IS NULL OR title_he='')",
  'title_en' => "(title_en IS NULL OR title_en='')",
  'overview' => "(COALESCE(NULLIF(plot,''), NULLIF(plot_he,''), NULLIF(overview_he,'')) IS NULL)",
  'imdb'     => "(imdb_rating IS NULL OR imdb_rating=0)",
  'image'    => "((image_url IS NULL OR image_url='' OR image_url='N/A') AND (poster_url IS NULL OR poster_url='' OR poster_url='N/A'))",
  'year'     => "(year IS NULL OR year='')",
  'genres'   => "((genre IS NULL OR genre='') AND (genres IS NULL OR genres=''))",
  'tmdb'     => "(tmdb_url IS NULL OR tmdb_url='')",
];
$cond_all = implode(" OR ", array_values($cond_map));

/* WHERE סופי */
$where = ($missing_key === 'all') ? $cond_all : $cond_map[$missing_key];
if ($selected_type_id > 0) {
  $where = "(type_id = $selected_type_id) AND ($where)";
}

/* --- מונה לכל כפתור --- */
$counts_sql =
  "SELECT
     SUM(CASE WHEN {$cond_map['title_he']} THEN 1 ELSE 0 END) AS c_title_he,
     SUM(CASE WHEN {$cond_map['title_en']} THEN 1 ELSE 0 END) AS c_title_en,
     SUM(CASE WHEN {$cond_map['overview']} THEN 1 ELSE 0 END) AS c_overview,
     SUM(CASE WHEN {$cond_map['imdb']} THEN 1 ELSE 0 END)     AS c_imdb,
     SUM(CASE WHEN {$cond_map['image']} THEN 1 ELSE 0 END)    AS c_image,
     SUM(CASE WHEN {$cond_map['year']} THEN 1 ELSE 0 END)     AS c_year,
     SUM(CASE WHEN {$cond_map['genres']} THEN 1 ELSE 0 END)   AS c_genres,
     SUM(CASE WHEN {$cond_map['tmdb']} THEN 1 ELSE 0 END)     AS c_tmdb,
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
  'tmdb'     => (int)($counts_row['c_tmdb']     ?? 0),
];

/* --- הגדרות עימוד --- */
$results_per_page = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $results_per_page;

/* --- ספירה ושליפה --- */
$count_result = $conn->query("SELECT COUNT(*) AS total FROM posters WHERE $where");
$total_missing = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_missing / $results_per_page);

$query = "SELECT * FROM posters WHERE $where ORDER BY id DESC LIMIT $offset, $results_per_page";
$result = $conn->query($query);

/* --- יצירת HTML לעימוד --- */
$pagination_html = '';
if ($total_pages > 1) {
    $total_results = $total_missing;
    ob_start();
    include 'pagination.php';
    $pagination_html = ob_get_clean();
}


/* --- סטייל --- */
echo <<<STYLE
<style>
  body { font-family: 'Segoe UI', sans-serif; }
  .styled-table {
    width: 95%; margin: 14px auto 20px; border-collapse: collapse; direction: rtl;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .styled-table th { background-color: #F2F2F3; color: black; padding: 10px; text-align: center; }
  .styled-table td { padding: 10px; text-align: right; border-bottom: 1px solid #ccc; }
  .styled-table td:nth-child(1), .styled-table td:nth-child(3), .styled-table td:nth-child(4),
  .styled-table td:nth-child(5), .styled-table td:nth-child(6) { text-align: center; }
  .styled-table tr:nth-child(even) { background-color: #f9f9f9; }
  .btn { text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
  .btn-edit { background: #007bff; color: white; }

  /* --- Type Filters (Images) --- */
  .type-filters { text-align: center; margin: 10px 0 15px; display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; }
  .type-filters label { font-weight: bold; font-size: 16px; margin-left: 5px; }
  .type-filters .type-link {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      padding: 5px;
      border: 2px solid transparent;
      border-radius: 8px;
      transition: all 0.2s ease;
      text-decoration: none;
      min-width: 60px;
  }
  .type-filters .type-link:hover { border-color: #ccc; }
  .type-filters .type-link.active { border-color: #007bff; background-color: #e7f1ff; }
  .type-filters img { height: 40px; vertical-align: middle; }
  .type-filters .type-label { font-size: 12px; color: #333; line-height: 1.2; }
  .type-filters a[title="כל הסוגים"] {
      font-size: 16px;
      padding: 10px 15px;
      background-color: #f0f0f0;
      color: #333;
      justify-content: center;
  }

  .missing-bar { text-align:center; margin: 10px 0 10px; }
  .missing-bar a {
    display:inline-block; margin:4px 5px; padding:6px 10px; border-radius:999px;
    border:1px solid #d0d7e2; text-decoration:none; color:#123; background:#fff; font-size:14px;
  }
  .missing-bar a .count { color:#555; margin-right:4px; }
  .missing-bar a.active { background:#e7f1ff; border-color:#9dbcf5; }
  .missing-summary { text-align:center; font-weight:bold; margin:6px 0; }
  
  /* Pagination CSS */
  .pagination { text-align: center; margin: 15px 0; }
  .pagination a, .pagination span { display: inline-block; color: #007bff; text-decoration: none; padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; border-radius: 4px; }
  .pagination a.active { background-color: #007bff; color: white; border-color: #007bff; cursor: default; }
  .pagination a:hover:not(.active) { background-color: #f1f1f1; }
  .pagination span { color: #999; border-color: transparent; }
</style>
STYLE;

echo '<h2 style="text-align:center; margin:10px;">🎯 פוסטרים עם מידע חסר</h2>';

/* --- פס כפתורים --- */
echo '<div class="missing-bar">';
$base_qs = $_GET; unset($base_qs['missing'], $base_qs['page']);
foreach ($allowed_missing as $key => $label) {
  $qs = $base_qs; $qs['missing'] = $key;
  $url = htmlspecialchars($_SERVER['PHP_SELF'].'?'.http_build_query($qs), ENT_QUOTES, 'UTF-8');
  $cls = ($missing_key === $key) ? 'active' : '';
  $num = (int)($counts[$key] ?? 0);
  echo '<a class="'.$cls.'" href="'.$url.'">'.$label.' <span class="count">('.$num.')</span></a>';
}
echo '</div>';

/* --- סינון סוג (תמונות ושמות) --- */
echo '<div class="type-filters">';
echo '  <label>סוג פוסטר:</label> ';

// קישור "כל הסוגים"
$base_qs_type = $_GET;
unset($base_qs_type['type_id'], $base_qs_type['page']);
$url_all = htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($base_qs_type));
$all_class = ($selected_type_id === 0) ? 'active' : '';
echo '<a href="' . $url_all . '" class="type-link ' . $all_class . '" title="כל הסוגים">הכל</a>';

// קישורים לכל סוג עם תמונה ושם
foreach ($types as $id => $data) {
    $image_file = trim($data['image'] ?? '');
    if (!empty($image_file)) {
        $qs = $base_qs_type;
        $qs['type_id'] = $id;
        $url = htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($qs));
        $cls = ($selected_type_id === $id) ? 'active' : '';

        echo '<a href="' . $url . '" class="type-link ' . $cls . '" title="' . htmlspecialchars($data['label']) . '">';
        echo '  <img src="' . htmlspecialchars(IMAGE_BASE_PATH . $image_file) . '" alt="' . htmlspecialchars($data['label']) . '">';
        echo '  <span class="type-label">' . htmlspecialchars($data['label']) . '</span>';
        echo '</a>';
    }
}
echo '</div>';


/* --- סיכום --- */
$sel_label = $allowed_missing[$missing_key] ?? 'הכול';
echo "<p class='missing-summary'>מציג: <u>$sel_label</u> — נמצאו <span style='color:darkred;'>$total_missing</span> פריטים</p>";

/* --- טבלה --- */
if ($total_missing === 0) {
  echo "<p style='text-align:center;'>✅ אין פריטים חסרים לפי הסינון.</p>";
} else {
  echo $pagination_html;
  
  echo '<table class="styled-table">
    <tr>
      <th>#</th>
      <th>כותרת</th>
      <th>שנה</th>
      <th>IMDb</th>
      <th>TMDb</th>
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
    $img_empty = (empty($row['image_url']) || $row['image_url'] === 'N/A') && (empty($row['poster_url']) || $row['poster_url'] === 'N/A');
    if ($img_empty) $missing[] = 'תמונה';
    if (empty($row['year'])) $missing[] = 'שנה';
    $has_genres = !(empty(trim((string)($row['genre'] ?? ''))) && empty(trim((string)($row['genres'] ?? ''))));
    if (!$has_genres) $missing[] = 'ז׳אנרים';
    $tmdb = $row['tmdb_url'] ?? '';
    if (empty($tmdb)) $missing[] = 'TMDb קישור';

    $id_link = '<a href="poster.php?id=' . (int)$row['id'] . '" target="_blank">' . (int)$row['id'] . '</a>';
    $title_raw = $row['title_he'] ?: $row['title_en'];
    $title = mb_strlen($title_raw) > 60 ? mb_substr($title_raw, 0, 60) . '…' : $title_raw;
    $title_link = '<a href="poster.php?id=' . (int)$row['id'] . '" target="_blank">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a>';
    $imdb_id = htmlspecialchars((string)($row['imdb_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $imdb_link = $imdb_id ? '<a href="https://www.imdb.com/title/' . $imdb_id . '" target="_blank">' . $imdb_id . '</a>' : '—';
    $imdb_show = ($imdb_rating_val > 0) ? htmlspecialchars($row['imdb_rating'], ENT_QUOTES, 'UTF-8') : '—';
    $tmdb_show = $tmdb ? '<a href="'.htmlspecialchars($tmdb, ENT_QUOTES, 'UTF-8').'" target="_blank">קישור</a>' : '—';

    echo '<tr>
      <td>' . $id_link . '</td>
      <td>' . $title_link . '</td>
      <td>' . htmlspecialchars((string)($row['year'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
      <td>' . $imdb_show . '</td>
      <td>' . $tmdb_show . '</td>
      <td>' . $imdb_link . '</td>
      <td style="color:red;">' . htmlspecialchars(implode(', ', $missing), ENT_QUOTES, 'UTF-8') . '</td>
      <td><a class="btn btn-edit" href="edit.php?id=' . (int)$row['id'] . '" target="_blank">✏ ערוך</a></td>
    </tr>';
  }

  echo '</table>';
  
  echo $pagination_html;
}

include 'footer.php';
?>