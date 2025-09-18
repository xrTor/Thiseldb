<?php
require_once 'server.php'; // וודא שקובץ זה כולל חיבור למסד הנתונים
session_start(); // התחל סשן עבור שמירת משתנים אם צריך
include 'header.php'; // כלול את קובץ ה-header שלך

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

// הגדרות מגבלת פוסטרים לדף
$allowed_limits = [5, 10, 20, 50, 100, 250];
$limit = in_array((int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50), $allowed_limits)
    ? (int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50) : 50;
$_SESSION['limit'] = $limit;

// מצב תצוגה
$view = $_SESSION['view_mode'] = $_GET['view'] ?? $_SESSION['view_mode'] ?? 'modern_grid';

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
    ['lang_code',        'שפות',         '🌐'],
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
            transition: background .18s, border-color .15s;
        }
        .b {
            display: block;
            text-align: center;
            margin: 10px 0 20px 0;
            font-size: 14px;
            color: #555;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="bar-outer">
    <h2>🔍טופס סינון פוסטרים</h2>
    <span class="b" style="color:green">
        הערה: ניתן להשתמש בפסיקים להפרדה, ב-! לשלילה (לדוגמה: !Comedy), ובטווחים מספריים כמו 1990-2000 או 60-
    </span>
    <form class="bar-form" method="get" action="home.php" autocomplete="off">
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
        <div class="bar-bottom-row">
            <label><input type="radio" name="search_mode" value="and" <?= $search_mode === 'and' ? 'checked' : '' ?>> AND</label>
            <label><input type="radio" name="search_mode" value="or" <?= $search_mode === 'or' ? 'checked' : '' ?>> OR</label>
            <button type="button" class="flags-btn" id="toggleFlags">הצג דגלים 🏳️</button>
            <button type="button" class="flags-btn" id="toggleTags">הצג תגיות 🏷️</button>
            <button type="button" class="flags-btn" id="toggleGenres">הצג ז'אנרים 🎭</button>
            <button type="button" class="flags-btn" id="toggleNetworks">הצג רשתות 📡</button>
            <select name="limit"><?php foreach ($allowed_limits as $opt): ?>
                <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?></select>
            <select name="view">
                <option value="modern_grid" <?= $view === 'modern_grid' ? 'selected' : '' ?>>רשת מודרנית</option>
                <option value="collections_view" <?= $view === 'collections_view' ? 'selected' : '' ?>>תצוגת גלריה</option>
                <option value="grid" <?= $view === 'grid' ? 'selected' : '' ?>>Grid</option>
                <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>List</option>
                <option value="default" <?= $view === 'default' ? 'selected' : '' ?>>רגילה</option>
            </select>
            <select name="sort">
                <option value="">מיון</option>
                <option value="year_asc" <?= ($_GET['sort'] ?? '') == 'year_asc' ? 'selected' : '' ?>>שנה ↑</option>
                <option value="year_desc" <?= ($_GET['sort'] ?? '') == 'year_desc' ? 'selected' : '' ?>>שנה ↓</option>
                <option value="rating_desc" <?= ($_GET['sort'] ?? '') == 'rating_desc' ? 'selected' : '' ?>>דירוג ↓</option>
            </select>
            <button type="submit" class="filter-btn">סנן</button>
            <a href="home.php" class="reset-btn">איפוס</a>
        </div>

        <div id="flagsMenu" style="display:none;"><?php include 'links_flags.php'; ?></div>
        <div id="tagsMenu" style="display:none;"><?php include 'links_user_tag.php'; ?></div>
        <div id="genresMenu" style="display:none;"><?php include 'links_genres.php'; ?></div>
        <div id="networksMenu" style="display:none;"><?php include 'links_network.php'; ?></div>
    </form>
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

    const langMenu = document.getElementById("flagsMenu");
    if (langMenu) {
        langMenu.querySelectorAll(".language-cell").forEach(cell => {
            cell.addEventListener("click", () => {
                const lang = cell.getAttribute("data-lang") || cell.title || "";
                if (lang) window.location = "language.php?lang_code=" + encodeURIComponent(lang);
            });
        });
    }
});
</script>
</body>
</html>