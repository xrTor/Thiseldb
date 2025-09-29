<?php 
include 'header.php'; 
require_once 'server.php';

// full-info-text.php — טקסטואלי, עם הצג/הסתר, TOC, ושמירת מצב ב-localStorage
mb_internal_encoding('UTF-8');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function norm_split($s){
  if ($s === null) return [];
  $s = str_replace([';', '/', '|'], ',', (string)$s);
  $parts = array_map('trim', explode(',', $s));
  return array_values(array_filter($parts, fn($x)=>$x!==''));
}
function aggFromColumn(mysqli $conn, string $col): array {
  $rs = $conn->query("SELECT `$col` v FROM posters WHERE `$col` IS NOT NULL AND `$col`<>''");
  $m = [];
  while($row=$rs->fetch_assoc()){
    foreach(norm_split($row['v']) as $name){
      $key = mb_strtolower($name,'UTF-8');
      if (!isset($m[$key])) $m[$key] = ['label'=>$name,'count'=>1];
      else $m[$key]['count']++;
    }
  }
  uasort($m, fn($a,$b)=>$b['count']<=>$a['count'] ?: strnatcasecmp($a['label'],$b['label']));
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

// === DATA ===
$languages = aggFromColumn($conn,'languages');
$countries = aggFromColumn($conn,'countries');
$genres    = aggFromColumn($conn,'genres');
$networks  = aggFromColumn($conn,'networks');
$actors    = aggFromColumn($conn,'cast');
$directors = aggFromColumn($conn,'directors');
$writers   = aggFromColumn($conn,'writers');
$producers = aggFromColumn($conn,'producers');
$composers = aggFromColumn($conn,'composers');
$cinematog = aggFromColumn($conn,'cinematographers');

$years=[];
$r=$conn->query("SELECT year,COUNT(*) c FROM posters WHERE year<>'' GROUP BY year ORDER BY year DESC");
while($row=$r->fetch_assoc()){ $years[$row['year']] = ['label'=>$row['year'],'count'=>$row['c']]; }

$collections=[];
$r=$conn->query("SELECT c.id,c.name,COUNT(pc.poster_id) cnt FROM collections c LEFT JOIN poster_collections pc ON pc.collection_id=c.id GROUP BY c.id ORDER BY cnt DESC,c.name ASC");
while($row=$r->fetch_assoc()){ $collections[$row['id']] = ['id'=>$row['id'],'label'=>$row['name'],'count'=>$row['cnt']]; }

$user_tags=[];
$r=$conn->query("SELECT genre,COUNT(*) c FROM user_tags GROUP BY genre ORDER BY c DESC,genre ASC");
while($row=$r->fetch_assoc()){ $g=trim($row['genre']); if($g!=='') $user_tags[mb_strtolower($g)] = ['label'=>$g,'count'=>$row['c']]; }

$types=[];
$r=$conn->query("SELECT pt.id,COALESCE(pt.label_he,pt.label_en,pt.code) label,COUNT(p.id)c FROM poster_types pt LEFT JOIN posters p ON p.type_id=pt.id GROUP BY pt.id ORDER BY c DESC,label ASC");
while($row=$r->fetch_assoc()){ $types[$row['id']] = ['id'=>$row['id'],'label'=>$row['label'],'count'=>$row['c']]; }

$total_posters=(int)($conn->query("SELECT COUNT(*) c FROM posters")->fetch_assoc()['c']??0);

$icons = [
  'languages'   => '🌐',
  'countries'   => '🌍',
  'genres'      => '🎭',
  'networks'    => '📡',
  'actors'      => '👥',
  'directors'   => '🎬',
  'writers'     => '✍️',
  'producers'   => '🎥',
  'composers'   => '🎼',
  'cinematog'   => '📸',
  'years'       => '🗓',
  'collections' => '🧩',
  'user_tags'   => '🏷️',
  'types'       => '🧪',
  'posters'     => '🎬'
];

$sections=[
 ['languages','שפות',$languages,fn($lbl)=>'home.php?'.buildSearchQuery(['lang_code'=>$lbl])],
 ['countries','מדינות',$countries,fn($lbl)=>'home.php?'.buildSearchQuery(['country'=>$lbl])],
 ['genres','ז׳אנרים',$genres,fn($lbl)=>'home.php?'.buildSearchQuery(['genre'=>$lbl])],
 ['networks','רשתות',$networks,fn($lbl)=>'home.php?'.buildSearchQuery(['network'=>$lbl])],
 ['actors','שחקנים',$actors,fn($lbl)=>'actor.php?name='.urlencode($lbl)],
 ['directors','במאים',$directors,fn($lbl)=>'actor.php?name='.urlencode($lbl)],
 ['writers','תסריטאים',$writers,fn($lbl)=>'actor.php?name='.urlencode($lbl)],
 ['producers','מפיקים',$producers,fn($lbl)=>'actor.php?name='.urlencode($lbl)],
 ['composers','מלחינים',$composers,fn($lbl)=>'actor.php?name='.urlencode($lbl)],
 ['cinematog','צלמים',$cinematog,fn($lbl)=>'actor.php?name='.urlencode($lbl)],
 ['years','שנים',$years,fn($lbl)=>'home.php?'.buildSearchQuery(['year'=>$lbl])],
 ['collections','אוספים',$collections,fn($lbl,$row)=>'collection.php?id='.urlencode($row['id'])],
 ['user_tags','תגיות',$user_tags,fn($lbl)=>'home.php?'.buildSearchQuery(['user_tag'=>$lbl])],
 ['types','סוגים',$types,fn($lbl,$row)=>'home.php?'.buildSearchQuery(['type[]'=>(string)$row['id']])]
];
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<title>מפת אתר טקסטואלית</title>
<style>
body { font-family: Arial, sans-serif; background:#fafafa; color:#333; direction:rtl; text-align:right; }
h1 { text-align:center; }
h2 { 
  margin-top:25px; border-bottom:2px solid #ccc; padding-bottom:4px; 
  display:grid; grid-template-columns:auto 1fr auto; 
  align-items:center; gap:8px; cursor:pointer; text-align:right;
}
h2 .title { font-weight:bold; }
ol { 
  max-width:900px; margin:10px auto; padding:0 20px; 
  list-style:decimal; list-style-position:inside; display:none; text-align:right;
}
ol.open { display:block; }
li { margin:4px 0; }
a { color:#0066cc; text-decoration:none; font-weight:bold; }
a:hover { text-decoration:underline; }
.cnt { font-weight:normal; }
.controls { text-align:center; margin:20px 0; }
button { font-size:13px; padding:4px 10px; border:1px solid #aaa; border-radius:6px; background:#f0f0f0; cursor:pointer; }
button:hover { background:#e0e0e0; }
.toc { max-width:400px; margin:20px auto; padding:0; list-style:none; border:1px solid #ccc; background:#fff; text-align:right; }
.toc li { margin:6px 0; padding:4px 8px; border-bottom:1px solid #eee; }
.toc a { text-decoration:none; color:#0066cc; font-weight:bold; }
section {
  background:#fff;
  border:1px solid #ccc;
  border-radius:6px;
  padding:10px 15px;
  margin:20px auto;
  max-width:900px;
}
.toggle-btn { font-size:13px; padding:4px 10px; border:1px solid #cfd8ea; border-radius:10px; background:#f1f6ff; cursor:pointer; }
    .toggle-btn:hover { background:#e7f1ff; }
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  const KEY='fi_text_hidden';
  function getHidden(){ try{return JSON.parse(localStorage.getItem(KEY))||[]}catch(e){return []} }
  function saveHidden(hidden){ localStorage.setItem(KEY,JSON.stringify(hidden)); }
  function toggleSection(id,forceOpen=null){
    const ol=document.querySelector('#list-'+id);
    const btn=document.querySelector('.sec-toggle[data-sec="'+id+'"]');
    if(!ol)return;
    const isOpen=ol.classList.contains('open');
    const shouldOpen=(forceOpen!==null)?forceOpen:!isOpen;
    let hidden=getHidden();
    if(shouldOpen){
      ol.classList.add('open'); if(btn)btn.textContent='הסתר';
      hidden=hidden.filter(x=>x!==id);
    } else {
      ol.classList.remove('open'); if(btn)btn.textContent='הצג';
      if(!hidden.includes(id)) hidden.push(id);
    }
    saveHidden(hidden);
  }
  document.querySelectorAll('.sec-toggle').forEach(btn=>{
    btn.addEventListener('click',e=>{
      e.stopPropagation();
      toggleSection(btn.dataset.sec);
    });
  });
  document.querySelectorAll('h2').forEach(h2=>{
    h2.addEventListener('click',()=>toggleSection(h2.dataset.sec));
  });
  document.querySelector('.btn-show-all').addEventListener('click',()=>{
    document.querySelectorAll('ol').forEach(ol=>ol.classList.add('open'));
    document.querySelectorAll('.sec-toggle').forEach(b=>b.textContent='הסתר');
    saveHidden([]);
  });
  document.querySelector('.btn-hide-all').addEventListener('click',()=>{
    const ids=[];
    document.querySelectorAll('ol').forEach(ol=>{
      ol.classList.remove('open'); ids.push(ol.id.replace('list-',''));
    });
    document.querySelectorAll('.sec-toggle').forEach(b=>b.textContent='הצג');
    saveHidden(ids);
  });
  document.querySelectorAll('.toc a').forEach(a=>{
    a.addEventListener('click',e=>{
      e.preventDefault();
      const id=a.dataset.sec;
      toggleSection(id,true);
      const sec=document.getElementById('sec-'+id);
      if(sec) sec.scrollIntoView({behavior:'smooth'});
    });
  });
  getHidden().forEach(id=>{
    const ol=document.querySelector('#list-'+id);
    const btn=document.querySelector('.sec-toggle[data-sec="'+id+'"]');
    if(ol){ ol.classList.remove('open'); }
    if(btn){ btn.textContent='הצג'; }
  });
});
</script>
</head>
<body>
<h1>📚 כל הערכים באתר בגרסא טקסטואלית</h1>


  <div style="text-align:center;margin:8px 0 10px;">
  <a href="full-info.php" class="toggle-btn">↗ אל הגרסא הצבעונית</a>
  <a href="full-info-text-he.php" class="btn toggle-btn">↗ אל העמוד בעברית</a>
  </div>

<div class="controls">
  <button type="button" class="btn-show-all toggle-btn">הצג הכל</button>
  <button type="button" class="btn-hide-all toggle-btn">הסתר הכל</button>
</div>

<ul class="toc">
  <?php foreach($sections as $section): [$id,$title,$data]=$section; $cnt=count($data); ?>
    <li><a href="#sec-<?=h($id)?>" data-sec="<?=h($id)?>"><span class="ico"><?=$icons[$id]??'•'?></span> <?=h($title)?> <span class="cnt">(<?=$cnt?>)</span></a></li>
  <?php endforeach; ?>
  <li><a href="#sec-posters" data-sec="posters"><span class="ico">🎬</span> פוסטרים <span class="cnt">(<?=$total_posters?>)</span></a></li>
</ul>

<?php foreach($sections as $section):
  [$id,$title,$data,$linkFn]=$section; $totalItems=count($data);
?>
<section id="sec-<?=h($id)?>">
  <h2 data-sec="<?=h($id)?>">
    <span class="ico"><?=$icons[$id]??'•'?></span>
    <span class="title"><?=h($title)?> <span class="cnt">(<?=$totalItems?>)</span></span>
    <button type="button" class="sec-toggle" data-sec="<?=h($id)?>">הצג</button>
  </h2>
  <ol id="list-<?=h($id)?>">
    <?php foreach($data as $k=>$row):
      $label=is_array($row)?($row['label']??$k):$row;
      $count=(int)($row['count']??0);
      $href=($id==='collections'||$id==='types')?$linkFn($label,$row):$linkFn($label);
    ?>
    <li><a href="<?=h($href)?>"><?=h($label)?></a> <span class="cnt">(<?=$count?>)</span></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endforeach; ?>

<section id="sec-posters">
  <h2 data-sec="posters">
    <span class="ico">🎬</span>
    <span class="title">פוסטרים <span class="cnt">(<?=$total_posters?>)</span></span>
    <button type="button" class="sec-toggle" data-sec="posters">הצג</button>
  </h2>
  <ol id="list-posters">
    <?php
    $res=$conn->query("SELECT id,title_he,title_en,year FROM posters ORDER BY id DESC");
    while($row=$res->fetch_assoc()):
      $he=$row['title_he']; $en=$row['title_en'];
      $titles=array_filter([$he,$en],fn($t)=>$t!=='');
      $name=implode(' / ',$titles) ?: 'ללא שם';
      $year = $row['year'] ? " [{$row['year']}]" : "";
      $label=$name.$year;
    ?>
    <li><a href="poster.php?id=<?=$row['id']?>"><?=h($label)?></a></li>
    <?php endwhile; ?>
  </ol>
</section>

</body>
</html>

<?php include 'footer.php'; ?>
