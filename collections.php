<?php
require_once 'server.php';
require_once 'bbcode.php'; // המרת BBCode ל-HTML

// ==========================================================
// == כל הלוגיקה לפני ה-HTML ==
// ==========================================================
$message = '';

// --- לוגיקת נעיצה / ביטול נעיצה ---
if (isset($_GET['pin'])) {
  $pin_id = (int)$_GET['pin'];
  $stmt = $conn->prepare("UPDATE collections SET is_pinned = 1, updated_at = NOW() WHERE id = ?");
  $stmt->bind_param("i", $pin_id);
  $stmt->execute();
  $redirect_params = $_GET;
  unset($redirect_params['pin']);
  header("Location: collections.php?" . http_build_query($redirect_params));
  exit;
}
if (isset($_GET['unpin'])) {
  $unpin_id = (int)$_GET['unpin'];
  $stmt = $conn->prepare("UPDATE collections SET is_pinned = 0, updated_at = NOW() WHERE id = ?");
  $stmt->bind_param("i", $unpin_id);
  $stmt->execute();
  $redirect_params = $_GET;
  unset($redirect_params['unpin']);
  header("Location: collections.php?" . http_build_query($redirect_params));
  exit;
}

// מחיקת אוסף
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_collection'])) {
  $cid = (int)$_POST['delete_collection'];
  $conn->query("DELETE FROM collections WHERE id = $cid");
  $conn->query("DELETE FROM poster_collections WHERE collection_id = $cid");
  $message = "🗑️ האוסף נמחק בהצלחה";
}

// --- פרמטרים: פאגינציה, מיון וחיפוש ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_value = $_GET['per_page'] ?? 50;
$per_page = intval($per_page_value);
$per_page_for_query = ($per_page === 0) ? 999999 : $per_page;
$offset = ($page - 1) * $per_page_for_query;

