<?php
include 'header.php';
require_once 'server.php';
require_once __DIR__ . '/alias.php'; // קובץ האליאסים

mb_internal_encoding('UTF-8');

// ==== פונקציות עזר ====
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function hasHebrew(string $s): bool { return (bool)preg_match('/\p{Hebrew}/u', $s); }
function norm_split($s){
  if ($s === null) return [];
  $s = str_replace([';', '/', '|'], ',', (string)$s);
  $parts = array_map('trim', explode(',', $s));
  return array_values(array_filter($parts, fn($x)=>$x!==''));
}
function aggFromColumn(mysqli $conn, string $col): array {
  $rs = $conn->query("SELECT `$col` v FROM posters WHERE `$col` IS NOT NULL AND `$col`<>''");
  $m=[];
  while($row=$rs->fetch_assoc()){
    foreach(norm_split($row['v']) as $name){
      $key=mb_strtolower($name,'UTF-8');
      if(!isset($m[$key])) $m[$key]=['label'=>$name,'count'=>1];
      else $m[$key]['count']++;
    }
  }
  return $m;
}
function aggFromUserTags(mysqli $conn): array {
  $m=[];
  $rs=$conn->query("SELECT genre AS v,COUNT(*) c FROM user_tags GROUP BY genre");
  while($row=$rs->fetch_assoc()){
    $g=trim($row['v']); if($g==='') continue;
    $m[mb_strtolower($g,'UTF-8')]=['label'=>$g,'count'=>(int)$row['c']];
  }
  return $m;
}
function buildSearchQuery(array $overrides=[]):string{
  $defaults=[
    'search'=>'','year'=>'','min_rating'=>'','metacritic'=>'','rt_score'=>'',
    'imdb_id'=>'','genre'=>'','user_tag'=>'','actor'=>'','directors'=>'',
    'producers'=>'','writers'=>'','composers'=>'','cinematographers'=>'',
    'lang_code'=>'','country'=>'','runtime'=>'','network'=>'',
    'search_mode'=>'and','limit'=>'50','view'=>'modern_grid','sort'=>'',
  ];
  return http_build_query(array_merge($defaults,$overrides));
}
function buildReverseMap(array $fieldMap): array {
  $rev=[];
  foreach($fieldMap as $he=>$en){
    if(!hasHebrew((string)$he)) continue;
    $en_lc=mb_strtolower($en,'UTF-8');
    $rev[$en_lc]=$he;
  }
  return $rev;
}
function buildForwardMap(array $fieldMap): array {
  $fwd=[];
  foreach($fieldMap as $he=>$en){
    if(!hasHebrew((string)$he)) continue;
    $fwd[mb_strtolower($he,'UTF-8')]=$en;
  }
  return $fwd;
}
function mapAggByAliases(array $agg, array $revENtoHE, array $heToEn=[]): array {
  $out=[];
  foreach($agg as $row){
    $raw=trim($row['label']??''); if($raw==='') continue;
    if(hasHebrew($raw)){
      $he=$raw;
      $he_lc=mb_strtolower($he,'UTF-8');
      $en_for_link=$heToEn[$he_lc] ?? $raw;
    } else {
      $en_lc=mb_strtolower($raw,'UTF-8');
      if(!isset($revENtoHE[$en_lc])) continue;
      $he=$revENtoHE[$en_lc]; $en_for_link=$raw;
    }
    $key=mb_strtolower($he,'UTF-8');
    if(!isset($out[$key])) $out[$key]=['label'=>$he,'count'=>(int)$row['count'],'_link_value'=>$en_for_link];
    else $out[$key]['count']+=(int)$row['count'];
  }
  uasort($out,fn($a,$b)=>$b['count']<=>$a['count']);
  return $out;
}

// ==== מיפויים מתוך alias.php ====
$REV=[
 'lang_code'=>buildReverseMap($ALIASES['lang_code']??[]),
 'country'  =>buildReverseMap($ALIASES['country']??[]),
 'genre'    =>buildReverseMap($ALIASES['genre']??[]),
 'network'  =>buildReverseMap($ALIASES['network']??[]),
 'user_tag' =>buildReverseMap($ALIASES['user_tag']??[]),
];
$FWD=[
 'lang_code'=>buildForwardMap($ALIASES['lang_code']??[]),
 'country'  =>buildForwardMap($ALIASES['country']??[]),
 'genre'    =>buildForwardMap($ALIASES['genre']??[]),
 'network'  =>buildForwardMap($ALIASES['network']??[]),
 'user_tag' =>buildForwardMap($ALIASES['user_tag']??[]),
];

// ==== DATA ====
$languages = mapAggByAliases(aggFromColumn($conn,'languages'), $REV['lang_code'],$FWD['lang_code']);
$countries = mapAggByAliases(aggFromColumn($conn,'countries'), $REV['country'],$FWD['country']);
$genres    = mapAggByAliases(aggFromColumn($conn,'genres'),    $REV['genre'],$FWD['genre']);
$networks  = mapAggByAliases(aggFromColumn($conn,'networks'),  $REV['network'],$FWD['network']);
$user_tags = mapAggByAliases(aggFromUserTags($conn),           $REV['user_tag'],$FWD['user_tag']);

