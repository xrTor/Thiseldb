<?php
// full-info.php — צבעוני + אייקונים + כפתור יחיד הצג/הסתר + ללא חיתוך טקסט
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'server.php';
include 'header.php';

/* ===== Helpers ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_split($s){
  if ($s === null) return [];
  $s = (string)$s;
  if ($s === '') return [];
  $s = str_replace([';', '/', '|'], ',', $s);
  $parts = array_map('trim', explode(',', $s));
  return array_values(array_filter($parts, function($x){ return $x!==''; })); // תאימות לאחור
}
function aggFromColumn(mysqli $conn, string $col): array {
  $rs = $conn->query("SELECT `$col` AS v FROM posters WHERE `$col` IS NOT NULL AND `$col`<>''");
  $m = [];
  if ($rs) while($row=$rs->fetch_assoc()){
    foreach (norm_split($row['v']) as $name){
      $key = mb_strtolower($name,'UTF-8');
      if (!isset($m[$key])) $m[$key] = ['label'=>$name,'count'=>1];
      else $m[$key]['count']++;
    }
  }
  uasort($m, function($a,$b){
    if ($a['count']===$b['count']) return strnatcasecmp($a['label'],$b['label']);
    return $b['count'] <=> $a['count'];
  });
  return $m;
}

/** בונה URL חיפוש ל-home.php עם ברירות מחדל ומעליה overrides */
function buildSearchQuery(array $overrides = []): string {
  $defaults = [
    'search' => '',
    'year' => '',
    'min_rating' => '',
    'metacritic' => '',
    'rt_score' => '',
    'imdb_id' => '',
    'genre' => '',
    'user_tag' => '',
    'actor' => '',
    'directors' => '',
    'producers' => '',
    'writers' => '',
    'composers' => '',
    'cinematographers' => '',
    'lang_code' => '',
    'country' => '',
    'runtime' => '',
    'network' => '',
    'search_mode' => 'and',
    'limit' => '50',
    'view' => 'modern_grid',
    'sort' => '',
  ];
  $params = array_merge($defaults, $overrides);
  return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}


/* ===== Data ===== */
$languages   = aggFromColumn($conn, 'languages');
$countries   = aggFromColumn($conn, 'countries');
$genres      = aggFromColumn($conn, 'genres');
$networks    = aggFromColumn($conn, 'networks');

$actors      = aggFromColumn($conn, 'cast');
$directors   = aggFromColumn($conn, 'directors');
$writers     = aggFromColumn($conn, 'writers');
$producers   = aggFromColumn($conn, 'producers');
$composers   = aggFromColumn($conn, 'composers');
$cinematog   = aggFromColumn($conn, 'cinematographers');

$years = [];
if ($r = $conn->query("SELECT year, COUNT(*) c FROM posters WHERE year IS NOT NULL AND year<>'' GROUP BY year ORDER BY year DESC")) {
  while($row=$r->fetch_assoc()){
    $y = trim((string)$row['year']);
    if ($y!=='') $years[$y] = ['label'=>$y, 'count'=>(int)$row['c']];
  }
}

$collections = [];
$q = "SELECT c.id, c.name, COUNT(pc.poster_id) cnt
      FROM collections c
      LEFT JOIN poster_collections pc ON pc.collection_id=c.id
      GROUP BY c.id
      ORDER BY cnt DESC, c.name ASC";
if ($r = $conn->query($q)) {
  while($row=$r->fetch_assoc()){
    $collections[(string)$row['id']] = [
      'id'=>(int)$row['id'],
      'label'=>$row['name'],
      'count'=>(int)$row['cnt'],
    ];
  }
}

$user_tags = [];
if ($r = $conn->query("SELECT genre, COUNT(*) c FROM user_tags GROUP BY genre ORDER BY c DESC, genre ASC")) {
  while($row=$r->fetch_assoc()){
    $g = trim((string)$row['genre']);
    if ($g!==''){
      $key = mb_strtolower($g,'UTF-8');
      $user_tags[$key] = ['label'=>$g, 'count'=>(int)$row['c']];
    }
  }
}

