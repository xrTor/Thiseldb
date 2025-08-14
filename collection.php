<?php
require_once 'server.php';
include 'header.php';

set_time_limit(3000000);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) {
  echo "<p>❌ אוסף לא צוין</p>";
  include 'footer.php'; exit;
}

$stmt = $conn->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->bind_param("i", $id); $stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<p>❌ האוסף לא נמצא</p>";
  include 'footer.php'; exit;
}
$collection = $result->fetch_assoc();
$stmt->close();

// =================================================================
// == שדרוג: לוגיקת סינון מתקדמת שעובדת יחד עם החיפוש הקיים שלך ==
// =================================================================
$types_list = [];
$types_result = $conn->query("SELECT id, label_he FROM poster_types ORDER BY sort_order ASC");
while($row = $types_result->fetch_assoc()) $types_list[] = $row;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 250;
$offset = ($page - 1) * $per_page;

// איסוף כל הפילטרים מה-URL
$filters = [
    'q' => $_GET['q'] ?? '',
    'type_id' => $_GET['type_id'] ?? '',
    'min_year' => $_GET['min_year'] ?? '',
    'max_year' => $_GET['max_year'] ?? '',
    'min_rating' => $_GET['min_rating'] ?? ''
];

$where_conditions = ["pc.collection_id = ?"];
$params = [$id];
$types = "i";
$filter_query_string = ''; // מחרוזת שתוצמד לקישורי העמודים

// שמירה על לוגיקת החיפוש המקורית שלך
if (!empty($filters['q'])) {
    $keyword = $filters['q'];
    $filter_query_string .= "&q=" . urlencode($keyword);
    $searchFields = [
        "p.title_en", "p.title_he", "p.plot", "p.plot_he", "p.actors", "p.genre",
        "p.directors", "p.writers", "p.producers", "p.composers", "p.cinematographers",
        "p.languages", "p.countries", "p.imdb_id", "p.year", "p.tvdb_id"
    ];
    $like = "%$keyword%";
    $like_parts = [];
    foreach ($searchFields as $field) {
        $like_parts[] = "$field LIKE ?";
        $params[] = $like;
        $types .= "s";
    }
    $where_conditions[] = "(" . implode(" OR ", $like_parts) . ")";
}

// הוספת הפילטרים החדשים
foreach ($filters as $key => $value) {
    if ($key !== 'q' && !empty($value)) {
        $filter_query_string .= "&$key=" . urlencode($value);
        switch ($key) {
            case 'type_id':
                $where_conditions[] = "p.type_id = ?";
                $params[] = $value; $types .= "i";
                break;
            case 'min_year':
                $where_conditions[] = "p.year >= ?";
                $params[] = $value; $types .= "i";
                break;
            case 'max_year':
                $where_conditions[] = "p.year <= ?";
                $params[] = $value; $types .= "i";
                break;
            case 'min_rating':
                $where_conditions[] = "p.imdb_rating >= ?";
                $params[] = $value; $types .= "d";
                break;
        }
    }
}
$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// ספירת תוצאות כוללת
$count_sql = "SELECT COUNT(DISTINCT p.id) FROM posters p JOIN poster_collections pc ON p.id = pc.poster_id $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_posters = $count_stmt->get_result()->fetch_row()[0] ?? 0;
$total_pages = ceil($total_posters / $per_page);
$count_stmt->close();

// שליפת הפוסטרים לעמוד הנוכחי
$posters_sql = "SELECT p.* FROM posters p JOIN poster_collections pc ON p.id = pc.poster_id $where_clause ORDER BY p.year DESC LIMIT ? OFFSET ?";
$params_with_limit = $params;
$params_with_limit[] = $per_page;
$types_with_limit = $types . "i";
$params_with_limit[] = $offset;
$types_with_limit .= "i";
$stmt = $conn->prepare($posters_sql);
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$res = $stmt->get_result();
$poster_list = [];
while ($row = $res->fetch_assoc()) $poster_list[] = $row;
$stmt->close();

