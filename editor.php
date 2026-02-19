<?php
require_once 'server.php';
include 'header.php';

// הגדרת נתיב לתמונות
if (!defined('IMAGE_BASE_PATH')) {
    define('IMAGE_BASE_PATH', 'images/types/');
}

// 1. קליטת חיפוש
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// 2. הגדרות בסיסיות (Limit & Page)
$defaultLimit = 50;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $defaultLimit;
if ($limit == -1) $limit = 1000000;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 3. טיפול בסינון סוג (Type ID)
$types = [];
$typeResult = $conn->query("SELECT id, label_he, image FROM poster_types ORDER BY id ASC");
while ($row = $typeResult->fetch_assoc()) {
    $types[$row['id']] = $row;
}
$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;

// 4. מנגנון סינון חוסרים
$filter = $_GET['filter'] ?? 'any';

$filtersMap = [
    'any' => [ 'label' => 'כל החוסרים', 'sql' => "(title_he IS NULL OR title_he = '') OR (year IS NULL OR year = 0) OR (imdb_id IS NULL OR imdb_id = '') OR (imdb_rating IS NULL OR imdb_rating = 0) OR (trailer_url IS NULL OR trailer_url = '') OR (runtime IS NULL OR runtime = 0) OR (tmdb_url IS NULL OR tmdb_url = '') OR ((image_url IS NULL OR image_url = '') AND (poster_url IS NULL OR poster_url = '')) OR (overview_he IS NULL OR overview_he = '') OR (genres IS NULL OR genres = '')" ],
    'no_he' => [ 'label' => 'חסר עברית', 'sql' => "(title_he IS NULL OR title_he = '')" ],
    'no_en' => [ 'label' => 'חסר אנגלית', 'sql' => "(title_en IS NULL OR title_en = '')" ],
    'no_year' => [ 'label' => 'חסר שנה', 'sql' => "(year IS NULL OR year = 0)" ],
    'no_imdb' => [ 'label' => 'חסר IMDb', 'sql' => "(imdb_id IS NULL OR imdb_id = '')" ],
    'no_tmdb' => [ 'label' => 'חסר TMDb', 'sql' => "(tmdb_url IS NULL OR tmdb_url = '')" ],
    'no_rating' => [ 'label' => 'חסר דירוג', 'sql' => "(imdb_rating IS NULL OR imdb_rating = 0)" ],
    'no_image' => [ 'label' => 'חסר תמונה', 'sql' => "((image_url IS NULL OR image_url = '' OR image_url = 'N/A') AND (poster_url IS NULL OR poster_url = '' OR poster_url = 'N/A'))" ],
    'no_overview' => [ 'label' => 'חסר תקציר', 'sql' => "(overview_he IS NULL OR overview_he = '')" ],
    'no_genre' => [ 'label' => 'חסר ז\'אנר', 'sql' => "(genres IS NULL OR genres = '')" ],
    'no_trailer' => [ 'label' => 'חסר טריילר', 'sql' => "(trailer_url IS NULL OR trailer_url = '')" ],
    'no_runtime' => [ 'label' => 'חסר זמן', 'sql' => "(runtime IS NULL OR runtime = 0)" ],
    'all_rows' => [ 'label' => 'הצג הכל', 'sql' => "1=1" ]
];

if (!array_key_exists($filter, $filtersMap)) $filter = 'any';

// === בניית ה-WHERE ===
$conditions = [];

// א. סוג (תמיד תקף אם נבחר)
if ($type_id > 0) {
    $conditions[] = "type_id = $type_id";
}

// ב. חיפוש מול פילטר
if (!empty($search)) {
    // מצב חיפוש: מתעלמים מהפילטרים ומחפשים לפי טקסט
    $s = $conn->real_escape_string($search);
    $idCondition = is_numeric($s) ? " OR id = '$s'" : ""; // חיפוש ID רק אם זה מספר
    $conditions[] = "(title_he LIKE '%$s%' OR title_en LIKE '%$s%' OR imdb_id LIKE '%$s%'$idCondition)";
} else {
    // מצב רגיל: מפעילים את הפילטר שנבחר
    $conditions[] = $filtersMap[$filter]['sql'];
}

