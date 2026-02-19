<?php
require_once 'server.php';
require_once 'bbcode.php'; // המרת BBCode ל-HTML

// ==========================================================
// == כל הלוגיקה לפני ה-HTML ==
// ==========================================================

// הפעלת opcache reset (לא חובה)
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }



// ===============================
// שמירת מיון וכפילויות בעוגייה
// ===============================
if (isset($_GET['sort'])) {
    setcookie('collections_sort', (string)$_GET['sort'], time() + 86400*30, '/');
}
if (isset($_GET['dup'])) {
    setcookie('collections_dup', (string)$_GET['dup'], time() + 86400*30, '/');
}
// נטרול GET מסוכן — מפנים מיידית ללא ביצוע פעולה
$danger_keys = ['pin','unpin','make_private','make_public'];
foreach ($danger_keys as $k) {
  if (isset($_GET[$k])) {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  }
}

// --- טיפול בבקשות POST (כולל הגדרות תצוגה ומחיקה) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. שינוי תצוגת "פרטיים" (שמירת Cookie)
    if (isset($_POST['toggle_private_view'])) {
        $val = $_POST['toggle_private_view']; // 'show' או 'hide'
        if ($val === 'show') {
            setcookie('show_private', '1', time() + (86400 * 30), "/");
        } else {
            setcookie('show_private', '', time() - 3600, "/");
        }
        header("Location: collections.php?" . http_build_query($_GET));
        exit;
    }

    // 2. שינוי תצוגת "נעוצים" (שמירת Cookie)
    if (isset($_POST['toggle_pinned_view'])) {
        $val = $_POST['toggle_pinned_view']; // 'show' או 'hide'
        if ($val === 'hide') { 
            setcookie('hide_pinned', '1', time() + (86400 * 30), "/");
        } else {
            setcookie('hide_pinned', '', time() - 3600, "/");
        }
        header("Location: collections.php?" . http_build_query($_GET));
        exit;
    }

    // 3. מחיקת אוסף
    if (isset($_POST['delete_collection'])) {
        $cid = (int)$_POST['delete_collection'];
        $conn->query("DELETE FROM collections WHERE id = $cid");
        $conn->query("DELETE FROM poster_collections WHERE collection_id = $cid");
        
        $redir = $_GET;
        $redir['msg'] = 'deleted';
        header("Location: collections.php?" . http_build_query($redir));
        exit;
    }

    // 4. פעולות על אוסף בודד
    if (isset($_POST['action']) && isset($_POST['collection_id'])) {
        $action = $_POST['action'];
        $act_id = (int)$_POST['collection_id'];

        if ($action === 'pin') {
            $stmt = $conn->prepare("UPDATE collections SET is_pinned = 1, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'unpin') {
            $stmt = $conn->prepare("UPDATE collections SET is_pinned = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'make_private') {
            $stmt = $conn->prepare("UPDATE collections SET is_private = 1, is_pinned = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'make_public') {
            $stmt = $conn->prepare("UPDATE collections SET is_private = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        
        } elseif ($action === 'duplicate') {
            // שכפול אוסף: יצירת אוסף חדש + העתקת כל התוכן
            $src_id = $act_id;

            $stmtC = $conn->prepare("SELECT name, description, image_url, search_terms, is_private, is_safe, is_urgent, is_green FROM collections WHERE id = ?");
            $src = null;
            if ($stmtC) {
                $stmtC->bind_param("i", $src_id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $src = $resC ? $resC->fetch_assoc() : null;
                $stmtC->close();
            }

            if ($src) {
                $conn->begin_transaction();
                try {
                    $new_name  = 'העתק של ' . (string)$src['name'];
                    $desc      = (string)($src['description'] ?? '');
                    $img       = (string)($src['image_url'] ?? '');
                    $terms     = (string)($src['search_terms'] ?? '');
                    $is_private = (int)($src['is_private'] ?? 0);
                    $is_safe    = (int)($src['is_safe'] ?? 0);
                    $is_urgent  = (int)($src['is_urgent'] ?? 0);
                    $is_green   = (int)($src['is_green'] ?? 0);

                    $stmtI = $conn->prepare("INSERT INTO collections (name, description, image_url, search_terms, is_private, is_pinned, is_safe, is_urgent, is_green, created_at, updated_at)
                                             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW())");
                    if (!$stmtI) throw new Exception('insert collection failed');

                    $stmtI->bind_param("ssssiiii", $new_name, $desc, $img, $terms, $is_private, $is_safe, $is_urgent, $is_green);
                    $stmtI->execute();
                    $new_id = (int)$conn->insert_id;
                    $stmtI->close();

                    // Copy items (preserve per-item pin + added_at)
                    $copy_sql = "INSERT INTO poster_collections (collection_id, poster_id, is_pinned, added_at)
                                 SELECT ?, poster_id, is_pinned, added_at
                                 FROM poster_collections WHERE collection_id = ?";
                    $stmtCP = $conn->prepare($copy_sql);
                    if (!$stmtCP) throw new Exception('copy items failed');
                    $stmtCP->bind_param("ii", $new_id, $src_id);
                    $stmtCP->execute();
                    $stmtCP->close();

                    $conn->commit();

                    header('Location: collection.php?id=' . $new_id . '&msg=duplicated');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                }
            }

} elseif ($action === 'toggle_safe') {
            $stmt = $conn->prepare("UPDATE collections SET is_safe = NOT is_safe, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'toggle_urgent') {
            $stmt = $conn->prepare("UPDATE collections SET is_urgent = NOT is_urgent, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'toggle_green') {
            // פעולה חדשה: צביעה בירוק
            $stmt = $conn->prepare("UPDATE collections SET is_green = NOT is_green WHERE id = ?");
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $redirect_params = $_GET;
        $redirect_params['msg'] = 'updated';
        header("Location: collections.php?" . http_build_query($redirect_params));
        exit;
    }
}

// --- חישוב מצב תצוגה נוכחי ---
$show_private = isset($_COOKIE['show_private']);
$show_pinned = true;
if (isset($_COOKIE['hide_pinned'])) {
    $show_pinned = false;
}

// --- פרמטרים: פאגינציה, מיון וחיפוש ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_value = $_GET['per_page'] ?? 50;
$per_page = intval($per_page_value);
$per_page_for_query = ($per_page === 0) ? 999999 : $per_page;
$offset = ($page - 1) * $per_page_for_query;

$sort = $_GET['sort'] ?? ($_COOKIE['collections_sort'] ?? 'updated_desc');

$only_duplicates = (($_GET['dup'] ?? ($_COOKIE['collections_dup'] ?? '0')) == '1');

switch ($sort) {
  case 'created_asc': $order = "c.created_at ASC"; break;
  case 'created_desc':$order = "c.created_at DESC"; break;
  case 'name':        $order = "c.name ASC";        break;
  case 'za':
  case 'name_desc':   $order = "c.name DESC";       break;
  case 'az':          $order = "c.name ASC";        break;
  case 'count':       $order = "total_items DESC";  break;
  case 'updated_desc':
  default:            $order = "c.updated_at DESC";
}

$search_txt = trim($_GET['txt'] ?? '');

// אם הודבק קישור
if ($search_txt !== '') {
    $parsed = @parse_url($search_txt);
    if ($parsed !== false && !empty($parsed['query'])) {
        parse_str($parsed['query'], $qs);
        if (!empty($qs['id']) && ctype_digit((string)$qs['id'])) {
            $search_txt = (string)$qs['id'];
        }
    }
}

if (isset($_GET['txt'])) {
    $search_in_title = isset($_GET['search_title']);
    $search_in_desc  = isset($_GET['search_desc']);
} else {
    $search_in_title = true;
    $search_in_desc  = true;
}

// --- בניית שאילתות דינמית לחיפוש ---
$search_sql_part = '';
if ($search_txt !== '') {
    $search_words = array_filter(explode(' ', $search_txt));
    $search_and_conditions = [];
    foreach ($search_words as $word) {
        $safe_word = $conn->real_escape_string($word);
        $search_term_like = "%{$safe_word}%";
        $search_or_conditions_for_word = [];

        if ($search_in_title) {
            $search_or_conditions_for_word[] = "c.name LIKE '{$search_term_like}'";
        }
        if ($search_in_desc) {
            $search_or_conditions_for_word[] = "c.description LIKE '{$search_term_like}'";
        }
        $search_or_conditions_for_word[] = "c.search_terms LIKE '{$search_term_like}'";

        if (!empty($search_or_conditions_for_word)) {
            $search_and_conditions[] = '(' . implode(' OR ', $search_or_conditions_for_word) . ')';
        }
    }
    if (!empty($search_and_conditions)) {
        $search_sql_part = implode(' AND ', $search_and_conditions);
    }
}

// אוספים ציבוריים לא נעוצים
$where_conditions = ["c.is_pinned = 0", "c.is_private = 0"];
if ($only_duplicates) {
    $where_conditions[] = "c.name IN (SELECT name FROM collections GROUP BY name HAVING COUNT(*) > 1)";
}

if ($search_sql_part !== '') {
    $where_conditions[] = $search_sql_part;
}
$final_where_clause = implode(" AND ", $where_conditions);

// ספירות
$total_all_collections = $conn->query("SELECT COUNT(*) FROM collections")->fetch_row()[0];
$count_sql = "SELECT COUNT(DISTINCT c.id) FROM collections c WHERE " . $final_where_clause;
$total_public_collections = $conn->query($count_sql)->fetch_row()[0] ?? 0;
$total_pages = $per_page_for_query > 0 ? ceil($total_public_collections / $per_page_for_query) : 0;

// שליפת נעוצים
$pinned_data = [];
if ($show_pinned && $page == 1) {
    $pinned_sql = "
      SELECT c.*, COUNT(pc.poster_id) AS total_items
      FROM collections c
      LEFT JOIN poster_collections pc ON c.id = pc.collection_id
      WHERE c.is_pinned = 1 AND c.is_private = 0
    ";
    if ($search_sql_part !== '') {
        $pinned_sql .= " AND ({$search_sql_part})";
    }
    $pinned_sql .= " GROUP BY c.id ORDER BY c.name ASC";
    $pinned_res = $conn->query($pinned_sql);
    while ($row = $pinned_res->fetch_assoc()) { $pinned_data[] = $row; }
}

// שליפת פרטיים
$private_data = [];
if ($show_private) {
    $private_sql = "
      SELECT c.*, COUNT(pc.poster_id) AS total_items
      FROM collections c
      LEFT JOIN poster_collections pc ON c.id = pc.collection_id
      WHERE c.is_private = 1
    ";
    if ($search_sql_part !== '') {
        $private_sql .= " AND ({$search_sql_part})";
    }
    $private_sql .= " GROUP BY c.id ORDER BY $order";
    $private_res = $conn->query($private_sql);
    while ($row = $private_res->fetch_assoc()) { $private_data[] = $row; }
}

// שליפת אוספים ציבוריים
$public_sql = "
  SELECT c.*, COUNT(pc.poster_id) AS total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE $final_where_clause
  GROUP BY c.id
  ORDER BY $order
  LIMIT ? OFFSET ?
";

$public_data = [];
if ($stmt_data = $conn->prepare($public_sql)) {
    $stmt_data->bind_param('ii', $per_page_for_query, $offset);
    $stmt_data->execute();
    $result = $stmt_data->get_result();
    while ($row = $result->fetch_assoc()) { $public_data[] = $row; }
    $stmt_data->close();
}

include 'header.php';
function bb($t){ return $t; } 
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 רשימת אוספים</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    body { font-family:Arial, sans-serif; direction:rtl; background:#f9f9f9; padding:10px; }
    .collection-card { background:white; padding:20px; margin:10px auto; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); max-width:1100px; position:relative; text-align:right; }
    
    /* עיצובים לפי סטטוס */
    .collection-card.pinned { background-color:#fffff0; border-left:5px solid #ffd700; }
    .collection-card.private { background-color:#f0f8ff; border-left:5px solid #4682b4; }
    .collection-card.urgent { border-left:5px solid #dc3545; }

    /* עיצוב לאוסף צבוע (ירוק) - עדיפות עליונה! */
    .collection-card.green-mode { 
        background-color: #e8f5e9 !important; /* ירוק בהיר מאוד */
        border-left: 5px solid #2e7d32 !important; /* ירוק כהה */
    }

    /* מצב "ללא צבעים" - דורס הכל והופך את הרקע ללבן */
    body.no-colors .collection-card {
        background-color: #fff !important;
    }

    /* --- תיקון מבנה הכותרת והכפתורים --- */
    .card-header-row {
        display: flex;
        flex-direction: column; /* כותרת מעל כפתורים */
        gap: 10px;
        margin-bottom: 15px;
    }
    .card-header-row h3 {
        margin: 0;
        font-size: 20px;
        line-height: 1.4;
        width: 100%; 
    }
    /* הכפתורים בתוך ה-flex container */
    .collection-card .actions { 
        display: flex; 
        flex-wrap: wrap; 
        gap: -5px; /* רווח מצומצם */
        justify-content: flex-start;
        flex-shrink: 0; 
    }
    .collection-card .actions a, .collection-card .actions button { 
        text-decoration:none; 
        font-size:14px; 
        background:none; 
        border:none; 
        color:#007bff; 
        cursor:pointer; 
        padding: 2px 5px;
    }
    .collection-card .actions a:hover, .collection-card .actions button:hover { text-decoration:underline; }
    /* ---------------------------------------------------- */

    .collection-card .description { color:#555; margin-bottom:10px; }
    .collection-card .meta-info { display:flex; align-items:center; gap:15px; color:#888; font-size:13px; }
    
    .message { background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #ddc; color:#333; max-width:600px; margin:auto; }
    .pagination { text-align:center; margin-top:20px; }
    .pagination a { margin:0 6px; padding:6px 10px; background:#eee; border-radius:4px; text-decoration:none; color:#333; }
    .pagination a.active { font-weight:bold; background:#ccc; }
    .toggle-desc-btn { background:#f0f0f0; border:1px solid #ccc; padding:6px 10px; border-radius:4px; cursor:pointer; font-size:13px; margin-bottom:8px; }
    .toggle-desc-btn:hover { background:#e2e2e2; }
    .description.collapsible { display:none; background:#fafafa; padding:8px; border:1px solid #ddd; border-radius:6px; margin-top:6px; }
    .description.collapsible.open { display:block; }
    .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
    .modal-content { background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:8px; position:relative; }
    .close-btn { color:#aaa; position:absolute; left:15px; top:5px; font-size:28px; font-weight:bold; }
    .close-btn:hover, .close-btn:focus { color:black; text-decoration:none; cursor:pointer; }
    .modal-content h3 { margin-top:0; }
    .modal-content textarea { width:100%; font-size:15px; padding:8px; border-radius:7px; border:1px solid #bbb; background:#fafcff; margin-bottom:10px; resize:vertical; min-height:120px; }
    .modal-content button { width:100%; font-size:16px; padding:10px 0; border-radius:7px; border:none; background:#007bff; color:#fff; margin-top:6px; cursor:pointer; }
    .main-controls { display:flex; flex-direction:column; align-items:center; gap:20px; margin-top:30px; }
    .search-form { display:flex; flex-wrap:wrap; justify-content:center; align-items:center; gap:15px; }
    .search-form input[type="text"] { font-size:16px; padding:10px; border:1px solid #ccc; border-radius:8px; width:300px; }
    .search-form .checkbox-group { display:flex; gap:15px; }
    .search-form label { font-size:14px; user-select:none; }
    .action-buttons { display:flex; justify-content:center; gap:15px; width:100%; flex-wrap:wrap; }
    .filter-controls { display:flex; flex-direction:column; justify-content:center; gap:15px; flex-wrap:wrap; }
    .btn-main { display:inline-flex; align-items:center; justify-content:center; min-width:160px; height:45px; font-size:16px; font-weight:bold; padding:0 20px; border-radius:8px; text-decoration:none; text-align:center; cursor:pointer; transition:background 0.2s, transform 0.1s; color:#fff !important; border:none; }
    .btn-main:hover { transform: translateY(-2px); color:#fff !important; }
    .btn-search { background:#28a745; }
    .btn-create { background:#007bff; }
    .btn-reset { background: purple; color:white; }
    .btn-toggle { background: deepskyblue; }
    .btn-toggle:hover { background:#5a6268; }
    .button-group { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center; }
    .button-group label { font-weight:bold; font-size:16px; color:#555; margin-left:10px;}
    .btn-filter { text-decoration:none; padding:8px 15px; border-radius:6px; background:#f0f0f0; color:#333; border:1px solid #ccc; font-size:14px; transition: background-color 0.2s; }
    .btn-filter:hover { background:#e2e2e2; }
    .btn-filter.active { background:#007bff; color:white; border-color:#007bff; font-weight:bold; }
    .section-divider { max-width:1100px; margin:25px auto; border:0; border-top:1px solid #ccc; }
    .admin-only { display:none !important; }
    .admin-switch { position:absolute; opacity:0; pointer-events:none; }
    .admin-switch:checked ~ .page .admin-only { display:inline-flex !important; }
    .admin-switch:checked ~ .page .actions.admin-only { display:flex !important; }
    .admin-toggle { background:#dc3545 !important; color:#fff !important; }
    label[for="admin-switch"] { background:#dc3545 !important; color:#fff !important; }
    
    .when-on { display: none; }
    .when-off { display: inline; }
    .admin-switch:checked ~ .page .when-off { display: none; }
    .admin-switch:checked ~ .page .when-on { display: inline; }

    .collections-suggestions {
      position:absolute; top:100%; right:0; left:0;
      background:#fff; border:1px solid #ccc; border-radius:8px; margin-top:4px;
      max-height:250px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,0.1);
      z-index:1000; display:none;
    }
    .collections-suggestions .suggestion-item { padding:8px 10px; cursor:pointer; font-size:14px; display:flex; align-items:center; gap:8px; }
    .collections-suggestions .suggestion-item:hover { background:#f0f0f0; }
    .collections-suggestions .suggestion-icon { width:20px; }
    
    .qc-missing-row { display: none; color: #d63384; font-weight: bold; margin-top: 5px; font-size: 13px; }

    /* 1. אוספים עם חוסרים במצב QC */
    body.qc-mode .collection-card.has-missing {
        border: 2px solid #d63384;
        background: #fff5f5;
    }
    
    /* וידוא שדחוף נהיה אדום אך ורק במצב QC */
    body.qc-mode .collection-card.urgent {
        background-color: #ffcccc; /* אדום אמיתי */
    }

    /* 2. תצוגת השורה "חסר: ..." */
    body.qc-mode .qc-missing-row {
        display: block; 
    }

    /* 3. אוספים מושלמים במצב QC (כחול) */
    body.qc-mode .collection-card.qc-perfect {
        border: 2px solid #007bff;
        background: #f0f8ff;
    }
  </style>
</head>
<body class="rtl">

<input id="admin-switch" type="checkbox" class="admin-switch">

<h2 style="text-align:center;">📁 רשימת אוספים: <?= $total_all_collections ?></h2>

<div class="main-controls page">
  <form method="get" action="collections.php" class="search-form" id="search-form">
  <div style="position:relative;">
    <input type="text" name="txt" id="collection-search-input" autocomplete="off" placeholder="🔍 חיפוש אוסף..." value="<?= htmlspecialchars($search_txt) ?>">
    <div id="collection-suggestions" class="collections-suggestions"></div>
  </div>

  <div class="checkbox-group">
    <label><input type="checkbox" name="search_title" value="1" <?= $search_in_title ? 'checked' : '' ?>> בכותרת</label>
    <label><input type="checkbox" name="search_desc" value="1" <?= $search_in_desc ? 'checked' : '' ?>> בתיאור</label>
  </div>
</form>

  <div class="action-buttons">
    <button type="submit" form="search-form" class="btn-main btn-search">🔍 חפש</button>

<?php
  $dup_params = $_GET;
  unset($dup_params['page'], $dup_params['msg']);
  $dup_params['dup'] = $only_duplicates ? '0' : '1';
  $dup_text = $only_duplicates ? '🧬 כפילויות ✓' : '🧬 כפילויות';
?>
<a href="?<?= http_build_query($dup_params) ?>" class="btn-main" style="text-decoration:none;background:#6f42c1;color:#fff;">
  <?= $dup_text ?>
</a>

<a href="collections.php" class="btn-main btn-reset" onclick="localStorage.setItem('collectionsQC', '0');">🔄 איפוס</a>
    
    <?php
      $current_params = $_GET;
      unset($current_params['msg']);
      $action_url = '?' . http_build_query($current_params);

      // כפתור בקרת איכות
      echo '<button type="button" id="qcBtn" onclick="toggleQC()" class="btn-main" style="background:green; color:#d63384;">⚠️ בקרת איכות</button> ';
      
      // כפתור הצג/הסתר צבעים חדש
      echo '<button type="button" id="toggleColorsBtn" onclick="toggleColors()" class="btn-main" style="background:#fd7e14;">🎨 הסתר צבעים</button> ';

      echo '<a href="create_collection.php" class="btn-main btn-create admin-only">➕ צור אוסף חדש</a> ';
      // כפתור נעוצים (POST)
      echo '<form method="post" action="'.$action_url.'" style="display:inline;">';
      if ($show_pinned) {
          echo '<input type="hidden" name="toggle_pinned_view" value="hide">';
          echo '<button type="submit" class="btn-main btn-toggle">🙈 הסתר נעוצים</button>';
      } else {
          echo '<input type="hidden" name="toggle_pinned_view" value="show">';
          echo '<button type="submit" class="btn-main btn-toggle">📌 הצג נעוצים</button>';
      }
      echo '</form> ';
      // כפתור פרטיים (POST)
      echo '<form method="post" action="'.$action_url.'" style="display:inline;">';
      if ($show_private) {
          echo '<input type="hidden" name="toggle_private_view" value="hide">';
          echo '<button type="submit" class="btn-main btn-toggle admin-only">🙈 הסתר פרטיים</button>';
      } else {
          echo '<input type="hidden" name="toggle_private_view" value="show">';
          echo '<button type="submit" class="btn-main btn-toggle admin-only">🔒 הצג פרטיים</button>';
      }
      echo '</form>';
    ?>
    
    <label for="admin-switch" class="btn-main btn-toggle admin-toggle">
      <span class="when-off">🔑 מצב ניהול</span>
      <span class="when-on">🚪 יציאה ממצב ניהול</span>
    </label>
  </div>

  <div class="filter-controls">
    <?php
      $sort_options = [
        'updated_desc' => 'עדכון אחרון', 'created_desc' => 'חדש → ישן', 'created_asc' => 'ישן → חדש',
        'name' => 'שם (א-ת)',
        'az' => 'A-Z',
        'za' => 'Z-A', 'count' => 'מס׳ פוסטרים'
      ];
      echo '<div class="button-group"><label>מיון:</label>';
      foreach ($sort_options as $key => $label) {
          $sort_params = $_GET;
          $sort_params['sort'] = $key;
          $is_active = ($sort === $key) ? 'active' : '';
          echo '<a href="?'.http_build_query($sort_params).'" class="btn-filter '.$is_active.'">'.$label.'</a>';
      }
      
      // כפתור כפילויות בתוך שורת המיון
#6f42c1;color:#fff;">'.$dup_text.'</a>';

echo '</div>';

      $per_page_options = [ 20 => '20', 50 => '50', 100 => '100', 250 => '250', 0 => 'הכל' ];
      echo '<div class="button-group"><label>הצג:</label>';
      foreach ($per_page_options as $value => $label) {
          $page_params = $_GET;
          $page_params['per_page'] = $value;
          $is_active = ($per_page_value == $value) ? 'active' : '';
          echo '<a href="?'.http_build_query($page_params).'" class="btn-filter '.$is_active.'">'.$label.'</a>';
      }
      echo '</div>';
    ?>
  </div>
</div><br>
<h1><div><a href="collections_miniview.php">📚 רשימת אוספים עם פוסטרים</a></div></h1>
<br>

<?php if (!empty($_GET['msg'])): ?>
  <div class="message">
    <?php if ($_GET['msg']==='deleted'): ?>🗑️ האוסף נמחק בהצלחה<?php else: ?>✅ עודכן בהצלחה<?php endif; ?>
  </div>
<?php endif; ?>

<div id="collections-list" class="page">
<?php
// פונקציית רנדור עם תמיכה בבקרת איכות + בטוח + דחוף + צבע ירוק
function render_collection_card($c) {
  $desc = trim($c['description'] ?? '');
  $default_desc = "[עברית]\n\n[/עברית]\n\n\n[אנגלית]\n\n[/אנגלית]";
  
  $clean_desc = trim(str_replace(array("\r","\n"," "), '', $desc));
  $clean_default = trim(str_replace(array("\r","\n"," "), '', $default_desc));
  $is_default = ($clean_desc === $clean_default);
  $is_empty_desc = ($desc === '' || $is_default);

  // בדיקת סטטוסים
  $is_safe = !empty($c['is_safe']);
  $is_urgent = !empty($c['is_urgent']); // סטטוס דחוף
  $is_green = !empty($c['is_green']);   // סטטוס צבע ירוק

  // --- לוגיקת בדיקת חוסרים לבקרת איכות ---
  $missing = [];

  // בודקים חוסרים רק אם האוסף לא בטוח (דחוף כן מציג חוסרים בתוך הכרטיס, רק הצבע שונה)
  if (!$is_safe) {
      if (empty($c['image_url'])) $missing[] = 'לוגו';
      if ($is_empty_desc) $missing[] = 'תיאור/תרגום';
      if ($c['total_items'] == 0) $missing[] = 'ריק';
      if (empty(trim($c['search_terms'] ?? ''))) $missing[] = 'מילות מפתח';
  }

  $has_missing = !empty($missing);
  
  // קביעת הקלאס של בקרת איכות
  $missing_class = $has_missing ? 'has-missing' : 'qc-perfect';
  // ----------------------------------------

  // חישוב Class התצוגה הראשי
  $card_class = '';
  // סדר העדיפויות לצבעים (האחרון קובע ודורס):
  if (!empty($c['is_pinned'])) $card_class = 'pinned';   // צהוב
  if (!empty($c['is_private'])) $card_class = 'private'; // כחול
  if ($is_urgent) $card_class = 'urgent';                // אדום
  if ($is_green) $card_class = 'green-mode';             // ירוק (עכשיו הכי חזק, דורס הכל)

  $icon = '📁';
  if (!empty($c['is_pinned'])) $icon = '📌';
  if (!empty($c['is_private'])) $icon = '🔒';
  
  // תוספות אייקונים
  if ($is_safe) $icon .= ' <span title="תקין ידנית" style="cursor:help;">🛡️</span>';
  if ($is_urgent) $icon .= ' 🚨'; 

  ?>
  <div class="collection-card <?= $card_class ?> <?= $missing_class ?>">
    
    <div class="card-header-row">
        <h3>
          <a href="collection.php?id=<?= (int)$c['id'] ?>">
            <?= $icon ?>
            <?= htmlspecialchars($c['name']) ?>
          </a>
        </h3>

        <div class="actions admin-only">
          <?php $qs = http_build_query($_GET); ?>
          <?php if (!empty($c['is_private'])): ?>
            <form method="post" action="?<?= $qs ?>" style="display:inline;">
              <input type="hidden" name="action" value="make_public">
              <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
              <button type="submit">🔓 הפוך לציבורי</button>
            </form>

            <form method="post" action="?<?= $qs ?>" style="display:inline;">
              <input type="hidden" name="action" value="duplicate">
              <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
              <button type="submit" title="שכפל אוסף">🧬 שכפל</button>
            </form>

<?php else: ?>
            <form method="post" action="?<?= $qs ?>" style="display:inline;">
              <input type="hidden" name="action" value="make_private">
              <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
              <button type="submit">🔒 הפוך לפרטי</button>
            </form>

            <?php if (!empty($c['is_pinned'])): ?>
              <form method="post" action="?<?= $qs ?>" style="display:inline;">
                <input type="hidden" name="action" value="unpin">
                <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
                <button type="submit">📌 הסר נעיצה</button>
              </form>
            <?php else: ?>
              <form method="post" action="?<?= $qs ?>" style="display:inline;">
                <input type="hidden" name="action" value="pin">
                <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
                <button type="submit">📌 נעיצה</button>
              </form>
            <?php endif; ?>

          <form method="post" action="?<?= $qs ?>" style="display:inline;">
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" title="שכפל אוסף">🧬 שכפל</button>
          </form>

          <?php endif; ?>

          <form method="post" action="?<?= $qs ?>" style="display:inline;">
            <input type="hidden" name="action" value="toggle_safe">
            <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
            <?php if ($is_safe): ?>
                <button type="submit" style="color:#28a745;">🛡️ תקין</button>
            <?php else: ?>
                <button type="submit" style="color:#6c757d;">🛡️ סמן כתקין</button>
            <?php endif; ?>
          </form>

          <form method="post" action="?<?= $qs ?>" style="display:inline;">
            <input type="hidden" name="action" value="toggle_urgent">
            <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
            <?php if ($is_urgent): ?>
                <button type="submit" style="color:#dc3545; font-weight:bold;">🚨 בטל דחיפות</button>
            <?php else: ?>
                <button type="submit" style="color:#6c757d;">🚨 סמן כדחוף</button>
            <?php endif; ?>
          </form>

          <form method="post" action="?<?= $qs ?>" style="display:inline;">
            <input type="hidden" name="action" value="toggle_green">
            <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
            <?php if ($is_green): ?>
                <button type="submit" style="color:#2e7d32; font-weight:bold;">🎨 בטל צבע</button>
            <?php else: ?>
                <button type="submit" style="color:#6c757d;">🎨 צבע</button>
            <?php endif; ?>
          </form>

          <a href="edit_collection.php?id=<?= (int)$c['id'] ?>">✏️ ערוך</a>
          <form method="post" action="?<?= http_build_query($_GET)?>" style="display:inline;">
            <button type="submit" name="delete_collection" value="<?= (int)$c['id'] ?>" onclick="return confirm('למחוק את האוסף?')">🗑️ מחק</button>
          </form>
          <a href="#" class="open-modal-btn" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">➕ הוסף פוסטר</a>
        </div>
    </div>

    <?php if (!empty($desc) && !$is_default): ?>
      <button class="toggle-desc-btn">📝 הצג / הסתר תיאור</button>
      <div class="description collapsible"><?= bbcode_to_html($desc) ?></div>
    <?php else: ?>
      <div class="description"><em>אין תיאור</em></div>
    <?php endif; ?>

    <div class="meta-info">
        <div class="count">🎞️ <?= (int)$c['total_items'] ?> פוסטרים</div>
        <?php if (!empty($c['updated_at'])): ?>
          <div class="updated-at">
            <i class="fa-regular fa-clock"></i> עדכון אחרון: <?= date('d/m/Y H:i', strtotime($c['updated_at'])) ?>
          </div>
        <?php endif; ?>
    </div>
    
    <?php if ($has_missing): ?>
      <div class="qc-missing-row">⚠️ חסר: <?= implode(', ', $missing) ?></div>
    <?php endif; ?>

  </div>
  <?php
}
if ($show_pinned && $page == 1 && !empty($pinned_data)) {
  foreach($pinned_data as $c) { render_collection_card($c); }
  echo '<hr class="section-divider">';
}
if ($show_private && !empty($private_data)) {
  foreach ($private_data as $c) { render_collection_card($c); }
  echo '<hr class="section-divider">';
}
if (!empty($public_data)) {
  foreach ($public_data as $c) { render_collection_card($c); }
} else {
    // הודעה אם אין תוצאות בכלל
    if (empty($public_data) && empty($pinned_data) && empty($private_data)) {
        if ($search_txt !== '') {
            echo '<p style="text-align:center;">😢 לא נמצאו אוספים התואמים לחיפוש.</p>';
        } else {
            echo '<p style="text-align:center;">😢 לא קיימים אוספים כרגע.</p>';
        }
    }
}
?>
</div>

<?php if ($total_pages > 1): ?>
  <div class="pagination page">
    <?php 
    $page_params = $_GET;
    unset($page_params['page']);
    $base_url = '?' . http_build_query($page_params);
    ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="<?= $base_url ?>&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<div id="add-poster-modal" class="modal page">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h3 id="modal-title">הוספת פוסטר לאוסף: <span></span></h3>
    <form method="post" action="add_to_collection_batch.php">
      <input type="hidden" name="collection_id" id="modal-collection-id" value="">
      <label for="poster-ids-textarea">🔗 מזהים (ID רגיל או IMDb: tt...)</label>
      <textarea id="poster-ids-textarea" name="poster_ids_raw" rows="8" placeholder="לדוגמה:
45
tt1375666
89"></textarea>
      <button type="submit">📥 קשר פוסטרים</button>
    </form>
  </div>
</div>

<script>
function toggleQC() {
    const body = document.body;
    const btn = document.getElementById('qcBtn');
    const isOn = body.classList.toggle('qc-mode');
    
    // שמירת מצב בזיכרון הדפדפן
    localStorage.setItem('collectionsQC', isOn ? '1' : '0');

    if (isOn) {
        btn.innerHTML = '✅ הצג הכל';
        btn.style.background = '#d63384';
        btn.style.color = 'white';
    } else {
        btn.innerHTML = '⚠️ בקרת איכות';
        btn.style.background = 'green';
        btn.style.color = '#d63384';
    }
}

function toggleColors() {
    const body = document.body;
    const isOn = body.classList.toggle('no-colors');
    localStorage.setItem('hideCollectionColors', isOn ? '1' : '0');
    updateColorBtnText();
}

function updateColorBtnText() {
    const btn = document.getElementById('toggleColorsBtn');
    if (!btn) return;
    const isHidden = document.body.classList.contains('no-colors');
    if (isHidden) {
        btn.innerHTML = '🎨 הצג צבעים';
        btn.style.background = '#6c757d'; // אפור כשהצבעים מוסתרים
    } else {
        btn.innerHTML = '🎨 הסתר צבעים';
        btn.style.background = '#fd7e14'; // כתום כברירת מחדל
    }
}
</script>

<script>
(function(){
  var input = document.getElementById('collection-search-input');
  var box   = document.getElementById('collection-suggestions');
  if (!input || !box) return;

  var timer = null;

  function clearSuggestions() {
    box.innerHTML = '';
    box.style.display = 'none';
  }

  function renderSuggestions(items) {
    if (!items || !items.length) {
      clearSuggestions();
      return;
    }
    box.innerHTML = '';
    items.forEach(function(item){
      var div = document.createElement('div');
      div.className = 'suggestion-item';
      div.dataset.url  = item.url;
      div.dataset.name = item.name || '';
      
      var iconSpan = document.createElement('span');
      iconSpan.className = 'suggestion-icon';
      iconSpan.textContent = item.icon || '📁';

      var labelSpan = document.createElement('span');
      labelSpan.textContent = item.label || item.name || '';

      div.appendChild(iconSpan);
      div.appendChild(labelSpan);

      div.addEventListener('click', function(){
        if (div.dataset.url) {
          window.location.href = div.dataset.url;
        } else if (div.dataset.name) {
          input.value = div.dataset.name;
          document.getElementById('search-form').submit();
        }
      });

      box.appendChild(div);
    });
    box.style.display = 'block';
  }

  input.addEventListener('input', function(){
    var q = input.value.trim();
    if (q.length < 2) {
      clearSuggestions();
      return;
    }

    if (timer) clearTimeout(timer);
    timer = setTimeout(function(){
      var url = 'collections_search_handler.php?term=' + encodeURIComponent(q);
      fetch(url)
        .then(function(r){ return r.json(); })
        .then(renderSuggestions)
        .catch(function(){ clearSuggestions(); });
    }, 250);
  });

  document.addEventListener('click', function(ev){
    if (!box.contains(ev.target) && ev.target !== input) {
      clearSuggestions();
    }
  });
})();
</script>

<script>
try{
  var key='adminMode';
  var sw=document.getElementById('admin-switch');
  if(sw){
    if(localStorage.getItem(key)==='1'){ sw.checked=true; }
    sw.addEventListener('change', function(){ localStorage.setItem(key, sw.checked?'1':'0'); });
  }

  // שחזור מצב בקרת איכות
  if(localStorage.getItem('collectionsQC') === '1'){
      toggleQC();
  }

  // שחזור מצב הסתרת צבעים
  if(localStorage.getItem('hideCollectionColors') === '1'){
      document.body.classList.add('no-colors');
  }
  updateColorBtnText(); // עדכון טקסט הכפתור בטעינה

  document.querySelectorAll('.toggle-desc-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var d=btn.nextElementSibling;
      if(d && d.classList.contains('collapsible')) d.classList.toggle('open');
    });
  });
  (function(){
    var modal=document.getElementById('add-poster-modal');
    if(!modal) return;
    var closeBtn=modal.querySelector('.close-btn');
    var titleSpan=modal.querySelector('#modal-title span');
    var idInput=modal.querySelector('#modal-collection-id');
    document.querySelectorAll('.open-modal-btn').forEach(function(a){
      a.addEventListener('click', function(ev){
        ev.preventDefault();
        if(idInput) idInput.value=a.dataset.id||'';
        if(titleSpan) titleSpan.textContent=a.dataset.name||'';
        modal.style.display='block';
      });
    });
    if(closeBtn) closeBtn.addEventListener('click', function(){ modal.style.display='none'; });
    window.addEventListener('click', function(ev){ if(ev.target===modal) modal.style.display='none'; });
  })();
}catch(e){/* ignore */}
</script>

<?php include 'footer.php'; ?>
</body>
</html>