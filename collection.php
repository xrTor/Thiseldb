<?php
require_once 'server.php'; // DB connection
require_once 'bbcode.php'; // BBCode -> HTML

// ===============================
// Redirect-style logic (pin/unpin/delete all)
// ===============================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) {
  echo "<p>❌ אוסף לא צוין</p>";
  exit;
}

// Preserve params (remove one-off flags)
$current_params = $_GET;
unset($current_params['pin_poster'], $current_params['unpin_poster'], $current_params['csv_success'], $current_params['inserted'], $current_params['already'], $current_params['pin_collection'], $current_params['unpin_collection']);
$redirect_query_string = http_build_query($current_params);

// Pin / Unpin poster
if (isset($_GET['pin_poster'])) {
  $poster_to_pin = (int)$_GET['pin_poster'];
  $stmt_pin = $conn->prepare("UPDATE poster_collections SET is_pinned = 1 WHERE collection_id = ? AND poster_id = ?");
  $stmt_pin->bind_param("ii", $id, $poster_to_pin);
  $stmt_pin->execute();
  $stmt_pin->close();
  header("Location: collection.php?" . $redirect_query_string);
  exit;
}
if (isset($_GET['unpin_poster'])) {
  $poster_to_unpin = (int)$_GET['unpin_poster'];
  $stmt_unpin = $conn->prepare("UPDATE poster_collections SET is_pinned = 0 WHERE collection_id = ? AND poster_id = ?");
  $stmt_unpin->bind_param("ii", $id, $poster_to_unpin);
  $stmt_unpin->execute();
  $stmt_unpin->close();
  header("Location: collection.php?" . $redirect_query_string);
  exit;
}

// Pin / Unpin COLLECTION
if (isset($_GET['pin_collection'])) {
  $collection_to_pin = (int)$_GET['pin_collection'];
  if ($collection_to_pin === $id) { // Sanity check
    $stmt_pin_coll = $conn->prepare("UPDATE collections SET is_pinned = 1 WHERE id = ?");
    $stmt_pin_coll->bind_param("i", $id);
    $stmt_pin_coll->execute();
    $stmt_pin_coll->close();
  }
  header("Location: collection.php?" . $redirect_query_string);
  exit;
}
if (isset($_GET['unpin_collection'])) {
  $collection_to_unpin = (int)$_GET['unpin_collection'];
  if ($collection_to_unpin === $id) { // Sanity check
    $stmt_unpin_coll = $conn->prepare("UPDATE collections SET is_pinned = 0 WHERE id = ?");
    $stmt_unpin_coll->bind_param("i", $id);
    $stmt_unpin_coll->execute();
    $stmt_unpin_coll->close();
  }
  header("Location: collection.php?" . $redirect_query_string);
  exit;
}


// Delete all content in collection
if (isset($_GET['delete_all_content'])) {
  if ($id > 0) {
    $stmt_delete = $conn->prepare("DELETE FROM poster_collections WHERE collection_id = ?");
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();
    $stmt_delete->close();
  }
  header("Location: collection.php?id=" . $id);
  exit;
}

// ===============================
// End of redirect logic
// ===============================

include 'header.php';
set_time_limit(3000000);

// Fetch collection
$stmt = $conn->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<p>❌ האוסף לא נמצא</p>";
  include 'footer.php';
  exit;
}
$collection = $result->fetch_assoc();
$stmt->close();

// Types for filter
$types_list = [];
$types_result = $conn->query("SELECT id, label_he FROM poster_types ORDER BY sort_order ASC");
while($row = $types_result->fetch_assoc()) $types_list[] = $row;

// Filters
$filters = [
    'q' => $_GET['q'] ?? '',
    'type_id' => $_GET['type_id'] ?? '',
    'min_year' => $_GET['min_year'] ?? '',
    'max_year' => $_GET['max_year'] ?? '',
    'min_rating' => $_GET['min_rating'] ?? ''
];
$sort_order = $_GET['sort'] ?? 'added_desc';

$where_conditions = ["pc.collection_id = ?"];
$params = [$id];
$types = "i";
$filter_query_string = '';

$join_user_tags = 'LEFT JOIN user_tags ut ON ut.poster_id = p.id';
$join_akas = '';

if (!empty($filters['q'])) {
    $keyword = $filters['q'];
    $filter_query_string .= "&q=" . urlencode($keyword);

    $searchFields = [
        "p.title_en", "p.title_he", "p.overview_he", "p.cast", "p.genres", "p.directors", 
        "p.writers", "p.producers", "p.composers", "p.cinematographers", "p.languages", 
        "p.countries", "p.imdb_id", "p.year"
    ];
    $like = "%$keyword%";
    $like_parts = [];
    foreach ($searchFields as $field) {
        $like_parts[] = "$field LIKE ?";
        $params[] = $like;
        $types .= "s";
    }
    $like_parts[] = "ut.genre LIKE ?"; $params[] = $like; $types .= "s";
    $like_parts[] = "pa.aka_title LIKE ?"; $params[] = $like; $types .= "s";
    $like_parts[] = "pa.aka LIKE ?"; $params[] = $like; $types .= "s";

    $where_conditions[] = "(" . implode(" OR ", $like_parts) . ")";
    $join_akas = ' LEFT JOIN poster_akas pa ON pa.poster_id = p.id ';
}

// Extra filters
foreach ($filters as $key => $value) {
    if ($key !== 'q' && $value !== '' && $value !== null) {
        $filter_query_string .= "&$key=" . urlencode($value);
        switch ($key) {
            case 'type_id': $where_conditions[] = "p.type_id = ?"; $params[] = (int)$value; $types .= "i"; break;
            case 'min_year': $where_conditions[] = "p.year >= ?"; $params[] = (int)$value; $types .= "i"; break;
            case 'max_year': $where_conditions[] = "p.year <= ?"; $params[] = (int)$value; $types .= "i"; break;
            case 'min_rating': $where_conditions[] = "p.imdb_rating >= ?"; $params[] = (float)$value; $types .= "d"; break;
        }
    }
}

