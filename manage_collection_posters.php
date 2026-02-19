<?php
require_once 'server.php'; // DB connection
include 'header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) {
    die('<div class="container"><h2>❌ אוסף לא צוין</h2></div>');
}

// ===============================
// "לפני X ימים" בעברית (על בסיס תאריך/שעה)
// ===============================
function time_ago_he($dt): string {
    if (empty($dt)) return '';
    $ts = @strtotime((string)$dt);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 0) $diff = 0;

    $minutes = (int)floor($diff / 60);
    $hours   = (int)floor($diff / 3600);
    $days    = (int)floor($diff / 86400);

    if ($days <= 0) {
        if ($hours <= 0) {
            if ($minutes <= 1) return 'הרגע';
            return 'לפני ' . $minutes . ' דקות';
        }
        return 'לפני ' . $hours . ' שעות';
    }
    if ($days === 1) return 'לפני יום';
    return 'לפני ' . $days . ' ימים';
}

// ===============================
// מיון (ברירת מחדל כמו בעמוד אוסף: מהחדש לישן)
// ===============================
$sort_order = $_GET['sort'] ?? 'collection';
// Backward compatibility (old sort values)
if ($sort_order === 'added_desc') $sort_order = 'col_added_desc';
if ($sort_order === 'added_asc')  $sort_order = 'col_added_asc';


// 1. Fetch collection details
$stmt = $conn->prepare("SELECT name FROM collections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('<div class="container"><h2>❌ האוסף לא נמצא</h2></div>');
}
$collection = $result->fetch_assoc();
$stmt->close();

// 2. Fetch ALL posters in this collection, in their defined order
$posters = [];
// *** שינוי SQL: הוספנו מיון לפי is_pinned ראשון ***
$sql = "SELECT p.id, p.title_en, p.title_he, p.year, p.image_url, pc.is_pinned, p.imdb_id, p.imdb_rating 
        FROM posters p 
        JOIN poster_collections pc ON p.id = pc.poster_id 
        WHERE pc.collection_id = ? 
        ORDER BY pc.is_pinned DESC, pc.sort_order ASC, pc.added_at ASC"; // הוספנו pc.is_pinned DESC
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $posters[] = $row;
}
$stmt->close();

// שמור אינדקס מקורי ("סדר אוסף" כפי שהשאילתה החזירה)
foreach ($posters as $i => &$pp) { $pp['__idx'] = $i; }
unset($pp);

// שליפת תאריך הוספה לאוסף לכל פוסטר (ללא שינוי בשאילתת הפוסטרים)
$addedMap = [];
$stmt2 = $conn->prepare("SELECT poster_id, added_at FROM poster_collections WHERE collection_id = ?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($r2 = $res2->fetch_assoc()) {
    $addedMap[(int)$r2['poster_id']] = $r2['added_at'];
}
$stmt2->close();

foreach ($posters as &$pp) {
    $pid = isset($pp['id']) ? (int)$pp['id'] : 0;
    $pp['collection_added_at'] = $addedMap[$pid] ?? null;
}
unset($pp);

