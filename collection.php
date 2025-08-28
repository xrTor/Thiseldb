<?php
require_once 'server.php'; // 1. חיבור למסד הנתונים

// ==========================================================
// == כל הלוגיקה שכוללת הפנייה (redirect) ממוקמת כאן == 
// ==========================================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) {
  echo "<p>❌ אוסף לא צוין</p>";
  exit;
}

// שמירת כל הפרמטרים הנוכחיים לשימוש בקישורים
$current_params = $_GET;
unset($current_params['pin_poster'], $current_params['unpin_poster']);
$redirect_query_string = http_build_query($current_params);

// לוגיקה לנעיצה והסרת נעיצה של פוסטר
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
// ==========================================================
// == סוף הלוגיקה של ההפניות ==
// ==========================================================

include 'header.php';
set_time_limit(3000000);

// שולפים את כל נתוני האוסף, כולל סטטוס הנעיצה שלו
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

// =================================================================
// == לוגיקת סינון ומיון מתקדמת שעובדת יחד עם החיפוש ==
// =================================================================
$types_list = [];
$types_result = $conn->query("SELECT id, label_he FROM poster_types ORDER BY sort_order ASC");
while($row = $types_result->fetch_assoc()) $types_list[] = $row;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 250;
$offset = ($page - 1) * $per_page;

// איסוף כל הפילטרים והמיון מה-URL
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

// חיפוש טקסטואלי – מותאם לסכימה החדשה + תגיות משתמש
$join_user_tags = ''; // ייקבע כשה-q לא ריק
if (!empty($filters['q'])) {
    $keyword = $filters['q'];
    $filter_query_string .= "&q=" . urlencode($keyword);

    // שדות קיימים בסכימה החדשה
    $searchFields = [
        "p.title_en", "p.title_he", "p.overview_he",
        "p.cast", "p.genres",
        "p.directors", "p.writers", "p.producers", "p.composers", "p.cinematographers",
        "p.languages", "p.countries",
        "p.imdb_id", "p.year"
    ];
    $like = "%$keyword%";
    $like_parts = [];
    foreach ($searchFields as $field) {
        $like_parts[] = "$field LIKE ?";
        $params[] = $like;
        $types .= "s";
    }
    // חיפוש גם בתגיות משתמש
    $like_parts[] = "ut.genre LIKE ?";
    $params[] = $like;
    $types .= "s";

    $where_conditions[] = "(" . implode(" OR ", $like_parts) . ")";
    $join_user_tags = ' LEFT JOIN user_tags ut ON ut.poster_id = p.id ';
}

// הוספת הפילטרים החדשים
foreach ($filters as $key => $value) {
    if ($key !== 'q' && $value !== '' && $value !== null) {
        $filter_query_string .= "&$key=" . urlencode($value);
        switch ($key) {
            case 'type_id':
                $where_conditions[] = "p.type_id = ?";
                $params[] = (int)$value; $types .= "i";
                break;
            case 'min_year':
                $where_conditions[] = "p.year >= ?";
                $params[] = (int)$value; $types .= "i";
                break;
            case 'max_year':
                $where_conditions[] = "p.year <= ?";
                $params[] = (int)$value; $types .= "i";
                break;
            case 'min_rating':
                // משאיר כמו שהיה (אם אצלך הדירוג נשמר כמספר טהור זה יעבוד;
                // אם הוא טקסט "8.1/10" – שקול להמיר ל-Cast כמו בעמודים אחרים)
                $where_conditions[] = "p.imdb_rating >= ?";
                $params[] = (float)$value; $types .= "d";
                break;
        }
    }
}

