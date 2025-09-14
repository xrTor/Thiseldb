<?php
require_once 'server.php';
require_once 'bbcode.php'; // המרת BBCode ל-HTML

// ==========================================================
// == כל הלוגיקה לפני ה-HTML ==
// ==========================================================
$message = '';

// החזרה לעמוד קודם (אם סופק)
$redirect_location = isset($_GET['return_url']) ? $_GET['return_url'] : 'collections.php';

// לוגיקת נעיצה / ביטול נעיצה
if (isset($_GET['pin'])) {
  $pin_id = (int)$_GET['pin'];
  $stmt = $conn->prepare("UPDATE collections SET is_pinned = 1, updated_at = NOW() WHERE id = ?");
  $stmt->bind_param("i", $pin_id);
  $stmt->execute();
  header("Location: " . $redirect_location);
  exit;
}
if (isset($_GET['unpin'])) {
  $unpin_id = (int)$_GET['unpin'];
  $stmt = $conn->prepare("UPDATE collections SET is_pinned = 0, updated_at = NOW() WHERE id = ?");
  $stmt->bind_param("i", $unpin_id);
  $stmt->execute();
  header("Location: " . $redirect_location);
  exit;
}

// מחיקת אוסף
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_collection'])) {
  $cid = (int)$_POST['delete_collection'];
  $conn->query("DELETE FROM collections WHERE id = $cid");
  $conn->query("DELETE FROM poster_collections WHERE collection_id = $cid");
  $message = "🗑️ האוסף נמחק בהצלחה";
}

// טוענים header רק אחרי כל ה-headers/redirect
include 'header.php';

// --- פרמטרים: פאגינציה + מיון ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_value = isset($_GET['per_page']) ? $_GET['per_page'] : 50; // ברירת מחדל 50
$per_page = intval($per_page_value);
$per_page_for_query = ($per_page === 0) ? 999999 : $per_page;
$offset = ($page - 1) * $per_page_for_query;

// מיון (שומר על מיון גם בחיפוש AJAX דרך פרמ׳)
$sort = $_GET['sort'] ?? 'created_desc';
switch ($sort) {
  case 'created_asc': $order = "c.created_at ASC"; break;
  case 'name':        $order = "c.name ASC";        break;
  case 'count':       $order = "total_items DESC";  break;
  case 'updated_desc':$order = "c.updated_at DESC"; break;
  default:            $order = "c.created_at DESC";
}

// ספירות
$total_all_collections = $conn->query("SELECT COUNT(*) FROM collections")->fetch_row()[0];
$total_unpinned_collections = $conn->query("SELECT COUNT(*) FROM collections WHERE is_pinned = 0")->fetch_row()[0];
$total_pages = $per_page_for_query > 0 ? ceil($total_unpinned_collections / $per_page_for_query) : 0;


