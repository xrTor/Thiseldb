<?php
require_once 'server.php'; // וודא שקובץ זה כולל חיבור למסד הנתונים
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include 'header.php'; // כלול את קובץ ה-header שלך

// === פונקציה לבניית קישורי מיון מהיר (שומרת את כל פרמטרי ה-GET) ===
if (!function_exists('thiseldb_build_sort_url')) {
    function thiseldb_build_sort_url(string $sortValue): string {
        $params = $_GET;
        $params['sort'] = $sortValue;
        // בעת שינוי מיון נחזור לעמוד הראשון
        $params['page'] = 1;
        $self = $_SERVER['PHP_SELF'] ?? 'home.php';
        $base = basename($self);
        return $base . '?' . http_build_query($params);
    }
}

$current_sort = $_GET['sort'] ?? '';

// שליפת טבלת סוגים מהמסד
// וודא שעמודת 'image' קיימת בטבלת poster_types במסד הנתונים
$type_result = $conn->query("SELECT id, label_he, icon, image FROM poster_types ORDER BY sort_order ASC");
$type_options = [];
while ($type = $type_result->fetch_assoc()) {
    $type_options[$type['id']] = [
        'label' => $type['label_he'],
        'icon'  => $type['icon'],
        'image' => $type['image'] // נתיב לקובץ תמונה (לדוגמה: 'movie.png')
    ];
}

// === הוספת לוגיקת גודל פוסטר (SIZE) + תרגום לתוויות ===
$allowed_sizes = [210, 240, 320];
$size_labels = [
    210 => 'קטן',
    240 => 'בינוני',
    320 => 'גדול'
];
$size_param = $_GET['size'] ?? $_SESSION['poster_size'] ?? 260; // ברירת מחדל ל-260
$card_w = in_array((int)$size_param, $allowed_sizes, true) ? (int)$size_param : 260;
$_SESSION['poster_size'] = $card_w;
// =======================================================

// הגדרות מגבלת פוסטרים לדף
$allowed_limits = [5, 10, 20, 50, 100, 250, 500, 1000, 2500, 30000];
$limit = in_array((int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50), $allowed_limits)
    ? (int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50) : 50;
$_SESSION['limit'] = $limit;

// מצב תצוגה
$view = $_SESSION['view_mode'] = $_GET['view'] ?? $_SESSION['view_mode'] ?? 'modern_grid';
$view_options = [
    'modern_grid'    => 'רשת מודרנית',
    'collections_view' => 'תצוגת גלריה',
    'grid'           => 'Grid',
    'list'           => 'List',
    'default'        => 'רגילה'
];


// פונקציה לקבלת ערך מ-GET או ברירת מחדל
$get = fn($k) => $_GET[$k] ?? '';

// מצב חיפוש (AND/OR)
$search_mode = $get('search_mode') ?: 'and';

// סוגים שנבחרו בטופס
$types_selected = $_GET['type'] ?? [];

// פונקציה לניקוי קלט HTML
function fieldVal($k) { return htmlspecialchars($_GET[$k] ?? '', ENT_QUOTES); }

