<?php
// ==== Security bootstrap (session + CSRF) ====
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrf_verify')) {
    function csrf_verify() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $t = $_POST['csrf_token'] ?? '';
            if (!$t || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) {
                http_response_code(403);
                exit('❌ Invalid CSRF token');
            }
        }
    }
}
?>
<?php
require_once 'force_post.php';
include 'bar.php'; // כולל את server.php, $card_w, $allowed_limits, $limit, $view, $allowed_sizes, $card_w, $view_options
require_once 'server.php';
require_once 'alias.php';
include 'languages.php';

// ----- Language to Flag Mapping -----
$lang_map = [];
foreach ($languages as $lang) {
    $lang_data = ['code' => $lang['code'], 'label' => $lang['label'], 'flag' => $lang['flag']];
    $lang_map[strtolower($lang['code'])] = $lang_data;
    $lang_map[strtolower($lang['label'])] = $lang_data;
}

// ----- Base Definitions -----
// $allowed_limits ו- $limit מגיעים מ-bar.php
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
// $view מגיע מ-bar.php
$search_mode = $_GET['search_mode'] ?? 'and';

// ----- Input Normalization -----
if (!empty($_GET['imdb_id'])) {
  if (preg_match('~tt\d{6,10}~i', (string)$_GET['imdb_id'], $m)) {
    $_GET['imdb_id'] = strtolower($m[0]);
  } else {
    $_GET['imdb_id'] = '';
  }
}
if (!empty($_GET['metacritic']) && preg_match('/^\d+$/', (string)$_GET['metacritic'])) {
  $_GET['metacritic'] = $_GET['metacritic'] . '-';
}
if (!empty($_GET['min_rating']) && preg_match('/^\d+(\.\d+)?$/', (string)$_GET['min_rating'])) {
  $_GET['min_rating'] = $_GET['min_rating'] . '-';
}

// ----- Possible Form Fields -----
$fields = [
  'search', 'min_rating', 'metacritic', 'rt_score', 'year', 'imdb_id', 'tvdb_id',
  'genre', 'actor', 'user_tag', 'directors', 'producers', 'writers',
  'composers', 'cinematographers', 'lang_code', 'country', 'runtime',
  'network'
];
$types_selected = $_GET['type'] ?? [];
$ALIAS_FIELDS = ['search','country','lang_code','genre','network','user_tag'];
foreach ($ALIAS_FIELDS as $k) {
  if (!empty($_GET[$k]) && is_string($_GET[$k])) {
    $_GET[$k] = applyAliases($k, $_GET[$k], $ALIASES);
  }
}

// ----- Dynamic WHERE Clause -----
$where = [];
$params = [];
$bind_types = '';
$join_akas = '';

// --- טיפול בבחירת דגלים (שדה נסתר, נפרד לגמרי מ-lang_code) ---
$join_langs = ""; 
if (!empty($_GET['flag_code'])) {
    $fc = $_GET['flag_code'];
    // חיבור לטבלת poster_languages כאשר יש בקשה לדגל ספציפי
    $join_langs = " JOIN poster_languages pl ON posters.id = pl.poster_id ";
    $where[] = "pl.lang_code = ?";
    $params[] = $fc;
    $bind_types .= 's';
}

if ($types_selected && is_array($types_selected)) {
  $placeholders = implode(',', array_fill(0, count($types_selected), '?'));
  $where[] = 'posters.type_id IN (' . $placeholders . ')';
  foreach ($types_selected as $tid) { $params[] = (int)$tid; $bind_types .= 'i'; }
}

function addCondition($col, $val, &$where, &$params, &$bind_types, $mode = 'and') {
  if ($val === '') return;
  $parts = array_map('trim', explode(',', $val));
  $cond_group = [];
  foreach ($parts as $item) {
    if ($item === '') continue;
    if ($item[0] === '!') {
      $value = substr($item, 1);
      $cond_group[] = "$col NOT LIKE ?";
      $params[] = "%$value%";
      $bind_types .= 's';
    } elseif (preg_match('/^(\d+)?-(\d+)?$/', $item, $m)) {
      $from = (isset($m[1]) && $m[1] !== '') ? (int)$m[1] : null;
      $to   = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : null;
      if ($from && $to)      { $cond_group[] = "($col BETWEEN ? AND ?)"; $params[] = $from; $params[] = $to; $bind_types .= 'ii'; }
      elseif ($from)         { $cond_group[] = "($col >= ?)"; $params[] = $from; $bind_types .= 'i'; }
      elseif ($to)           { $cond_group[] = "($col <= ?)"; $params[] = $to; $bind_types .= 'i'; }
    } else {
      $cond_group[] = "$col LIKE ?";
      $params[] = "%$item%";
      $bind_types .= 's';
    }
  }
  if ($cond_group) {
    $logic = strtoupper($mode) === 'or' ? 'OR' : 'AND';
    $where[] = '(' . implode(" $logic ", $cond_group) . ')';
  }
}