$types = [];
if ($r = $conn->query("SELECT pt.id, COALESCE(pt.label_he, pt.label_en, pt.code) label,
                       COUNT(p.id) c
                       FROM poster_types pt
                       LEFT JOIN posters p ON p.type_id=pt.id
                       GROUP BY pt.id
                       ORDER BY c DESC, label ASC")) {
  while($row=$r->fetch_assoc()){
    $types[(string)$row['id']] = [
      'id'=>(int)$row['id'],
      'label'=>$row['label'],
      'count'=>(int)$row['c'],
    ];
  }
}

/* צבעים */
$colors = [
  "#d1ecf1","#d4edda","#fff3cd","#f8d7da","#e2e3e5","#fde2ff",
  "#e0f7fa","#fce4ec","#f1f8e9","#fff8e1","#e3f2fd","#ede7f6"
];

/* אייקונים לכל מחלקה */
$icons = [
  'languages'   => '🌐 ',
  'countries'   => '🌍 ',
  'genres'      => '🎭 ',
  'networks'    => '📡 ',
  'actors'      => '👥 ',
  'directors'   => '🎬 ',
  'writers'     => '✍️ ',
  'producers'   => '🎥 ',
  'composers'   => '🎼 ',
  'cinematog'   => '📸 ',
  'years'       => '🗓 ',
  'collections' => '🧩 ',
  'user_tags'   => '🏷️ ',
  'types'       => '🧪 ',
];

/* ===== Sections (עם תאימות לאחור) ===== */
$sections = [
    ['languages',   'שפות',          $languages,   function($lbl){ return 'home.php?'.buildSearchQuery(['lang_code'=>$lbl]); }],
    ['countries',   'מדינות',        $countries,   function($lbl){ return 'home.php?'.buildSearchQuery(['country'=>$lbl]); }],
    ['genres',      'ז׳אנרים',       $genres,      function($lbl){ return 'home.php?'.buildSearchQuery(['genre'=>$lbl]); }],
    ['networks',    'רשתות',         $networks,    function($lbl){ return 'home.php?'.buildSearchQuery(['network'=>$lbl]); }],

    ['actors',      'שחקנים',        $actors,      function($lbl){ return 'home.php?'.buildSearchQuery(['actor'=>$lbl]); }],
    ['directors',   'במאים',         $directors,   function($lbl){ return 'home.php?'.buildSearchQuery(['directors'=>$lbl]); }],
    ['writers',     'תסריטאים',      $writers,     function($lbl){ return 'home.php?'.buildSearchQuery(['writers'=>$lbl]); }],
    ['producers',   'מפיקים',        $producers,   function($lbl){ return 'home.php?'.buildSearchQuery(['producers'=>$lbl]); }],
    ['composers',   'מלחינים',       $composers,   function($lbl){ return 'home.php?'.buildSearchQuery(['composers'=>$lbl]); }],
    ['cinematog',   'צלמים',         $cinematog,   function($lbl){ return 'home.php?'.buildSearchQuery(['cinematographers'=>$lbl]); }],

    ['years',       'שנים',          $years,       function($lbl){ return 'home.php?'.buildSearchQuery(['year'=>$lbl]); }],
    ['collections', 'אוספים',        $collections, function($lbl, $row){ return 'collection.php?id='.urlencode($row['id'] ?? $lbl); }],
    ['user_tags',   'תגיות',         $user_tags,   function($lbl){ return 'home.php?'.buildSearchQuery(['user_tag'=>$lbl]); }],
    ['types',       'סוגים',         $types,       function($lbl, $row){ return 'home.php?'.buildSearchQuery(['type[]'=>(string)($row['id'] ?? $lbl)]); }],
];
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>Full Info — כל המידע (צבעוני + אייקונים)</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background:#fff; margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial; }
    .wrap { max-width:1200px; margin:22px auto; padding:0 12px; }
    h1 { text-align:center; margin:0 0 14px; }

    /* תפריט קפיצה */
    .toc { text-align:center; margin:10px 0 18px; }
    .toc a {
      display:inline-block; margin:4px 5px; padding:6px 12px;
      border-radius:999px; text-decoration:none; color:#123; font-size:14px;
      background:white; border:1px solid #d8e6ff;
    }
    .toc a:hover { background:#dff0ff; }
    .toc .cnt { margin-inline-start:4px; }
    .ico { font-size:16px; margin-inline-start:6px; }

    .sec { margin:22px 0; }
    .sec h2 { margin:0 0 12px; font-size:20px; display:flex; align-items:center; gap:10px; }
    .toggle-btn { font-size:13px; padding:4px 10px; border:1px solid #cfd8ea; border-radius:10px; background:#f1f6ff; cursor:pointer; }
    .toggle-btn:hover { background:#e7f1ff; }

    /* גריד: עד 6 בעמודה */
    .grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:8px; }
    @media (max-width:1100px){ .grid{ grid-template-columns:repeat(4, minmax(0,1fr)); } }
    @media (max-width:800px){  .grid{ grid-template-columns:repeat(3, minmax(0,1fr)); } }
    @media (max-width:560px){  .grid{ grid-template-columns:repeat(2, minmax(0,1fr)); } }

    /* "כדור" צבעוני — ללא חיתוך, המספר והשם באותו צבע */
    .pill {
      display:flex; align-items:center; justify-content:flex-start; gap:8px;
      padding:8px 12px; border-radius:16px; text-decoration:none; color:#222;
      border:1px solid rgba(0,0,0,.06); transition:transform .1s ease, box-shadow .1s ease;
      direction:rtl; white-space:normal;
    }
    .pill:hover { transform:scale(1.03); box-shadow:0 2px 6px rgba(0,0,0,.15); }
    .nm { }
    .cnt { color:inherit; }

    /* מצב מוסתר: רק הגריד נסגר, הכותרת/כפתור נשארים */
    .sec.collapsed .grid { display:none; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>📚 כל הערכים באתר</h1>
  <div style="text-align:center;margin:8px 0 10px;">
    <a href="full-info-he.php" class="toggle-btn">↗ אל העמוד בעברית</a>
  </div>

  <div class="toc" id="toc">
    <?php foreach ($sections as $section): 
        list($id, $title, $data) = $section;
        $cnt = is_array($data) ? count($data) : 0;
    ?>
      <a href="#sec-<?=h($id)?>" data-sec="<?=h($id)?>">
        <span class="ico"><?= h($icons[$id] ?? '•') ?></span><?=h($title)?>
        <span class="cnt">(<?= (int)$cnt ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
  $palette = $colors; $paletteCount = count($palette);
  $fixedCols = 6; // מקסימום 6 בעמודה
  foreach ($sections as $section):
    list($id, $title, $data, $linkFn) = $section;
    $totalItems = is_array($data) ? count($data) : 0;
    $icon = $icons[$id] ?? '•';
  ?>
    <section class="sec" id="sec-<?=h($id)?>" data-sec="<?=h($id)?>">
      <h2>
        <span class="ico"><?= h($icon) ?></span>
        <?=h($title)?>
        <button type="button" class="toggle-btn" data-sec="<?=h($id)?>" data-state="open">הסתר</button>
      </h2>

      <?php if (empty($data)): ?>
        <p style="color:#777; margin:8px 0 16px;">אין נתונים להצגה.</p>
      <?php else: ?>
        <div class="grid">
          <?php
          $i=0;
          foreach ($data as $k=>$row):
            $label = is_array($row) ? ($row['label'] ?? (string)$k) : (string)$row;
            $count = (int)($row['count'] ?? 0);
            $href  = ($id==='collections' || $id==='types') ? $linkFn($label, $row) : $linkFn($label);

            // צבע לא חוזר באותה עמודה
            $col = $i % $fixedCols;
            // -- התיקון כאן --
            $rowIdx = floor($i / $fixedCols); // החלפה של intdiv ל-floor שעובד בכל הגרסאות
            $colorIndex = ($rowIdx + $col) % $paletteCount;
            $bg = $palette[$colorIndex];

            $i++;
          ?>
            <a class="pill" href="<?=h($href)?>" style="background: <?=h($bg)?>;">
              <span class="cnt">(<?= $count ?>)</span>
              <span class="nm"><?=h($label)?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>

<script>
// כפתור יחיד "הצג/הסתר" — משנה טקסט בהתאם למצב
document.addEventListener('click', function(e){
  const btn = e.target.closest('.toggle-btn');
  if (!btn) return;
  const id = btn.getAttribute('data-sec');
  const sec = document.querySelector('.sec[data-sec="'+id+'"]');
  if (!sec) return;

  const isOpen = btn.getAttribute('data-state') !== 'closed';
  if (isOpen) {
    sec.classList.add('collapsed');
    btn.setAttribute('data-state','closed');
    btn.textContent = 'הצג';
  } else {
    sec.classList.remove('collapsed');
    btn.setAttribute('data-state','open');
    btn.textContent = 'הסתר';
  }
});

// קפיצה מהתפריט: אם מוסתר — נפתח וגם מעדכן טקסט הכפתור
document.getElementById('toc').addEventListener('click', function(e){
  const a = e.target.closest('a[data-sec]');
  if (!a) return;
  const id = a.getAttribute('data-sec');
  const sec = document.querySelector('.sec[data-sec="'+id+'"]');
  if (!sec) return;
  if (sec.classList.contains('collapsed')) {
    sec.classList.remove('collapsed');
    const btn = document.querySelector('.toggle-btn[data-sec="'+id+'"]');
    if (btn) { btn.setAttribute('data-state','open'); btn.textContent='הסתר'; }
  }
});
</script>
<script>
// === זיכרון מצב הצגה/הסתרה למקטעים (ללא שינוי קוד קיים) ===
(function () {
  const KEY = 'fi_hidden_sections_v1';

  function getHidden() {
    try { return JSON.parse(localStorage.getItem(KEY)) || []; }
    catch (e) { return []; }
  }
  function setHidden(arr) {
    try { localStorage.setItem(KEY, JSON.stringify(Array.from(new Set(arr)))); }
    catch (e) {}
  }
  function addHidden(id) {
    const a = getHidden();
    if (!a.includes(id)) { a.push(id); setHidden(a); }
  }
  function removeHidden(id) {
    setHidden(getHidden().filter(function(x){ return x !== id; })); // תאימות לאחור
  }

  // שחזור מצב בהטענה
  document.addEventListener('DOMContentLoaded', function () {
    getHidden().forEach(function (id) {
      const sec = document.querySelector('.sec[data-sec="' + id + '"]');
      const btn = document.querySelector('.toggle-btn[data-sec="' + id + '"]');
      if (sec) sec.classList.add('collapsed');
      if (btn) { btn.setAttribute('data-state', 'closed'); btn.textContent = 'הצג'; }
    });
  });

  // בעת לחיצה על כפתור הצג/הסתר – לעדכן זיכרון אחרי שהקוד המקורי סיים
  document.addEventListener('click', function (e) {
    const btn = e.target.closest && e.target.closest('.toggle-btn');
    if (btn) {
      const id = btn.getAttribute('data-sec');
      // להריץ אחרי המאזין המקורי כדי לקרוא את המצב הסופי
      setTimeout(function () {
        const sec = document.querySelector('.sec[data-sec="' + id + '"]');
        if (!sec) return;
        if (sec.classList.contains('collapsed')) addHidden(id);
        else removeHidden(id);
      }, 0);
    }

    // קפיצה מתפריט הניווט למקטע — אם הוא נפתח על־ידי הקוד הקיים, להסיר מהזיכרון
    const tocLink = e.target.closest && e.target.closest('#toc a[data-sec]');
    if (tocLink) {
      const id = tocLink.getAttribute('data-sec');
      setTimeout(function () { removeHidden(id); }, 0);
    }
  });
})();
</script>
<script>
(function () {
  const KEY = 'fi_hidden_sections_v1';

  function getAllIds() {
    return Array.from(document.querySelectorAll('.sec[data-sec]'))
      .map(function(s){ return s.getAttribute('data-sec'); }); // תאימות לאחור
  }

  function saveHiddenFromDOM() {
    const hidden = getAllIds().filter(function(id){
      const sec = document.querySelector('.sec[data-sec="'+id+'"]');
      return sec && sec.classList.contains('collapsed');
    });
    try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch(e) {}
  }

  function setAll(open) {
    document.querySelectorAll('.sec[data-sec]').forEach(function(sec){
      const id  = sec.getAttribute('data-sec');
      const btn = document.querySelector('.toggle-btn[data-sec="'+id+'"]');
      if (open) {
        sec.classList.remove('collapsed');
        if (btn) { btn.setAttribute('data-state','open'); btn.textContent='הסתר'; }
      } else {
        sec.classList.add('collapsed');
        if (btn) { btn.setAttribute('data-state','closed'); btn.textContent='הצג'; }
      }
    });
    try {
      localStorage.setItem(KEY, JSON.stringify(open ? [] : getAllIds()));
    } catch(e) {}
  }

  document.addEventListener('DOMContentLoaded', function () {
    // יצירת שני הכפתורים והזרקתם למעלה (לפני תפריט הקפיצה)
    const toc = document.getElementById('toc');
    const wrap = document.querySelector('.wrap');
    const box = document.createElement('div');
    box.style.textAlign = 'center';
    box.style.margin = '8px 0 10px';

    const btnShowAll = document.createElement('button');
    btnShowAll.type = 'button';
    btnShowAll.className = 'toggle-btn';
    btnShowAll.textContent = 'הצג הכל';
    btnShowAll.addEventListener('click', function(){ setAll(true); }); // תאימות לאחור

    const btnHideAll = document.createElement('button');
    btnHideAll.type = 'button';
    btnHideAll.className = 'toggle-btn';
    btnHideAll.style.marginInlineStart = '8px';
    btnHideAll.textContent = 'הסתר הכל';
    btnHideAll.addEventListener('click', function(){ setAll(false); }); // תאימות לאחור

    box.appendChild(btnShowAll);
    box.appendChild(btnHideAll);

    if (wrap && toc) wrap.insertBefore(box, toc);
    else if (wrap) wrap.insertBefore(box, wrap.firstChild);

    // שמירה אוטומטית בזיכרון אחרי כל לחיצה על כפתור מקומי של מקטע
    document.addEventListener('click', function (e) {
      const btn = e.target.closest && e.target.closest('.toggle-btn');
      if (!btn || btn === btnShowAll || btn === btnHideAll) return;
      setTimeout(saveHiddenFromDOM, 0); // אחרי שהמאזין המקורי סיים
    });
  });
})();
</script>

<?php include 'footer.php'; ?>