// הגדרת שדות החיפוש עם שמות, Placeholders ואייקונים
$fields = [
    ['search',           'שם',          '🎬'],
    ['year',             'שנה',          '🗓'],
    ['min_rating',       'IMDb Rating',  '⭐'],
    ['metacritic',       'Metacritic Rating','🎯'],
    ['rt_score',         'Rotten Tomatoes Rating', '🍅'],
    ['imdb_id',          'IMDb ID',      '🔗'],
    ['genre',            'ז׳אנרים',      '🎭'],
    ['user_tag',         'תגיות',        '🏷️'],
    ['actor',            'שחקנים',       '👥'],
    ['directors',        'במאים',        '🎬'],
    ['producers',        'מפיקים',       '🎥'],
    ['writers',          'תסריטאים',     '✍️'],
    ['composers',        'מלחינים',      '🎼'],
    ['cinematographers', 'צלמים',        '📸'],
    ['lang_code',        'שפות',         '🌐'], // זה השדה הטקסטואלי הרגיל מ-IMDB
    ['country',          'מדינות',       '🌍'],
    ['runtime',          'אורך (דקות)',  '⏱️'],
    ['network',          'רשת',          '📡']
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>טופס סינון פוסטרים</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f2f2f6;
            margin: 0;
            color: #333;
        }
        .bar-outer {
            max-width: 1200px;
            margin: 38px auto 6px auto;
            padding: 0 15px;
            background: none;
        }
        h2 {
            font-size: 2em;
            text-align: center;
            margin: 0 0 17px 0;
            font-weight: 600;
            color: #222;
        }
        .bar-form {
            width: 100%;
        }
        .bar-fields-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 7px 8px;
            margin-bottom: 8px;
        }
        .bar-field {
            position: relative;
            display: flex;
            align-items: center;
        }
        .bar-field input[type="text"] {
            width: 100%;
            font-size: 15px;
            border: 1px solid #bbb;
            border-radius: 7px;
            padding: 5px 30px 5px 8px;
            background: white;
            margin: 0;
            box-sizing: border-box;
            transition: border .15s, box-shadow .15s;
        }
        .bar-field input[type="text"]:focus {
            border-color: #268dff;
            background: #fafdff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(38, 141, 255, 0.2);
        }
        .bar-icon {
            position: absolute;
            right: 7px;
            font-size: 16px;
            pointer-events: none;
            opacity: .77;
        }

        .bar-types {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 15px;
            margin: 15px 0 10px 0;
        }
        .bar-types label {
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: none;
            padding: 5px;
            border-radius: 8px;
            cursor: pointer;
        }
        .bar-types input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .type-content-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .type-image {
            height: 80px;
            margin-bottom: 4px;
            object-fit: contain;
        }
        .type-label-text {
            font-size: 13px;
            color: #333;
            text-align: center;
            line-height: 1.2;
            transition: all 0.2s ease;
        }
        .bar-types input[type="checkbox"]:checked + .type-content-wrapper .type-label-text {
            font-weight: bold;
            color: #0056b3;
        }

        .bar-bottom-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 8px 13px;
            margin: 15px 0 0 0;
        }
        .bar-bottom-row select,
        .bar-bottom-row button,
        .bar-bottom-row .flags-btn,
        .bar-bottom-row .reset-btn {
            font-size: 15px;
            border-radius: 6px;
            padding: 3px 11px;
            border: 1px solid #c0c0cc;
            background: #fff;
            transition: background .18s, border-color .15s;            cursor: pointer;

        }
        .b {
            display: block;
            text-align: center;
            margin: 10px 0 20px 0;
            font-size: 14px;
            color: #555;
            line-height: 1.5;
        }

        .sort-bar {
            text-align: center;
            margin: 6px 0 14px 0;
            font-size: 13px;
        }
        .sort-btn {
            display: inline-block;
            background: #f5f5f5;
            border-radius: 16px;
            border: 1px solid #ccc;
            color: #333;
            font-size: 13px;
            padding: 3px 9px;
            margin: 0 2px 6px 2px;
            text-decoration: none;
        }
        .sort-btn.selected {
            background: #28a745;
            color: #fff;
            border-color: #1e7e34;
            font-weight: bold;
        }
        
        /* === סגנונות חדשים: כפתורי Limit & Size & View - מפוצלים לשורות === */
        .controls-row {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 13px;
            margin-bottom: 8px; /* הפרדה בין השורות */
        }
        
        /* סגנון חדש לשמירת קבוצות הבקרה (View + Size) בשורה אחת */
        .view-size-row {
            flex-wrap: wrap; /* שומר על יכולת מעבר שורה במסכים קטנים */
            justify-content: flex-start;
            gap: 25px; /* רווח בין קבוצות הבקרה View ו-Size */
            margin-bottom: 8px;
        }

        /* עוטף כל קבוצת בקרה (תצוגה או גודל) כדי לשלוט ב-Display */
        .control-group-wrapper {
            display: flex;
            align-items: center;
            gap: 8px 13px;
        }

        .limit-buttons-group, .size-buttons-group, .view-buttons-group {
            display: inline-flex;
            gap: 4px;
            flex-wrap: wrap;
            align-items: center;
        }
        .limit-btn, .size-btn, .view-btn {
            font-size: 14px;
            border-radius: 6px;
            padding: 4px 8px;
            border: 1px solid #c0c0cc;
            background: #fff;
            cursor: pointer;
            transition: background .18s, border-color .15s;
        }
        .limit-btn:hover, .size-btn:hover, .view-btn:hover {
            background: #f0f0f0;
        }
        /* הצבע החדש לסימון הנוכחי */
        .limit-btn-selected, .size-btn-selected, .view-btn-selected {
            background: deepskyblue;
            color: #fff;
            border-color: #00bfff;
            font-weight: bold;
        }
        .control-label {
            font-size: 15px;
            font-weight: 600;
            color: #444;
            padding-right: 5px;
        }
        /* ================================================================= */
    </style>
