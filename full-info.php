<?php
// full-info.php — צבעוני + מספור עם נקודה + ספירת כמות בסקשנים + פוסטרים בסוף
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
  return array_values(array_filter($parts, function($x){ return $x!==''; }));
}

function aggFromColumn(mysqli $conn, string $col): array {
  $rs = $conn->query("SELECT `$col` AS v FROM posters WHERE `$col` IS NOT NULL AND `$col`<>''");
  $m = [];
  if ($rs) {
    while($row=$rs->fetch_assoc()){
      foreach (norm_split($row['v']) as $name){
        $key = mb_strtolower($name,'UTF-8');
        if (!isset($m[$key])) {
          $m[$key] = ['label'=>$name,'count'=>1];
        } else {
          $m[$key]['count']++;
        }
      }
    }
  }
  uasort($m, function($a,$b){
    if ($a['count']===$b['count']) return strnatcasecmp($a['label'],$b['label']);
    return $b['count'] <=> $a['count'];
  });
  return $m;
}

function buildSearchQuery(array $overrides = []): string {
  $defaults = [
    'search'=>'','year'=>'','min_rating'=>'','metacritic'=>'','rt_score'=>'',
    'imdb_id'=>'','genre'=>'','user_tag'=>'','actor'=>'','directors'=>'',
    'producers'=>'','writers'=>'','composers'=>'','cinematographers'=>'',
    'lang_code'=>'','country'=>'','runtime'=>'','network'=>'',
    'search_mode'=>'and','limit'=>'50','view'=>'modern_grid','sort'=>'',
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

/* אייקונים */
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

/* Sections */
$sections = [
  ['languages',   'שפות',          $languages,   fn($lbl)=>'home.php?'.buildSearchQuery(['lang_code'=>$lbl])],
  ['countries',   'מדינות',        $countries,   fn($lbl)=>'home.php?'.buildSearchQuery(['country'=>$lbl])],
  ['genres',      'ז׳אנרים',       $genres,      fn($lbl)=>'home.php?'.buildSearchQuery(['genre'=>$lbl])],
  ['networks',    'רשתות',         $networks,    fn($lbl)=>'home.php?'.buildSearchQuery(['network'=>$lbl])],
  ['actors',      'שחקנים',        $actors,      fn($lbl)=>'actor.php?name='.urlencode($lbl)],
  ['directors',   'במאים',         $directors,   fn($lbl)=>'actor.php?name='.urlencode($lbl)],
  ['writers',     'תסריטאים',      $writers,     fn($lbl)=>'actor.php?name='.urlencode($lbl)],
  ['producers',   'מפיקים',        $producers,   fn($lbl)=>'actor.php?name='.urlencode($lbl)],
  ['composers',   'מלחינים',       $composers,   fn($lbl)=>'actor.php?name='.urlencode($lbl)],
  ['cinematog',   'צלמים',         $cinematog,   fn($lbl)=>'actor.php?name='.urlencode($lbl)],
  ['years',       'שנים',          $years,       fn($lbl)=>'home.php?'.buildSearchQuery(['year'=>$lbl])],
  ['collections', 'אוספים',        $collections, fn($lbl,$row)=>'collection.php?id='.urlencode($row['id'] ?? $lbl)],
  ['user_tags',   'תגיות',         $user_tags,   fn($lbl)=>'home.php?'.buildSearchQuery(['user_tag'=>$lbl])],
  ['types',       'סוגים',         $types,       fn($lbl,$row)=>'home.php?'.buildSearchQuery(['type[]'=>(string)($row['id'] ?? $lbl)])],
];

/* Count posters */
$total_posters = (int)($conn->query("SELECT COUNT(*) AS c FROM posters")->fetch_assoc()['c'] ?? 0);
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>Full Info — כל המידע</title>
  <style>
    body { background:#fff; margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial; }
    .wrap { max-width:1200px; margin:22px auto; padding:0 12px; }
    h1 { text-align:center; margin:0 0 14px; }
    .toc { text-align:center; margin:10px 0 18px; }
    .toc a { display:inline-block; margin:4px 5px; padding:6px 12px;
      border-radius:999px; text-decoration:none; color:#123; font-size:14px;
      background:#fff; border:1px solid #d8e6ff; }
    .toc a:hover { background:#dff0ff; }
    .ico { font-size:16px; margin-inline-start:6px; }
    .bulk-controls { text-align:center; margin:8px 0 10px; }
    .btn { font-size:13px; padding:6px 12px; border:1px solid #cfd8ea; border-radius:10px;
      background:#f1f6ff; cursor:pointer; margin:0 4px; }
    .btn:hover { background:#e7f1ff; }
    .sec { margin:22px 0; }
    .sec h2 { margin:0 0 12px; font-size:20px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .sec-toggle { font-size:13px; padding:4px 10px; border:1px solid #cfd8ea; border-radius:10px; background:#f1f6ff; cursor:pointer; }
    .sec-toggle:hover { background:#e7f1ff; }
    .sec.collapsed .grid { display:none; }
    .grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:8px; }
    @media (max-width:1100px){ .grid{ grid-template-columns:repeat(4,1fr);} }
    @media (max-width:800px){ .grid{ grid-template-columns:repeat(3,1fr);} }
    @media (max-width:560px){ .grid{ grid-template-columns:repeat(2,1fr);} }
    .pill { display:flex; align-items:center; gap:8px;
      padding:8px 12px; border-radius:16px; text-decoration:none; color:#222;
      border:1px solid rgba(0,0,0,.06); transition:transform .1s ease, box-shadow .1s ease; direction:rtl; }
    .pill:hover { transform:scale(1.03); box-shadow:0 2px 6px rgba(0,0,0,.15); }
  </style>
</head>
<body>
<div class="wrap">
  <h1>📚 כל הערכים באתר</h1>
  <div style="text-align:center;margin:8px 0 10px;"></div>
  <a href="full-info-text.php" class="btn">↗ אל הגרסא הטקסטואלית</a>
    <a href="full-info-he.php" class="btn">↗ אל העמוד בעברית</a>
  </div>

  <div class="bulk-controls">
    <button type="button" class="btn btn-show-all">הצג הכל</button>
    <button type="button" class="btn btn-hide-all">הסתר הכל</button>
  </div>

  <div class="toc" id="toc">
    <?php foreach ($sections as $section):
      [$id,$title,$data] = $section;
      $cnt = is_array($data)?count($data):0; ?>
      <a href="#sec-<?=h($id)?>" data-sec="<?=h($id)?>">
        <span class="ico"><?=h($icons[$id]??'•')?></span><?=h($title)?>
        <span class="cnt">(<?=$cnt?>)</span>
      </a>
    <?php endforeach; ?>
    <a href="#sec-posters" data-sec="posters">
      <span class="ico">🎬</span> פוסטרים <span class="cnt">(<?=$total_posters?>)</span>
    </a>
  </div>

  <?php
  $palette=$colors;
  $paletteCount=count($palette);
  $fixedCols=6;

  foreach ($sections as $section):
    [$id,$title,$data,$linkFn]=$section;
    $icon=$icons[$id]??'•';
    $totalItems=is_array($data)?count($data):0;
  ?>
    <section class="sec" id="sec-<?=h($id)?>" data-sec="<?=h($id)?>">
      <h2>
        <span class="ico"><?=h($icon)?></span>
        <?=h($title)?> <span class="cnt">(<?=$totalItems?>)</span>
        <button type="button" class="sec-toggle" data-sec="<?=h($id)?>" data-state="open">הסתר</button>
      </h2>

      <?php if(empty($data)):?>
        <p style="color:#777">אין נתונים להצגה.</p>
      <?php else:?>
        <div class="grid">
          <?php
          $i=0;
          foreach($data as $k=>$row):
            $label=is_array($row)?($row['label']??(string)$k):(string)$row;
            $count=(int)($row['count']??0);
            $href=($id==='collections'||$id==='types')?$linkFn($label,$row):$linkFn($label);
            $col=$i%$fixedCols;
            $rowIdx=floor($i/$fixedCols);
            $colorIndex=($rowIdx+$col)%$paletteCount;
            $bg=$palette[$colorIndex];
          ?>
            <a class="pill" href="<?=h($href)?>" style="background:<?=h($bg)?>;">
              <span class="cnt"><?=$i+1?>. (<?=$count?>)</span>
              <span class="nm"><?=h($label)?></span>
            </a>
          <?php
          $i++;
          endforeach;
          ?>
        </div>
      <?php endif;?>
    </section>
  <?php endforeach; ?>

  <!-- סקשן פוסטרים -->
  <section class="sec" id="sec-posters" data-sec="posters">
    <h2>
      <span class="ico">🎬</span>
      פוסטרים <span class="cnt">(<?=$total_posters?>)</span>
      <button type="button" class="sec-toggle" data-sec="posters" data-state="open">הסתר</button>
    </h2>
    <div class="grid">
      <?php
      $res=$conn->query("SELECT id,title_he,title_en,year FROM posters ORDER BY id DESC");
      $i=0;
      while($row=$res->fetch_assoc()):
        $title_he=trim((string)$row['title_he']);
        $title_en=trim((string)$row['title_en']);
        $parts=array_values(array_filter([$title_he,$title_en],fn($x)=>$x!==''));
        $name=count($parts)?implode(' / ',$parts):'ללא שם';
        $year=$row['year']?" [{$row['year']}]":"";
        $label=$name.$year;
        $col=$i%$fixedCols;
        $rowIdx=floor($i/$fixedCols);
        $colorIndex=($rowIdx+$col)%$paletteCount;
        $bg=$palette[$colorIndex];
      ?>
        <a class="pill" href="poster.php?id=<?=$row['id']?>" style="background:<?=h($bg)?>;">
          <span class="cnt"><?=$i+1?>.</span>
          <span class="nm"><?=h($label)?></span>
        </a>
      <?php
      $i++;
      endwhile;
      ?>
    </div>
  </section>
</div>

<script>
(function(){
  function toggle(id,open){
    const s=document.querySelector('.sec[data-sec="'+id+'"]');
    const b=document.querySelector('.sec-toggle[data-sec="'+id+'"]');
    if(!s) return;
    if(open){
      s.classList.remove('collapsed');
      if(b){b.dataset.state='open';b.textContent='הסתר';}
    } else {
      s.classList.add('collapsed');
      if(b){b.dataset.state='closed';b.textContent='הצג';}
    }
  }
  document.addEventListener('click',e=>{
    if(e.target.closest('.sec-toggle')){
      const id=e.target.dataset.sec;
      const sec=document.querySelector('.sec[data-sec="'+id+'"]');
      if(sec.classList.contains('collapsed')) toggle(id,true);
      else toggle(id,false);
    }
    if(e.target.closest('.btn-show-all')){
      document.querySelectorAll('.sec').forEach(s=>toggle(s.dataset.sec,true));
    }
    if(e.target.closest('.btn-hide-all')){
      document.querySelectorAll('.sec').forEach(s=>toggle(s.dataset.sec,false));
    }
  });
})();
</script>

<?php include 'footer.php'; ?>
