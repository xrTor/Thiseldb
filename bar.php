<?php
require_once 'server.php';
session_start();
include 'header.php';

// טבלת סוגים מהמסד
$type_result = $conn->query("SELECT id, label_he, icon FROM poster_types ORDER BY sort_order ASC");
$type_options = [];
while ($type = $type_result->fetch_assoc()) {
    $type_options[$type['id']] = [
        'label' => $type['label_he'],
        'icon'  => $type['icon']
    ];
}
$allowed_limits = [5, 10, 20, 50, 100, 250];
$limit = in_array((int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50), $allowed_limits)
    ? (int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50) : 50;
$_SESSION['limit'] = $limit;

$view = $_SESSION['view_mode'] = $_GET['view'] ?? $_SESSION['view_mode'] ?? 'grid';
$get = fn($k) => $_GET[$k] ?? '';
$search_mode = $get('search_mode') ?: 'and';
$types_selected = $_GET['type'] ?? [];

function fieldVal($k) { return htmlspecialchars($_GET[$k] ?? '', ENT_QUOTES); }
$fields = [
  ['search',            'שם',            '🎬'],
  ['year',              'שנה',           '🗓'],
  ['min_rating',        'IMDb Rating',   '⭐'],
  ['metacritic',        'Metacritic Rating','🎯'],
  ['rt_score',          'Rotten Tomatoes Rating', '🍅'],
  ['imdb_id',           'IMDb ID',       '🔗'],
  ['genre',             'ז׳אנרים',       '🎭'],
  ['user_tag',          'תגיות',         '🏷️'],
  ['actor',             'שחקנים',        '👥'],
  ['directors',         'במאים',         '🎬'],
  ['producers',         'מפיקים',        '🎥'],
  ['writers',           'תסריטאים',      '✍️'],
  ['composers',         'מלחינים',       '🎼'],
  ['cinematographers',  'צלמים',         '📸'],
  ['lang_code',         'שפות',          '🌐'],
  ['country',           'מדינות',        '🌍'],
  ['runtime',           'אורך (דקות)',  '⏱️'],
  ['network',           'רשת',           '📡']  // ← הוחזר כשדה חיפוש
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>טופס סינון פוסטרים</title>
  <style>
    body { font-family: "Segoe UI",Arial,sans-serif; background: #f2f2f6; margin:0; }
    .bar-outer {
      max-width: 1200px;
      margin: 38px auto 6px auto;
      padding: 0;
      background: none;
    }
    h2 {
      font-size: 2em;
      text-align: center;
      margin: 0 0 17px 0;
      font-weight: 600;
    }
    .bar-form { width: 100%; }
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
      transition: border .15s;
    }
    .bar-field input[type="text"]:focus {
      border-color: #268dff;
      background: #fafdff;
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
      gap: 6px 8px;
      margin: 10px 0 6px 0;
    }
    .bar-types label {
      font-size: 15px;
      border-radius: 6px;
      padding: 1px 10px 1px 10px;
      border: 1px solid #e0e0e0;
      background: white;
      display: flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
    }
    .bar-types input[type="checkbox"] {
      margin-left: 5px;
    }
    .bar-bottom-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-start;
      gap: 8px 13px;
      margin: 11px 0 0 0;
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
      transition: background .18s;
    }
    .bar-bottom-row select:focus { border-color: #268dff; background: white; }
    .flags-btn {
      background: ;
      color: #008;
      font-weight: bold;
      border: 1px solid #3498db;
      cursor: pointer;
      padding: 3px 10px;
    }
    .flags-btn.active { background: #cae7ff; }
    .reset-btn { background: #eee; color: #333; border: 1px solid #bbb; }
    .reset-btn:hover { background: #ddd; }
    .filter-btn {
      background: #268dff;
      color: black;
      font-weight: bold;
      border: 1px solid #2274c8;
      cursor: pointer;
    }
    .filter-btn:hover { background: #176dc7; }
    .info-text {
      color: #444;
      font-size: 13px;
      text-align: center;
      margin: 8px 0 0 0;
    }
    @media (max-width: 1100px) {
      .bar-fields-row { grid-template-columns: repeat(3,1fr);}
    }
    @media (max-width: 800px) {
      .bar-fields-row { grid-template-columns: repeat(2,1fr);}
      .bar-outer { padding: 7vw 2vw 2vw 2vw; }
    }
    @media (max-width: 600px) {
      .bar-fields-row input[type="text"] { font-size: 13px;}
      .bar-types label { font-size: 13px;}
      .bar-bottom-row select, .bar-bottom-row button { font-size: 13px;}
    }
  </style>
</head>
<body>
<div class="bar-outer">
  <h2>🔍טופס סינון פוסטרים <span style="font-size:22px;"></span></h2>
<span class="b">
  הערה: ניתן להשתמש בפסיקים להפרדה, ב-! לשלילה (לדוגמה: !Comedy), ובטווחים מספריים כמו 1990-2000 או 60-
</span><br><br>
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
          <?= htmlspecialchars($data['icon'] . ' ' . $data['label']) ?>
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

    <div id="flagsMenu" style="display:none;"><?php include 'flags_links.php'; ?></div>
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
      div.style.display = div.style.display === "block" ? "none" : "block";
      btn.classList.toggle('active');
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