</head>
<body>
<div class="bar-outer">
    <h2>🔍טופס סינון פוסטרים</h2>
    <span class="b" style="color:green">
        הערה: ניתן להשתמש בפסיקים להפרדה, ב-! לשלילה (לדוגמה: !Comedy), ובטווחים מספריים כמו 1990-2000 או 60-
    </span>
     <span class="b" style="color:blue">
ניתן לחפש בעברית, לדוגמא: מדינה: קנדה, שפה: צרפתית, רשת שידור: נטפליקס, ז'אנר: מתח, תגית: פוליטיקה    </span>
    <form class="bar-form" method="get" action="home.php" autocomplete="off">
        
        <input type="hidden" name="flag_code" id="hidden_flag_code" value="<?= htmlspecialchars($_GET['flag_code'] ?? '') ?>">

        <div class="bar-fields-row">
            <?php foreach ($fields as [$name, $placeholder, $icon]): ?>
            <div class="bar-field">
                <input type="text" name="<?= $name ?>" placeholder="<?= $placeholder ?>" value="<?= fieldVal($name) ?>">
                <span class="bar-icon"><?= $icon ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bar-types">
            <?php foreach ($type_options as $tid => $data): ?>
                <label>
                    <input type="checkbox" name="type[]" value="<?= $tid ?>" <?= in_array($tid, $types_selected) ? 'checked' : '' ?>>
                    
                    <div class="type-content-wrapper">
                        <?php
                        $image_base_path = 'images/types/';
                        if (!empty($data['image'])) {
                            $image_path = $image_base_path . htmlspecialchars($data['image']);
                            echo '<img src="' . $image_path . '" alt="' . htmlspecialchars($data['label']) . '" class="type-image">';
                        } else {
                            echo '<span class="type-image" style="font-size: 24px; display: flex; align-items: center; justify-content: center;">' . htmlspecialchars($data['icon']) . '</span>';
                        }
                        echo '<span class="type-label-text">' . htmlspecialchars($data['label']) . '</span>';
                        ?>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        
        <div class="controls-row">
            <span class="control-label">כמות פוסטרים להצגה: </span>
            <div class="limit-buttons-group">
                <?php foreach ($allowed_limits as $opt):
                    $lbl = ($opt == 30000 ? 'הכל' : $opt);
                    $cls = $limit == $opt ? ' limit-btn-selected' : '';
                ?>
                    <button type="submit" name="limit" value="<?= $opt ?>" class="limit-btn<?= $cls ?>" title="הצג <?= $lbl ?> פוסטרים">
                        <?= $lbl ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="controls-row view-size-row">
            <div class="control-group-wrapper">
                <span class="control-label">תצוגה:</span>
                <div class="view-buttons-group">
                    <?php foreach ($view_options as $val => $label):
                        $cls = $view === $val ? ' view-btn-selected' : '';
                    ?>
                        <button type="submit" name="view" value="<?= $val ?>" class="view-btn<?= $cls ?>" title="תצוגה: <?= $label ?>">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="control-group-wrapper">
                <span class="control-label">גודל פוסטר:</span>
                <div class="size-buttons-group">
                    <?php foreach ($allowed_sizes as $sz):
                        $lbl = $size_labels[$sz] ?? $sz.'px';
                        $cls = $card_w === $sz ? ' size-btn-selected' : ''; ?>
                        <button type="submit" name="size" value="<?= (int)$sz ?>" class="size-btn<?= $cls ?>" title="רוחב: <?= (int)$sz ?>px">
                            <?= $lbl ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="bar-bottom-row">
            <label><input type="radio" name="search_mode" value="and" <?= $search_mode === 'and' ? 'checked' : '' ?>> AND</label>
            <label><input type="radio" name="search_mode" value="or" <?= $search_mode === 'or' ? 'checked' : '' ?>> OR</label>
            <button type="button" class="flags-btn" id="toggleFlags">הצג דגלים 🏳️</button>
            <button type="button" class="flags-btn" id="toggleTags">הצג תגיות 🏷️</button>
            <button type="button" class="flags-btn" id="toggleGenres">הצג ז'אנרים 🎭</button>
            <button type="button" class="flags-btn" id="toggleNetworks">הצג רשתות 📡</button>
       
            <button type="submit" class="filter-btn">סנן</button>
            <a href="home.php" class="reset-btn">איפוס</a>
        </div>

        <div id="flagsMenu" style="display:none;"><?php include 'links_flags.php'; ?></div>
        <div id="tagsMenu" style="display:none;"><?php include 'links_user_tag.php'; ?></div>
        <div id="genresMenu" style="display:none;"><?php include 'links_genres.php'; ?></div>
        <div id="networksMenu" style="display:none;"><?php include 'links_network.php'; ?></div>
    </form>

    <div class="sort-bar">
        מיון מהיר:
        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('rating_desc')) ?>"
           class="sort-btn<?= $current_sort === 'rating_desc' ? ' selected' : '' ?>">דירוג ↓</a>
        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('rating_asc')) ?>"
           class="sort-btn<?= $current_sort === 'rating_asc' ? ' selected' : '' ?>">דירוג ↑</a>

        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('votes_desc')) ?>"
           class="sort-btn<?= $current_sort === 'votes_desc' ? ' selected' : '' ?>">כמות הצבעות ↓</a>
        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('votes_asc')) ?>"
           class="sort-btn<?= $current_sort === 'votes_asc' ? ' selected' : '' ?>">כמות הצבעות ↑</a>

        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('year_desc')) ?>"
           class="sort-btn<?= $current_sort === 'year_desc' ? ' selected' : '' ?>">שנה ↓</a>
        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('year_asc')) ?>"
           class="sort-btn<?= $current_sort === 'year_asc' ? ' selected' : '' ?>">שנה ↑</a>

        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('added_desc')) ?>"
           class="sort-btn<?= $current_sort === 'added_desc' ? ' selected' : '' ?>">תאריך הוספה ↓</a>
           <a class="sort-btn" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'updated_at_desc'])); ?>">
    התעדכן לאחרונה ↓