foreach ($fields as $f) {
  $val = trim($_GET[$f] ?? '');
  if ($val === '') continue;

  $col = match($f) {
    'search'      => '(posters.title_en LIKE ? OR posters.title_he LIKE ? OR posters.imdb_id LIKE ?)',
    'actor'       => 'posters.`cast`',
    'user_tag'    => '(SELECT GROUP_CONCAT(genre) FROM user_tags WHERE poster_id = posters.id)',
    'runtime'     => 'posters.runtime',
    'genre'       => 'posters.genres',
    'year'        => 'posters.year',
    'min_rating'  => 'posters.imdb_rating',
    'metacritic'  => 'posters.mc_score',
    'rt_score'    => 'posters.rt_score',
    'imdb_id'     => 'posters.imdb_id',
    'tvdb_id'     => 'posters.tvdb_id',
    'lang_code'   => 'posters.languages',
    'country'     => 'posters.countries',
    'directors'   => 'posters.directors',
    'producers'   => 'posters.producers',
    'writers'     => 'posters.writers',
    'composers'   => 'posters.composers',
    'cinematographers' => 'posters.cinematographers',
    'network'     => 'posters.networks',
    default       => 'posters.' . $f
  };

  if ($f === 'search') {
    $v = $_GET['search'];
    if ($v !== '') {
      $join_akas = " LEFT JOIN poster_akas pa ON pa.poster_id = posters.id ";
    }
    if ($v !== '' && $v[0] === '!') {
      $value = substr($v, 1);
      $where[] = '(posters.title_en NOT LIKE ? AND posters.title_he NOT LIKE ? AND posters.imdb_id  NOT LIKE ? AND COALESCE(pa.aka_title, \'\') NOT LIKE ? AND COALESCE(pa.aka, \'\') NOT LIKE ?)';
      for ($i = 0; $i < 5; $i++) { $params[] = "%$value%"; $bind_types .= 's'; }
    } else {
      $where[] = '(posters.title_en LIKE ? OR posters.title_he LIKE ? OR posters.imdb_id  LIKE ? OR pa.aka_title LIKE ? OR pa.aka LIKE ?)';
      for ($i = 0; $i < 5; $i++) { $params[] = "%$v%"; $bind_types .= 's'; }
    }
  } elseif ($f === 'user_tag') {
    $v = $_GET['user_tag'];
    if ($v !== '' && $v[0] === '!') {
      $where[] = 'posters.id NOT IN (SELECT poster_id FROM user_tags WHERE genre LIKE ?)';
      $params[] = '%' . substr($v, 1) . '%'; $bind_types .= 's';
    } else {
      $where[] = 'posters.id IN (SELECT poster_id FROM user_tags WHERE genre LIKE ?)';
      $params[] = "%$v%"; $bind_types .= 's';
    }
  } else {
    addCondition($col, $val, $where, $params, $bind_types, $search_mode);
  }
}
if (isset($_GET['is_foreign_language'])) {
  $where[] = "NOT (posters.languages LIKE '%Hebrew%' OR posters.languages LIKE '%עברית%')";
}
$logic = strtoupper($search_mode) === 'or' ? 'OR' : 'AND';
$sql_where = $where ? "WHERE " . implode(" $logic ", $where) : "";

// ----- Sorting -----
$orderBy = "ORDER BY posters.id DESC"; // ברירת מחדל: זמן הוספה לאתר (חדש -> ישן)
$sort = $_GET['sort'] ?? '';

switch ($sort) {
    // שנה
    case 'year_asc':      // שנה מישן לחדש
        $orderBy = "ORDER BY posters.year ASC, posters.id DESC";
        break;
    case 'year_desc':     // שנה מחדש לישן
        $orderBy = "ORDER BY posters.year DESC, posters.id DESC";
        break;
case 'updated_at_desc':
    $orderBy = "ORDER BY posters.updated_at DESC, posters.id DESC";
    break;

case 'updated_at_asc':
    $orderBy = "ORDER BY posters.updated_at ASC, posters.id DESC";
    break;

    // דירוג IMDb
    case 'rating_desc':   // דירוג מגבוה לנמוך
        $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(posters.imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC, posters.id DESC";
        break;
    case 'rating_asc':    // דירוג מנמוך לגבוה
        $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(posters.imdb_rating, '/', 1) AS DECIMAL(3,1)) ASC, posters.id DESC";
        break;

    // כמות הצבעות IMDb
    case 'votes_desc':    // כמות הצבעות – גבוה -> נמוך
        $orderBy = "ORDER BY CAST(REPLACE(posters.imdb_votes, ',', '') AS UNSIGNED) DESC, posters.id DESC";
        break;
    case 'votes_asc':     // כמות הצבעות – נמוך -> גבוה
        $orderBy = "ORDER BY CAST(REPLACE(posters.imdb_votes, ',', '') AS UNSIGNED) ASC, posters.id DESC";
        break;

    // זמן הוספה לאתר (נשתמש ב-ID כסדר)
    case 'added_desc':    // חדש -> ישן
        $orderBy = "ORDER BY posters.id DESC";
        break;
    case 'added_asc':     // ישן -> חדש
        $orderBy = "ORDER BY posters.id ASC";
        break;

    // אורך (זמן ריצה)
    case 'runtime_desc':  // ארוך -> קצר
        $orderBy = "ORDER BY CAST(posters.runtime AS UNSIGNED) DESC, posters.id DESC";
        break;
    case 'runtime_asc':   // קצר -> ארוך
        $orderBy = "ORDER BY CAST(posters.runtime AS UNSIGNED) ASC, posters.id DESC";
        break;

    // כותרת אנגלית – ABC
    case 'title_en_asc':  // A -> Z
        $orderBy = "ORDER BY posters.title_en ASC, posters.id DESC";
        break;
    case 'title_en_desc': // Z -> A
        $orderBy = "ORDER BY posters.title_en DESC, posters.id DESC";
        break;

    // כותרת עברית – א"ב
    case 'title_he_asc':  // א -> ת
        $orderBy = "ORDER BY posters.title_he ASC, posters.id DESC";
        break;
    case 'title_he_desc': // ת -> א
        $orderBy = "ORDER BY posters.title_he DESC, posters.id DESC";
        break;

    default:
        // נשאר ברירת מחדל: ORDER BY posters.id DESC
        break;
}