// הוספת פרמטר המיון למחרוזת הקישורים
if ($sort_order !== 'added_desc') {
    $filter_query_string .= "&sort=" . urlencode($sort_order);
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// הגדרת תנאי המיון באופן דינמי, עם עדיפות לפריטים נעוצים
$order_by_clause = 'ORDER BY pc.is_pinned DESC';
switch ($sort_order) {
    case 'added_asc':
        $order_by_clause .= ", pc.added_at ASC, p.id ASC";
        break;
    case 'year_desc':
        $order_by_clause .= ", p.year DESC, p.id DESC";
        break;
    case 'year_asc':
        $order_by_clause .= ", p.year ASC, p.id ASC";
        break;
    case 'added_desc':
    default:
        $order_by_clause .= ", pc.added_at DESC, p.id DESC";
        break;
}

// ספירת תוצאות כוללת (עם JOIN לתגיות רק כשה-q פעיל)
$count_sql = "SELECT COUNT(DISTINCT p.id)
              FROM posters p
              JOIN poster_collections pc ON p.id = pc.poster_id
              $join_user_tags
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_posters = $count_stmt->get_result()->fetch_row()[0] ?? 0;
$total_pages = ceil($total_posters / $per_page);
$count_stmt->close();

// שליפת הפוסטרים עם המיון הדינמי והמידע על נעיצה
$posters_sql = "SELECT DISTINCT p.*, pc.is_pinned, pc.added_at
                FROM posters p
                JOIN poster_collections pc ON p.id = pc.poster_id
                $join_user_tags
                $where_clause
                $order_by_clause
                LIMIT ? OFFSET ?";
$params_with_limit = $params;
$params_with_limit[] = $per_page; $types_with_limit = $types . "i";
$params_with_limit[] = $offset;   $types_with_limit .= "i";
$stmt = $conn->prepare($posters_sql);
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$res = $stmt->get_result();
$poster_list = [];
while ($row = $res->fetch_assoc()) $poster_list[] = $row;
$stmt->close();

// כל הפוסטרים לאוסף (עבור הרשימה השמית בצד)
$all_posters_for_list = [];
$sql_all = "SELECT p.*
            FROM poster_collections pc
            JOIN posters p ON p.id = pc.poster_id
            WHERE pc.collection_id = ?
            ORDER BY pc.added_at ASC";
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
    .header-img { max-width:100%; border-radius:1px; margin-top:10px; max-width:420px;width:auto; height:auto;}
    .description { margin-top:10px; color:#444; }
    .link-btn { background:#eee; padding:6px 12px; border-radius:6px; text-decoration:none; margin:10px 10px 0 0; display:inline-block; }
    .link-btn:hover { background:#ddd; }
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
    .poster-item.small small, .poster-item.small .title-he, .poster-item.small .imdb-id { font-size:10px; line-height:1; margin-top:1px !important; margin-bottom:0 !important; }
    .title-he { font-size:12px; color:#555; }
    .imdb-id a {  color: #99999A !important; font-size: 12px; text-decoration: none; }
    .remove-btn { background:#dc3545; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin-top:6px; }
    .pin-btn { text-decoration:none; font-size:12px; background:#f0f0f0; padding:2px 6px; border-radius:4px; color:#333; margin-top:4px; display:inline-block; }
    .delete-box { display:none; }
    .pin-box { display:none; }
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
    .poster-list-sidebar .year { font-size: 10px; color: #555; }
    .controls-bar { display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap: 20px; margin-bottom:15px; padding: 0 5px; }
    .sort-box { display:flex; align-items:center; gap:8px; }
    .sort-box label { font-weight:bold; font-size:15px; }
    .sort-box select { padding: 5px 8px; border-radius: 5px; border: 1px solid #ccc; font-size:15px; }
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
<div id="csvUploadResult" style="max-width:900px;margin:12px auto 0;display:none;background:#f6fff6;border:1px solid #cde8cd;padding:12px;border-radius:8px;"></div>

  
  <div><button type="button" class="name-list-toggle-btn" onclick="toggleNameList()">
      <span class="icon">📄</span> הצג/הסתר רשימה שמית
    </button>
<form id="csvUploadForm" action="collection_upload_csv_api.php" method="post" enctype="multipart/form-data" style="display:inline;">
  <input type="hidden" name="collection_id" value="<?= $id ?>">
  <input type="file" name="ids_file" id="ids_file" accept=".csv,.txt,text/csv,text/plain" style="display:none" onchange="uploadCsvToCollection(this.form)">
  <button type="button" class="size-btn" onclick="document.getElementById('ids_file').click()" style="margin-right:6px; padding:8px 20px; border-radius:7px; border:none; background:#6f42c1; color:#fff; text-decoration:none !important;">⬆️ העלאת CSV/TXT לאוסף</button>
</form>

    <a href="edit_collection.php?id=<?= $collection['id'] ?>" class="link-btn">✏️ ערוך</a>

    <?php $return_url = urlencode("collection.php?id=" . $collection['id']); ?>
    <?php if (!empty($collection['is_pinned'])): ?>
        <a href="collections.php?unpin=<?= $collection['id'] ?>&return_url=<?= $return_url ?>" class="link-btn" style="background:#fff8ad; font-weight:bold;">📌 הסר נעיצת אוסף</a>
    <?php else: ?>
        <a href="collections.php?pin=<?= $collection['id'] ?>&return_url=<?= $return_url ?>" class="link-btn" style="background:#e0ffad;">📌 נעיצת אוסף</a>
    <?php endif; ?>

    <a href="manage_collections.php?delete=<?= $collection['id'] ?>" onclick="return confirm('למחוק את האוסף?')" class="link-btn" style="background:#fdd;">🗑️ מחק</a>
    <a href="universe.php?collection_id=<?= $collection['id'] ?>" class="link-btn" style="background:#d4edda; color:#155724;">🌌 הצג בציר זמן</a>
    <a href="collections.php" class="link-btn">⬅ חזרה לרשימת האוספים</a>
    <a href="#" onclick="toggleDelete()" class="link-btn" style="background:#ffc1c1;">🧹 הצג/הסתר מחיקה</a>
    <a href="#" onclick="togglePin()" class="link-btn" style="background:#c1e8ff;">📌 הצג/הסתר נעיצה</a>
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
            <label for="sort">מיין לפי:</label>
            <select name="sort" id="sort" onchange="document.getElementById('sortForm').submit()">
                <option value="added_desc" <?= ($sort_order == 'added_desc') ? 'selected' : '' ?>>האחרון שהתווסף</option>
                <option value="added_asc"  <?= ($sort_order == 'added_asc') ? 'selected' : '' ?>>הראשון שהתווסף</option>
                <option value="year_desc"  <?= ($sort_order == 'year_desc') ? 'selected' : '' ?>>שנה (מהחדש לישן)</option>
                <option value="year_asc"   <?= ($sort_order == 'year_asc') ? 'selected' : '' ?>>שנה (מהישן לחדש)</option>
            </select>
        </form>
    </div>
  </div>
  
  <div class="poster-section">
      <div class="poster-grid medium">
        <?php if ($poster_list): ?>
            <?php
            $base_link_params = http_build_query(array_merge($_GET, ['id' => $id]));
            ?>
            <?php foreach ($poster_list as $p): ?>
              <div class="poster-item medium <?= !empty($p['is_pinned']) ? 'pinned' : '' ?>">
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
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
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

  <div class="form-box" id="add-posters-form">
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
  function togglePin() {
    document.querySelectorAll('.pin-box').forEach(el => {
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
<script>
async function uploadCsvToCollection(form) {
  const inp = form.querySelector('#ids_file');
  if (!inp.files || !inp.files.length) return;

  const fd = new FormData(form);
  fd.set('ids_file', inp.files[0]);

  const box = document.getElementById('csvUploadResult');
  if (box) {
    box.style.display = 'block';
    box.style.background = '#fff';
    box.style.borderColor = '#cde8cd';
    box.innerHTML = '⏳ מעלה ומעבד...';
  }

  try {
    const res = await fetch(form.action, { method: 'POST', body: fd });
    const data = await res.json();

    if (!res.ok || data.error || data.errors) {
      const errs = (data.errors || [data.error || 'שגיאה לא ידועה']).map(e => `<li>${e}</li>`).join('');
      if (box) {
        box.style.background = '#fff5f5';
        box.style.borderColor = '#f3c0c0';
        box.innerHTML = `<strong>שגיאה:</strong><ul>${errs}</ul>`;
      }
      return;
    }

    if (box) {
      box.style.background = '#f6fff6';
      box.style.borderColor = '#cde8cd';
      box.innerHTML = `
        ✅ הועלה ועובד בהצלחה.<br>
        נוספו לאוסף: <strong>${data.inserted||0}</strong>, כבר היו: <strong>${data.already||0}</strong>.<br>
        מרענן את העמוד…
      `;
    }

    setTimeout(() => { window.location.reload(); }, 600);

  } catch (e) {
    if (box) {
      box.style.background = '#fff5f5';
      box.style.borderColor = '#f3c0c0';
      box.innerHTML = `❌ תקלה בחיבור לשרת: ${e}`;
    }
  } finally {
    inp.value = '';
  }
}
</script>

</body>
</html>
<?php include 'footer.php'; ?>