// מיון (ברירת מחדל: "לפי סדר האוסף" כמו בעמוד collection.php: נעוצים קודם ואז חדש→ישן)
usort($posters, function($a, $b) use ($sort_order) {
        $ap = !empty($a['is_pinned']) ? 1 : 0;
        $bp = !empty($b['is_pinned']) ? 1 : 0;
        if ($ap !== $bp) return ($bp <=> $ap);

        $get_year = function($v) {
            $v = (string)($v ?? '');
            if (preg_match('/(\d{4})/', $v, $m)) return (int)$m[1];
            return 0;
        };

        $ta = !empty($a['collection_added_at']) ? @strtotime((string)$a['collection_added_at']) : 0;
        $tb = !empty($b['collection_added_at']) ? @strtotime((string)$b['collection_added_at']) : 0;

        switch ($sort_order) {
            case 'collection':
                // בדיוק כמו ברירת המחדל בעמוד האוסף: added_at DESC (חדש→ישן)
                if ($ta !== $tb) return ($tb <=> $ta);
                return ((int)$b['id'] <=> (int)$a['id']);
            case 'added_asc':
                if ($ta !== $tb) return ($ta <=> $tb);
                return ((int)$a['id'] <=> (int)$b['id']);
            case 'added_desc':
                if ($ta !== $tb) return ($tb <=> $ta);
                return ((int)$b['id'] <=> (int)$a['id']);
            case 'year_asc':
                $ya = $get_year($a['year'] ?? '');
                $yb = $get_year($b['year'] ?? '');
                if ($ya !== $yb) return ($ya <=> $yb);
                return ((int)$a['id'] <=> (int)$b['id']);
            case 'year_desc':
                $ya = $get_year($a['year'] ?? '');
                $yb = $get_year($b['year'] ?? '');
                if ($ya !== $yb) return ($yb <=> $ya);
                return ((int)$b['id'] <=> (int)$a['id']);
            case 'rating_asc':
                $ra = (float)($a['imdb_rating'] ?? 0);
                $rb = (float)($b['imdb_rating'] ?? 0);
                if ($ra !== $rb) return ($ra <=> $rb);
                return ((int)$a['id'] <=> (int)$b['id']);
            case 'rating_desc':
                $ra = (float)($a['imdb_rating'] ?? 0);
                $rb = (float)($b['imdb_rating'] ?? 0);
                if ($ra !== $rb) return ($rb <=> $ra);
                return ((int)$b['id'] <=> (int)$a['id']);
            case 'title_he_asc':
            case 'title_he_desc':
                $ta1 = (string)($a['title_he'] ?: ($a['title_en'] ?? ''));
                $tb1 = (string)($b['title_he'] ?: ($b['title_en'] ?? ''));
                $cmp = strcasecmp($ta1, $tb1);
                if ($cmp !== 0) return ($sort_order === 'title_he_asc') ? $cmp : -$cmp;
                return ((int)$a['id'] <=> (int)$b['id']);
            case 'title_en_asc':
            case 'title_en_desc':
                $ta2 = (string)($a['title_en'] ?? ($a['title_he'] ?? ''));
                $tb2 = (string)($b['title_en'] ?? ($b['title_he'] ?? ''));
                $cmp2 = strcasecmp($ta2, $tb2);
                if ($cmp2 !== 0) return ($sort_order === 'title_en_asc') ? $cmp2 : -$cmp2;
                return ((int)$a['id'] <=> (int)$b['id']);
            default:
                // default: keep original collection order (by __idx)
                break;
}

        return ((int)$a['__idx'] <=> (int)$b['__idx']);
    });