$sort = $_GET['sort'] ?? 'created_desc';
switch ($sort) {
  case 'created_asc': $order = "c.created_at ASC"; break;
  case 'name':        $order = "c.name ASC";        break;
  case 'count':       $order = "total_items DESC";  break;
  case 'updated_desc':$order = "c.updated_at DESC"; break;
  default:            $order = "c.created_at DESC";
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

// --- בניית שאילתות דינמית (בשיטה חדשה ויציבה) ---
$where_conditions = ["c.is_pinned = 0"];

if ($search_txt !== '' && ($search_in_title || $search_in_desc)) {
    // מנקים את טקסט החיפוש כדי למנוע SQL Injection
    $safe_search_txt = $conn->real_escape_string($search_txt);
    $search_term_like = "%{$safe_search_txt}%";
    
    $search_or_conditions = [];
    if ($search_in_title) {
        $search_or_conditions[] = "c.name LIKE '{$search_term_like}'";
    }
    if ($search_in_desc) {
        $search_or_conditions[] = "c.description LIKE '{$search_term_like}'";
    }
    
    if (!empty($search_or_conditions)) {
        $where_conditions[] = "(" . implode(" OR ", $search_or_conditions) . ")";
    }
}

$final_where_clause = implode(" AND ", $where_conditions);

// ספירות
$total_all_collections = $conn->query("SELECT COUNT(*) FROM collections")->fetch_row()[0];
$count_sql = "SELECT COUNT(DISTINCT c.id) FROM collections c WHERE " . $final_where_clause;
$total_unpinned_collections = $conn->query($count_sql)->fetch_row()[0] ?? 0;
$total_pages = $per_page_for_query > 0 ? ceil($total_unpinned_collections / $per_page_for_query) : 0;

// שליפת נעוצים
$pinned_res = $conn->query("
  SELECT c.*, COUNT(pc.poster_id) AS total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE c.is_pinned = 1
  GROUP BY c.id
  ORDER BY c.name ASC
");

// שליפת לא-נעוצים
$unpinned_sql = "
  SELECT c.*, COUNT(pc.poster_id) AS total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE $final_where_clause
  GROUP BY c.id
  ORDER BY $order
  LIMIT ? OFFSET ?
";

$unpinned_data = [];
if ($stmt_data = $conn->prepare($unpinned_sql)) {
    // כאן נשתמש ב-bind_param רק עבור מספרים (LIMIT/OFFSET), שזה עובד תמיד
    $stmt_data->bind_param('ii', $per_page_for_query, $offset);
    $stmt_data->execute();
    $result = $stmt_data->get_result();
    while ($row = $result->fetch_assoc()) {
        $unpinned_data[] = $row;
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
    body { font-family:Arial, sans-serif; direction:rtl; background:#f9f9f9; padding:10px; }
    .collection-card {
      background:white; padding:20px; margin:10px auto;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); max-width:1100px; position:relative; text-align:right;
    }
    .collection-card.pinned { background-color:#fffff0; border-left:5px solid #ffd700; }
    .collection-card h3 { margin:0 0 10px 0; font-size:20px; }
    .collection-card .description { color:#555; margin-bottom:10px; }
    .collection-card .meta-info { display: flex; align-items: center; gap: 15px; color: #888; font-size: 13px; }
    .collection-card .actions { position:absolute; top:20px; left:20px; }
    .collection-card .actions a, .collection-card .actions button {
      margin-right:6px; text-decoration:none; font-size:14px; background:none; border:none; color:#007bff; cursor:pointer;
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

    /* --- עיצוב חדש לבקרות העליונות --- */
    .main-controls { display:flex; flex-direction: column; align-items: center; gap: 20px; margin-top:30px; }
    .search-form { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 15px; }
    .search-form input[type="text"] { font-size: 16px; padding: 10px; border: 1px solid #ccc; border-radius: 8px; width: 300px; }
    .search-form .checkbox-group { display: flex; gap: 15px; }
    .search-form label { font-size: 14px; user-select: none; }
    
    .action-buttons { display: flex; justify-content: center; gap: 15px; width: 100%; }
    .filter-controls { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
    .sort-box, .per-page-selector { display:flex; align-items:center; gap:8px; }
    .sort-box select, .per-page-selector select { font-size:16px; padding:8px; border-radius:6px; cursor:pointer; }
    
    .btn-main {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 160px; height: 45px; font-size: 16px; font-weight: bold;
      padding: 0 20px; border-radius: 8px; text-decoration: none;
      text-align: center; cursor: pointer; transition: background 0.2s, transform 0.1s;
      color: #fff !important; border: none;
    }
    .btn-main:hover { transform: translateY(-2px); color: #fff !important; }
    .btn-search { background: #28a745; }
    .btn-search:hover { background: #218838; }
    .btn-create { background: #007bff; }
    .btn-create:hover { background: #0056b3; }
    .btn-reset { background: deepskyblue; }
    .btn-reset:hover { background: deepskyblue; }
  </style>
</head>
<body>

<h2 style="text-align:center;">📁 רשימת אוספים: <?= $total_all_collections ?></h2>

<div class="main-controls">
  <form method="get" action="collections.php" class="search-form" id="search-form">
    <input type="text" name="txt" placeholder="🔍 חיפוש אוסף..." value="<?= htmlspecialchars($search_txt) ?>">
    <div class="checkbox-group">
      <label><input type="checkbox" name="search_title" value="1" <?= $search_in_title ? 'checked' : '' ?>> בכותרת</label>
      <label><input type="checkbox" name="search_desc" value="1" <?= $search_in_desc ? 'checked' : '' ?>> בתיאור</label>
    </div>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    <input type="hidden" name="per_page" value="<?= htmlspecialchars($per_page_value) ?>">
  </form>

  <div class="action-buttons">
    <button type="submit" form="search-form" class="btn-main btn-search">🔍 חפש</button>
    <a href="create_collection.php" class="btn-main btn-create">➕ צור אוסף חדש</a>
    <a href="collections.php" class="btn-main btn-reset">🔄 איפוס</a>
  </div>

  <div class="filter-controls">
    <div class="sort-box">
      <form method="get" action="collections.php" id="sort-form">
        <label>מיון:</label>
        <select name="sort" onchange="this.form.submit()">
          <option value="created_desc" <?= $sort==='created_desc'?'selected':''; ?>>חדש → ישן</option>
          <option value="created_asc"  <?= $sort==='created_asc'?'selected':''; ?>>ישן → חדש</option>
          <option value="updated_desc" <?= $sort==='updated_desc'?'selected':''; ?>>עדכון אחרון</option>
          <option value="name"         <?= $sort==='name'?'selected':''; ?>>שם (א-ת)</option>
          <option value="count"        <?= $sort==='count'?'selected':''; ?>>מס' פוסטרים</option>
        </select>
        <input type="hidden" name="per_page" value="<?= htmlspecialchars($per_page_value) ?>">
        <input type="hidden" name="txt" value="<?= htmlspecialchars($search_txt) ?>">
        <?php if ($search_in_title): ?><input type="hidden" name="search_title" value="1"><?php endif; ?>
        <?php if ($search_in_desc): ?><input type="hidden" name="search_desc" value="1"><?php endif; ?>
      </form>
    </div>
    <div class="per-page-selector">
      <form method="get" action="collections.php" id="per-page-form">
          <select name="per_page" onchange="this.form.submit()">
              <option value="20"  <?= ($per_page_value == 20)  ? 'selected' : '' ?>>הצג 20</option>
              <option value="50"  <?= ($per_page_value == 50)  ? 'selected' : '' ?>>הצג 50</option>
              <option value="100" <?= ($per_page_value == 100) ? 'selected' : '' ?>>הצג 100</option>
              <option value="250" <?= ($per_page_value == 250) ? 'selected' : '' ?>>הצג 250</option>
              <option value="0"   <?= ($per_page_value == 0)   ? 'selected' : '' ?>>הצג הכל</option>
          </select>
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <input type="hidden" name="txt" value="<?= htmlspecialchars($search_txt) ?>">
          <?php if ($search_in_title): ?><input type="hidden" name="search_title" value="1"><?php endif; ?>
          <?php if ($search_in_desc): ?><input type="hidden" name="search_desc" value="1"><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<br>

<?php if ($message): ?>
  <div class="message"><?= $message ?></div>
<?php endif; ?>

<div id="collections-list">
<?php
// פונקציה שמציגה כרטיס אוסף יחיד
function render_collection_card($c) {
  $desc = trim($c['description'] ?? '');
  $default_desc = "[עברית]\n\n[/עברית]\n\n\n[אנגלית]\n\n[/אנגלית]";
  $is_default = (trim(str_replace(["\r","\n"," "], '', $desc)) === trim(str_replace(["\r","\n"," "], '', $default_desc)));
  ?>
  <div class="collection-card <?= !empty($c['is_pinned']) ? 'pinned' : '' ?>">
    <h3>
      <a href="collection.php?id=<?= (int)$c['id'] ?>">
        <?= !empty($c['is_pinned']) ? '📌' : '📁' ?>
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

    <div class="actions">
      <?php if (!empty($c['is_pinned'])): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['unpin' => $c['id']])) ?>">📌 הסר נעיצה</a>
      <?php else: ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['pin' => $c['id']])) ?>">📌 נעיצה</a>
      <?php endif; ?>
      <a href="edit_collection.php?id=<?= (int)$c['id'] ?>">✏️ ערוך</a>
      <form method="post" action="?<?= http_build_query($_GET)?>" style="display:inline;">
        <button type="submit" name="delete_collection" value="<?= (int)$c['id'] ?>" onclick="return confirm('למחוק את האוסף?')">🗑️ מחק</button>
      </form>
      <a href="#" class="open-modal-btn" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">➕ הוסף פוסטר</a>
    </div>
  </div>
  <?php
}

// הצגת נעוצים בראש (רק בעמוד 1 וללא חיפוש)
if ($page == 1 && $search_txt === '' && $pinned_res && $pinned_res->num_rows > 0) {
  while ($c = $pinned_res->fetch_assoc()) {
    render_collection_card($c);
  }
  echo '<hr style="max-width:1100px; margin: 20px auto; border-top: 1px solid #ccc;">';
}

// הצגת יתר האוספים (או תוצאות חיפוש)
if (!empty($unpinned_data)) {
  foreach ($unpinned_data as $c) {
    render_collection_card($c);
  }
} else {
    if ($search_txt !== '') {
        echo '<p style="text-align:center;">😢 לא נמצאו אוספים התואמים לחיפוש.</p>';
    } elseif ($total_all_collections === 0) {
        echo '<p style="text-align:center;">😢 לא קיימים אוספים כרגע.</p>';
    }
}
?>
</div>

<?php if ($total_pages > 1): ?>
  <div class="pagination">
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

<div id="add-poster-modal" class="modal">
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
</body>
</html>

<script>
// -------- מודאל הוספת פוסטר --------
const modal = document.getElementById('add-poster-modal');
const closeBtn = modal.querySelector('.close-btn');
const modalTitleSpan = modal.querySelector('#modal-title span');
const modalCollectionIdInput = modal.querySelector('#modal-collection-id');
const openModalButtons = document.querySelectorAll('.open-modal-btn');

openModalButtons.forEach(button => {
  button.addEventListener('click', function(event) {
    event.preventDefault();
    modalCollectionIdInput.value = this.dataset.id;
    modalTitleSpan.textContent = this.dataset.name;
    modal.style.display = 'block';
  });
});
closeBtn.addEventListener('click', () => modal.style.display = 'none');
window.addEventListener('click', function(event) { if (event.target == modal) modal.style.display = 'none'; });

// -------- הסתרת/הצגת תיאור --------
document.querySelectorAll('.toggle-desc-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const desc = btn.nextElementSibling;
        if (desc && desc.classList.contains('collapsible')) {
            desc.classList.toggle('open');
        }
    });
});

// --- קישור כפתור החיפוש לטופס הנכון ---
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.search-form');
    if(searchForm) {
        const searchBtn = document.querySelector('.btn-search');
        if(searchBtn) {
            searchBtn.setAttribute('form', 'search-form');
        }
    }
});
</script>
<?php include 'footer.php'; ?>