// ----- Main Fetch -----
// שים לב: הוספנו את $join_langs לשאילתה
$sql = "SELECT DISTINCT posters.*, pt.label_he AS type_label, pt.image AS type_image
        FROM posters
        LEFT JOIN poster_types pt ON posters.type_id = pt.id
        $join_akas
        $join_langs
        $sql_where
        $orderBy
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($bind_types) $stmt->bind_param($bind_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;



// ===============================
// Watch status for grid buttons (collections: watched=231, watchlist=196)
// ===============================
$WATCHED_COLLECTION_ID = 231;
$WATCHLIST_COLLECTION_ID = 196;
$watched_set = [];
$watchlist_set = [];

if (!empty($rows)) {
    $poster_ids_for_watch = array_column($rows, 'id');
    $poster_ids_for_watch = array_values(array_filter(array_map('intval', $poster_ids_for_watch)));

    if (!empty($poster_ids_for_watch)) {
        $wc_ids_placeholder = implode(',', array_fill(0, count($poster_ids_for_watch), '?'));
        $wc_ids_types = str_repeat('i', count($poster_ids_for_watch));

        $sql_wc = "SELECT collection_id, poster_id
                   FROM poster_collections
                   WHERE collection_id IN (?, ?) AND poster_id IN ($wc_ids_placeholder)";

        $stmt_wc = $conn->prepare($sql_wc);
        if ($stmt_wc) {
            $wc_bind_types  = "ii" . $wc_ids_types;
            $wc_bind_params = array_merge([(int)$WATCHED_COLLECTION_ID, (int)$WATCHLIST_COLLECTION_ID], $poster_ids_for_watch);

            $wc_refs = [];
            $wc_refs[] = &$wc_bind_types;
            foreach ($wc_bind_params as $k => $v) { $wc_refs[] = &$wc_bind_params[$k]; }
            call_user_func_array([$stmt_wc, 'bind_param'], $wc_refs);

            $stmt_wc->execute();
            $r_wc = $stmt_wc->get_result();
            while ($rr = $r_wc->fetch_assoc()) {
                $pid = (int)$rr['poster_id'];
                if ((int)$rr['collection_id'] === (int)$WATCHED_COLLECTION_ID)  $watched_set[$pid] = true;
                if ((int)$rr['collection_id'] === (int)$WATCHLIST_COLLECTION_ID) $watchlist_set[$pid] = true;
            }
            $stmt_wc->close();
        }
    }
}

$stmt->close();

// ----- שליפת נתונים נוספים -----
$user_tags_by_poster_id = [];
$manual_languages_by_poster_id = [];
$stickers_by_poster_id = [];

if (!empty($rows)) {
    $poster_ids = array_column($rows, 'id');
    $ids_placeholder = implode(',', array_fill(0, count($poster_ids), '?'));
    $ids_types = str_repeat('i', count($poster_ids));
    
    $ut_sql = "SELECT poster_id, genre FROM user_tags WHERE poster_id IN ($ids_placeholder)";
    $stmt_ut = $conn->prepare($ut_sql);
    $stmt_ut->bind_param($ids_types, ...$poster_ids);
    $stmt_ut->execute();
    $ut_result = $stmt_ut->get_result();
    while ($ut_row = $ut_result->fetch_assoc()) {
        $user_tags_by_poster_id[$ut_row['poster_id']][] = $ut_row['genre'];
    }
    
    $lang_sql = "SELECT poster_id, lang_code FROM poster_languages WHERE poster_id IN ($ids_placeholder)";
    $stmt_lang = $conn->prepare($lang_sql);
    $stmt_lang->bind_param($ids_types, ...$poster_ids);
    $stmt_lang->execute();
    $lang_result = $stmt_lang->get_result();
    while ($lang_row = $lang_result->fetch_assoc()) {
        $manual_languages_by_poster_id[$lang_row['poster_id']][] = $lang_row['lang_code'];
    }

    // --- שליפת סטיקרים של אוספים עבור כל הפוסטרים בעמוד ---
    if (!empty($poster_ids)) {
        $sql_stickers = "SELECT pc.poster_id, c.poster_image_url, c.id as collection_id, c.name as collection_name
                         FROM poster_collections pc
                         JOIN collections c ON pc.collection_id = c.id
                         WHERE pc.poster_id IN ($ids_placeholder) AND c.poster_image_url IS NOT NULL AND c.poster_image_url <> ''";
        
        $stmt_stickers = $conn->prepare($sql_stickers);
        $stmt_stickers->bind_param($ids_types, ...$poster_ids);
        $stmt_stickers->execute();
        $stickers_result = $stmt_stickers->get_result();
        while ($sticker_row = $stickers_result->fetch_assoc()) {
            $stickers_by_poster_id[$sticker_row['poster_id']][] = $sticker_row;
        }
        $stmt_stickers->close();
    }
}

// ----- Count Total Rows -----
// שים לב: הוספנו את $join_langs גם לשאילתת הספירה
$count_sql = "SELECT COUNT(DISTINCT posters.id) as c FROM posters $join_akas $join_langs $sql_where";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types) $count_stmt->bind_param($bind_types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['c'] ?? 0;
$count_stmt->close();


$total_pages = max(1, ceil($total_rows / $limit));
$start = $offset + 1;
$end   = $offset + count($rows);

$results_summary_text = 'הצגת ' . $start . '-' . $end . ' מתוך ' . $total_rows . ' — עמוד ' . $page . ' מתוך ' . $total_pages;
$uri_base = preg_replace('/([&?])page=\d+/', '', $_SERVER['REQUEST_URI']);
$uri_sep  = (str_contains($uri_base, '?') ? '&' : '?');

$range = 5; // 5 עמודים אחורה ו-5 קדימה
$start_page = max(1, $page - $range);
$end_page   = min($total_pages, $page + $range);
// === קבלת גודל הפוסטר מ-bar.php ===
$poster_card_width = $card_w ?? 205; 
// ===================================

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>ספריית פוסטרים</title>
  <link rel="stylesheet" href="style.css"> <style>
    .poster-title {
      font-family: Assistant, "Segoe UI", Arial, sans-serif;
      line-height: 1.0;
      margin-top: 4px;
      font-weight: bold;
    }
    .poster-title b {
      font-size: 16px;
      font-weight: 700;
      color: #000;
    }
    .poster-title .hebrew-title {
      font-size: 16px;
      font-weight: 700;
      color: #666;
      font-family: Arial !important;
    }
    .poster-title .year-link {
      font-size: 14px;
      font-weight: 400;
    }
    .poster:hover {
      position: relative;
      z-index: 10;
      transform: scale(1.15); 
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    body { background-color:white; margin:0; }
    h1, .results-summary { text-align:center; margin:10px 0 15px 0; }
    .pager {
      text-align:center; margin: 18px 0 18px 0;
      display: flex; justify-content: center; gap: 7px; flex-wrap: wrap;
    }
    .pager a, .pager strong {
      padding: 6px 12px; border: 1px solid #bbb; border-radius: 6px;
      text-decoration: none; background: #fff; font-size: 15px; color: #147; margin: 0 1px;
    }
    .poster-wall {
      display: flex; flex-wrap: wrap; gap: 18px 12px;
      justify-content: center; margin: 10px 0 40px 0;
    }
    .poster {
      width: <?= (int)$poster_card_width ?>px; /* משתמש ב-card_w מ-bar.php */
      background: #fff; border: 0px solid #eee; border-radius: 0px;
      
      padding:0  0 10px; text-align: center; box-shadow:  25px rgba(0,0,0,0.07);
      display: flex; flex-direction: column;
      position: relative;
    }
    .poster-title { font-weight:bold; margin-top: 4px;}
    .title-link { text-decoration: none; color: inherit; }
    .imdb-container {
      display: flex; justify-content: center; align-items: center;
      gap: 5px; padding: 5px 0; text-decoration: none; color: inherit;
    }
    .imdb-container span { white-space: nowrap; }
    .imdb-container img {
      height: 30px;
      width: auto;
      object-fit: contain;
    }
    .poster-type-link { text-decoration: none; }
    .poster-type-display {
      font-size: 12px; color: #555; display: flex; align-items: center; justify-content: center;
      gap: 8px; padding: 4px 0;
    }
    .poster-tags { text-align: center; padding: 4px 0; }
    .tag-badge {
      display: inline-block; background: linear-gradient(to bottom, #f7f7f7, #e0e0e0); color: #333;
      padding: 4px 12px; border-radius: 16px; font-size: 12px; margin: 3px;
      text-decoration: none; font-weight: 500; border: 1px solid #ccc;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s ease-in-out;
    }
    .user-tag { background: linear-gradient(to bottom, #e3f2fd, #bbdefb); border-color: #90caf9; }
    .tag-badge:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .network-logo-container { display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; padding: 4px 0;}
    .flags-container { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 4px; flex-wrap: wrap; padding: 4px 0; }
    /* keep flag + name on one line */
    .flag-row a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      color: inherit;
      white-space: nowrap;
      font-size: 14px;
    }
    .flag-row img {
      height: 16px; width: auto; object-fit: contain; vertical-align: middle;
    }

    .poster-actions {
      margin-top: auto; padding-top: 8px;
      display: flex; justify-content: center; align-items: center;
      direction: rtl;
      gap: 8px;
      flex-wrap: nowrap;
    }

    .poster-actions a, .poster-actions button { text-decoration: none; font-size: 18px; margin: 0; display: inline-flex; align-items: center; }
    .trailer-btn { background-color: #d92323; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 13px; }
    
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
    .modal-content { position: relative; background-color: #181818; margin: 60px auto auto; padding: 0; width: 90%; max-width: 800px; }
    .close-btn { color: white; float: left; font-size: 38px; font-weight: bold; }
    .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; }
    .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
    
    .poster-list, .poster-regular { list-style:none; padding:0; width:95%; margin:24px auto; }
    .poster-list li { display:flex; align-items:center; gap:12px; background: #fff; border-radius: 7px; margin-bottom: 8px; padding: 8px; }
    .poster-list img { height:60px; border-radius:5px; }
    
    /* השתמש בגודל הדינמי גם פה */
    .poster-regular li { display:inline-block; width:<?= (int)$poster_card_width ?? 178 ?>px; margin:10px; vertical-align:top; text-align:center; background: #fff; border-radius: 7px; padding: 8px; }
    .poster-regular img { height:<?= (int)($poster_card_width * 0.73) ?? 150 ?>px; border-radius:4px; margin-bottom:6px; } /* יחס גובה-רוחב קבוע */
    
    .poster-list-item { display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 7px; margin-bottom: 8px; padding: 8px; }
    .poster-list-item .list-thumb { width: 50px; height: 75px; object-fit: cover; border-radius: 5px; flex-shrink: 0; }
    .poster-list-item .list-details { flex-grow: 1; text-align: right; }
    
    .network-logo-container:empty,
    .poster-tags:empty,
    .flags-container:empty { display: none; }
    .flag-row { width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap; }
    .flag-row + .flag-row { margin-top: 4px; }

    .collection-sticker-container {
      padding: 8px 0;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .collection-sticker-image:hover { transform: scale(1.1); }

    /* Admin toggle placement */
    .admin-toggle-wrap { text-align: center; margin: 6px 0 12px; }
    .admin-toggle { padding: 6px 12px; font-size: 14px; cursor: pointer; }
    
    /* ========== START: STYLES FOR collections_view (Gallery View) ========== */
    .collections-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 20px;
      padding: 20px;
      justify-content: center;
      margin: 0 auto;
      max-width: 1400px;
    }

    .collection-card {
      border: 1px solid #ddd;
      border-radius: 1px;
      overflow: hidden;
      text-decoration: none;
      color: black;
      background-color: #fff;
      display: flex;
      flex-direction: column;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .collection-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .collection-card .card-image-link img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .collection-card .card-details {
      padding: 12px;
      text-align: right;
      display: flex;
      flex-direction: column;
      flex-grow: 1;
      justify-content: space-between;
      min-height: 100px;
      background: #fff;
    }

    .card-details .card-title-link { text-decoration: none; color: inherit; }
    .card-details .title-en { font-weight: bold; font-size: 1.05em; line-height: 1.2; margin-bottom: 2px; color: #333; }
    .card-details .title-he { font-size: 1em; color: #666; margin-bottom: 8px; line-height: 1.2; }
    .card-details .meta-info {
      display: flex; justify-content: space-between; align-items: center;
      font-size: 0.9em; color: #555; border-top: 1px solid #eee;
      padding-top: 8px; margin-top: auto;
    }
    .card-details .meta-info a { text-decoration: none; color: #0056b3; font-weight: bold; }
    .card-details .meta-info a:hover { text-decoration: underline; }
    /* ========== END: STYLES FOR collections_view ========== */
  
    /* === Watch buttons under posters === */
    .watch-actions{display:flex;justify-content:center;align-items:center;gap:6px;margin-top:6px;flex-wrap:nowrap}
    .wa-btn{border:1px solid #ced4da;background:#f8f9fa;color:#212529;border-radius:6px;padding:5px 10px;font-size:12px;cursor:pointer;white-space:nowrap;line-height:1;display:inline-flex;align-items:center;gap:6px}
    .wa-btn.on{background:#d3f9d8;border-color:#b2f2bb;color:#1b5e20}
    .wa-btn.watched.on{background:#e7f5ff;border-color:#a5d8ff;color:#0b3d91}

</style>

<style>
/* Active/current page styling */
.page-btn.active,
.pagination .page-btn.active,
.pager strong,
.pager .active,
.pagination a.active,
.pagination span.active {
    background: deepskyblue !important;
    color: #fff !important;
    border-color: deepskyblue !important;
}
</style>

</head>
<body>
  <h1>🎬 ספריית פוסטרים</h1>
  <div class="admin-toggle-wrap">
    <button id="toggle-admin" class="admin-toggle" type="button">🔑 מצב ניהול</button>
  </div>

  <div class="results-summary">
    <b><?= htmlspecialchars($results_summary_text, ENT_QUOTES, 'UTF-8') ?></b>
  </div>

  <div class="pager">
    <?php if ($total_pages > 1): ?>
      <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=1') ?>">⏭ ראשון</a>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page - 1)) ?>">➡ הקודם</a>
      <?php endif; ?>

      <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <?php if ($i == $page): ?>
          <strong><?= (int)$i ?></strong>
        <?php else: ?>
          <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . $i) ?>"><?= (int)$i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page + 1)) ?>">⬅ הבא</a>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . $total_pages) ?>">⏮ אחרון</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (empty($rows)): ?>
    <p style="text-align:center; color:#888;">😢 לא נמצאו תוצאות</p>

  <?php elseif ($view === 'modern_grid'): ?>
    <div class="poster-wall">
      <?php foreach ($rows as $row): ?>
        <div class="poster ltr">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר" loading="lazy" style="width: 100%; height: auto; object-fit: cover;">
          </a> 
          
          <div class="poster-title">
            <a href="poster.php?id=<?= $row['id'] ?>" class="title-link">
                <b><?= htmlspecialchars($row['title_en']) ?></b>
                <?php if (!empty($row['title_he'])): ?><br><span class="hebrew-title"><?= htmlspecialchars($row['title_he']) ?></span><?php endif; ?>
            </a>
            <br><span class="year-link">[<a href="home.php?year=<?= htmlspecialchars($row['year']) ?>"><?= $row['year'] ?></a>]</span>
          </div>

          <div class="imdb-container" style="display:flex; flex-direction:column; justify-content:center; align-items:center; gap: 4px; padding-bottom: 4px; direction: rtl;">
            <?php if ($row['imdb_rating']): ?>
                <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none; color:inherit; white-space:nowrap;">
                    <span> 10 / <?= htmlspecialchars($row['imdb_rating']) ?></span> ⭐<img src="images/imdb.png" alt="IMDb">
                </a>
            <?php endif; ?>
            <?php if (!empty($row['runtime'])): ?>
              <span style="font-size: 0.9em; color: #555; white-space:nowrap; text-align:center;">⏱
                <?= htmlspecialchars($row['runtime']) ?> דקות 
              </span>
            <?php endif; ?>
          </div>
          

          <?php $pid = (int)$row['id']; $inWatched = !empty($watched_set[$pid]); $inWatch = !empty($watchlist_set[$pid]); ?>
          <div class="watch-actions" data-poster-id="<?= $pid ?>">
            <button type="button" class="wa-btn watched <?= $inWatched ? 'on' : '' ?>" data-cid="231"><?= $inWatched ? '✅ צפיתי' : '👁️ צפיתי' ?></button>
            <button type="button" class="wa-btn watch <?= $inWatch ? 'on' : '' ?>" data-cid="196"><?= $inWatch ? '✅ הסר מצפייה' : '⏳ הוסף לצפייה' ?></button>
          </div>

          <?php if (!empty($row['type_label'])): ?>
            <a href="home.php?type[]=<?= htmlspecialchars($row['type_id']) ?>" class="poster-type-link">
                <div class="poster-type-display">
                    <span><?= htmlspecialchars($row['type_label']) ?></span>
                    <?php if (!empty($row['type_image'])): ?>
                        <img src="images/types/<?= htmlspecialchars($row['type_image']) ?>" alt="" style="max-height: 32px; width: auto; vertical-align: middle;">
                    <?php endif; ?>
                </div>
            </a>
          <?php endif; ?>

          <div class="network-logo-container">
              <?php
                $networks = array_filter(array_map('trim', explode(',', $row['networks'] ?? '')));
                foreach ($networks as $net) {
                    $slug = strtolower(preg_replace('/\s+/', '', $net));
                    foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
                        $logoPath = "images/networks/{$slug}.{$ext}";
                        if (is_file($logoPath)) {
                            echo "<a href='home.php?network=" . urlencode($net) . "'><img src='" . htmlspecialchars($logoPath) . "' alt='" . htmlspecialchars($net) . "' style='max-width: 80px; max-height: 35px; width: auto; height: auto; object-fit: contain;'></a>";
                            break;
                        }
                    }
                }
              ?>
          </div>
          
          <?php if (isset($stickers_by_poster_id[$row['id']])): ?>
            <div class="collection-sticker-container">
                <?php foreach ($stickers_by_poster_id[$row['id']] as $sticker): ?>
                    <a href="collection.php?id=<?= (int)$sticker['collection_id'] ?>" title="שייך לאוסף: <?= htmlspecialchars($sticker['collection_name']) ?>">
                        <img src="<?= htmlspecialchars($sticker['poster_image_url']) ?>" class="collection-sticker-image" alt="<?= htmlspecialchars($sticker['collection_name']) ?>" style="width: 50px; height: 50px; object-fit: contain;">
                    </a>
                <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="poster-tags">
              <?php
                $official_genres = array_filter(array_map('trim', explode(',', $row['genres'] ?? '')));
                foreach ($official_genres as $genre): ?>
                    <a href="home.php?genre=<?= urlencode($genre) ?>" class="tag-badge"><?= htmlspecialchars($genre) ?></a>
              <?php endforeach; ?>
              <?php if (isset($user_tags_by_poster_id[$row['id']])): ?>
                  <?php foreach ($user_tags_by_poster_id[$row['id']] as $utag): ?>
                    <a href="home.php?user_tag=<?= urlencode($utag) ?>" class="tag-badge user-tag"><?= htmlspecialchars($utag) ?></a>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>

          <div class="flags-container">
                <?php
                  $manual_langs = $manual_languages_by_poster_id[$row['id']] ?? [];
                  $auto_langs = array_filter(array_map('trim', explode(',', $row['languages'] ?? '')));
                  $auto_langs_unique = array_diff($auto_langs, $manual_langs);
                ?>
                <?php if (!empty($manual_langs)): ?>
                    <div class="flag-row">
                    <?php foreach ($manual_langs as $lang_name): ?>
                        <?php
                            $lang_key = strtolower($lang_name);
                            if (isset($lang_map[$lang_key])):
                                $flag_data = $lang_map[$lang_key];
                        ?>
                            <a href="javascript:void(0);" onclick="document.querySelector('input[name=flag_code]').value='<?= $flag_data['code'] ?>'; document.querySelector('.bar-form').submit();" title="שפה: <?= htmlspecialchars($flag_data['label']) ?>">
                          <span><?= htmlspecialchars($flag_data['label']) ?></span>
                         <img src="<?= htmlspecialchars($flag_data['flag']) ?>">
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($auto_langs_unique)): ?>
                    <div class="flag-row">
                    <?php foreach ($auto_langs_unique as $lang_name): ?>
                        <?php
                            $lang_key = strtolower($lang_name);
                            if (isset($lang_map[$lang_key])):
                                $flag_data = $lang_map[$lang_key];
                        ?>
                            <a href="home.php?lang_code=<?= urlencode($flag_data['label']) ?>" title="שפה: <?= htmlspecialchars($flag_data['label']) ?>">
                                 <img src="<?= htmlspecialchars($flag_data['flag']) ?>">
                                
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
          </div>
      
          <div class="poster-actions">
    <?php if (!empty($row['trailer_url'])): ?>
        <button class="trailer-btn" data-trailer-url="<?= htmlspecialchars($row['trailer_url']) ?>">🎬 טריילר</button>
    <?php endif; ?>
    
    <span class="admin-only">
      <?php if (!empty($row['trailer_url'])): ?><span>|</span><?php endif; ?>
      <a href="edit.php?id=<?= $row['id'] ?>" title="עריכה">✏️</a>
      <span>|</span>
      <a href="delete.php?id=<?= $row['id'] ?>" title="מחיקה" onclick="return confirm('למחוק את הפוסטר?')">🗑️</a>
    </span>
</div>
        </div>
      <?php endforeach; ?>
    </div>
    
  <?php elseif ($view === 'collections_view'): ?>
    <div class="collections-grid">
      <?php foreach ($rows as $row): ?>
        <?php $image_to_show = $row['image_url'] ?: 'images/no-poster.png'; ?>
        <div class="collection-card">
            <a href="poster.php?id=<?= $row['id'] ?>" class="card-image-link">
                <img src="<?= htmlspecialchars($image_to_show) ?>" alt="תמונת פוסטר">
            </a>
            <div class="card-details">
                <a href="poster.php?id=<?= $row['id'] ?>" class="card-title-link">
                    <div class="title-en"><?= htmlspecialchars($row['title_en']) ?></div>
                    <?php if (!empty($row['title_he'])): ?>
                        <div class="title-he"><?= htmlspecialchars($row['title_he']) ?></div>
                    <?php endif; ?>
                </a>
                <div class="meta-info">
                    <span>🗓️ <?= htmlspecialchars($row['year']) ?></span>
                    <?php if (!empty($row['imdb_id'])): ?>
                        <a href="https://www.imdb.com/title/<?= htmlspecialchars($row['imdb_id']) ?>/" target="_blank" title="פתח ב-IMDB">
                           ⭐ <?= htmlspecialchars($row['imdb_rating']) ?>
                        </a>
                    <?php else: ?>
                        <span>⭐ <?= htmlspecialchars($row['imdb_rating']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($view === 'grid'): ?>
    <div class="poster-wall">
      <?php foreach ($rows as $row): ?>
        <div class="poster" style="width:<?= (int)$poster_card_width ?? 190 ?>px;">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר">
            <div class="poster-title" style="color:#1567c0;"><?= htmlspecialchars($row['title_en']) ?></div>
            <?php if (!empty($row['title_he'])): ?><div><?= htmlspecialchars($row['title_he']) ?></div><?php endif; ?>
            <div><?= htmlspecialchars($row['year']) ?></div>
          </a>
          <div class="rating" style="font-size:14px; color:#666; margin-top: 3px;">
            <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank" style="text-decoration:none; color:inherit;">
                ⭐ <?= htmlspecialchars($row['imdb_rating']) ?>/10
            </a>
          </div>
          <div class="tags" style="font-size:12px; color:#888; margin:2px 0;"><?= htmlspecialchars($row['genres'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    
  <?php else: ?>
    <ul class="<?= $view === 'list' ? 'poster-list' : 'poster-regular' ?>">
  <?php foreach ($rows as $row): ?>
    <?php if ($view === 'list'): ?>
        <li class="poster-list-item">
            <a href="poster.php?id=<?= $row['id'] ?>">
                <img class="list-thumb" src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר">
            </a>
            <div class="list-details">
                <a href="poster.php?id=<?= $row['id'] ?>" style="text-decoration: none; color: inherit; font-weight: bold; font-size: 16px;">
                    <?= htmlspecialchars($row['title_en']) ?>
                    <?php if (!empty($row['title_he'])): ?> — <?= htmlspecialchars($row['title_he']) ?><?php endif; ?>
                    (<?= htmlspecialchars($row['year']) ?>)
                </a>
                <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank" style="text-decoration:none; color:inherit;">
                    ⭐ <?= htmlspecialchars($row['imdb_rating']) ?>
                </a>
            </div>
            <a href="poster.php?id=<?= $row['id'] ?>" style="font-size: 18px; text-decoration: none; padding: 0 10px;"></a>
        </li>
    <?php else: // 'regular' view mode ?>
        <li style="width:<?= (int)$poster_card_width ?? 178 ?>px; margin:10px; vertical-align:top; text-align:center; display:inline-block;">
            <a href="poster.php?id=<?= $row['id'] ?>">
                <img style="height:<?= (int)($poster_card_width * 0.73) ?? 150 ?>px; border-radius:4px; margin-bottom:6px;" src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר">
                <br>
                <strong><?= htmlspecialchars($row['title_en']) ?></strong>
                <br><?= htmlspecialchars($row['title_he']) ?><br>🗓 <?= htmlspecialchars($row['year']) ?>
            </a>
        </li>
    <?php endif; ?>
  <?php endforeach; ?>
</ul>
  <?php endif; ?>

  
  <div class="results-summary bottom">
    <b><?= htmlspecialchars($results_summary_text, ENT_QUOTES, 'UTF-8') ?></b>
  </div>
<div class="pager">
    <?php if ($total_pages > 1): ?>
      <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=1') ?>">⏭ ראשון</a>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page - 1)) ?>">➡ הקודם</a>
      <?php endif; ?>

      <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <?php if ($i == $page): ?>
          <strong><?= (int)$i ?></strong>
        <?php else: ?>
          <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . $i) ?>"><?= (int)$i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page + 1)) ?>">⬅ הבא</a>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . $total_pages) ?>">⏮ אחרון</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div id="trailer-modal" style="display:none;" class="modal">
    <div class="modal-content ltr">
      <span class="close-btn">&times;</span>
      <div class="video-container" id="video-container"></div>
    </div>
  </div>
  


<script>
(function(){
  function findToggleBtn(){
    return document.getElementById('toggle-admin')
        || document.getElementById('admin-toggle')
        || document.querySelector('.admin-toggle');
  }

  function normalizeSaved(){
    var v = localStorage.getItem('adminMode');
    if (v !== '1' && v !== '0') {
      localStorage.setItem('adminMode', '0'); // default OFF
      return '0';
    }
    return v;
  }

  function applyState(active){
    var body = document.body;
    var btn = findToggleBtn();
    // Clear any forced classes then set
    body.classList.remove('admin-mode');
    if (active) body.classList.add('admin-mode');

    // Ensure admin-only elements follow the state even if CSS had !important earlier
    var els = document.querySelectorAll('.admin-only');
    els.forEach(function(el){
      // Clear any previous inline display to let CSS rule apply
      el.style.removeProperty('display');
      // If inactive, force hide to beat any stray styles
      if (!active) el.style.display = 'none';
      // If active, prefer CSS (.admin-mode .admin-only) but if still hidden, fallback:
      if (active && getComputedStyle(el).display === 'none') {
        // Guess an appropriate display for admin controls
        el.style.display = (el.classList.contains('actions') || el.classList.contains('flex')) ? 'flex' : 'inline-flex';
      }
    });

    if (btn) {
      try { btn.textContent = active ? '🚪 יציאה ממצב ניהול' : '🔑 מצב ניהול'; } catch(e){}
    }
  }

  function toggle(){
    var now = normalizeSaved() === '1' ? '0' : '1';
    localStorage.setItem('adminMode', now);
    applyState(now === '1');
  }

  function init(){
    var saved = normalizeSaved(); // '0' or '1'
    applyState(saved === '1');

    var btn = findToggleBtn();
    if (btn) {
      btn.addEventListener('click', function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        toggle();
      }, {capture:false});
    }

    // Delegation fallback in case button is replaced dynamically
    document.addEventListener('click', function(ev){
      var t = ev.target && ev.target.closest && ev.target.closest('#toggle-admin, #admin-toggle, .admin-toggle');
      if (!t) return;
      ev.preventDefault();
      ev.stopPropagation();
      toggle();
    }, {capture:true});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
<script>
// === פתיחת טריילר כפופ־אפ ===
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.trailer-btn');
  if (!btn) return;

  e.preventDefault();
  const videoUrl = btn.getAttribute('data-trailer-url');
  const modal = document.getElementById('trailer-modal');
  const container = document.getElementById('video-container');
  if (!modal || !container || !videoUrl) return;

  // הפוך את קישור ה־YouTube ל־embed אם צריך
  let embedUrl = videoUrl;
  if (videoUrl.includes('watch?v=')) {
    embedUrl = videoUrl.replace('watch?v=', 'embed/') + '?autoplay=1';
  } else if (!videoUrl.includes('autoplay=1')) {
    embedUrl += (videoUrl.includes('?') ? '&' : '?') + 'autoplay=1';
  }

  container.innerHTML = `<iframe src="${embedUrl}" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>`;
  modal.style.display = 'block';
});

// === סגירה בלחיצה על X או רקע ===
document.addEventListener('click', function(e) {
  const modal = document.getElementById('trailer-modal');
  if (!modal) return;
  if (e.target.classList.contains('close-btn') || e.target === modal) {
    modal.style.display = 'none';
    document.getElementById('video-container').innerHTML = '';
  }
});
</script>

<!-- ===============================
     Watch status for grid buttons (collections: watched=231, watchlist=196)
     =============================== -->
<?php
$WATCHED_COLLECTION_ID = 231;
$WATCHLIST_COLLECTION_ID = 196;
$watched_set = [];
$watchlist_set = [];

if (!empty($poster_ids)) {
    $ids_placeholder2 = implode(',', array_fill(0, count($poster_ids), '?'));
    $ids_types2 = str_repeat('i', count($poster_ids));

    $sql_wc = "SELECT collection_id, poster_id
               FROM poster_collections
               WHERE collection_id IN (?, ?) AND poster_id IN ($ids_placeholder2)";
    $stmt_wc = $conn->prepare($sql_wc);
    if ($stmt_wc) {
        $bind_types = "ii" . $ids_types2;
        $bind_params = array_merge([(int)$WATCHED_COLLECTION_ID, (int)$WATCHLIST_COLLECTION_ID], array_map('intval', $poster_ids));
        // mysqli bind_param דורש reference, לכן משתמשים ב-call_user_func_array
        $refs = [];
        $refs[] = &$bind_types;
        foreach ($bind_params as $k => $v) { $refs[] = &$bind_params[$k]; }
        call_user_func_array([$stmt_wc, 'bind_param'], $refs);
        $stmt_wc->execute();
        $r_wc = $stmt_wc->get_result();
        while ($rr = $r_wc->fetch_assoc()) {
            $pid = (int)$rr['poster_id'];
            if ((int)$rr['collection_id'] === (int)$WATCHED_COLLECTION_ID) $watched_set[$pid] = true;
            if ((int)$rr['collection_id'] === (int)$WATCHLIST_COLLECTION_ID) $watchlist_set[$pid] = true;
        }
        $stmt_wc->close();
    }
}

?>

<script>
(function(){
  function postToggle(posterId, collectionId){
    return fetch('toggle_collection_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body:new URLSearchParams({poster_id:String(posterId), collection_id:String(collectionId)}).toString()
    }).then(r=>r.json());
  }

  function setBtnState(btn, isOn){
    const cid = btn.dataset.cid;
    btn.classList.toggle('on', !!isOn);
    if (cid === '231'){
      btn.innerHTML = isOn ? '✅ צפיתי' : '👁️ צפיתי';
    } else if (cid === '196'){
      btn.innerHTML = isOn ? '✅ הסר מצפייה' : '⏳ הוסף לצפייה';
    }
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.wa-btn');
    if(!btn) return;
    e.preventDefault();

    const wrap = btn.closest('.watch-actions');
    const posterId = wrap ? wrap.dataset.posterId : null;
    const cid = btn.dataset.cid;
    if(!posterId || !cid) return;

    btn.disabled = true;
    postToggle(posterId, cid).then(data=>{
      if(!data || !data.ok) throw new Error((data && data.error) ? data.error : 'Toggle failed');
      setBtnState(btn, !!data.in_collection);
    }).catch(err=>{
      console.error(err);
      alert('שגיאה: ' + (err && err.message ? err.message : err));
    }).finally(()=>{ btn.disabled = false; });
  });
})();
</script>
<?php include 'footer.php'; ?>
</body>
</html>