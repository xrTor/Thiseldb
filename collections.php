<?php
require_once 'server.php';
require_once 'bbcode.php'; // המרת BBCode ל-HTML

// ==========================================================
// == כל הלוגיקה לפני ה-HTML ==
// ==========================================================

// הפעלת סשן + CSRF token
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_input() {
  $t = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
  return "<input type='hidden' name='csrf_token' value='{$t}'>";
}

// נטרול GET מסוכן — מפנים מיידית ללא ביצוע פעולה
$danger_keys = ['pin','unpin','make_private','make_public'];
foreach ($danger_keys as $k) {
  if (isset($_GET[$k])) {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  }
}

$message = '';

// --- לוגיקה בטוחה לפעולות (POST בלבד + CSRF) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // אימות CSRF
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit('CSRF check failed');
  }

  // מחיקת אוסף (קיים בקוד המקורי — נשמר, רק מוגן ב-CSRF)
  if (isset($_POST['delete_collection'])) {
    $cid = (int)$_POST['delete_collection'];
    $conn->query("DELETE FROM collections WHERE id = $cid");
    $conn->query("DELETE FROM poster_collections WHERE collection_id = $cid");
    $message = "🗑️ האוסף נמחק בהצלחה";
  }

  // פעולות: pin / unpin / make_private / make_public (הועברו מ-GET ל-POST)
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
    }
    // שמירה על פרמטרי החיפוש/מיון לאחר פעולה
    $redirect_params = $_GET;
    header("Location: collections.php?" . http_build_query($redirect_params));
    exit;
  }
}

// --- פרמטרים: פאגינציה, מיון וחיפוש ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_value = $_GET['per_page'] ?? 50;
$per_page = intval($per_page_value);
$per_page_for_query = ($per_page === 0) ? 999999 : $per_page;
$offset = ($page - 1) * $per_page_for_query;

$sort = $_GET['sort'] ?? 'updated_desc';
switch ($sort) {
  case 'created_asc': $order = "c.created_at ASC"; break;
  case 'created_desc':$order = "c.created_at DESC"; break;
  case 'name':        $order = "c.name ASC";        break;
  case 'count':       $order = "total_items DESC";  break;
  case 'updated_desc':
  default:            $order = "c.updated_at DESC";
}

// פרמטרי חיפוש
$search_txt = trim($_GET['txt'] ?? '');
if (isset($_GET['txt'])) {
    $search_in_title = isset($_GET['search_title']);
    $search_in_desc = isset($_GET['search_desc']);
} else {
    $search_in_title = true;
    $search_in_desc = true;
}

// פרמטרים להצגת קבוצות מיוחדות
$show_private = isset($_GET['show_private']);
$show_pinned = !isset($_GET['hide_pinned']);

// --- בניית שאילתות דינמית ---

// פיצול לוגיקת החיפוש לחלק נפרד לשימוש חוזר
$search_sql_part = '';
if ($search_txt !== '' && ($search_in_title || $search_in_desc)) {
    $search_words = array_filter(explode(' ', $search_txt));
    $search_and_conditions = [];
    foreach ($search_words as $word) {
        $safe_word = $conn->real_escape_string($word);
        $search_term_like = "%{$safe_word}%";
        $search_or_conditions_for_word = [];
        if ($search_in_title) $search_or_conditions_for_word[] = "c.name LIKE '{$search_term_like}'";
        if ($search_in_desc) $search_or_conditions_for_word[] = "c.description LIKE '{$search_term_like}'";
        if (!empty($search_or_conditions_for_word)) {
            $search_and_conditions[] = "(" . implode(" OR ", $search_or_conditions_for_word) . ")";
        }
    }
    if (!empty($search_and_conditions)) {
        $search_sql_part = implode(" AND ", $search_and_conditions);
    }
}

// בניית תנאי השאילתה הראשית (אוספים ציבוריים)
$where_conditions = ["c.is_pinned = 0", "c.is_private = 0"];
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
// שינוי: שליפת נעוצים תתבצע רק אם לא הוסתרו ורק בעמוד הראשון
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
    while ($row = $pinned_res->fetch_assoc()) {
        $pinned_data[] = $row;
    }
}

// שליפת פרטיים (רק אם המשתמש ביקש)
$private_data = [];
if ($show_private) {
    $private_res = $conn->query("
      SELECT c.*, COUNT(pc.poster_id) AS total_items
      FROM collections c
      LEFT JOIN poster_collections pc ON c.id = pc.collection_id
      WHERE c.is_private = 1
      GROUP BY c.id
      ORDER BY $order
    ");
    while ($row = $private_res->fetch_assoc()) {
        $private_data[] = $row;
    }
}

// שליפת אוספים ציבוריים (לא נעוצים)
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
    while ($row = $result->fetch_assoc()) {
        $public_data[] = $row;
    }
    $stmt_data->close();
}