// 3. Fetch all other collections (for the "Move" dropdown)
$all_collections_list = [];
$all_coll_result = $conn->query("SELECT id, name FROM collections WHERE id != $id ORDER BY name ASC");
while($row = $all_coll_result->fetch_assoc()) {
    $all_collections_list[] = $row;
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🛠️ ניהול פוסטרים: <?= htmlspecialchars($collection['name']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        
        /* Control Bar */
        #control-bar {
            background: #fff8e1;
            border: 1px solid #ffe57f;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        #control-bar label { font-weight: bold; }
        #control-bar select, #control-bar button {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        #control-bar button { cursor: pointer; }
        #applyBulkBtn { background: #007bff; color: white; border-color: #007bff; }
        #bulkCount { font-weight: bold; color: #333; margin: 0 10px; }
        #message-bar {
            text-align: center; font-weight: bold; padding: 10px; border-radius: 5px; margin: 10px 0;
            display: none; /* Hidden by default */
        }
        #message-bar.success { background: #d4edda; color: #155724; }
        #message-bar.error { background: #f8d7da; color: #721c24; }
        
        #message-bar a {
            color: #0056b3;
            font-weight: bold;
            text-decoration: underline;
        }
        #message-bar a:hover {
            color: #003d7c;
        }

        /* Poster Grid */
        #poster-grid-manage {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .poster-item {
            position: relative;
            border: 2px solid transparent; 
            border-radius: 6px;
            padding: 5px;
            text-align: center;
            background: #fff;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, border-color 0.2s; 
            display: flex;
            flex-direction: column;
        }
        .poster-item:hover { background: #f9f9f9; }
        .poster-item.selected {
            border-color: #007bff;
            background: #f0f8ff;
            box-shadow: 0 0 8px rgba(0,123,255,0.5);
        }
        .poster-item.pinned {
            background-color: #fffff0;
            border-color: #ffd700;
        }
        
        .poster-item img {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            border-radius: 1px;
        }
        
        .poster-text-content {
            flex-grow: 1;
            padding: 4px 0;
            font-size: 13px; 
            line-height: 1.3;
            overflow: hidden;
        }
        /* *** שינוי עיצוב שמות *** */
        .title-he {
            /* font-weight: bold; <-- הוסר */
            color: #666; /* צבע בהיר יותר */
            font-size: 13px; 
        }
        .title-en {
            color: #333; /* צבע כהה יותר */
            font-size: 13px; /* גודל זהה לעברית */
            font-weight: bold; /* מודגש */
        }
        .poster-year {
            font-size: 13px; 
            color: #666;
            margin-top: 3px;
        }
        .imdb-rating {
            margin-top: 3px;
            font-size: 13px; 
        }
        .imdb-rating a {
            color: #d18b00;
            text-decoration: none;
        }
        .imdb-rating a:hover { text-decoration: underline; }
        
        .poster-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            z-index: 10;
            cursor: pointer;
        }
        
        .poster-nav-link { 
            position: absolute; 
            top: 8px; 
            left: 8px; 
            z-index: 11; 
            font-size: 16px; 
            text-decoration: none; 
            background: rgba(255,255,255,0.8);
            border-radius: 50%; 
            width: 22px; 
            height: 22px; 
            line-height: 22px;
            text-align: center;
            color: #007bff;
            border: 1px solid #ddd;
        }
        .poster-nav-link:hover { background: #fff; }
        
        .select2-container--default .select2-selection--single {
            height: 38px; 
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-right: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-container {
            min-width: 200px;
            vertical-align: middle;
        }
        .select2-dropdown {
            z-index: 9999;
        }
        
        .select2-results__option {
            text-align: right;
        }

    </style>
</head>
<body>
    <div class="container">
        <a href="collection.php?id=<?= $id ?>" style="float: left; font-size: 14px; margin-top: 10px;">⬅ חזרה לצפייה באוסף</a>
        <h2>🛠️ ניהול פוסטרים</h2>
        <h1><?= htmlspecialchars($collection['name']) ?> (<?= count($posters) ?> פריטים)</h1>
        <p style="text-align: center; margin-top: -10px;">סמן פוסטרים לביצוע פעולות גורפות. החזק Shift ובחר פוסטר נוסף לבחירת טווח.</p>
        
        <div id="message-bar"></div>

        <div id="control-bar">
            <label><input type="checkbox" id="selectAllCheckbox"> בחר הכל</label>
            <span id="bulkCount">(0 נבחרו)</span>

            <form id="sortForm" method="get" style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <label for="sortSelect" style="margin:0;white-space:nowrap;">מיון לפי:</label>
                <select id="sortSelect" name="sort">
                    <option value="collection" <?= ($sort_order==='collection'?'selected':'') ?>>📚 לפי סדר האוסף (ברירת מחדל)</option>
                    <option value="site_added_desc" <?= ($sort_order == 'site_added_desc') ? 'selected' : '' ?>>⏱ נוספו לאתר: מהחדש לישן</option>
          <option value="site_added_asc"  <?= ($sort_order == 'site_added_asc') ? 'selected' : '' ?>>⏱ נוספו לאתר: מהישן לחדש</option>
          <option value="col_added_desc" <?= ($sort_order == 'col_added_desc') ? 'selected' : '' ?>>📥 נוספו לאוסף: מהחדש לישן</option>
          <option value="col_added_asc"  <?= ($sort_order == 'col_added_asc') ? 'selected' : '' ?>>📤 נוספו לאוסף: מהישן לחדש</option>
                    <option value="year_desc"  <?= ($sort_order==='year_desc'?'selected':'')  ?>>📅 שנה ↓</option>
                    <option value="year_asc"   <?= ($sort_order==='year_asc'?'selected':'')   ?>>📅 שנה ↑</option>
                    <option value="rating_desc"<?= ($sort_order==='rating_desc'?'selected':'')?>>⭐ דירוג ↓</option>
                    <option value="rating_asc" <?= ($sort_order==='rating_asc'?'selected':'') ?>>⭐ דירוג ↑</option>
                    <option value="title_he_asc" <?= ($sort_order==='title_he_asc'?'selected':'') ?>>🔤 ת-א (עברית)</option>
                    <option value="title_he_desc"<?= ($sort_order==='title_he_desc'?'selected':'')?>>🔤 א-ת (עברית)</option>
                    <option value="title_en_asc" <?= ($sort_order==='title_en_asc'?'selected':'') ?>>🔠 A-Z (English)</option>
                    <option value="title_en_desc"<?= ($sort_order==='title_en_desc'?'selected':'')?>>🔠 Z-A (English)</option>
                </select>
            </form>

            <select id="bulkActionSelect">
                <option value="">בחר פעולה גורפת...</option>
                <option value="pin">📌 נעיצת נבחרים</option>
                <option value="unpin">📌 הסרת נעיצת נבחרים</option>
                <option value="move">🚚 העבר לאוסף אחר...</option>
                <option value="copy">📋 העתק לאוסף אחר...</option>
                <option value="delete">🗑️ מחק נבחרים (רק מהאוסף)</option>
                <option value="delete_permanent">☠ מחיקה לצמיתות מהאתר</option>
            </select>
            <select id="targetCollectionSelect" style="display:none;">
                <option value="">בחר אוסף יעד...</option>
                <?php foreach ($all_collections_list as $coll): ?>
                    <option value="<?= $coll['id'] ?>"><?= htmlspecialchars($coll['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button id="applyBulkBtn">בצע פעולה</button>
        </div>

        <div id="poster-grid-manage">
            <?php foreach ($posters as $p): ?>
                <?php $img = trim($p['image_url'] ?? ''); if ($img === '') $img = 'images/no-poster.png'; ?>
                
                <div class="poster-item <?= $p['is_pinned'] ? 'pinned' : '' ?>" data-poster-id="<?= $p['id'] ?>">
                    
                    <a href="poster.php?id=<?= $p['id'] ?>" class="poster-nav-link" target="_blank" title="פתח בדף חדש" onclick="event.stopPropagation()">🔗</a>
                    
                    <input type="checkbox" class="poster-checkbox" value="<?= $p['id'] ?>">
                    <img src="<?= htmlspecialchars($img) ?>" alt="Poster" loading="lazy">
                    
                    <div class="poster-text-content">
                        
                        <?php if (!empty($p['title_en'])): ?>
                            <div class="title-en"><?= htmlspecialchars($p['title_en']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($p['title_he'])): ?>
                            <div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($p['year'])): ?>
                            <div class="poster-year"><?= htmlspecialchars($p['year']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($p['collection_added_at'])): ?>
                            <div class="poster-year">
                                נוסף לאוסף: <?= htmlspecialchars(date('d/m/Y', strtotime($p['collection_added_at']))) ?>
                                <span style="opacity:.75;">(<?= htmlspecialchars(time_ago_he($p['collection_added_at'])) ?>)</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($p['imdb_rating']) && !empty($p['imdb_id'])): ?>
                            <div class="imdb-rating">
                                <a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()">
                                    ⭐ <?= htmlspecialchars($p['imdb_rating']) ?>/10
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('poster-grid-manage');
    const collectionId = <?= $id ?>;
    const messageBar = document.getElementById('message-bar');
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const allCheckboxes = Array.from(grid.querySelectorAll('.poster-checkbox'));
    const bulkCountSpan = document.getElementById('bulkCount');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const targetCollectionSelect = document.getElementById('targetCollectionSelect');
    const applyBulkBtn = document.getElementById('applyBulkBtn');
    
    $('#targetCollectionSelect').select2({
        dir: "rtl",
        placeholder: "בחר אוסף יעד...",
    });
    $('#targetCollectionSelect').next('.select2-container').hide();

    
    let lastCheckedCheckbox = null;

    function updateBulkSelection() {
        const selectedCount = grid.querySelectorAll('.poster-checkbox:checked').length;
        bulkCountSpan.textContent = `(${selectedCount} נבחרו)`;
        selectAllCheckbox.checked = (selectedCount > 0 && selectedCount === allCheckboxes.length);
    }
    
    grid.addEventListener('change', (e) => {
        if (e.target.classList.contains('poster-checkbox')) {
            const item = e.target.closest('.poster-item');
            item.classList.toggle('selected', e.target.checked);
            
            if (!e.shiftKey) {
                lastCheckedCheckbox = e.target;
            }
            updateBulkSelection();
        }
    });

    grid.addEventListener('click', (e) => {
        if (e.defaultPrevented) {
            return;
        }
        
        if (e.target.closest('.poster-nav-link')) {
            return;
        }
        if (e.target.closest('.imdb-rating a')) {
            return;
        }
        if (e.target.classList.contains('poster-checkbox')) {
            return;
        }
        
        const item = e.target.closest('.poster-item');
        if (!item) return;
        
        const checkbox = item.querySelector('.poster-checkbox');
        if (!checkbox) return;
        
        e.preventDefault(); 

        if (e.shiftKey && lastCheckedCheckbox) {
            const start = allCheckboxes.indexOf(lastCheckedCheckbox);
            const end = allCheckboxes.indexOf(checkbox);
            
            if (start === -1 || end === -1) return;

            const min = Math.min(start, end);
            const max = Math.max(start, end);
            
            for (let i = min; i <= max; i++) {
                const cb = allCheckboxes[i];
                cb.checked = true;
                cb.closest('.poster-item').classList.add('selected');
            }

        } else {
            checkbox.checked = !checkbox.checked;
            item.classList.toggle('selected', checkbox.checked);
            lastCheckedCheckbox = checkbox;
        }
        
        updateBulkSelection();
    });
    

    // "Select All" logic
    selectAllCheckbox.addEventListener('change', () => {
        const isChecked = selectAllCheckbox.checked;
        allCheckboxes.forEach(cb => {
            cb.checked = isChecked;
            cb.closest('.poster-item').classList.toggle('selected', isChecked);
        });
        lastCheckedCheckbox = null;
        updateBulkSelection();
    });

    // Show/hide "Move to" / "Copy to" dropdown
    bulkActionSelect.addEventListener('change', () => {
        const action = bulkActionSelect.value;
        if (action === 'move' || action === 'copy') {
            $('#targetCollectionSelect').next('.select2-container').show();
        } else {
            $('#targetCollectionSelect').next('.select2-container').hide();
        }
    });

    // "Apply Bulk Action" logic
    applyBulkBtn.addEventListener('click', async () => {
        const action = bulkActionSelect.value;
        const selectedIds = getSelectedIds();
        
        if (action === "") {
            alert('אנא בחר פעולה לביצוע.');
            return;
        }
        if (selectedIds.length === 0) {
            alert('לא נבחרו פוסטרים.');
            return;
        }

        // 🔥 אזהרת מחיקה לצמיתות
        if (action === 'delete_permanent') {
            const sure = confirm('אזהרה!\nפעולה זו תמחק את הפוסטרים לחלוטין מכל האתר (לא רק מהאוסף).\nזה בלתי הפיך.\nלהמשיך?');
            if (!sure) {
                return;
            }
        }
        
        let targetCollectionId = null;
        let targetCollectionName = '';
        
        if (action === 'move' || action === 'copy') {
            targetCollectionId = targetCollectionSelect.value;
            const selectedOption = targetCollectionSelect.options[targetCollectionSelect.selectedIndex];
            targetCollectionName = selectedOption ? selectedOption.text : '';

            if (targetCollectionId === "") {
                alert('אנא בחר אוסף יעד.');
                return;
            }
        }
        
        applyBulkBtn.disabled = true;
        applyBulkBtn.textContent = 'מבצע...';
        
        try {
            const response = await fetch('manage_collection_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    collection_id: collectionId,
                    action: action,
                    poster_ids: selectedIds,
                    target_collection_id: targetCollectionId
                })
            });
            const result = await response.json();
            
            if (result.success) {
                let successMessage = `✅ הפעולה בוצעה בהצלחה על ${result.affected_rows} פריטים.`;
                if ((action === 'move' || action === 'copy') && targetCollectionId && targetCollectionName) {
                    successMessage += ` <br> <a href="collection.php?id=${targetCollectionId}" target="_blank">🔗 הצג את אוסף היעד "${targetCollectionName}"</a>`;
                }
                
                showMessage('success', successMessage, action);
                
                updateUIAfterBulk(action, selectedIds, result);
            } else {
                showMessage('error', '❌ שגיאה בביצוע הפעולה: ' + result.error, 'error');
            }
            
        } catch (err) {
            showMessage('error', '❌ שגיאת תקשורת עם השרת.', 'error');
        } finally {
            applyBulkBtn.disabled = false;
            applyBulkBtn.textContent = 'בצע פעולה';
        }
    });

    function getSelectedIds() {
        return allCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
    }
    
    function updateUIAfterBulk(action, selectedIds, result) {
        let itemsToRemove = [];
        let remainingCheckboxes = [];

        allCheckboxes.forEach(cb => {
            const item = cb.closest('.poster-item');
            if (!item) return;

            if (selectedIds.includes(cb.value)) {
                cb.checked = false;
                item.classList.remove('selected');
                
                if (action === 'delete' || action === 'move' || action === 'delete_permanent') {
                    // אם זה הוסר מהאוסף או נמחק לגמרי - להעלים מהמסך
                    itemsToRemove.push(item);
                } else if (action === 'pin' || action === 'unpin') {
                    item.classList.toggle('pinned', action === 'pin');
                    remainingCheckboxes.push(cb);
                } else if (action === 'copy') {
                    remainingCheckboxes.push(cb);
                }
            } else {
                remainingCheckboxes.push(cb);
            }
        });
        
        itemsToRemove.forEach(item => {
            item.style.transition = 'opacity 0.3s';
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
        });
        
        allCheckboxes.length = 0;
        allCheckboxes.push(...remainingCheckboxes);
        
        lastCheckedCheckbox = null;
        updateBulkSelection();
    }
    
    let messageTimeout;
    function showMessage(type, html, action = '') {
        clearTimeout(messageTimeout);
        messageBar.className = type;
        messageBar.innerHTML = html;
        messageBar.style.display = 'block';
        
        if (action !== 'move' && action !== 'copy') {
            messageTimeout = setTimeout(() => {
                messageBar.style.display = 'none';
            }, 4000);
        }
    }
});

// מיון לפי - שליחה אוטומטית
(function(){
  var s = document.getElementById('sortSelect');
  var f = document.getElementById('sortForm');
  if (s && f) {
    s.addEventListener('change', function(){ f.submit(); });
  }
})();

</script>

</body>
</html>

<?php include 'footer.php'; ?>