$whereSql = implode(' AND ', $conditions);
// אם אין תנאים בכלל (קורה רק אם מוחקים הכל מהקוד), ברירת מחדל 1=1
if (empty($whereSql)) $whereSql = "1=1"; 

$whereClause = "WHERE " . $whereSql;

// 5. ספירה
$countSql = "SELECT COUNT(id) as total FROM posters $whereClause";
$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];

$grandTotalSql = "SELECT COUNT(id) as total FROM posters";
$grandTotalRes = $conn->query($grandTotalSql);
$grandTotal = $grandTotalRes->fetch_assoc()['total'];

$totalPages = ceil($totalRows / $limit);
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// 6. שליפה
$sql = "SELECT id, title_he, title_en, year, imdb_id, tmdb_url, imdb_rating, trailer_url, runtime, overview_he, genres, image_url, poster_url 
        FROM posters 
        $whereClause
        ORDER BY id DESC 
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

function getUrl($p = null, $l = null, $f = null, $t = null) {
    global $page, $limit, $filter, $type_id, $search;
    $p = $p ?? $page;
    $l = $l ?? $limit;
    $f = $f ?? $filter;
    $t = $t ?? $type_id;
    $q = urlencode($search);
    return "?page=$p&limit=$l&filter=$f&type_id=$t&q=$q";
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>עורך ראשי - Dark Mode</title>
    <style>
        
        /* === הגדרות כלליות === */
        html, body { 
            background-color: #000000 !important; 
            color: #e0e0e0 !important; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; padding-bottom: 0px;
            min-width: 1600px;
        }

        /* איפוס רקעים מ-Header */
        header, nav, .w3-bar, .w3-light-grey, .w3-white, .container, .wrapper {
            background-color: #000000 !important;
            background: #000000 !important;
            color: #ffffff !important;
            border: none !important;
        }
        
        .w3-button, .w3-bar-item { color: #fff !important; }
        .w3-button:hover { background-color: #333 !important; }

        /* === אזור סינונים === */
        .filters-container {
            background: #000; 
            padding: 20px 0;
            border: none;
        }

        .type-filters { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
        
        .type-link {
            display: inline-flex; flex-direction: column; align-items: center; gap: 5px;
            padding: 5px; 
            background: transparent !important; 
            border: none !important; 
            text-decoration: none; 
            color: #888 !important; 
            opacity: 1; 
            transition: all 0.3s ease;
            min-width: 60px;
        }
        .type-link:hover { transform: translateY(-3px); }
        .type-link.active { color: #fff !important; transform: scale(1.1); }
        .type-link img { height: 40px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); }
        .type-label { font-size: 13px; font-weight: 500; }
        .type-link.active .type-label { color: deepskyblue !important; font-weight: bold; }

        .missing-filters { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; padding: 0 10px; }
        
        .filter-btn {
            background-color: #1a1a1a !important; 
            border: 1px solid #333 !important; 
            color: #ffffff !important; 
            padding: 6px 14px; border-radius: 50px; 
            text-decoration: none; font-size: 13px;
            transition: 0.2s;
        }
        .filter-btn:hover { background-color: #333 !important; border-color: #666; }
        .filter-btn.active { 
            background-color: deepskyblue !important; 
            color: #ffffff !important; 
            border-color: deepskyblue !important; 
            font-weight: bold; 
            box-shadow: 0 0 10px rgba(0, 191, 255, 0.4);
        }

        /* === סרגל כלים תחתון === */
        .toolbar {
            position: sticky; top: 0; z-index: 1000;
            background: #0a0a0a !important; padding: 10px 20px;
            border-bottom: 1px solid #222; border-top: 1px solid #222;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.9);
            min-width: 1600px;
        }
        .toolbar-left { display: flex; align-items: center; gap: 10px; }
        
        /* חיפוש */
        .search-form { display: flex; align-items: center; background: #1a1a1a; border: 1px solid #333; border-radius: 50px; padding: 0 10px; margin-left: 15px; }
        .search-input { background: transparent; border: none; color: #fff !important; padding: 6px; outline: none; font-size: 13px; width: 150px; }
        .search-btn { background: transparent; border: none; color: #aaa; cursor: pointer; padding: 5px; }
        .search-btn:hover { color: #fff; }

        .stats-badge { background: #3a1c1c; color: #ff8a80; padding: 5px 12px; border-radius: 4px; font-size: 13px; border: 1px solid #5a2a2a; white-space: nowrap; }
        .stats-label { color: #aaa; margin-left: 5px; font-size: 12px; }
        
        .limit-btn { background: #1a1a1a !important; color: #aaa !important; border: 1px solid #333; padding: 4px 8px; font-size: 12px; text-decoration: none; border-radius: 3px; }
        .limit-btn.active { background: #333 !important; color: #fff !important; border-color: #555; }
        
        .reset-btn { background: #1a1a1a; color: #fff !important; border: 1px solid #333; padding: 8px 15px; border-radius: 50px; font-size: 14px; text-decoration: none; transition: 0.2s; }
        .reset-btn:hover { background: #333; }
        
        .save-btn { background-color: #1a1a1a; color: #666; border: 1px solid #333; padding: 8px 20px; border-radius: 50px; font-size: 14px; font-weight: bold; cursor: not-allowed; transition: all 0.3s; white-space: nowrap; }
        .save-btn.active { background-color: #00e676; color: #000; border-color: #00e676; cursor: pointer; box-shadow: 0 0 15px rgba(0, 230, 118, 0.4); }

        /* === טבלה === */
        .excel-table { 
            width: 100%; 
            min-width: 1600px; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }
        
        .excel-table th { 
            background: #0a0a0a !important; color: #888 !important; 
            padding: 12px 10px; text-align: right; 
            font-weight: 600; font-size: 13px; border: 1px solid #222;
            position: relative; user-select: none;
        }
        
        /* ידית הגרירה */
        .resizer { position: absolute; top: 0; left: 0; width: 5px; cursor: col-resize; user-select: none; height: 100vh; z-index: 999; opacity: 0; transition: opacity 0.2s; }
        .resizer:hover, .resizing { background-color: deepskyblue; opacity: 1; box-shadow: 0 0 5px deepskyblue; }

        .excel-table td { border: 1px solid #222; padding: 0; background: #000000 !important; transition: background 0.3s; }
        
        /* ID */
        .id-cell a { display: block; width: 100%; height: 100%; background: #050505; color: #fff !important; text-align: center; font-size: 12px; font-weight: bold; text-decoration: none; padding: 10px 0; }
        .id-cell a:hover { text-decoration: underline; color: deepskyblue !important; }
        
        .excel-input { width: 100%; height: 40px; border: none; background: transparent; color: #ddd !important; padding: 0 8px; font-size: 13px; outline: none; box-sizing: border-box; font-family: inherit; }
        .excel-input:focus { background: #111; color: #fff !important; }
        .ltr-input { direction: ltr; text-align: left; }

        tr.edited td { background-color: #0288d1 !important; border-color: #0277bd; }
        tr.edited input { color: #ffffff !important; font-weight: bold; }
        tr.edited input::placeholder { color: #b3e5fc !important; opacity: 0.7; }

        /* עימוד */
        .pagination-wrapper { margin: 40px 0; text-align: center; direction: rtl; min-width: 1600px; }
        .page-btn { 
            display: inline-block; 
            padding: 6px 12px; 
            background: #111; 
            border: 1px solid #333; 
            margin: 2px; 
            text-decoration: none; 
            color: #ffffff !important; 
            border-radius: 4px; 
            font-size: 14px; 
        }
        .page-btn:hover { background: #333; color: #fff; }
        .page-btn.current { background: #f44336; color: white; border-color: #d32f2f; font-weight: bold; }
        .page-dots { color: #555; margin: 0 5px; }
        .empty-state { text-align: center; padding: 100px 20px; color: #666; min-width: 1600px; }

        
      /* ============================================== */
    /* ==   סגנונות שנלקחו מהקובץ w3.css   == */
    /* ============================================== */

    /* הגדרות בסיס ואיפוס */
    html {
        box-sizing: border-box;
    }
    *, *:before, *:after {
        box-sizing: inherit;
    }
    
    /* .w3-bar */
    .w3-bar {
        width: 100%;
        overflow: hidden;
    }
    .w3-bar .w3-bar-item {
        padding: 8px 16px;
        float: left; /* הדפדפן הופך אוטומטית לימין ב-RTL */
        width: auto;
        border: none;
        display: block;
        outline: 0;
    }
    .w3-bar .w3-button {
        color: white !important;;
        white-space: normal;
    }
    .w3-bar:before, .w3-bar:after {
        content: "";
        display: table;
        clear: both;
    }

    /* .w3-padding */
    .w3-padding {
        padding: 8px 16px !important;
    }

    /* .w3-button */
    .w3-button {
        border: none;
        display: inline-block;
        padding: 8px 16px;
        vertical-align: middle;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        text-align: center;
        cursor: pointer;
        white-space: nowrap;
    }
    
    /* צבעים */
    .w3-black, .w3-hover-black:hover {
        color: #fff !important;
        background-color: white;
    }
    .w3-white, .w3-hover-white:hover {
        color: #000 !important;
        background-color: #fff !important;
    }
    .white {color: #f1f1f1 !important;}
    .w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover{color:#000!important;background-color:#f1f1f1!important}
    .logo {  filter: saturate(500%) contrast(800%) brightness(500%) 
      invert(100%) sepia(50%) hue-rotate(120deg); }
        filter: saturate(500%) contrast(800%) brightness(500%) 
      invert(80%) sepia(50%) hue-rotate(120deg); }

      
    </style>
</head>
<body>

<div class="filters-container">
    <div class="type-filters">
        <a href="<?= getUrl(1, null, null, 0) ?>" class="type-link <?= $type_id == 0 ? 'active' : '' ?>">
            <span style="font-size:24px; line-height:35px;">🌐</span>
            <span class="type-label">הכל</span>
        </a>
        <?php foreach ($types as $t_id => $t_data): ?>
            <a href="<?= getUrl(1, null, null, $t_id) ?>" class="type-link <?= $type_id == $t_id ? 'active' : '' ?>">
                <?php if(!empty($t_data['image'])): ?>
                    <img src="<?= IMAGE_BASE_PATH . $t_data['image'] ?>" alt="">
                <?php else: ?>
                    <span>🎥</span>
                <?php endif; ?>
                <span class="type-label"><?= $t_data['label_he'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="missing-filters">
        <?php foreach ($filtersMap as $key => $data): ?>
            <a href="<?= getUrl(1, null, $key) ?>" class="filter-btn <?= $filter == $key ? 'active' : '' ?>">
                <?= $data['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="" class="search-form">
            <input type="hidden" name="type_id" value="<?= $type_id ?>">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="חפש שם, ID, IMDb...">
            <button type="submit" class="search-btn">🔍</button>
        </form>

        <a href="editor.php" class="reset-btn">🔄 איפוס</a>
        <span class="stats-badge">נמצאו: <b><?= number_format($totalRows) ?></b><span class="stats-label">מתוך <?= number_format($grandTotal) ?></span></span>
        <span style="color:#666; font-size:12px; margin-right:5px;">הצג:</span>
        <a href="<?= getUrl(1, 20) ?>" class="limit-btn <?= $limit == 20 ? 'active' : '' ?>">20</a>
        <a href="<?= getUrl(1, 50) ?>" class="limit-btn <?= $limit == 50 ? 'active' : '' ?>">50</a>
        <a href="<?= getUrl(1, 100) ?>" class="limit-btn <?= $limit == 100 ? 'active' : '' ?>">100</a>
        <a href="<?= getUrl(1, 250) ?>" class="limit-btn <?= $limit == 250 ? 'active' : '' ?>">250</a>
        <a href="<?= getUrl(1, -1) ?>" class="limit-btn <?= $limit > 10000 ? 'active' : '' ?>">הכל</a>
    </div>
    <button id="saveBtn" class="save-btn" onclick="saveChanges()">אין שינויים</button>
</div>

<?php if ($totalRows > 0): ?>
    <table class="excel-table">
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th style="width: 200px;">שם עברית</th>
                <th style="width: 200px;">שם אנגלית</th>
                <th style="width: 80px;">שנה</th>
                <th style="width: 90px;">IMDb ID</th>
                <th style="width: 90px;">TMDb URL</th>
                <th style="width: 60px;">דירוג</th>
                <th style="width: 150px;">טריילר</th>
                <th style="width: 250px;">תקציר</th>
                <th style="width: 150px;">ז'אנר</th>
                <th style="width: 150px;">תמונה (URL)</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr data-id="<?= $row['id'] ?>">
                <td class="id-cell"><a href="poster.php?id=<?= $row['id'] ?>" target="_blank"><?= $row['id'] ?></a></td>
                <td><input type="text" class="excel-input" name="title_he" value="<?= htmlspecialchars($row['title_he']) ?>" placeholder="חסר..."></td>
                <td><input type="text" class="excel-input ltr-input" name="title_en" value="<?= htmlspecialchars($row['title_en']) ?>" placeholder="Missing..."></td>
                
                <td><input type="text" class="excel-input ltr-input" name="year" value="<?= htmlspecialchars($row['year']) ?>" placeholder="0"></td>
                
                <td><input type="text" class="excel-input ltr-input" name="imdb_id" value="<?= htmlspecialchars($row['imdb_id']) ?>" placeholder="tt..."></td>
                <td><input type="text" class="excel-input ltr-input" name="tmdb_url" value="<?= htmlspecialchars($row['tmdb_url']) ?>" placeholder="URL..."></td>
                <td><input type="text" class="excel-input" name="imdb_rating" value="<?= htmlspecialchars($row['imdb_rating']) ?>" placeholder="0.0"></td>
                
                <td><input type="text" class="excel-input ltr-input" name="trailer_url" value="<?= htmlspecialchars($row['trailer_url']) ?>" placeholder="YouTube..."></td>

                <td><input type="text" class="excel-input" name="overview_he" value="<?= htmlspecialchars($row['overview_he']) ?>" placeholder="תקציר..."></td>
                <td><input type="text" class="excel-input" name="genres" value="<?= htmlspecialchars($row['genres']) ?>" placeholder="פעולה, דרמה..."></td>
                <td><input type="text" class="excel-input ltr-input" name="image_url" value="<?= htmlspecialchars($row['image_url']) ?>" placeholder="http://..."></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="pagination-wrapper">
        <div style="margin-bottom:15px;">
            <?php
            $realLimit = ($limit > 10000) ? $totalRows : $limit;
            $from = ($totalRows > 0) ? $offset + 1 : 0;
            $to = min($offset + $realLimit, $totalRows);
            ?>
            <div style="font-size: 16px; color: #fff; font-weight: bold; margin-bottom: 5px;">עמוד <?= number_format($page) ?> מתוך <?= number_format($totalPages) ?></div>
            <div style="font-size: 13px; color: #bbb;">(מציג רשומות <?= number_format($from) ?>-<?= number_format($to) ?> מתוך <?= number_format($totalRows) ?>)</div>
        </div>
        <div style="margin-top: 15px;">
            <?php if ($page > 1): ?>
                <a href="<?= getUrl(1) ?>" class="page-btn">⇤ ראשון</a>
                <a href="<?= getUrl($page - 1) ?>" class="page-btn">➡️ הקודם</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 5);
            $end = min($totalPages, $page + 3);
            if ($start > 1) echo '<span class="page-dots">...</span>';
            for ($i = $start; $i <= $end; $i++): $activeClass = ($i == $page) ? 'current' : ''; ?>
                <a href="<?= getUrl($i) ?>" class="page-btn <?= $activeClass ?>"><?= $i ?></a>
            <?php endfor; if ($end < $totalPages) echo '<span class="page-dots">...</span>'; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= getUrl($page + 1) ?>" class="page-btn">הבא ⬅️</a>
                <a href="<?= getUrl($totalPages) ?>" class="page-btn">אחרון ⇥</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state"><span style="font-size:50px">🎉</span><h2>אין תוצאות להצגה לפי הסינון הנוכחי.</h2></div>
<?php endif; ?>

<script>
   function makeTableResizable() {
        const table = document.querySelector('.excel-table');
        if (!table) return;

        // חישוב גובה הטבלה המלא (כולל גלילה)
        const tableHeight = table.scrollHeight; 

        const cols = table.querySelectorAll('th');
        cols.forEach((col) => {
            const resizer = document.createElement('div');
            resizer.classList.add('resizer');
            
            // --- קביעת גובה הפס לגובה הטבלה כולה ---
            resizer.style.height = `${tableHeight}px`;
            // ------------------------------------------------

            col.appendChild(resizer);
            
            let x = 0; let w = 0;
            const mouseDownHandler = function(e) {
                x = e.clientX;
                const styles = window.getComputedStyle(col);
                w = parseInt(styles.width, 10);
                resizer.classList.add('resizing');
                document.addEventListener('mousemove', mouseMoveHandler);
                document.addEventListener('mouseup', mouseUpHandler);
            };
            const mouseMoveHandler = function(e) {
                const dx = x - e.clientX; 
                col.style.width = `${w + dx}px`;
            };
            const mouseUpHandler = function() {
                resizer.classList.remove('resizing');
                document.removeEventListener('mousemove', mouseMoveHandler);
                document.removeEventListener('mouseup', mouseUpHandler);
            };
            resizer.addEventListener('mousedown', mouseDownHandler);
        });
    }
    makeTableResizable();

    let changes = {};
    const saveBtn = document.getElementById('saveBtn');
    document.querySelectorAll('.excel-input').forEach(input => {
        input.setAttribute('data-original', input.value);
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            const field = this.name;
            const value = this.value;
            const original = this.getAttribute('data-original');
            if (value !== original) {
                row.classList.add('edited');
                if (!changes[id]) changes[id] = {};
                changes[id][field] = value;
            } else {
                if (changes[id]) {
                    delete changes[id][field];
                    if (Object.keys(changes[id]).length === 0) {
                        delete changes[id];
                        let rowStillDirty = false;
                        row.querySelectorAll('input').forEach(i => { if(i.value !== i.getAttribute('data-original')) rowStillDirty = true; });
                        if(!rowStillDirty) row.classList.remove('edited');
                    }
                }
            }
            updateSaveButton();
        });
    });
    function updateSaveButton() {
        const count = Object.keys(changes).length;
        if (count > 0) { saveBtn.innerText = `💾 שמור ${count} תיקונים`; saveBtn.classList.add('active'); saveBtn.disabled = false; }
        else { saveBtn.innerText = "אין שינויים"; saveBtn.classList.remove('active'); saveBtn.disabled = true; }
    }
    async function saveChanges() {
        if (Object.keys(changes).length === 0) return;
        saveBtn.innerText = "⏳ שומר...";
        saveBtn.style.opacity = "0.7";
        try {
            const response = await fetch('save_bulk.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(changes)
            });
            const result = await response.json();
            if (result.success) {
                saveBtn.style.backgroundColor = "#00e676"; saveBtn.innerText = "✅ נשמר בהצלחה!";
                setTimeout(() => location.reload(), 500);
            } else { alert('❌ שגיאה: ' + result.error); saveBtn.innerText = "שגיאה בשמירה"; }
        } catch (error) { console.error(error); alert('❌ שגיאת תקשורת'); }
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>