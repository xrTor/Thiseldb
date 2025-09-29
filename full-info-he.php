<?php
/****************************************************
 * full-info-he.php — "עברית בלבד" על בסיס alias.php
 * מציג רק ערכים שיש להם תרגום בעברית לפי alias.php,
 * או ערכים שכבר נשמרו בעברית במסד.
 * כולל תמיכה ב-user_tag (HE↔EN) + הקשחה נגד מפתחות לא-עבריים.
 * כל הלחיצות נפתחו ל-home.php עם ברירות מחדל ארוכות.
 * צבעי "גלולות" נבחרים במיקס יציב לפי תוכן הפריט (hash).
 ****************************************************/
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'server.php';
include 'header.php';
require_once __DIR__ . '/alias.php'; // קובץ האליאסים שאתה מתחזק

/* ===== Helpers ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function hasHebrew(string $s): bool { return (bool)preg_match('/\p{Hebrew}/u', $s); }
function norm_split($s){
  if ($s === null) return [];
  $s = (string)$s;
  if ($s === '') return [];
  $s = str_replace([';', '/', '|'], ',', $s);
  $parts = array_map('trim', explode(',', $s));
  return array_values(array_filter($parts, fn($x)=>$x!==''));
}
// בחירת צבע יציבה לפי מחרוזת זרע (seed)
function pickColor(array $palette, string $seed): string {
  $n = count($palette);
  if ($n === 0) return '#e3f2fd';
  $u = hexdec(substr(md5($seed), 0, 8)); // hash יציב
  return $palette[$u % $n];
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

/** אגרגציה מתוך עמודת posters (פיצול ערכים מרובי-פריטים) */
function aggFromColumn(mysqli $conn, string $col): array {
  $rs = $conn->query("SELECT `$col` AS v FROM posters WHERE `$col` IS NOT NULL AND `$col`<>''");
  $m = [];
  if ($rs) while($row=$rs->fetch_assoc()){
    foreach (norm_split($row['v']) as $name){
      $key = mb_strtolower(trim((string)$name),'UTF-8');
      if ($key==='') continue;
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

/** אגרגציה מטבלת user_tags (שם השדה הוא genre) */
function aggFromUserTags(mysqli $conn): array {
  $m = [];
  $rs = $conn->query("SELECT genre AS v, COUNT(*) c FROM user_tags GROUP BY genre ORDER BY c DESC, v ASC");
  if ($rs) while ($row = $rs->fetch_assoc()){
    $raw = trim((string)$row['v']);
    if ($raw==='') continue;
    $key = mb_strtolower($raw,'UTF-8');
    $m[$key] = ['label'=>$raw, 'count'=>(int)$row['c']];
  }
  return $m;
}

/** בונה Reverse Map: HE→EN (alias.php) ל-EN→HE, תוך התעלמות ממפתחות שאינם בעברית */
function buildReverseMap(array $fieldMap): array {
  $rev = [];
  foreach ($fieldMap as $he => $en) {
    if (!hasHebrew((string)$he)) continue; // הקשחה: רק מפתחות בעברית
    $en_lc = mb_strtolower(trim((string)$en), 'UTF-8');
    $he    = trim((string)$he);
    if ($en_lc === '') continue;
    if (!isset($rev[$en_lc])) $rev[$en_lc] = $he; // הראשון מנצח
  }
  return $rev;
}

/** בונה Forward Map: HE(lower) → EN(canonical), תוך התעלמות ממפתחות שאינם בעברית */
function buildForwardMap(array $fieldMap): array {
  $fwd = [];
  foreach ($fieldMap as $he => $en) {
    if (!hasHebrew((string)$he)) continue; // הקשחה: רק מפתחות בעברית
    $he_lc = mb_strtolower(trim((string)$he), 'UTF-8');
    $en    = trim((string)$en);
    if ($he_lc === '') continue;
    if (!isset($fwd[$he_lc])) $fwd[$he_lc] = $en; // הראשון מנצח
  }
  return $fwd;
}

/**
 * מיפוי אגרגציה לעברית:
 * - אם raw בעברית → label=HE, לינק=HE→EN מה-Forward Map (אם קיים), אחרת raw.
 * - אם raw באנגלית עם תרגום ב-REV → label=HE, לינק=raw (EN).
 * - אחרת → נזרק.
 * מחזיר: key(he_lc)=>['label'=>HE,'count'=>N,'_link_value'=>EN_CANON]
 */
function mapAggByAliases(array $agg, array $revENtoHE, array $heToEn = []): array {
  $out = [];
  foreach ($agg as $row) {
    $raw = trim((string)($row['label'] ?? ''));
    if ($raw==='') continue;

    if (hasHebrew($raw)) {
      $he = $raw;
      $he_lc = mb_strtolower($he,'UTF-8');
      $en_for_link = $heToEn[$he_lc] ?? $raw; // אם אין מיפוי קדימה – נשאר HE (ייתכן עמוד יעד לא יתמוך)
      // קנוניקליזציה: אם יש HE→EN, חזור לעברית הקאנונית לפי REV (מאחד "ארה״ב"/"ארצות הברית")
      if (isset($heToEn[$he_lc])) {
        $en_canon_lc = mb_strtolower($heToEn[$he_lc], 'UTF-8');
        if (isset($revENtoHE[$en_canon_lc])) {
          $he = $revENtoHE[$en_canon_lc];
        }
      }
    } else {
      $en_lc = mb_strtolower($raw, 'UTF-8');
      if (!isset($revENtoHE[$en_lc])) continue; // אין תרגום → זרוק
      $he = $revENtoHE[$en_lc];
      $en_for_link = $raw;
    }

    $key = mb_strtolower($he,'UTF-8');
    if (!isset($out[$key])) $out[$key] = ['label'=>$he,'count'=>(int)$row['count'],'_link_value'=>$en_for_link];
    else $out[$key]['count'] += (int)$row['count'];
  }
  uasort($out, function($a,$b){
    if ($a['count']===$b['count']) return strnatcasecmp($a['label'],$b['label']);
    return $b['count'] <=> $a['count'];
  });
  return $out;
}

/* ===== Reverse/Forward Maps מתוך alias.php ===== */
$REV = [
  'lang_code' => !empty($ALIASES['lang_code']) ? buildReverseMap($ALIASES['lang_code']) : [],
  'country'   => !empty($ALIASES['country'])   ? buildReverseMap($ALIASES['country'])   : [],
  'genre'     => !empty($ALIASES['genre'])     ? buildReverseMap($ALIASES['genre'])     : [],
  'network'   => !empty($ALIASES['network'])   ? buildReverseMap($ALIASES['network'])   : [],
  'user_tag'  => !empty($ALIASES['user_tag'])  ? buildReverseMap($ALIASES['user_tag'])  : [],
];
$FWD = [
  'lang_code' => !empty($ALIASES['lang_code']) ? buildForwardMap($ALIASES['lang_code']) : [],
  'country'   => !empty($ALIASES['country'])   ? buildForwardMap($ALIASES['country'])   : [],
  'genre'     => !empty($ALIASES['genre'])     ? buildForwardMap($ALIASES['genre'])     : [],
  'network'   => !empty($ALIASES['network'])   ? buildForwardMap($ALIASES['network'])   : [],
  'user_tag'  => !empty($ALIASES['user_tag'])  ? buildForwardMap($ALIASES['user_tag'])  : [],
];

/* ===== נתוני מקור מהמסד ===== */
$languages_src = aggFromColumn($conn, 'languages');
$countries_src = aggFromColumn($conn, 'countries');
$genres_src    = aggFromColumn($conn, 'genres');
$networks_src  = aggFromColumn($conn, 'networks');
$user_tags_src = aggFromUserTags($conn);

/* ===== מיפוי/סינון לעברית על בסיס alias.php ===== */
$languages = mapAggByAliases($languages_src, $REV['lang_code'], $FWD['lang_code']);
$countries = mapAggByAliases($countries_src, $REV['country'],   $FWD['country']);
$genres    = mapAggByAliases($genres_src,    $REV['genre'],     $FWD['genre']);
$networks  = mapAggByAliases($networks_src,  $REV['network'],   $FWD['network']);
$user_tags = mapAggByAliases($user_tags_src, $REV['user_tag'],  $FWD['user_tag']); // HE↔EN מלא

/* ===== סוגים: label_he בלבד ===== */
$types = [];
$qTypes = "SELECT pt.id, COALESCE(pt.label_he,'') AS label_he, COUNT(p.id) c
           FROM poster_types pt
           LEFT JOIN posters p ON p.type_id=pt.id
           GROUP BY pt.id
           HAVING label_he <> ''
           ORDER BY c DESC, label_he ASC";
if ($r = $conn->query($qTypes)) {
  while($row=$r->fetch_assoc()){
    $types[(string)$row['id']] = [
      'id'=>(int)$row['id'],
      'label'=>$row['label_he'],
      'count'=>(int)$row['c'],
    ];
  }
}

/* ===== אוספים: רק שם בעברית ===== */
$collections = [];
$q = "SELECT c.id, c.name, COUNT(pc.poster_id) cnt
      FROM collections c
      LEFT JOIN poster_collections pc ON pc.collection_id=c.id
      GROUP BY c.id
      ORDER BY cnt DESC, c.name ASC";
if ($r = $conn->query($q)) {
  while($row=$r->fetch_assoc()){
    $name = (string)$row['name'];
    if ($name!=='' && hasHebrew($name)) {
      $collections[(string)$row['id']] = [
        'id'=>(int)$row['id'],
        'label'=>$name,
        'count'=>(int)$row['cnt'],
      ];
    }
  }
}

/* ===== צבעים ואייקונים ===== */
$colors = [
  "#d1ecf1","#d4edda","#fff3cd","#f8d7da","#e2e3e5","#fde2ff",
  "#e0f7fa","#fce4ec","#f1f8e9","#fff8e1","#e3f2fd","#ede7f6"
];
$icons = [
  'languages'   => '🌐 ',
  'countries'   => '🌍 ',
  'genres'      => '🎭 ',
  'networks'    => '📡 ',
  'collections' => '🧩 ',
  'user_tags'   => '🏷️ ',
  'types'       => '🧪 ',
];

/* ===== Sections =====
   כל הלחיצות עוברת ל-home.php עם EN קנוני שנשמר ב-['_link_value'] */
$sections = [
  ['languages',   'שפות',   $languages,   fn($row)=>'home.php?'.buildSearchQuery(['lang_code'=>$row['_link_value']])],
  ['countries',   'מדינות', $countries,   fn($row)=>'home.php?'.buildSearchQuery(['country'=>$row['_link_value']])],
  ['genres',      'ז׳אנרים',$genres,      fn($row)=>'home.php?'.buildSearchQuery(['genre'=>$row['_link_value']])],
  ['user_tags',   'תגיות',  $user_tags,   fn($row)=>'home.php?'.buildSearchQuery(['user_tag'=>$row['_link_value']])],
  ['networks',    'רשתות',  $networks,    fn($row)=>'home.php?'.buildSearchQuery(['network'=>$row['_link_value']])],
  // אוספים נשארים לעמוד הייעודי (אלא אם הוספת תמיכה ב-home.php)
  ['collections', 'אוספים (בעברית)', $collections, fn($row)=>'collection.php?id='.urlencode((string)$row['id'])],
  ['types',       'סוגים',   $types,       fn($row)=>'home.php?'.buildSearchQuery(['type[]'=>(string)$row['id']])],
];
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>Full Info — עברית בלבד (alias.php)</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background:#fff; margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial; }
    .wrap { max-width:1200px; margin:22px auto; padding:0 12px; }
    h1 { text-align:center; margin:0 0 14px; }

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

    .toolbar { text-align:center; margin:8px 0 10px; }
    .toolbar .link-en {
      display:inline-block; margin:0 8px; padding:6px 12px; border-radius:999px;
      text-decoration:none; border:1px solid #cfd8ea; background:#fff; color:#0b5ed7;
    }
    .toolbar .link-en:hover { background:#e7f1ff; }

    .grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:8px; }
    @media (max-width:1100px){ .grid{ grid-template-columns:repeat(4, minmax(0,1fr)); } }
    @media (max-width:800px){  .grid{ grid-template-columns:repeat(3, minmax(0,1fr)); } }
    @media (max-width:560px){  .grid{ grid-template-columns:repeat(2, minmax(0,1fr)); } }

    .pill {
      display:flex; align-items:center; justify-content:flex-start; gap:8px;
      padding:8px 12px; border-radius:16px; text-decoration:none; color:#222;
      border:1px solid rgba(0,0,0,.06); transition:transform .1s ease, box-shadow .1s ease;
      direction:rtl; white-space:normal;
    }
    .pill:hover { transform:scale(1.03); box-shadow:0 2px 6px rgba(0,0,0,.15); }
    .nm { }
    .cnt { color:inherit; }

    .sec.collapsed .grid { display:none; }
    .note { text-align:center; color:#555; margin:8px 0 14px; font-size:14px; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>📚 ערכים מתורגמים — עברית בלבד</h1>
  <div class="note">
העמוד מציג רק ערכים שיש להם תרגום בעברית, או ערכים שכבר נשמרו בעברית במסד.
  </div>

  <!-- כפתורי שליטה כלליים + קישור לעמוד באנגלית -->
  <div class="toolbar">
    <a href="full-info.php" class="toggle-btn">↗ אל העמוד באנגלית</a>
  <a href="full-info-text-he.php" class="toggle-btn">↗ אל הגרסא הטקסטואלית</a><br><br>
    <button type="button" class="toggle-btn" id="btnShowAll">הצג הכל</button>
    <button type="button" class="toggle-btn" id="btnHideAll">הסתר הכל</button>
  </div>

  <!-- תפריט קפיצה -->
  <div class="toc" id="toc">
    <?php foreach ($sections as [$id,$title,$data]): $cnt = is_array($data)?count($data):0; ?>
      <a href="#sec-<?=h($id)?>" data-sec="<?=h($id)?>">
        <span class="ico"><?= h($icons[$id] ?? '•') ?></span><?=h($title)?>
        <span class="cnt">(<?= (int)$cnt ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php foreach ($sections as [$id,$title,$data,$linkFn]): $icon = $icons[$id] ?? '•'; ?>
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
          <?php foreach ($data as $row):
            $labelHe = (string)($row['label'] ?? '');
            $count   = (int)($row['count'] ?? 0);
            $href    = $linkFn($row);
            $bg      = pickColor($colors, $id.'|'.$labelHe); // צבע “מעורבב” יציב לכל פריט
          ?>
            <a class="pill" href="<?=h($href)?>" style="background: <?=h($bg)?>;">
              <span class="cnt">(<?= $count ?>)</span>
              <span class="nm"><?=h($labelHe)?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>

<script>
// הצג/הסתר מקטע בודד
document.addEventListener('click', function(e){
  const btn = e.target.closest('.toggle-btn');
  if (!btn || btn.id === 'btnShowAll' || btn.id === 'btnHideAll') return; // הכפתורים הכלליים מטופלים בנפרד
  const sec = btn.closest('.sec');
  const isOpen = btn.getAttribute('data-state') !== 'closed';
  if (isOpen) {
    sec.classList.add('collapsed'); btn.setAttribute('data-state','closed'); btn.textContent='הצג';
  } else {
    sec.classList.remove('collapsed'); btn.setAttribute('data-state','open'); btn.textContent='הסתר';
  }
});

// פונקציה לפתיחה/סגירה של כל המקטעים
function setAll(open) {
  document.querySelectorAll('.sec').forEach(sec => {
    const btn = sec.querySelector('.toggle-btn[data-sec]');
    if (open) {
      sec.classList.remove('collapsed');
      if (btn) { btn.setAttribute('data-state','open'); btn.textContent='הסתר'; }
    } else {
      sec.classList.add('collapsed');
      if (btn) { btn.setAttribute('data-state','closed'); btn.textContent='הצג'; }
    }
  });
}

// חיבור לכפתורים הכלליים
document.getElementById('btnShowAll').addEventListener('click', function(){ setAll(true); });
document.getElementById('btnHideAll').addEventListener('click', function(){ setAll(false); });

// פתיחה אוטומטית בעת קפיצה מהתפריט
document.getElementById('toc').addEventListener('click', function(e){
  const a = e.target.closest('a[data-sec]');
  if (!a) return;
  const id = a.getAttribute('data-sec');
  const sec = document.getElementById('sec-'+id);
  if (sec && sec.classList.contains('collapsed')) {
    sec.classList.remove('collapsed');
    const btn = sec.querySelector('.toggle-btn[data-sec]');
    if (btn){ btn.setAttribute('data-state','open'); btn.textContent='הסתר'; }
  }
});
</script>

<?php include 'footer.php'; ?>