// שליפת נעוצים (תמיד מוצגים למעלה בעמוד 1)
$pinned_res = $conn->query("
  SELECT c.*, COUNT(pc.poster_id) AS total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE c.is_pinned = 1
  GROUP BY c.id
  ORDER BY c.name ASC
");

// שליפת לא-נעוצים עם מיון ופאגינציה
$unpinned_res = $conn->query("
  SELECT c.*, COUNT(pc.poster_id) AS total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE c.is_pinned = 0
  GROUP BY c.id
  ORDER BY $order
  LIMIT $per_page_for_query OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 רשימת אוספים</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:10px; }
    .main-controls { display:flex; justify-content:center; align-items:center; gap:20px; margin-top:30px; flex-wrap:wrap; }
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
    .add-new a { background:#007bff; color:white; padding:10px 20px; border-radius:1px; text-decoration:none; }
 
    .per-page-selector, .search-box, .sort-box { display:flex; align-items:center; gap:8px; }
    .per-page-selector select, .sort-box select { font-size:16px; padding:8px; border-radius:6px; cursor:pointer; }
    .search-box input[type="text"] { padding:6px; border:1px solid #ccc; border-radius:4px; }
    .search-box label { font-size:14px; }
    .search-box button { padding:6px 10px; border-radius:4px; border:1px solid #ccc; background:#f5f5f5; cursor:pointer; }

    .pagination { text-align:center; margin-top:20px; }
    .pagination a {
      margin:0 6px; padding:6px 10px; background:#eee; border-radius:4px;
      text-decoration:none; color:#333;
    }
    .pagination a.active { font-weight:bold; background:#ccc; }

    .toggle-desc-btn {
      background:#f0f0f0; border:1px solid #ccc; padding:6px 10px;
      border-radius:4px; cursor:pointer; font-size:13px; margin-bottom:8px;
    }
    .toggle-desc-btn:hover { background:#e2e2e2; }
    .description.collapsible {
      display:none; background:#fafafa; padding:8px; border:1px solid #ddd; border-radius:6px; margin-top:6px;
    }
    .description.collapsible.open { display:block; }

    .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
    .modal-content {
      background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888;
      width:80%; max-width:500px; border-radius:8px; position:relative;
    }
    .close-btn { color:#aaa; position:absolute; left:15px; top:5px; font-size:28px; font-weight:bold; }
    .close-btn:hover, .close-btn:focus { color:black; text-decoration:none; cursor:pointer; }
    .modal-content h3 { margin-top:0; }
    .modal-content textarea {
      width:100%; font-size:15px; padding:8px; border-radius:7px; border:1px solid #bbb;
      background:#fafcff; margin-bottom:10px; resize:vertical; min-height:120px;
    }
    .modal-content button { width:100%; font-size:16px; padding:10px 0; border-radius:7px; border:none; background:#007bff; color:#fff; margin-top:6px; cursor:pointer; }
  /* 🎨 כפתורי חיפוש ואיפוס */
.search-box button {
  font-weight: bold;
  border: none;
  border-radius: 20px;
  padding: 6px 14px;
  cursor: pointer;
  transition: 0.2s;
}

#reset-search {
  background: #28a745;
  color: #fff;
}
#reset-search:hover {
  background: #b02a37;
}

.search-box .search-btn {
  background: #28a745;
  color: #fff;
}
.search-box .search-btn:hover {
  background: #218838;
}
/* כפתור איפוס */
#reset-search {
  background: #218838;
  color: #fff;
  border: none;
  border-radius: 1px;
  padding: 6px 14px;
  font-weight: bold;
  cursor: pointer;
  transition: 0.2s;
}
#reset-search:hover {
  background: #e0ffad; color:black;
}

/* מיכל לכפתורים */
.add-new {
  display: flex;
  gap: 10px; /* רווח קבוע בין הכפתורים */
  align-items: center; /* מיישר אותם לאותו גובה */
}

/* כפתור בסיס אחיד */
.btn-main {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 160px;     /* גודל מינימלי אחיד */
  font-size: 16px;
  font-weight: bold;
  padding: 10px 20px;
  border-radius: 8px;
  text-decoration: none;
  text-align: center;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}
.btn-main:hover {
  transform: translateY(-2px);
}

/* כחול – צור אוסף חדש */
.btn-create {
  background: #007bff;
  color: #fff;
  border: none;
}
.btn-create:hover { background: #0056b3; }

/* ירוק – איפוס */
.btn-reset {
  background: #28a745;
  color: #fff;
  border: none;
}
.btn-reset:hover { background: #218838; }


  .add-new a  {height: 34px; text-align: center; color: white}

  .add-new a:hover { background:#e0ffad;
    /* בסיס לכל הכפתורים */
.btn-main {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 160px;
  height: 45px;          /* גובה אחיד */
  font-size: 16px;
  font-weight: bold;
  padding: 0 20px;       /* רווח פנימי צדדים */
  border-radius: 8px;
  text-decoration: none;
  text-align: center;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
  color: #fff;           /* טקסט לבן */
}
.btn-main:hover {
  transform: translateY(-2px);
}

/* כחול – צור אוסף חדש */
.btn-create {
  background: #007bff;
  border: none;
}
.btn-create:hover { background: #0056b3; }

/* ירוק – איפוס */
.btn-reset {
  background: #28a745;
  border: none;
}
.btn-reset:hover { background: #218838; }
/* כפתור בסיס אחיד */
.btn-main {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 160px;
  height: 45px;          /* גובה אחיד */
  font-size: 16px;
  font-weight: bold;
  padding: 0 20px;
  border-radius: 8px;
  text-decoration: none;
  text-align: center;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
  color: #fff;           /* טקסט לבן לכל הכפתורים */
}
.btn-main:hover {
  transform: translateY(-2px);
}

/* כחול – צור אוסף חדש */
.btn-create {
  background: #007bff;
  border: none;
}
.btn-create:hover { background: #0056b3; }

/* ירוק – איפוס */
.btn-reset {
  background: #28a745;
  border: none;
}
.btn-reset:hover { background: #218838; }

  </style>
</head>
<body>

<h2 style="text-align:center;">📁 רשימת אוספים: <?= $total_all_collections ?></h2>

<div class="main-controls">
<div class="add-new">
  <a href="create_collection.php" class="btn-main btn-create">➕ צור אוסף חדש</a>
  <button type="button" id="reset-search" class="btn-main btn-reset">🔄 איפוס</button>
</div>



  <div class="search-box">
    <input type="text" id="search-text" placeholder="🔍 חיפוש...">
    <label><input type="checkbox" id="search-title" checked> בכותרת</label>
    <label><input type="checkbox" id="search-desc" checked> בתיאור</label>
  </div>

  <div class="sort-box">
    <form method="get" id="sortForm">
      <label>מיון:</label>
      <select name="sort" onchange="this.form.submit()">
        <option value="created_desc" <?= $sort==='created_desc'?'selected':''; ?>>חדש → ישן</option>
        <option value="created_asc"  <?= $sort==='created_asc'?'selected':''; ?>>ישן → חדש</option>
        <option value="updated_desc" <?= $sort==='updated_desc'?'selected':''; ?>>עדכון אחרון</option>
        <option value="name"         <?= $sort==='name'?'selected':''; ?>>שם (א-ת)</option>
        <option value="count"        <?= $sort==='count'?'selected':''; ?>>מס' פוסטרים</option>
      </select>
      <input type="hidden" name="per_page" value="<?= htmlspecialchars($per_page_value) ?>">
    </form>
  </div>

  <div class="per-page-selector">
    <form method="get" id="perPageForm">
        <select name="per_page" id="per_page" onchange="this.form.submit()">
            <option value="20"  <?= ($per_page_value == 20)  ? 'selected' : '' ?>>הצג 20</option>
            <option value="50"  <?= ($per_page_value == 50)  ? 'selected' : '' ?>>הצג 50</option>
            <option value="100" <?= ($per_page_value == 100) ? 'selected' : '' ?>>הצג 100</option>
            <option value="250" <?= ($per_page_value == 250) ? 'selected' : '' ?>>הצג 250</option>
            <option value="0"   <?= ($per_page_value == 0)   ? 'selected' : '' ?>>הצג הכל</option>
        </select>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    </form>
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

  // תבנית ברירת המחדל כפי שבטופס יצירת אוסף
  $default_desc = "[עברית]\n\n[/עברית]\n\n\n[אנגלית]\n\n[/אנגלית]";
  $is_default   = (trim(str_replace(["\r","\n"," "], '', $desc)) === trim(str_replace(["\r","\n"," "], '', $default_desc)));
  ?>
  <div class="collection-card <?= !empty($c['is_pinned']) ? 'pinned' : '' ?>">
    <h3>
      <a href="collection.php?id=<?= (int)$c['id'] ?>">
        <?= !empty($c['is_pinned']) ? '📌' : '📁' ?>
        <?= htmlspecialchars($c['name']) ?>
      </a>
    </h3>

    <?php if (!empty($desc) && !$is_default): ?>
      <button class="toggle-desc-btn" onclick="this.nextElementSibling.classList.toggle('open')">📝 הצג / הסתר תיאור</button>
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
        <a href="?unpin=<?= (int)$c['id'] ?>&sort=<?= urlencode($_GET['sort'] ?? '') ?>&per_page=<?= urlencode($_GET['per_page'] ?? '') ?>">📌 הסר נעיצה</a>
      <?php else: ?>
        <a href="?pin=<?= (int)$c['id'] ?>&sort=<?= urlencode($_GET['sort'] ?? '') ?>&per_page=<?= urlencode($_GET['per_page'] ?? '') ?>">📌 נעיצה</a>
      <?php endif; ?>

      <a href="edit_collection.php?id=<?= (int)$c['id'] ?>">✏️ ערוך</a>

      <form method="post" style="display:inline;">
        <button type="submit" name="delete_collection" value="<?= (int)$c['id'] ?>" onclick="return confirm('למחוק את האוסף?')">🗑️ מחק</button>
      </form>

      <a href="#" class="open-modal-btn" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">➕ הוסף פוסטר</a>
    </div>
  </div>
  <?php
}

// הצגת נעוצים בראש (רק בעמוד 1)
if ($page == 1 && $pinned_res && $pinned_res->num_rows > 0) {
  while ($c = $pinned_res->fetch_assoc()) {
    render_collection_card($c);
  }
  echo '<hr style="max-width:1100px; margin: 20px auto; border-top: 1px solid #ccc;">';
}

// הצגת יתר האוספים
if ($unpinned_res && $unpinned_res->num_rows > 0) {
  while ($c = $unpinned_res->fetch_assoc()) {
    render_collection_card($c);
  }
} elseif ($pinned_res->num_rows === 0 && $total_unpinned_collections === 0) {
  echo '<p style="text-align:center;">😢 לא קיימים אוספים כרגע</p>';
}
?>
</div>

<?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a
        href="?page=<?= $i ?>&per_page=<?= urlencode($per_page_value) ?>&sort=<?= urlencode($sort) ?>"
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
const closeBtn = document.querySelector('.close-btn');
const modalTitleSpan = document.querySelector('#modal-title span');
const modalCollectionIdInput = document.getElementById('modal-collection-id');
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

// -------- חיפוש AJAX עם צ׳קבוקסים + איפוס --------
function doSearch(){
  const txt     = document.getElementById('search-text').value.trim();
  const inTitle = document.getElementById('search-title').checked ? 1 : 0;
  const inDesc  = document.getElementById('search-desc').checked  ? 1 : 0;

  // אם שדה חיפוש ריק → נחזור לעמוד הרגיל (כדי שהנעוצים ישובו לראש)
  if (txt === '') {
    // שומרים גם sort & per_page בבקשת הרענון
    const qs = new URLSearchParams({
      sort: '<?= $sort ?>',
      per_page: '<?= $per_page_value ?>'
    });
    window.location.href = 'collections.php?' + qs.toString();
    return;
  }

  // טעינת תוצאות AJAX (שומר מיון ו-per_page)
  const qs = new URLSearchParams({
    txt: txt,
    title: inTitle,
    desc: inDesc,
    sort: '<?= $sort ?>',
    per_page: '<?= $per_page_value ?>'
  });

  fetch('collections_search.php?' + qs.toString())
    .then(r => r.text())
    .then(html => { document.getElementById('collections-list').innerHTML = html; });
}

document.getElementById('search-text').addEventListener('input', doSearch);
document.getElementById('search-title').addEventListener('change', doSearch);
document.getElementById('search-desc').addEventListener('change', doSearch);

// כפתור איפוס → חזרה לעמוד נקי (נעוצים נשארים למעלה)
document.getElementById('reset-search').addEventListener('click', () => {
  const qs = new URLSearchParams({
    sort: '<?= $sort ?>',
    per_page: '<?= $per_page_value ?>'
  });
  window.location.href = 'collections.php?' + qs.toString();
});
</script>
<?php include 'footer.php'; ?>