// ==== אייקונים ====
$icons=[
 'languages'=>'🌐',
 'countries'=>'🌍',
 'genres'=>'🎭',
 'networks'=>'📡',
 'user_tags'=>'🏷️',
];
$sections=[
 ['languages','שפות',$languages,fn($row)=>'home.php?'.buildSearchQuery(['lang_code'=>$row['_link_value']])],
 ['countries','מדינות',$countries,fn($row)=>'home.php?'.buildSearchQuery(['country'=>$row['_link_value']])],
 ['genres','ז׳אנרים',$genres,fn($row)=>'home.php?'.buildSearchQuery(['genre'=>$row['_link_value']])],
 ['user_tags','תגיות',$user_tags,fn($row)=>'home.php?'.buildSearchQuery(['user_tag'=>$row['_link_value']])],
 ['networks','רשתות',$networks,fn($row)=>'home.php?'.buildSearchQuery(['network'=>$row['_link_value']])],
];
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<title>מפת אתר טקסטואלית (עברית בלבד)</title>
<style>
body {font-family:Arial, sans-serif; background:#fafafa; color:#333; direction:rtl; text-align:right;}
h1 {text-align:center;}
.section-box {max-width:900px; margin:20px auto; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0;}
h2 {
  margin:0;
  padding:10px;
  background:#f5f5f5;
  border-bottom:1px solid #ddd;
  cursor:pointer;
  display:flex;
  align-items:center;
  justify-content:flex-start; /* ✅ הכל מיושר לימין */
  gap:8px;
}
h2 span.title {flex:1; font-weight:bold; text-align:right;}
ol {margin:0; padding:15px 40px; background:#fff; list-style:decimal; list-style-position:inside; display:block; text-align:right;}
li {margin:4px 0;}
a {color:#0066cc; text-decoration:none; font-weight:bold;}
a:hover {text-decoration:underline;}
.controls {text-align:center; margin:20px 0;}
button {font-size:13px; padding:4px 10px; border:1px solid #aaa; border-radius:6px; background:#f0f0f0; cursor:pointer;}
button:hover {background:#e0e0e0;}
.toc {max-width:400px; margin:20px auto; padding:0; list-style:none; border:1px solid #ccc; background:#fff;}
.toc li {margin:6px 0; text-align:right; padding:4px 8px; border-bottom:1px solid #eee;}
.toc a {text-decoration:none; color:#0066cc; font-weight:bold;}
.ico {margin-left:6px;}

.toggle-btn { font-size:13px; padding:4px 10px; border:1px solid #cfd8ea; border-radius:10px; background:#f1f6ff; cursor:pointer; }
    .toggle-btn:hover { background:#e7f1ff; }

</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  // ברירת מחדל – הכל פתוח
  document.querySelectorAll('ol').forEach(ol=>ol.classList.add('open'));
  document.querySelectorAll('.sec-toggle').forEach(btn=>btn.textContent='הסתר');

  function toggle(id){
    const ol=document.getElementById('list-'+id);
    const btn=document.querySelector('.sec-toggle[data-sec="'+id+'"]');
    if(!ol) return;
    const isOpen=ol.classList.contains('open');
    if(isOpen){ol.classList.remove('open'); ol.style.display='none'; if(btn) btn.textContent='הצג';}
    else {ol.classList.add('open'); ol.style.display='block'; if(btn) btn.textContent='הסתר';}
  }
  document.querySelectorAll('.sec-toggle').forEach(btn=>{
    btn.addEventListener('click',e=>{e.stopPropagation(); toggle(btn.dataset.sec);});
  });
});
</script>
</head>
<body>
  <h1>📚 ערכים מתורגמים — עברית בלבד</h1>
  <div class="note">
העמוד מציג רק ערכים שיש להם תרגום בעברית, או ערכים שכבר נשמרו בעברית במסד.
  </div>

  <div class="toolbar"><br>
    <a href="full-info-text.php" class="toggle-btn">↗ אל העמוד באנגלית</a>
  <a href="full-info-he.php" class="toggle-btn">↗ אל הגרסא הצבעונית</a>
  </div>

<div class="controls">
  <button class="toggle-btn" type="button" onclick="document.querySelectorAll('ol').forEach(ol=>{ol.classList.add('open');ol.style.display='block';}); document.querySelectorAll('.sec-toggle').forEach(b=>b.textContent='הסתר');">הצג הכל</button>
  <button class="toggle-btn" type="button" onclick="document.querySelectorAll('ol').forEach(ol=>{ol.classList.remove('open');ol.style.display='none';}); document.querySelectorAll('.sec-toggle').forEach(b=>b.textContent='הצג');">הסתר הכל</button>
</div>

<ul class="toc">
<?php foreach($sections as [$id,$title,$data]): ?>
  <li><a href="#sec-<?=h($id)?>"><span class="ico"><?=$icons[$id]??'•'?></span><?=h($title)?> (<?=count($data)?>)</a></li>
<?php endforeach; ?>
</ul>

<?php foreach($sections as [$id,$title,$data,$linkFn]): ?>
<div class="section-box" id="sec-<?=h($id)?>">
  <h2 data-sec="<?=h($id)?>">
    <button type="button" class="sec-toggle" data-sec="<?=h($id)?>">הסתר</button>
    <span class="ico"><?=$icons[$id]??'•'?></span>
    <span class="title"><?=h($title)?> (<?=count($data)?>)</span>
  </h2>
  <ol id="list-<?=h($id)?>" class="open" style="display:block;">
    <?php foreach($data as $row): ?>
    <li><a href="<?=h($linkFn($row))?>"><?=h($row['label'])?></a> (<?=$row['count']?>)</li>
    <?php endforeach; ?>
  </ol>
</div>
<?php endforeach; ?>

</body>
</html>

<?php include 'footer.php'; ?>