// כל הפוסטרים לאוסף (עבור הרשימה השמית בצד)
$all_posters_for_list = [];
$sql_all = "SELECT p.* FROM poster_collections pc JOIN posters p ON p.id = pc.poster_id WHERE pc.collection_id = ? ORDER BY pc.poster_id ASC";
$stmt_all = $conn->prepare($sql_all);
$stmt_all->bind_param("i", $id);
$stmt_all->execute();
$res_all = $stmt_all->get_result();
while ($row = $res_all->fetch_assoc()) $all_posters_for_list[] = $row;
$stmt_all->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></title>
  <style>
    body { font-family:Arial; background:#f9f9f9; padding:10px; direction:rtl; }
    .container { max-width:1300px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    .header-img { max-width:100%; border-radius:1px; margin-top:10px; }
    .description { margin-top:10px; color:#444; }
    .link-btn { background:#eee; padding:6px 12px; border-radius:6px; text-decoration:none; margin:10px 10px 0 0; display:inline-block; }
    .link-btn:hover { background:#ddd; }
    .poster-section { display:flex; flex-direction:row-reverse; gap:14px; margin-top:20px; align-items:flex-start; flex-wrap:nowrap !important; }
    .poster-grid { flex:1 1 0; min-width:0; display:flex; flex-wrap:wrap; }
    .poster-grid.small { gap:2px; }
    .poster-grid.medium { gap:8px; }
    .poster-grid.large { gap:12px; }
    .poster-item { text-align:center; position:relative; }
    .poster-item.small { width:100px; margin-bottom:0 !important; }
    .poster-item.medium { width:160px; }
    .poster-item.large { width:220px; }
    .poster-item img { width:100%; aspect-ratio:2/3; object-fit:cover; border-radius:1px; margin:0; box-shadow:0 0 4px rgba(0,0,0,0.1); }
    .poster-item.small small, .poster-item.small .title-he, .poster-item.small .imdb-id { font-size:10px; line-height:1; margin-top:1px !important; margin-bottom:0 !important; }
    .title-he { font-size:12px; color:#555; }
    .imdb-id a {  color: #99999A !important; font-size: 12px; text-decoration: none; }
    .remove-btn { background:#dc3545; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin-top:6px; }
    .delete-box { display:none; }
    .poster-list-sidebar h4 { margin-top:0; font-size:16px; }
    .poster-list-sidebar input { width:100%; padding:8px 14px; border:1px solid #bbb; border-radius:8px; margin-bottom:10px; background:#fafcff; font-size:15px; box-shadow:0 1px 6px #0001; outline:none; transition:.17s; direction: rtl; }
    .poster-list-sidebar ol { list-style-type:decimal !important; direction: rtl; padding-right: 8px; margin: 0; list-style-position: inside !important; }
    .poster-list-sidebar li { margin-bottom: 8px; line-height: 1.3; text-align: right; }
    .name-list-toggle-btn { display:inline-block; margin-right:8px; margin-bottom:5px; padding:8px 18px 8px 36px; background:#ededed; border-radius:13px; border:none; cursor:pointer; font-size:19px; color:#22644d; transition:background .15s, box-shadow .2s; font-family:inherit; position:relative; box-shadow:0 2px 8px #0001; vertical-align:middle; }
    .size-btn { padding:7px 19px; font-size:18px; margin:0 2px; background:#f6f6f8; color:#222; border-radius:8px; border:1.5px solid #b0b0b5; transition:.15s; cursor:pointer; font-family:inherit; }
    .size-btn.active, .size-btn:focus { background:#2c88ee !important; color:#fff !important; border-color:#125a50; outline: none; }
    .form-box textarea { width: 100%; font-size: 15px; padding: 8px; border-radius: 7px; border: 1px solid #bbb; background: #fafcff; margin-bottom: 10px; resize: vertical; min-height: 65px; }
    .form-box button { width: 100%; font-size: 16px; padding: 8px 0; border-radius: 7px; border: none; background: #007bff; color: #fff; margin-top: 6px; cursor: pointer; transition: background 0.2s; }
    .main-search-box { background: #fff; border: 1.5px solid #bbb; border-radius: 11px; padding: 10px 18px; width: 270px; font-size: 16px; box-shadow: 0 2px 10px #e1e6eb50; outline: none; transition: border .2s, box-shadow .2s; margin-left: 0; margin-right: 0; color: #1d1d1d; }
    .main-search-btn { padding: 8px 20px; border-radius: 7px; border: none; background: #1576cc; color: #fff; font-size: 16px; cursor: pointer; }
    
    /* --- הוספה: CSS עבור תיבת הסינון --- */
    .sidebar-wrapper {
        flex:none !important; width:305px; min-width:250px; max-width:320px;
        display: flex; flex-direction: column; gap: 20px; align-self: flex-start;
    }
    .filter-panel {
        background: #fdfdfd; border: 1px solid #eee; padding: 15px;
        border-radius: 6px; box-shadow: 0 0 4px rgba(0,0,0,0.05);
    }
    .filter-panel h4 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
    .filter-group { margin-bottom: 15px; }
    .filter-group label { display: block; margin-bottom: 5px; color: #333; font-weight: bold; font-size: 14px; }
    .filter-group input, .filter-group select { width: 100%; padding: 8px; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .filter-submit-btn { width: 100%; padding: 10px; font-size: 1.1em; font-weight: bold; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; }
    .filter-submit-btn:hover { background-color: #0056b3; }
    .poster-list-sidebar {
      background:#f8f8f8; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.05); font-size:14px; color:#333; height:fit-content;
      text-align:right; padding:12px 0px;
    }
    .poster-list-sidebar.hide-list { display:none !important; }
    
    @media (max-width:1000px) {
      .container { padding: 8px 2px; }
      .poster-section { flex-direction:column; gap:0; }
      .sidebar-wrapper { width:100%; max-width:100%; margin-bottom:16px; }
    }
    .poster-list-sidebar .year {
    font-size: 10px;
    color: #555;
}
  </style>
</head>
<body><br>
<div class="container">
  <h2>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></h2>
  <?php if (!empty($collection['image_url'])): ?>
    <img src="<?= htmlspecialchars($collection['image_url']) ?>" alt="תמונה" class="header-img">
  <?php endif; ?>
  <?php if (!empty($collection['description'])): ?>
    <div class="description">📝 <?= nl2br(htmlspecialchars($collection['description'])) ?></div>
  <?php endif; ?>

  <div><button type="button" class="name-list-toggle-btn" onclick="toggleNameList()">
      <span class="icon">📄</span> הצג/הסתר רשימה שמית
    </button>
    <a href="edit_collection.php?id=<?= $collection['id'] ?>" class="link-btn">✏️ ערוך</a>
    <a href="manage_collections.php?delete=<?= $collection['id'] ?>" onclick="return confirm('למחוק את האוסף?')" class="link-btn" style="background:#fdd;">🗑️ מחק</a>
    <a href="universe.php?collection_id=<?= $collection['id'] ?>" class="link-btn" style="background:#d4edda; color:#155724; font-weight:bold;">🌌 הצג בציר זמן</a>
    <a href="collections.php" class="link-btn">⬅ חזרה לרשימת האוספים</a>
    <a href="#" onclick="toggleDelete()" class="link-btn" style="background:#ffc;">🧹 הצג/הסתר מחיקה</a>
  </div>

  <div style="margin-top:10px;">
    גודל פוסטרים:
    <button onclick="setSize('small')" class="size-btn" id="size-small">קטן</button>
    <button onclick="setSize('medium')" class="size-btn" id="size-medium">בינוני</button>
    <button onclick="setSize('large')" class="size-btn" id="size-large">גדול</button>
    <a href="collection_csv.php?id=<?= $id ?>" target="_blank" class="size-btn" style="margin-right:14px; padding:8px 20px; border-radius:7px; border:none; background:#2a964a; color:#fff; text-decoration:none !important;">⬇️ ייצא רשימה כ־CSV</a>
  </div>

  <form method="get" style="margin:12px 0 15px 0; display:flex; gap:10px; align-items:center; justify-content:center;">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" class="main-search-box" placeholder="🔎 חפש שם, שנה, במאי, שפה, מזהה IMDb ועוד...">
    <button type="submit" class="main-search-btn">חפש</button>
    <?php if ($filters['q']): ?>
      <a href="collection.php?id=<?= $id ?>" style="color:#1576cc; margin-right:7px;">נקה חיפוש</a>
    <?php endif; ?>
  </form>
  <h3>🎬 פוסטרים באוסף: (<?= $total_posters ?> תוצאות)</h3>
  
  <div class="poster-section">
      <div class="poster-grid medium">
        <?php if ($poster_list): ?>
            <?php foreach ($poster_list as $p): ?>
              <div class="poster-item medium">
                <a href="poster.php?id=<?= $p['id'] ?>">
                  <?php $img = trim($p['image_url'] ?? '') ?: 'images/no-poster.png'; ?>
                  <img src="<?= htmlspecialchars($img) ?>" alt="Poster">
                  <small><?= htmlspecialchars($p['title_en']) ?></small>
                  <?php if (!empty($p['title_he'])): ?>
                    <div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($p['imdb_id'])): ?>
                    <div class="imdb-id">
                      <a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank">
                        IMDb: <?= htmlspecialchars($p['imdb_id']) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                </a>
                <div class="delete-box">
                  <form method="post" action="remove_from_collection.php">
                    <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                    <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="remove-btn">🗑️ הסר</button>
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
                <div class="filter-group">
                    <label for="type_id">סוג</label>
                    <select name="type_id" id="type_id" onchange="this.form.submit()">
                        <option value="">הכל</option>
                        <?php foreach ($types_list as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= ($filters['type_id'] == $type['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['label_he']) ?>
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
                    <a href="collection.php?id=<?= $id ?>&q=<?= urlencode($filters['q']) ?>" style="display: block; text-align: center; margin-top: 10px;">נקה סינון</a>
                <?php endif; ?>
            </form>
          </div>

          <div class="poster-list-sidebar" id="name-list-sidebar">
            <h4>📃 רשימה שמית</h4>
            <div style="position:relative;">
              <input type="text" id="poster-search" placeholder="חפש פוסטר...">
            </div>
            <ol id="poster-list">
              <?php foreach ($all_posters_for_list as $i => $p): ?>
                <li>
                  <a href="poster.php?id=<?= $p['id'] ?>" style="color:#007bff;">
                    <?= htmlspecialchars($p['title_en']) ?>
                  </a>
                  <?php if (!empty($p['title_he'])): ?>
                    <div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($p['year'])): ?>
                    <div class="year">שנה: <?= htmlspecialchars($p['year']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($p['imdb_id'])): ?>
                    <div class="imdb-id">
                      <a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank">
                        <?= htmlspecialchars($p['imdb_id']) ?>
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
    <div style="text-align:center; margin-top:20px;">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="collection.php?id=<?= $id ?>&page=<?= $i ?><?= $filter_query_string ?>"
           style="margin:0 6px; padding:6px 10px; background:<?= $i==$page ? '#007bff' : '#eee' ?>; color:<?= $i==$page ? 'white' : 'black' ?>; border-radius:4px; text-decoration:none;">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <div class="form-box">
    <h3>➕ הוספת פוסטרים לפי מזהים</h3>
    <form method="post" action="add_to_collection_batch.php">
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

<script>
  function toggleDelete() {
    document.querySelectorAll('.delete-box').forEach(el => {
      el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    });
  }
  function setSize(size) {
    localStorage.setItem('posterSize', size);
    document.querySelectorAll('.poster-item').forEach(item => {
      item.classList.remove('small', 'medium', 'large');
      item.classList.add(size);
    });
    const grid = document.querySelector('.poster-grid');
    grid.classList.remove('small', 'medium', 'large');
    grid.classList.add(size);
    ['small', 'medium', 'large'].forEach(function(sz){
      document.getElementById('size-'+sz).classList.remove('active');
    });
    document.getElementById('size-'+size).classList.add('active');
  }
  window.addEventListener('DOMContentLoaded', () => {
    const savedSize = localStorage.getItem('posterSize') || 'medium';
    setSize(savedSize);
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
  function toggleNameList() {
    var sidebar = document.getElementById('name-list-sidebar');
    sidebar.classList.toggle('hide-list');
  }
</script>
</body>
</html>
<?php include 'footer.php'; ?>
<?php $conn->close(); ?>