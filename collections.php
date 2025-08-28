<?php
require_once 'server.php';

// ==========================================================
// == כל הלוגיקה שמטפלת בפעולות מרוכזת כאן, לפני ה-HTML ==
// ==========================================================
$message = '';

// בדיקה אם קיים URL לחזרה
$redirect_location = isset($_GET['return_url']) ? $_GET['return_url'] : 'collections.php';

// לוגיקה לנעיצה והסרת נעיצה
if (isset($_GET['pin'])) {
  $pin_id = (int)$_GET['pin'];
  $stmt = $conn->prepare("UPDATE collections SET is_pinned = 1 WHERE id = ?");
  $stmt->bind_param("i", $pin_id);
  $stmt->execute();
  header("Location: " . $redirect_location);
  exit;
}
if (isset($_GET['unpin'])) {
  $unpin_id = (int)$_GET['unpin'];
  $stmt = $conn->prepare("UPDATE collections SET is_pinned = 0 WHERE id = ?");
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

// טעינת החלק העליון של האתר רק אחרי שהסתיימה כל לוגיקת הרקע
include 'header.php';

// --- שדרוג: לוגיקת תצוגה ופאגינציה חדשה ---

// 1. קליטת פרמטרים מה-URL
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_value = isset($_GET['per_page']) ? $_GET['per_page'] : 50; // ברירת מחדל 50
$per_page = intval($per_page_value);

if ($per_page === 0) { // אם המשתמש בחר "הכל"
    $per_page_for_query = 999999;
} else {
    $per_page_for_query = $per_page;
}

// 2. ספירת סך כל האוספים (לתצוגה בכותרת)
$total_all_collections = $conn->query("SELECT COUNT(*) FROM collections")->fetch_row()[0];

// 3. ספירת האוספים הלא-נעוצים (עבור חישוב הפאגינציה)
$total_unpinned_collections = $conn->query("SELECT COUNT(*) FROM collections WHERE is_pinned = 0")->fetch_row()[0];
$total_pages = ceil($total_unpinned_collections / $per_page_for_query);
$offset = ($page - 1) * $per_page_for_query;

// 4. שליפה כפולה: קודם כל הנעוצים, ואז הרגילים עם פאגינציה
// שליפת כל האוספים הנעוצים
$pinned_res = $conn->query("
  SELECT c.*, COUNT(pc.poster_id) as total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE c.is_pinned = 1
  GROUP BY c.id
  ORDER BY c.name ASC
");

// שליפת האוספים הרגילים עם פאגינציה
$unpinned_res = $conn->query("
  SELECT c.*, COUNT(pc.poster_id) as total_items
  FROM collections c
  LEFT JOIN poster_collections pc ON c.id = pc.collection_id
  WHERE c.is_pinned = 0
  GROUP BY c.id
  ORDER BY c.created_at DESC
  LIMIT $per_page_for_query OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📁 רשימת אוספים</title>
  <style>
    body { font-family:Arial; direction:rtl; background:#f9f9f9; padding:10px; }
    .main-controls { display: flex; justify-content: center; align-items: center; gap: 20px; margin-top: 30px; }
    .collection-card {
      background:white; padding:20px; margin:10px auto;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); max-width:1100px; position:relative; text-align: right;
    }
    .collection-card.pinned {
      background-color: #fffff0;
      border-left: 5px solid #ffd700;
    }
    .collection-card h3 { margin:0 0 10px 0; font-size:20px; }
    .collection-card .description { color:#555; margin-bottom:10px; }
    .collection-card .count { color:#999; font-size:14px; }
    .collection-card .actions { position:absolute; top:20px; left:20px; }
    .collection-card .actions a, .collection-card .actions button {
      margin-right:6px; text-decoration:none; font-size:14px;
      background:none; border:none; color:#007bff; cursor:pointer;
    }
    .collection-card .actions a:hover, .collection-card .actions button:hover {
      text-decoration:underline;
    }
    .message {
      background:#ffe; padding:10px; border-radius:6px; margin-bottom:10px;
      border:1px solid #ddc; color:#333; max-width:600px; margin:auto;
    }
    .add-new a {
      background:#007bff; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;
    }
    .per-page-selector { display: flex; align-items: center; gap: 8px; }
    .per-page-selector select { font-size: 16px; padding: 8px; border-radius: 6px; cursor:pointer; }
    .pagination {
      text-align:center; margin-top:20px;
    }
    .pagination a {
      margin:0 6px; padding:6px 10px; background:#eee; border-radius:4px;
      text-decoration:none; color:#333;
    }
    .pagination a.active {
      font-weight:bold; background:#ccc;
    }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; position: relative; }
    .close-btn { color: #aaa; position: absolute; left: 15px; top: 5px; font-size: 28px; font-weight: bold; }
    .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
    .modal-content h3 { margin-top: 0; }
    .modal-content textarea { width: 100%; font-size: 15px; padding: 8px; border-radius: 7px; border: 1px solid #bbb; background: #fafcff; margin-bottom: 10px; resize: vertical; min-height: 120px; }
    .modal-content button { width: 100%; font-size: 16px; padding: 10px 0; border-radius: 7px; border: none; background: #007bff; color: #fff; margin-top: 6px; cursor: pointer; }
  </style>
</head>
<body>

<h2 style="text-align:center;">📁 רשימת אוספים: <?= $total_all_collections ?></h2>

<div class="main-controls">
  <div class="add-new">
    <a href="create_collection.php">➕ צור אוסף חדש</a>
  </div>
  <div class="per-page-selector">
    <form method="get" id="perPageForm">
        <select name="per_page" id="per_page" onchange="this.form.submit()">
            <option value="20" <?= ($per_page_value == 20) ? 'selected' : '' ?>>הצג 20 בעמוד</option>
            <option value="50" <?= ($per_page_value == 50) ? 'selected' : '' ?>>הצג 50 בעמוד</option>
            <option value="100" <?= ($per_page_value == 100) ? 'selected' : '' ?>>הצג 100 בעמוד</option>
            <option value="250" <?= ($per_page_value == 250) ? 'selected' : '' ?>>הצג 250 בעמוד</option>
            <option value="0" <?= ($per_page_value == 0) ? 'selected' : '' ?>>הצג הכל</option>
        </select>
    </form>
  </div>
</div>
<br>

<?php if ($message): ?>
  <div class="message"><?= $message ?></div>
<?php endif; ?>

<?php
// פונקציית עזר להצגת כרטיס אוסף
function render_collection_card($c) {
?>
  <div class="collection-card <?= !empty($c['is_pinned']) ? 'pinned' : '' ?>">
    <h3><a href="collection.php?id=<?= $c['id'] ?>">
      <?= !empty($c['is_pinned']) ? '📌' : '📁' ?>
      <?= htmlspecialchars($c['name']) ?>
    </a></h3>
    <div class="description"><?= htmlspecialchars($c['description']) ?></div>
    <div class="count">🎞️ <?= $c['total_items'] ?> פוסטרים</div>
    <div class="actions">
      <?php if (!empty($c['is_pinned'])): ?>
        <a href="?unpin=<?= $c['id'] ?>">📌 הסר נעיצה</a>
      <?php else: ?>
        <a href="?pin=<?= $c['id'] ?>">📌 נעיצה</a>
      <?php endif; ?>
      <a href="edit_collection.php?id=<?= $c['id'] ?>">✏️ ערוך</a>
      <form method="post" style="display:inline;">
        <button type="submit" name="delete_collection" value="<?= $c['id'] ?>" onclick="return confirm('למחוק את האוסף?')">🗑️ מחק</button>
      </form>
      <a href="#" class="open-modal-btn" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">➕ הוסף פוסטר</a>
    </div>
  </div>
<?php
}

// הצגת האוספים הנעוצים (אם אנחנו בעמוד הראשון)
if ($page == 1 && $pinned_res->num_rows > 0) {
    // echo '<hr style="max-width:1100px; margin: 20px auto; border-top: 1px solid #ccc;">';
    while ($c = $pinned_res->fetch_assoc()) {
        render_collection_card($c);
    }
    echo '<hr style="max-width:1100px; margin: 20px auto; border-top: 1px solid #ccc;">';
}

// הצגת האוספים הרגילים
if ($unpinned_res->num_rows > 0) {
    while ($c = $unpinned_res->fetch_assoc()) {
        render_collection_card($c);
    }
} elseif ($pinned_res->num_rows === 0 && $total_unpinned_collections === 0) {
    // הצג הודעה רק אם באמת אין אוספים לא נעוצים
    echo '<p style="text-align:center;">😢 לא קיימים אוספים (לא נעוצים) כרגע</p>';
}

?>

<?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?page=<?= $i ?>&per_page=<?= $per_page_value ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
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
// קבלת רכיבי החלון הקופץ
const modal = document.getElementById('add-poster-modal');
const closeBtn = document.querySelector('.close-btn');
const modalTitleSpan = document.querySelector('#modal-title span');
const modalCollectionIdInput = document.getElementById('modal-collection-id');
const openModalButtons = document.querySelectorAll('.open-modal-btn');

// פונקציה לפתיחת החלון
function openModal(collectionId, collectionName) {
  modalCollectionIdInput.value = collectionId;
  modalTitleSpan.textContent = collectionName;
  modal.style.display = 'block';
}

// פונקציה לסגירת החלון
function closeModal() {
  modal.style.display = 'none';
}

// הוספת מאזיני אירועים (event listeners)
openModalButtons.forEach(button => {
  button.addEventListener('click', function(event) {
    event.preventDefault(); // מונע מהקישור לרענן את הדף
    const collectionId = this.dataset.id;
    const collectionName = this.dataset.name;
    openModal(collectionId, collectionName);
  });
});

closeBtn.addEventListener('click', closeModal);

// סגירת החלון בלחיצה על הרקע האפור
window.addEventListener('click', function(event) {
  if (event.target == modal) {
    closeModal();
  }
});
</script>
<?php include 'footer.php'; ?>