// טוענים header רק אחרי כל הלוגיקה
include 'header.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 רשימת אוספים</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    /* ---- CSS קיים שלך (נשמר) ---- */
    body { font-family:Arial, sans-serif; direction:rtl; background:#f9f9f9; padding:10px; }
    .collection-card {
      background:white; padding:20px; margin:10px auto;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); max-width:1100px; position:relative; text-align:right;
    }
    .collection-card.pinned { background-color:#fffff0; border-left:5px solid #ffd700; }
    .collection-card.private { background-color:#f0f8ff; border-left:5px solid #4682b4; }
    .collection-card h3 { margin:0 0 10px 0; font-size:20px; }
    .collection-card .description { color:#555; margin-bottom:10px; }
    .collection-card .meta-info { display: flex; align-items: center; gap: 15px; color: #888; font-size: 13px; }
    .collection-card .actions { display: flex; flex-wrap: wrap; gap: 12px; position:absolute; top:20px; left:20px; }
    .collection-card .actions a, .collection-card .actions button {
      text-decoration:none; font-size:14px; background:none; border:none; color:#007bff; cursor:pointer;
    }
    .collection-card .actions a:hover, .collection-card .actions button:hover { text-decoration:underline; }
    .message {
      background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px;
      border:1px solid #ddc; color:#333; max-width:600px; margin:auto;
    }
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

    /* --- עיצוב קיים לבקרות העליונות --- */
    .main-controls { display:flex; flex-direction: column; align-items: center; gap: 20px; margin-top:30px; }
    .search-form { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 15px; }
    .search-form input[type="text"] { font-size: 16px; padding: 10px; border: 1px solid #ccc; border-radius: 8px; width: 300px; }
    .search-form .checkbox-group { display: flex; gap: 15px; }
    .search-form label { font-size: 14px; user-select: none; }
    
    .action-buttons { display: flex; justify-content: center; gap: 15px; width: 100%; flex-wrap:wrap; }
    .filter-controls { display: flex; flex-direction: column; justify-content: center; gap: 15px; flex-wrap: wrap; }
    
    .btn-main {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 160px; height: 45px; font-size: 16px; font-weight: bold;
      padding: 0 20px; border-radius: 8px; text-decoration: none;
      text-align: center; cursor: pointer; transition: background 0.2s, transform 0.1s;
      color: #fff !important; border: none;
    }
    .btn-main:hover { transform: translateY(-2px); color: #fff !important; }
    .btn-search { background: #28a745; }
    .btn-create { background: #007bff; }
    .btn-reset { background: purple; color: white; }
    .btn-toggle { background: deepskyblue; }
    .btn-toggle:hover { background: #5a6268; }

    .button-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: center; }
    .button-group label { font-weight: bold; font-size: 16px; color: #555; margin-left:10px;}
    .btn-filter {
        text-decoration: none; padding: 8px 15px; border-radius: 6px;
        background: #f0f0f0; color: #333; border: 1px solid #ccc;
        font-size: 14px; transition: background-color 0.2s;
    }
    .btn-filter:hover { background: #e2e2e2; }
    .btn-filter.active {
        background: #007bff; color: white; border-color: #007bff; font-weight: bold;
    }
    .section-divider { max-width:1100px; margin: 25px auto; border: 0; border-top: 1px solid #ccc; }

    /* ========== CSS מינימלי למצב ניהול (לא משנה עיצוב קיים) ========== */
    .admin-only { display: none !important; } /* ברירת מחדל: מוסתר */
    .admin-switch { position:absolute; opacity:0; pointer-events:none; } /* checkbox נסתר */
    .admin-switch:checked ~ .page .admin-only { display: inline-flex !important; } /* מציג במצב ניהול */
    .admin-switch:checked ~ .page .actions.admin-only { display:flex !important; } /* דיבים שלמים */
    /* החלפת טקסט בכפתור לפי המצב (ללא JS) */
    .admin-toggle .when-on { display:none; }
    .admin-switch:checked ~ .page .admin-toggle .when-on { display:inline; }
    .admin-switch:checked ~ .page .admin-toggle .when-off { display:none; }

    /* מספיקה אחת מהשתיים — בחר את שאתה מעדיף */
.admin-toggle {
  background: #dc3545 !important;
  color: #fff !important;
}
label[for="admin-switch"] {
  background: #dc3545 !important;
  color: #fff !important;
}

  </style>
</head>
<body class="rtl">

<!-- 0) מתג מצב ניהול (נסתר) -->
<input id="admin-switch" type="checkbox" class="admin-switch">

<h2 style="text-align:center;">📁 רשימת אוספים: <?= $total_all_collections ?></h2>

<div class="main-controls page"><!-- עטפתי ב.page כדי שהסלקטור יעבוד -->
  <form method="get" action="collections.php" class="search-form" id="search-form">
    <input type="text" name="txt" placeholder="🔍 חיפוש אוסף..." value="<?= htmlspecialchars($search_txt) ?>">
    <div class="checkbox-group">
      <label><input type="checkbox" name="search_title" value="1" <?= $search_in_title ? 'checked' : '' ?>> בכותרת</label>
      <label><input type="checkbox" name="search_desc" value="1" <?= $search_in_desc ? 'checked' : '' ?>> בתיאור</label>
    </div>
  </form>

  <div class="action-buttons">
    <button type="submit" form="search-form" class="btn-main btn-search">🔍 חפש</button>
    <a href="collections.php" class="btn-main btn-reset">🔄 איפוס</a>
    
    <?php
      $current_params = $_GET;
      $pinned_params = $current_params;
      if ($show_pinned) {
          $pinned_params['hide_pinned'] = '1';
          echo '<a href="?'.http_build_query($pinned_params).'" class="btn-main btn-toggle">🙈 הסתר נעוצים</a>';
      } else {
          unset($pinned_params['hide_pinned']);
          echo '<a href="?'.http_build_query($pinned_params).'" class="btn-main btn-toggle">📌 הצג נעוצים</a>';
      }
    ?>
    
    <a href="create_collection.php" class="btn-main btn-create admin-only">➕ צור אוסף חדש</a>
    
    <?php
      $private_params = $current_params;
      if ($show_private) {
          unset($private_params['show_private']);
          echo '<a href="?'.http_build_query($private_params).'" class="btn-main btn-toggle admin-only">🙈 הסתר פרטיים</a>';
      } else {
          $private_params['show_private'] = '1';
          echo '<a href="?'.http_build_query($private_params).'" class="btn-main btn-toggle admin-only">🔒 הצג פרטיים</a>';
      }
    ?>

    <!-- כפתור ניהול — כתווית למתג (עובד גם בלי JS) -->
    <label for="admin-switch" class="btn-main btn-toggle admin-toggle">
      <span class="when-off">🔑 מצב ניהול</span>
      <span class="when-on">🚪 יציאה ממצב ניהול</span>
    </label>
  </div>

  <div class="filter-controls">
    <?php
      // --- כפתורי מיון ---
      $sort_options = [
        'updated_desc' => 'עדכון אחרון', 'created_desc' => 'חדש → ישן', 'created_asc' => 'ישן → חדש',
        'name' => 'שם (א-ת)', 'count' => 'מס׳ פוסטרים'
      ];
      echo '<div class="button-group"><label>מיון:</label>';
      foreach ($sort_options as $key => $label) {
          $sort_params = $_GET;
          $sort_params['sort'] = $key;
          $is_active = ($sort === $key) ? 'active' : '';
          echo '<a href="?'.http_build_query($sort_params).'" class="btn-filter '.$is_active.'">'.$label.'</a>';
      }
      echo '</div>';

      // --- כפתורי כמות לעמוד ---
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
</div>
<br>

<?php if ($message): ?>
  <div class="message"><?= $message ?></div>
<?php endif; ?>

<div id="collections-list" class="page"><!-- גם כאן בתוך .page -->
<?php
// פונקציה שמציגה כרטיס אוסף יחיד
function render_collection_card($c) {
  $desc = trim($c['description'] ?? '');
  $default_desc = "[עברית]\n\n[/עברית]\n\n\n[אנגלית]\n\n[/אנגלית]";
  $is_default = (trim(str_replace(array("\r","\n"," "), '', $desc)) === trim(str_replace(array("\r","\n"," "), '', $default_desc)));
  
  $card_class = '';
  if (!empty($c['is_pinned'])) $card_class = 'pinned';
  if (!empty($c['is_private'])) $card_class = 'private';
  
  $icon = '📁';
  if (!empty($c['is_pinned'])) $icon = '📌';
  if (!empty($c['is_private'])) $icon = '🔒';

  ?>
  <div class="collection-card <?= $card_class ?>">
    <h3>
      <a href="collection.php?id=<?= (int)$c['id'] ?>">
        <?= $icon ?>
        <?= htmlspecialchars($c['name']) ?>
      </a>
    </h3>

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

    <div class="actions admin-only">
      <?php
        // נשמרים פרמטרי ה-GET הנוכחיים כדי לא לאבד חיפוש/מיון
        $qs = http_build_query($_GET);

        if (!empty($c['is_private'])) {
          // 🔓 הפוך לציבורי
          echo "<form method='post' action='?{$qs}' style='display:inline;'>";
          echo csrf_input();
          echo "<input type='hidden' name='action' value='make_public'>";
          echo "<input type='hidden' name='collection_id' value='".(int)$c['id']."'>";
          echo "<button type='submit' style='padding:0;'>🔓 הפוך לציבורי</button>";
          echo "</form>";
        } else {
          // 🔒 הפוך לפרטי
          echo "<form method='post' action='?{$qs}' style='display:inline;'>";
          echo csrf_input();
          echo "<input type='hidden' name='action' value='make_private'>";
          echo "<input type='hidden' name='collection_id' value='".(int)$c['id']."'>";
          echo "<button type='submit' style='padding:0;'>🔒 הפוך לפרטי</button>";
          echo "</form>";

          if (!empty($c['is_pinned'])) {
            // 📌 הסר נעיצה
            echo "<form method='post' action='?{$qs}' style='display:inline;'>";
            echo csrf_input();
            echo "<input type='hidden' name='action' value='unpin'>";
            echo "<input type='hidden' name='collection_id' value='".(int)$c['id']."'>";
            echo "<button type='submit' style='padding:0;'>📌 הסר נעיצה</button>";
            echo "</form>";
          } else {
            // 📌 נעיצה
            echo "<form method='post' action='?{$qs}' style='display:inline;'>";
            echo csrf_input();
            echo "<input type='hidden' name='action' value='pin'>";
            echo "<input type='hidden' name='collection_id' value='".(int)$c['id']."'>";
            echo "<button type='submit' style='padding:0;'>📌 נעיצה</button>";
            echo "</form>";
          }
        }
      ?>
      <a href="edit_collection.php?id=<?= (int)$c['id'] ?>">✏️ ערוך</a>
      <form method="post" action="?<?= http_build_query($_GET)?>" style="display:inline;">
        <?= csrf_input() ?>
        <button type="submit" name="delete_collection" value="<?= (int)$c['id'] ?>" onclick="return confirm('למחוק את האוסף?')" style="padding:0;">🗑️ מחק</button>
      </form>
      <a href="#" class="open-modal-btn" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">➕ הוסף פוסטר</a>
    </div>
  </div>
  <?php
}

// הצגת נעוצים (אם לא הוסתרו ורק בעמוד הראשון)
if ($show_pinned && $page == 1 && !empty($pinned_data)) {
  foreach($pinned_data as $c) {
    render_collection_card($c);
  }
  echo '<hr class="section-divider">';
}

// הצגת פרטיים (אם המשתמש ביקש)
if ($show_private && !empty($private_data)) {
  foreach ($private_data as $c) {
    render_collection_card($c);
  }
  echo '<hr class="section-divider">';
}


// הצגת יתר האוספים (ציבוריים ולא נעוצים)
if (!empty($public_data)) {
  foreach ($public_data as $c) {
    render_collection_card($c);
  }
} else {
    if ($search_txt !== '') {
        echo '<p style="text-align:center;">😢 לא נמצאו אוספים ציבוריים התואמים לחיפוש.</p>';
    } elseif ($total_public_collections === 0 && !$show_private && empty($pinned_data)) {
        echo '<p style="text-align:center;">😢 לא קיימים אוספים ציבוריים כרגע.</p>';
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
      <a
        href="<?= $base_url ?>&page=<?= $i ?>"
        class="<?= $i == $page ? 'active' : '' ?>"
      ><?= $i ?></a>
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

<!-- ===== JS מינימלי: שימור מצב ומודאל/תיאורים (לא חובה למתג עצמו) ===== -->
<script>
try{
  // שמירת מצב ניהול
  var key='adminMode';
  var sw=document.getElementById('admin-switch');
  if(sw){
    if(localStorage.getItem(key)==='1'){ sw.checked=true; }
    sw.addEventListener('change', function(){ localStorage.setItem(key, sw.checked?'1':'0'); });
  }

  // הסתר/הצג תיאור
  document.querySelectorAll('.toggle-desc-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var d=btn.nextElementSibling;
      if(d && d.classList.contains('collapsible')) d.classList.toggle('open');
    });
  });

  // מודאל הוספת פוסטר
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