// Sorting
if ($sort_order !== 'added_desc') { $filter_query_string .= "&sort=" . urlencode($sort_order); }
$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$order_by_clause = 'ORDER BY pc.is_pinned DESC';
switch ($sort_order) {
    case 'added_asc': $order_by_clause .= ", pc.added_at ASC, p.id ASC"; break;
    case 'year_desc': $order_by_clause .= ", p.year DESC, p.id DESC"; break;
    case 'year_asc': $order_by_clause .= ", p.year ASC, p.id ASC"; break;
    case 'rating_desc': $order_by_clause .= ", p.imdb_rating DESC, p.id DESC"; break;
    case 'rating_asc': $order_by_clause .= ", p.imdb_rating ASC, p.id ASC"; break;
    case 'title_he_asc': $order_by_clause .= ", p.title_he ASC, p.id ASC"; break;
    case 'title_he_desc': $order_by_clause .= ", p.title_he DESC, p.id DESC"; break;
    default: $order_by_clause .= ", pc.added_at DESC, p.id DESC"; break;
}

// Count
$count_sql = "SELECT COUNT(DISTINCT p.id) FROM posters p JOIN poster_collections pc ON p.id = pc.poster_id $join_user_tags $join_akas $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_posters = $count_stmt->get_result()->fetch_row()[0] ?? 0;
$count_stmt->close();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$allowed_per_page = [20, 50, 100, 250, 500];
$per_page_request = $_GET['per_page'] ?? 250;
$per_page = 250; // Default
$show_all = false;

if ($per_page_request === 'all') {
    $show_all = true;
    $per_page = $total_posters > 0 ? $total_posters : 1;
} elseif (in_array((int)$per_page_request, $allowed_per_page)) {
    $per_page = (int)$per_page_request;
}

$offset = ($page - 1) * $per_page;
$total_pages = $show_all ? 1 : ceil($total_posters / $per_page);
if ($per_page_request != 250) { $filter_query_string .= "&per_page=" . urlencode($per_page_request); }


// Fetch posters
$sql_limit_clause = "LIMIT ? OFFSET ?";
if ($show_all) {
    $sql_limit_clause = ""; // No limit if showing all
}

$posters_sql = "SELECT p.*, pc.is_pinned, pc.added_at, GROUP_CONCAT(DISTINCT ut.genre SEPARATOR ', ') AS user_tags_list
                  FROM posters p
                  JOIN poster_collections pc ON p.id = pc.poster_id
                  $join_user_tags
                  $join_akas
                  $where_clause
                  GROUP BY p.id
                  $order_by_clause
                  $sql_limit_clause";

$params_with_limit = $params;
$types_with_limit = $types;

if (!$show_all) {
    $params_with_limit[] = $per_page; $types_with_limit .= "i";
    $params_with_limit[] = $offset;   $types_with_limit .= "i";
}

$stmt = $conn->prepare($posters_sql);
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$res = $stmt->get_result();
$poster_list = [];
while ($row = $res->fetch_assoc()) $poster_list[] = $row;
$stmt->close();

// Full list (sidebar)
$all_posters_for_list = [];
$sql_all = "SELECT p.* FROM poster_collections pc JOIN posters p ON p.id = pc.poster_id WHERE pc.collection_id = ? ORDER BY pc.added_at ASC";
$stmt_all = $conn->prepare($sql_all);
$stmt_all->bind_param("i", $id);
$stmt_all->execute();
$res_all = $stmt_all->get_result();
while ($row = $res_all->fetch_assoc()) $all_posters_for_list[] = $row;
$stmt_all->close();