</a>

        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('added_asc')) ?>"
           class="sort-btn<?= $current_sort === 'added_asc' ? ' selected' : '' ?>">תאריך הוספה ↑</a>

        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('runtime_desc')) ?>"
           class="sort-btn<?= $current_sort === 'runtime_desc' ? ' selected' : '' ?>">אורך ↓</a>
        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('runtime_asc')) ?>"
           class="sort-btn<?= $current_sort === 'runtime_asc' ? ' selected' : '' ?>">אורך ↑</a>

        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('title_en_asc')) ?>"
           class="sort-btn<?= $current_sort === 'title_en_asc' ? ' selected' : '' ?>">ABC ↑</a>
        <a href="<?= htmlspecialchars(thiseldb_build_sort_url('title_en_desc')) ?>"
           class="sort-btn<?= $current_sort === 'title_en_desc' ? ' selected' : '' ?>">ABC ↓</a>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const toggle = (btnId, divId) => {
        const btn = document.getElementById(btnId);
        const div = document.getElementById(divId);
        if (!btn || !div) return;
        btn.addEventListener('click', () => {
            if (div.style.display === "block") {
                div.style.display = "none";
                btn.classList.remove('active');
            } else {
                document.querySelectorAll('.flags-btn.active').forEach(activeBtn => {
                    const menuId = activeBtn.id.replace('toggle', '') + 'Menu';
                    document.getElementById(menuId).style.display = 'none';
                    activeBtn.classList.remove('active');
                });
                div.style.display = "block";
                btn.classList.add('active');
            }
        });
    };
    
    toggle("toggleFlags", "flagsMenu");
    toggle("toggleTags", "tagsMenu");
    toggle("toggleGenres", "genresMenu");
    toggle("toggleNetworks", "networksMenu");
});

// פונקציה לשליחת הטופס בעת לחיצה על דגל
function selectFlag(code) {
    // 1. עדכון השדה הנסתר של הדגלים בלבד
    const hiddenInput = document.getElementById('hidden_flag_code');
    if(hiddenInput) hiddenInput.value = code;
    
    // 2. איפוס עמוד ל-1
    const pageInput = document.querySelector('input[name="page"]');
    // אם אין שדה page מוסתר, אין צורך לאפס
    if (pageInput) pageInput.value = 1;

    // 3. שליחת הטופס
    const form = document.querySelector('.bar-form');
    if(form) form.submit();
}
</script>
</body>
</html>