// Helper to generate search links to HOME.PHP for hover card
function generate_home_search_link($param, $value) {
    return 'home.php?' . http_build_query([$param => $value]);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <link rel="stylesheet" href="bbcode.css">
  <meta charset="UTF-8">
  <title>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></title>
  <style>
    body { font-family:Arial, sans-serif; background:#f9f9f9; padding:10px; direction:rtl; }
    .container { max-width:1300px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    .header-img { max-width:100%; border-radius:1px; margin-top:10px; max-width:420px;width:auto; height:auto;}
    .description { margin-top:10px; color:#444; }
    
    .poster-section { display:flex; flex-direction:row-reverse; gap:14px; margin-top:20px; align-items:flex-start; flex-wrap:nowrap !important; }
    .poster-grid { flex:1 1 0; min-width:0; display:flex; flex-wrap:wrap; }
    .poster-grid.small { gap:2px; }
    .poster-grid.medium { gap:8px; }
    .poster-grid.large { gap:12px; }
    
    .poster-item { text-align:center; position:relative; padding-bottom: 5px; }
    .poster-item.pinned { background-color: #fffff0; border-radius: 4px; border: 1px solid #ffd700; }
    .poster-item.small { width:100px; margin-bottom:0 !important; }
    .poster-item.medium { width:160px; }
    .poster-item.large { width:220px; }
    .poster-item img { width:100%; aspect-ratio:2/3; object-fit:cover; border-radius:1px; margin:0; box-shadow:0 0 4px rgba(0,0,0,0.1); }
    .poster-item.small small, .poster-item.small .title-he { font-size:10px; line-height:1; margin-top:1px !important; margin-bottom:0 !important; }
    .poster-item .year { font-size:11px; color:#444; margin-top:2px; }
    .title-he { font-size:12px; color:#555; }
    .imdb-rating a {  color:#d18b00; font-size: 12px; text-decoration: none; }
    .remove-btn { background:#e0ffad; color:black; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin-top:6px; }
    .pin-btn { text-decoration:none; font-size:12px; background:#f0f0f0; padding:2px 6px; border-radius:4px; color:#333; margin-top:4px; display:inline-block; }
    .delete-box { display:none; }
    .pin-box { display:none; }
    .poster-list-sidebar h4 { margin-top:0; font-size:16px; }
    .poster-list-sidebar input { width:100%; padding:8px 14px; border:1px solid #bbb; border-radius:8px; margin-bottom:10px; background:#fafcff; font-size:15px; box-shadow:0 1px 6px #0001; outline:none; transition:.17s; direction: rtl; }
    .poster-list-sidebar ol { list-style-type:decimal !important; direction: rtl; padding-right: 8px; margin: 0; list-style-position: inside !important; }
    .poster-list-sidebar li { margin-bottom: 8px; line-height: 1.3; text-align: right; }
    
    .form-box textarea { width: 100%; font-size: 15px; padding: 8px; border-radius: 7px; border: 1px solid #bbb; background: #fafcff; margin-bottom: 10px; resize: vertical; min-height: 65px; }
    .form-box button { width: 100%; font-size: 16px; padding: 8px 0; border-radius: 7px; border: none; background: #007bff; color: #fff; margin-top: 6px; cursor: pointer; transition: background 0.2s; }
    .main-search-box { background: #fff; border: 1.5px solid #bbb; border-radius: 11px; padding: 10px 18px; width: 270px; font-size: 16px; box-shadow: 0 2px 10px #e1e6eb50; outline: none; transition: border .2s, box-shadow .2s; margin-left: 0; margin-right: 0; color: #1d1d1d; }
    .main-search-btn { padding: 8px 20px; border-radius: 7px; border: none; background: #1576cc; color: #fff; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center;}
    .sidebar-wrapper { flex:none !important; width:305px; min-width:250px; max-width:320px; display: flex; flex-direction: column; gap: 20px; alignself: flex-start; }
    .filter-panel { background: #fdfdfd; border: 1px solid #eee; padding: 15px; border-radius: 6px; box-shadow: 0 0 4px rgba(0,0,0,0.05); }
    .filter-panel h4 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
    .filter-group { margin-bottom: 15px; }
    .filter-group label { display: block; margin-bottom: 5px; color: #333; font-weight: bold; font-size: 14px; }
    .filter-group input, .filter-group select { width: 100%; padding: 8px; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .filter-submit-btn { width: 100%; padding: 10px; font-size: 1.1em; font-weight: bold; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; }
    .filter-submit-btn:hover { background-color: #0056b3; }
    .poster-list-sidebar { background:#f8f8f8; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.05); font-size:14px; color:#333; height:fit-content; text-align:right; padding:12px 0px; }
    .poster-list-sidebar.hide-list { display:none !important; }
    @media (max-width:1000px) { .container { padding: 8px 2px; } .poster-section { flex-direction:column; gap:0; } .sidebar-wrapper { width:100%; max-width:100%; margin-bottom:16px; } }
    .poster-list-sidebar .year, .poster-list-sidebar .imdb-id { font-size: 10px; color: #555; }
    .controls-bar { display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap: 20px; margin-bottom:15px; padding: 0 5px; }
    .sort-box { display:flex; align-items:center; gap:8px; }
    .sort-box label { font-weight:bold; font-size:15px; }
    .sort-box select { padding: 5px 8px; border-radius: 5px; border: 1px solid #ccc; font-size:15px; }

    /* --- Controls Styling --- */
    .controls-wrapper { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 20px 0; display: flex; flex-direction: column; gap: 15px; }
    .action-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .action-bar strong, .action-bar span { font-size: 14px; color: #495057; margin-left: 5px; }
    .action-bar form { margin: 0; }
    .action-bar .action-btn, .action-bar button { text-decoration: none; padding: 6px 12px; border-radius: 5px; border: 1px solid #ced4da; background-color: deepskyblue; color: white; border-color: #00BFFF; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; white-space: nowrap; font-family: inherit; }
    .action-bar .action-btn:hover, .action-bar button:hover { background-color: #00a8e6; border-color: #00a8e6; }
    .action-bar .action-btn.active, .action-bar button.active { background-color: #007bff; color: white; border-color: #007bff; z-index: 2; }
    
    .action-bar .btn-group { display: inline-flex; }
    .btn-group .action-btn, .btn-group button { border-radius: 0; margin-left: -1px; }
    .btn-group .action-btn:first-child, .btn-group button:first-child { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
    .btn-group .action-btn:last-child, .btn-group button:last-child { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
    
    #togglePinBtn.active,
    #toggleDeleteBtn.active {
        background-color: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }
    
    /* Split description */
    .desc-shared-wrap { text-align: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
    .desc-shared-wrap img { max-width: 100%; height: auto; border-radius: 4px; }
    .desc2-wrap{width:100%; text-align:center; margin:14px 0;}
    .desc2-table{display:inline-table; border-collapse:separate; border-spacing:24px 0; width:auto; max-width:1100px;}
    .desc2-td{vertical-align:top; padding:0 20px; max-width:520px;}
    .desc2-td:first-child { border-right: 2px solid black; }
    .desc2-col{ text-align:justify; text-justify:inter-word; unicode-bidi:plaintext; line-height:1.45; font-size:15px; }
    .desc2-col.en{ direction:ltr; text-align-last:left; }
    .desc2-col.he{ direction:rtl; text-align-last:right; }
    @media (max-width: 860px){ .desc2-table{ display:block; max-width:95%; margin:0 auto; border-spacing:0; } .desc2-td{ display:block; width:auto; max-width:none; margin:0 0 12px 0; } }

    /* Modal (batch add) */
    .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
    .modal-content { background-color: #fefefe; padding: 20px; border: 1px solid #888; border-radius: 8px; width: 90%; max-width: 500px; position: relative; }
    .modal-close-btn { color: #aaa; float: left; font-size: 28px; font-weight: bold; position: absolute; top: 5px; left: 15px; cursor: pointer; }
    .modal-close-btn:hover, .modal-close-btn:focus { color: black; }

    /* --- Hover Card Styling --- */
    .hover-card {
      position: absolute;
      top: 0;
      left: 105%; /* Default, JS manages direction */
      width: 420px;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 20;
      padding: 12px;
      text-align: right;
      direction: rtl;
      font-size: 14px;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out;
    }
    .hover-card a { color: #1576cc; text-decoration: none; }
    .hover-card a:hover { text-decoration: underline; }
    .hover-card h4 { margin: 0 0 8px 0; font-size: 1.2em; color: #111; }
    .hover-card .meta-item { margin-bottom: 6px; }
    .hover-card .meta-item strong { color: #555; }
    .hover-card .overview { font-size: 0.9em; line-height: 1.4; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
    .hover-card .trailer-wrap { margin-top: 10px; }

    /* Trailer Placeholder */
    .trailer-placeholder { position: relative; cursor: pointer; background-color: #000; border-radius: 4px; overflow: hidden; }
    .trailer-placeholder img { width: 100%; aspect-ratio: 16/9; border: 0; display: block; opacity: 0.8; transition: opacity 0.2s; }
    .trailer-placeholder:hover img { opacity: 1; }
    .trailer-placeholder .play-button { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 68px; height: 48px; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 48"><path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="%23f00"></path><path d="M 45,24 27,14 27,34" fill="%23fff"></path></svg>'); background-repeat: no-repeat; background-position: center; background-size: contain; border: none; transition: transform 0.2s; pointer-events: none; }
    .trailer-placeholder:hover .play-button { transform: translate(-50%, -50%) scale(1.1); }
    .trailer-wrap iframe { width: 100%; aspect-ratio: 16/9; border: 0; }
    
    /* --- Pagination Styling --- */
    .pagination {
        text-align: center;
        margin-top: 25px;
        padding: 10px 0;
        direction: rtl;
    }
    .pagination a, .pagination span.current {
        margin: 0 4px;
        padding: 8px 14px;
        border: 1px solid #ddd;
        background: #fdfdfd;
        color: #007bff;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
        transition: background-color 0.2s, border-color 0.2s;
    }
    .pagination a:hover {
        background: #f0f0f0;
        border-color: #ccc;
    }
    .pagination span.current {
        background: #007bff;
        color: white;
        border-color: #007bff;
        cursor: default;
    }

  </style>
</head>
<body><br>
<?php if (isset($_GET['csv_success'])): ?>
  <div id="csv-success-message" style="background:#f6fff6; border:1px solid #cde8cd; padding:12px; border-radius:8px; margin-bottom:15px; text-align:center;">
    ✅ הועלה ועובד בהצלחה.<br>
    נוספו לאוסף: <strong><?= (int)($_GET['inserted'] ?? 0) ?></strong>, כבר היו: <strong><?= (int)($_GET['already'] ?? 0) ?></strong>.
  </div>
<?php endif; ?>
<div class="container">
  <h2>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></h2>
  <?php if (!empty($collection['image_url'])): ?>
    <img src="<?= htmlspecialchars($collection['image_url']) ?>" alt="תמונה" class="header-img">
  <?php endif; ?>

  <?php if (!empty($collection['description'])): ?>
    <?php
      $raw = (string)($collection['description'] ?? '');
      $shared_raw = ''; $right_he_raw = ''; $left_en_raw  = '';
      
      $tags_detected = (strpos($raw, '[משותף]') !== false || strpos($raw, '[עברית]') !== false || strpos($raw, '[אנגלית]') !== false);
      
      if ($tags_detected) {
        if (preg_match('~\[משותף\](.*?)\[/משותף\]~is', $raw, $mShared)) { $shared_raw = trim($mShared[1]); }
        if (preg_match('~\[עברית\](.*?)\[/עברית\]~is', $raw, $mHe)) { $right_he_raw = trim($mHe[1]); }
        if (preg_match('~\[אנגלית\](.*?)\[/אנגלית\]~is', $raw, $mEn)) { $left_en_raw = trim($mEn[1]); }
      } else {
        $rawN = str_replace("\r\n", "\n", trim($raw));
        $rawN = preg_replace("/\n{4,}/", "\n\n\n", $rawN);
        $parts = preg_split("/\n{3,}/", $rawN, 2);
        $p0 = trim($parts[0] ?? '');
        $p1 = trim($parts[1] ?? '');
        $hasHeb = static function(string $t): bool { return (bool)preg_match('/\p{Hebrew}/u', $t); };

        if (count($parts) >= 2) {
          $right_he_raw = $p0;
          $left_en_raw  = $p1;
        } else {
          if ($p0 !== '') {
            if ($hasHeb($p0)) {
              $right_he_raw = $p0;
            } else {
              $left_en_raw = $p0;
            }
          }
        }
      }

      $desc_shared_html = trim(bbcode_to_html($shared_raw));
      $desc_he_html = trim(bbcode_to_html($right_he_raw));
      $desc_en_html = trim(bbcode_to_html($left_en_raw));
      
      $has_shared = !empty($desc_shared_html);
      $has_he = !empty($desc_he_html);
      $has_en = !empty($desc_en_html);
    ?>

    <?php if ($has_shared): ?>
      <div class="desc-shared-wrap bbcode">
        <?= $desc_shared_html ?>
      </div>
    <?php endif; ?>

    <?php if ($has_he || $has_en): ?>
      <div class="desc2-wrap">
          <table class="desc2-table" role="presentation" dir="ltr">
            <tr>
              <td class="desc2-td"><div class="desc2-col en bbcode"><?= $desc_en_html ?></div></td>
              <td class="desc2-td"><div class="desc2-col he bbcode"><?= $desc_he_html ?></div></td>
            </tr>
          </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>


  <div id="csvUploadResult" style="max-width:900px;margin:12px auto 0;display:none;background:#f6fff6;border:1px solid #cde8cd;padding:12px;border-radius:8px;"></div>

  <div class="controls-wrapper">
    <div class="action-bar">
        <strong>ניהול:</strong>
        <form id="csvUploadForm" action="collection_upload_csv_api.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="collection_id" value="<?= $id ?>">
            <input type="file" name="ids_file" id="ids_file" accept=".csv,.txt,text/csv,text/plain" style="display:none" onchange="uploadCsvToCollection(this.form)" multiple>
            <button type="button" class="action-btn" onclick="document.getElementById('ids_file').click()" style="background-color: #6f42c1; color: #fff; border-color: #6f42c1;">⬆️ העלאת CSV/TXT</button>
        </form>
        <button type="button" onclick="openBatchAddModal()" class="action-btn" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">➕ הוספה מרובה</button>
        <a href="edit_collection.php?id=<?= $collection['id'] ?>" class="action-btn">✏️ ערוך</a>
        
        <?php if (!empty($collection['is_pinned'])): ?>
            <a href="collection.php?unpin_collection=<?= $collection['id'] ?>&<?= $redirect_query_string ?>" class="action-btn" style="background:#fff3cd; color:#856404; border-color: #ffeeba;">📌 הסר נעיצת אוסף</a>
        <?php else: ?>
            <a href="collection.php?pin_collection=<?= $collection['id'] ?>&<?= $redirect_query_string ?>" class="action-btn" style="background:#d1e7dd; color:#0f5132; border-color: #badbcc;">📌 נעיצת אוסף</a>
        <?php endif; ?>

        <a href="manage_collections.php?delete=<?= $collection['id'] ?>" onclick="return confirm('למחוק את האוסף?')" class="action-btn" style="background:#f8d7da;color:#721c24; border-color: #f5c6cb;">🗑️ מחק אוסף</a>
        <a href="universe.php?collection_id=<?= $collection['id'] ?>" class="action-btn" style="background:#d1ecf1;color:#0c5460; border-color: #bee5eb;">🌌 הצג בציר זמן</a>
        <a href="#" onclick="deleteAllContent(<?= $id ?>)" class="action-btn" style="background:#f9e7cf; color: #856404; border-color: #f7ddb2;">מחק את כל התוכן</a>
        <a href="collections.php" class="action-btn" style="margin-right: auto;">⬅ חזרה לרשימת האוספים</a>
    </div>
    <div class="action-bar">
        <strong>תצוגה:</strong>
        <button type="button" id="toggleListBtn" onclick="toggleNameList()" class="action-btn" style="background: #e7f5ff; color: #1971c2; border-color: #a5d8ff;">📄 הצג רשימה שמית</button>
        <a href="collection_csv.php?id=<?= $id ?>" target="_blank" class="action-btn" style="background:#2a964a; color:#fff; border-color: #2a964a;">⬇️ ייצא כ־CSV</a>
        <a href="#" id="togglePinBtn" onclick="togglePin()" class="action-btn">📌 הצג נעיצה</a>
        <a href="#" id="toggleDeleteBtn" onclick="toggleDelete()" class="action-btn">🧹 הצג מחיקה</a>
        
        <div style="margin-right: auto;"></div>

        <span>גודל פוסטרים:</span>
        <div class="btn-group">
            <button onclick="setSize('small')" class="action-btn" id="size-small">קטן</button>
            <button onclick="setSize('medium')" class="action-btn" id="size-medium">בינוני</button>
            <button onclick="setSize('large')" class="action-btn" id="size-large">גדול</button>
        </div>
        
        <span>בחר כמות:</span>
        <div class="btn-group">
            <?php
            $per_page_params = $_GET;
            unset($per_page_params['page']);
            $per_page_options = [20, 50, 100, 250, 500, 'all'];
            foreach ($per_page_options as $option) {
                $per_page_params['per_page'] = $option;
                $current_per_page_val = $show_all ? 'all' : $per_page;
                $active_class = ($current_per_page_val == $option) ? 'active' : '';
                $label = ($option === 'all') ? 'הכל' : $option;
                echo '<a href="collection.php?' . http_build_query($per_page_params) . '" class="action-btn ' . $active_class . '">' . $label . '</a>';
            }
            ?>
        </div>
    </div>
</div>
  <form method="get" action="collection.php" style="margin:12px 0 15px 0; display:flex; gap:10px; align-items:center; justify-content:center;">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" class="main-search-box" placeholder="🔎 חפש שם, שנה, במאי... בתוך האוסף">
    <button type="submit" class="main-search-btn">חפש באוסף</button>
    <?php
        $is_filtered = !empty($filters['q']) || !empty($filters['type_id']) || !empty($filters['min_year']) || !empty($filters['max_year']) || !empty($filters['min_rating']);
        if ($is_filtered):
    ?>
        <a href="collection.php?id=<?= $id ?>&sort=<?= urlencode($sort_order) ?>" class="main-search-btn" style="background-color: #6c757d;">איפוס</a>
    <?php endif; ?>
  </form>

  <div class="controls-bar">
    <h3>🎬 פוסטרים באוסף: (<?= $total_posters ?> תוצאות)</h3>
    <div class="sort-box">
      <form method="get" id="sortForm">
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php foreach ($filters as $key => $value): ?>
          <?php if ($value !== '' && $value !== null): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if (isset($_GET['per_page'])): ?>
            <input type="hidden" name="per_page" value="<?= htmlspecialchars($_GET['per_page']) ?>">
        <?php endif; ?>
        <label for="sort">מיין לפי:</label>
        <select name="sort" id="sort" onchange="document.getElementById('sortForm').submit()">
          <option value="added_desc" <?= ($sort_order == 'added_desc') ? 'selected' : '' ?>>האחרון שהתווסף</option>
          <option value="added_asc"  <?= ($sort_order == 'added_asc') ? 'selected' : '' ?>>הראשון שהתווסף</option>
          <option value="year_desc"  <?= ($sort_order == 'year_desc') ? 'selected' : '' ?>>שנה (מהחדש לישן)</option>
          <option value="year_asc"   <?= ($sort_order == 'year_asc') ? 'selected' : '' ?>>שנה (מהישן לחדש)</option>
          <option value="rating_desc" <?= ($sort_order == 'rating_desc') ? 'selected' : '' ?>>דירוג (מהגבוה לנמוך)</option>
          <option value="rating_asc"  <?= ($sort_order == 'rating_asc') ? 'selected' : '' ?>>דירוג (מהנמוך לגבוה)</option>
          <option value="title_he_asc" <?= ($sort_order == 'title_he_asc') ? 'selected' : '' ?>>שם עברי (א-ת)</option>
          <option value="title_he_desc"  <?= ($sort_order == 'title_he_desc') ? 'selected' : '' ?>>שם עברי (ת-א)</option>
        </select>
      </form>
    </div>
  </div>

  <div class="poster-section">
    <div class="poster-grid medium">
      <?php if ($poster_list): ?>
        <?php $base_link_params = http_build_query(array_merge($_GET, ['id' => $id])); ?>
        <?php foreach ($poster_list as $p): ?>
          <?php $img = trim($p['image_url'] ?? ''); if ($img === '') $img = 'images/no-poster.png'; ?>
          <div class="poster-item medium <?= !empty($p['is_pinned']) ? 'pinned' : '' ?>">
            <a href="poster.php?id=<?= $p['id'] ?>">
              <img src="<?= htmlspecialchars($img) ?>" alt="Poster">
              <?php if (!empty($p['title_en'])): ?><small><?= htmlspecialchars($p['title_en']) ?></small><?php endif; ?>
              <?php if (!empty($p['title_he'])): ?><div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div><?php endif; ?>
              <?php if (!empty($p['year'])): ?> <div class="year"><?= htmlspecialchars($p['year']) ?></div> <?php endif; ?>

              <?php if (!empty($p['imdb_rating']) && !empty($p['imdb_id'])): ?>
                <div class="imdb-rating">
                  <a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank" rel="noopener">⭐ <?= htmlspecialchars($p['imdb_rating']) ?>/10</a>
                </div>
              <?php endif; ?>
            </a>

            <div class="hover-card">
              <h4>
                <a href="poster.php?id=<?= $p['id'] ?>">
                  <?= htmlspecialchars($p['title_he'] ?: $p['title_en']) ?>
                  <?php if (!empty($p['title_he']) && !empty($p['title_en'])): ?> | <?= htmlspecialchars($p['title_en']) ?><?php endif; ?>
                </a>
              </h4>

              <?php if (!empty($p['year'])): ?>
                <div class="meta-item"><strong>שנה:</strong>
                  <a href="<?= generate_home_search_link('year', $p['year']) ?>"><?= htmlspecialchars($p['year']) ?></a>
                </div>
              <?php endif; ?>

              <?php if (!empty($p['genres'])): ?>
                <div class="meta-item"><strong>ז'אנרים:</strong>
                  <?php $genresArr = array_values(array_filter(array_map('trim', explode(',', $p['genres'])))); ?>
                  <?php foreach ($genresArr as $idx => $g): ?>
                    <a href="<?= generate_home_search_link('genre', $g) ?>"><?= htmlspecialchars($g) ?></a><?php if ($idx < count($genresArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if(!empty($p['user_tags_list'])): ?>
                <div class="meta-item"><strong>תגיות:</strong>
                  <?php $tagsArr = array_values(array_filter(array_map('trim', explode(',', $p['user_tags_list'])))); ?>
                  <?php foreach ($tagsArr as $idx => $tag): ?>
                    <a href="<?= generate_home_search_link('user_tag', $tag) ?>"><?= htmlspecialchars($tag) ?></a><?php if ($idx < count($tagsArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($p['countries'])): ?>
                <div class="meta-item"><strong>מדינות:</strong>
                  <?php $countriesArr = array_values(array_filter(array_map('trim', explode(',', $p['countries'])))); ?>
                  <?php foreach ($countriesArr as $idx => $c): ?>
                    <a href="<?= generate_home_search_link('country', $c) ?>"><?= htmlspecialchars($c) ?></a><?php if ($idx < count($countriesArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($p['languages'])): ?>
                <div class="meta-item"><strong>שפות:</strong>
                  <?php $langsArr = array_values(array_filter(array_map('trim', explode(',', $p['languages'])))); ?>
                  <?php foreach ($langsArr as $idx => $l): ?>
                    <a href="<?= generate_home_search_link('lang_code', $l) ?>"><?= htmlspecialchars($l) ?></a><?php if ($idx < count($langsArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if(!empty($p['overview_he'])): ?>
                <div class="overview"><?= nl2br(htmlspecialchars(mb_substr($p['overview_he'], 0, 500) . (mb_strlen($p['overview_he']) > 500 ? '...' : ''))) ?></div>
              <?php endif; ?>

              <div class="trailer-wrap">
                <?php
                  if (!empty($p['trailer_url']) && (strpos($p['trailer_url'], 'youtube.com') !== false || strpos($p['trailer_url'], 'youtu.be') !== false)) {
                    if (preg_match('~(?:v=|youtu\.be/|/embed/)([A-Za-z0-9_-]{11})~', $p['trailer_url'], $m)) {
                      $youtube_id = $m[1];
                      $thumbnail_url = 'https://i.ytimg.com/vi/' . htmlspecialchars($youtube_id) . '/hqdefault.jpg';
                      echo '<div class="trailer-placeholder" data-youtube-id="' . htmlspecialchars($youtube_id) . '">';
                      echo '<img loading="lazy" src="' . $thumbnail_url . '" alt="Trailer Thumbnail">';
                      echo '<div class="play-button"></div>';
                      echo '</div>';
                    }
                  }
                ?>
              </div>
            </div>

            <div class="pin-box">
              <?php if (!empty($p['is_pinned'])): ?>
                <a href="collection.php?unpin_poster=<?= $p['id'] ?>&<?= $base_link_params ?>" class="pin-btn">📌 הסר נעיצה</a>
              <?php else: ?>
                <a href="collection.php?pin_poster=<?= $p['id'] ?>&<?= $base_link_params ?>" class="pin-btn">📌 נעיצה</a>
              <?php endif; ?>
            </div>
            <div class="delete-box">
              <form method="post" action="remove_from_collection.php">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                <button type="submit" class="remove-btn">🗑️ מחק</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>לא נמצאו פוסטרים באוסף זה התואמים לסינון.</p>
      <?php endif; ?>
    </div>

    <aside class="sidebar-wrapper">
      <div class="filter-panel">
        <h4>✔️ סנן פוסטרים</h4>
        <form method="get">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="q" value="<?= htmlspecialchars($filters['q']) ?>">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
          <div class="filter-group">
            <label for="type_id">סוג</label>
            <select name="type_id" id="type_id" onchange="this.form.submit()">
              <option value="">הכל</option>
              <?php foreach ($types_list as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($filters['type_id'] == $t['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['label_he']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label for="min_year">משנה</label>
            <input type="number" name="min_year" id="min_year" placeholder="1980" value="<?= htmlspecialchars($filters['min_year']) ?>">
          </div>
          <div class="filter-group">
            <label for="max_year">עד שנה</label>
            <input type="number" name="max_year" id="max_year" placeholder="2025" value="<?= htmlspecialchars($filters['max_year']) ?>">
          </div>
          <div class="filter-group">
            <label for="min_rating">דירוג IMDb מינימלי</label>
            <input type="number" name="min_rating" id="min_rating" step="0.1" min="1" max="10" placeholder="7.5" value="<?= htmlspecialchars($filters['min_rating']) ?>">
          </div>
          <button type="submit" class="filter-submit-btn">סנן</button>
          <?php if (!empty(array_filter(array_slice($filters, 1)))): ?>
            <a href="collection.php?id=<?= $id ?>&q=<?= urlencode($filters['q']) ?>&sort=<?= urlencode($sort_order) ?>" style="display: block; text-align: center; margin-top: 10px;">נקה סינון</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="poster-list-sidebar" id="name-list-sidebar">
        <h4>📃 רשימה שמית</h4>
        <div style="position:relative;">
          <input type="text" id="poster-search" placeholder="חפש פוסטר...">
        </div>
        <ol id="poster-list">
          <?php foreach ($all_posters_for_list as $i => $p2): ?>
            <li>
              <a href="poster.php?id=<?= $p2['id'] ?>" style="color:#1576cc;">
                <?= htmlspecialchars($p2['title_en'] ?: $p2['title_he']) ?>
              </a>
              <?php if (!empty($p2['title_he']) && !empty($p2['title_en'])): ?>
                <div class="title-he"><?= htmlspecialchars($p2['title_he']) ?> | <?= htmlspecialchars($p2['title_en']) ?></div>
              <?php elseif (!empty($p2['title_he'])): ?>
                <div class="title-he"><?= htmlspecialchars($p2['title_he']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p2['year'])): ?>
                <div class="year">שנה: <a href="<?= generate_home_search_link('year', $p2['year']) ?>"><?= htmlspecialchars($p2['year']) ?></a></div>
              <?php endif; ?>
              <?php if (!empty($p2['imdb_id'])): ?>
                <div class="imdb-id">
                  <a href="https://www.imdb.com/title/<?= htmlspecialchars($p2['imdb_id']) ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($p2['imdb_id']) ?>
                  </a>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </aside>
  </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $range = 5;
        $base_url = "collection.php?id=$id" . $filter_query_string;

        // "First" page link
        if ($page > 1) {
            echo "<a href='{$base_url}&page=1'>ראשון</a>";
        }

        // "Previous" page link
        if ($page > 1) {
            $prev_page = $page - 1;
            echo "<a href='{$base_url}&page={$prev_page}'>הקודם</a>";
        }

        // Numeric page links
        $start_range = max(1, $page - $range);
        $end_range = min($total_pages, $page + $range);

        if ($start_range > 1) {
            echo "<span>...</span>";
        }

        for ($i = $start_range; $i <= $end_range; $i++) {
            if ($i == $page) {
                echo "<span class='current'>$i</span>";
            } else {
                echo "<a href='{$base_url}&page={$i}'>$i</a>";
            }
        }
        
        if ($end_range < $total_pages) {
            echo "<span>...</span>";
        }

        // "Next" page link
        if ($page < $total_pages) {
            $next_page = $page + 1;
            echo "<a href='{$base_url}&page={$next_page}'>הבא</a>";
        }

        // "Last" page link
        if ($page < $total_pages) {
            echo "<a href='{$base_url}&page={$total_pages}'>אחרון</a>";
        }
        ?>
    </div>
    <?php endif; ?>
    <div class="form-box">
    <h3>➕ הוספת פוסטרים לפי מזהים</h3>
    <form method="post" action="add_to_collection_batch.php" class="batch-add-form">
      <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
      <label>🔗 מזהים (ID רגיל או IMDb: tt...)</label>
      <textarea name="poster_ids_raw" rows="6" placeholder="לדוגמה:
45
tt1375666
89"></textarea>
      <button type="submit">📥 קשר פוסטרים</button>
    </form>
  </div>
</div>

<div id="batchAddModal" class="modal-overlay" onclick="closeBatchAddModal(event)">
  <div class="modal-content">
    <span class="modal-close-btn" onclick="closeBatchAddModal({target: this, currentTarget: this})">&times;</span>
    <div class="form-box">
      <h3>➕ הוספת פוסטרים לפי מזהים</h3>
      <form method="post" action="add_to_collection_batch.php" class="batch-add-form">
        <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
        <label>🔗 מזהים (ID רגיל או IMDb: tt...)</label>
        <textarea name="poster_ids_raw" rows="6" placeholder="לדוגמה:
45
tt1375666
89"></textarea>
        <button type="submit">📥 קשר פוסטרים</button>
      </form>
    </div>
  </div>
</div>
<script>
  const batchAddModal = document.getElementById('batchAddModal');
  function openBatchAddModal() { batchAddModal.style.display = 'flex'; }
  function closeBatchAddModal(event) { if (event.target === event.currentTarget) { batchAddModal.style.display = 'none'; } }
  
  function deleteAllContent(collectionId) {
    if (confirm('האם אתה בטוח שברצונך למחוק את כל הפוסטרים מאוסף זה? הפעולה אינה הפיכה.')) {
      window.location.href = 'collection.php?id=' + collectionId + '&delete_all_content=1';
    }
  }

  function toggleState(key, selector, buttonId, showText, hideText) {
      const elements = document.querySelectorAll(selector);
      const button = document.getElementById(buttonId);
      let isVisible = false;

      elements.forEach(el => {
          const willBeVisible = (el.style.display === 'none' || el.style.display === '');
          el.style.display = willBeVisible ? 'block' : 'none';
          if (willBeVisible) isVisible = true;
      });

      localStorage.setItem(key, isVisible);

      if (button) {
          if (isVisible) {
              button.classList.add('active');
              button.innerHTML = hideText;
          } else {
              button.classList.remove('active');
              button.innerHTML = showText;
          }
      }
  }

  function toggleDelete() { toggleState('isDeleteVisible', '.delete-box', 'toggleDeleteBtn', '🧹 הצג מחיקה', '🧹 הסתר מחיקה'); }
  function togglePin() { toggleState('isPinVisible', '.pin-box', 'togglePinBtn', '📌 הצג נעיצה', '📌 הסתר נעיצה'); }

  function setSize(size) {
    localStorage.setItem('posterSize', size);
    document.querySelectorAll('.poster-item, .poster-grid').forEach(item => {
      item.classList.remove('small', 'medium', 'large');
      item.classList.add(size);
    });
    document.querySelectorAll('#size-small, #size-medium, #size-large').forEach(btn => btn.classList.remove('active'));
    const btn = document.getElementById('size-'+size);
    if (btn) btn.classList.add('active');
  }

  // START: MODIFIED FUNCTION
  function toggleNameList() {
    const sidebar = document.getElementById('name-list-sidebar');
    const button = document.getElementById('toggleListBtn');
    if (sidebar && button) {
        sidebar.classList.toggle('hide-list');
        const isHidden = sidebar.classList.contains('hide-list');
        button.innerHTML = isHidden ? '📄 הצג רשימה שמית' : '📄 הסתר רשימה שמית';
        localStorage.setItem('isNameListHidden', isHidden); // Save state
    }
  }
  // END: MODIFIED FUNCTION


  window.addEventListener('DOMContentLoaded', () => {
    setSize(localStorage.getItem('posterSize') || 'medium');
    
    // Initialize toggle buttons state on page load
    const deleteBtn = document.getElementById('toggleDeleteBtn');
    if (localStorage.getItem('isDeleteVisible') === 'true') {
        document.querySelectorAll('.delete-box').forEach(el => el.style.display = 'block');
        if (deleteBtn) {
            deleteBtn.classList.add('active');
            deleteBtn.innerHTML = '🧹 הסתר מחיקה';
        }
    } else if (deleteBtn) {
        deleteBtn.innerHTML = '🧹 הצג מחיקה';
    }

    const pinBtn = document.getElementById('togglePinBtn');
    if (localStorage.getItem('isPinVisible') === 'true') {
        document.querySelectorAll('.pin-box').forEach(el => el.style.display = 'block');
        if (pinBtn) {
            pinBtn.classList.add('active');
            pinBtn.innerHTML = '📌 הסתר נעיצה';
        }
    } else if (pinBtn) {
        pinBtn.innerHTML = '📌 הצג נעיצה';
    }
    
    // START: ADDED LOGIC FOR NAME LIST MEMORY
    const nameListSidebar = document.getElementById('name-list-sidebar');
    const toggleListBtn = document.getElementById('toggleListBtn');
    if (localStorage.getItem('isNameListHidden') === 'true') {
        if(nameListSidebar) nameListSidebar.classList.add('hide-list');
        if(toggleListBtn) toggleListBtn.innerHTML = '📄 הצג רשימה שמית';
    } else {
        if(nameListSidebar) nameListSidebar.classList.remove('hide-list');
        if(toggleListBtn) toggleListBtn.innerHTML = '📄 הסתר רשימה שמית';
    }
    // END: ADDED LOGIC

    document.querySelectorAll('.batch-add-form').forEach(form => {
      form.addEventListener('submit', function(event) {
        const textarea = this.querySelector('textarea[name="poster_ids_raw"]');
        if (textarea) {
          let content = textarea.value;
          const cleanedContent = content.replace(/https?:\/\/[^\s/]+\/.*?poster\.php\?id=(\d+)/g, '$1');
          textarea.value = cleanedContent;
        }
      });
    });

    // --- Hover Card (Side Popup with Smart Placement) ---
    const HIDE_DELAY = 100; 
    document.querySelectorAll('.poster-item').forEach(item => {
        const card = item.querySelector('.hover-card');
        if (!card) return;

        let hideTimeout;

        const showCard = () => {
            clearTimeout(hideTimeout);
            
            const rect = item.getBoundingClientRect();
            
            card.style.left = 'auto';
            card.style.right = '105%';
            if (rect.left < card.offsetWidth + 20) { // Add some buffer
                card.style.right = 'auto';
                card.style.left = '105%';
            }
            
            card.style.opacity = '1';
            card.style.visibility = 'visible';
            card.style.pointerEvents = 'auto';
        };

        const hideCard = () => {
            hideTimeout = setTimeout(() => {
                card.style.opacity = '0';
                card.style.visibility = 'hidden';
                card.style.pointerEvents = 'none';
            }, HIDE_DELAY);
        };

        item.addEventListener('mouseenter', showCard);
        item.addEventListener('mouseleave', hideCard);
        card.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
        card.addEventListener('mouseleave', hideCard);
    });

    // --- Lazy Load YouTube Trailers ---
    document.body.addEventListener('click', function(event) {
        const placeholder = event.target.closest('.trailer-placeholder');
        if (!placeholder) return;
        const youtubeId = placeholder.dataset.youtubeId;
        if (!youtubeId) return;
        const iframe = document.createElement('iframe');
        iframe.setAttribute('src', `https://www.youtube.com/embed/${youtubeId}?autoplay=1&rel=0`);
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('title', 'Trailer');
        const wrapper = placeholder.parentElement;
        if (wrapper && wrapper.classList.contains('trailer-wrap')) {
             wrapper.innerHTML = ''; 
             wrapper.appendChild(iframe);
        }
    });
  });

  const searchInput = document.getElementById('poster-search');
  if (searchInput) {
    const listItems = document.querySelectorAll('#poster-list li');
    searchInput.addEventListener('input', () => {
      const val = searchInput.value.toLowerCase();
      listItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(val) ? 'list-item' : 'none';
      });
    });
  }

  async function uploadCsvToCollection(form) {
    const inp = form.querySelector('#ids_file');
    if (!inp.files || !inp.files.length) return;

    const filesToUpload = inp.files;
    const totalFiles = filesToUpload.length;
    const box = document.getElementById('csvUploadResult');
    const staticBox = document.getElementById('csv-success-message');

    // הסתרת הודעות ישנות והצגת תיבת הסטטוס החדשה
    if (staticBox) staticBox.style.display = 'none';
    if (box) {
        box.style.display = 'block';
        box.style.background = '#f0f8ff';
        box.style.borderColor = '#cce5ff';
    }

    let totalInserted = 0;
    let totalAlready = 0;
    let errors = [];

    // לולאה על כל הקבצים שנבחרו
    for (let i = 0; i < totalFiles; i++) {
        const file = filesToUpload[i];
        const fd = new FormData(form);
        fd.set('ids_file', file); // שימוש בקובץ הנוכחי מהלולאה

        if (box) {
            box.innerHTML = `⏳ מעלה ומעבד קובץ ${i + 1}/${totalFiles}: <strong>${file.name}</strong>...`;
        }

        try {
            const res = await fetch(form.action, { method: 'POST', body: fd });
            const data = await res.json();

            if (!res.ok || data.error || data.errors) {
                const errorMsg = (data.errors || [data.error || 'שגיאה לא ידועה']).join(', ');
                errors.push(`שגיאה בקובץ ${file.name}: ${errorMsg}`);
                // נעצור בכישלון הראשון כדי לא להציף בשגיאות
                break; 
            }

            // צבירת התוצאות
            totalInserted += data.inserted || 0;
            totalAlready += data.already || 0;

        } catch (e) {
            errors.push(`❌ תקלה בחיבור לשרת בעת העלאת ${file.name}: ${e}`);
            // נעצור גם במקרה של שגיאת רשת
            break;
        }
    }

    // ניקוי שדה הקבצים
    inp.value = ''; 

    // הצגת התוצאה הסופית
    if (errors.length > 0) {
        if (box) {
            box.style.background = '#fff5f5';
            box.style.borderColor = '#f3c0c0';
            box.innerHTML = `<strong>אירעו שגיאות:</strong><ul>${errors.map(e => `<li>${e}</li>`).join('')}</ul>`;
        }
    } else {
        if (box) {
            box.style.background = '#f6fff6';
            box.style.borderColor = '#cde8cd';
            box.innerHTML = `✅ כל הקבצים הועלו בהצלחה. מרענן את העמוד…`;
        }
        
        // בניית כתובת ה-URL לרענון עם התוצאות המסוכמות
        const successUrl = new URL(window.location.href);
        ['csv_success', 'inserted', 'already'].forEach(p => successUrl.searchParams.delete(p));
        successUrl.searchParams.set('csv_success', '1');
        successUrl.searchParams.set('inserted', totalInserted);
        successUrl.searchParams.set('already', totalAlready);
        
        setTimeout(() => { window.location.href = successUrl.href; }, 1200);
    }
}
</script>
</body>
</html>
<?php include 'footer.php'; ?>