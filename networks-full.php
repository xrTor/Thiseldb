<?php
/****************************************************
 * network.php — Networks Explorer (RTL, HE)
 * v2025-10-25-nx+clean (NO BUTTONS)
 * Route: network.php?name=RAW_NAME  (index view when no ?name)
 * Logos dir: images/networks/{basename}.{png|jpg|jpeg|svg|webp}
 * Basename rule: keep only [a-z] lowercase, strip everything else (כמו poster.php).
 * Deps: server.php ($conn), header.php (optional)
 ****************************************************/
mb_internal_encoding('UTF-8');
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/server.php';

/* ===== Debug ===== */
$DEBUG = isset($_GET['debug']) && $_GET['debug'] !== '0';
if ($DEBUG) { ini_set('display_errors','1'); error_reporting(E_ALL); mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT); }

/* ===== Helpers ===== */
// תיקון: עטיפת h() בבדיקה למניעת הגדרה כפולה
if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function get_q($k,$d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function prefer_poster_url(array $row){
  foreach(['poster_he','poster','poster_url','tmdb_poster','imdb_poster','image','image_url','img'] as $k){ if(!empty($row[$k])) return $row[$k]; }
  return 'no-poster.png';
}
/* posters.networks may contain multiple values (JSON or delimited) */
function split_networks_field(?string $s): array{
  if(!$s) return [];
  $s=trim($s);
  if ($s !== '' && ($s[0]=='[' || $s[0]=='"')) {
    $j=@json_decode($s,true);
    if (is_array($j)) return array_values(array_filter(array_map('trim',$j)));
  }
  $parts=preg_split('/[|;,\/]+/u',$s);
  $out=[]; foreach($parts as $p){ $p=trim($p," \t\n\r\0\x0B\"'"); if($p!=='') $out[]=$p; }
  return $out;
}

/* ===== Network logo helpers — EXACT poster.php logic ===== */
/* normalize: keep only [a-z], lowercase */
function normalize_network_basename(?string $name): string {
  if (!$name) return '';
  $lower = mb_strtolower($name, 'UTF-8');
  $base  = preg_replace('~[^a-z]+~', '', $lower);
  return $base ?: '';
}
function local_logo(?string $name): string {
  if (!$name) return '';
  $base = normalize_network_basename($name);
  if ($base === '') return '';
  $abs  = __DIR__ . '/images/networks/';
  $rel  = 'images/networks/';
  foreach (['png','jpg','jpeg','svg','webp'] as $ext) {
    $p = $abs . $base . '.' . $ext;
    if (file_exists($p)) return $rel . $base . '.' . $ext;
  }
  return '';
}

/* ===== Params ===== */
$name    = get_q('name','');
$genre   = get_q('genre','');
$y1      = preg_replace('/\D/','', get_q('year_from',''));
$y2      = preg_replace('/\D/','', get_q('year_to',''));
$page    = max(1,(int)get_q('page','1'));
$perPage = 30; $offset=($page-1)*$perPage;

/* ===== Index renderer: with/without logo ===== */
function render_networks_index(mysqli $conn, bool $DEBUG){
  echo '<div class="networks-index"><h1>רשתות / סטרימינג</h1>';

  $sql="SELECT p.networks FROM posters p WHERE p.networks IS NOT NULL AND p.networks<>'' LIMIT 50000";
  try { $rs=$conn->query($sql); }
  catch(Throwable $e){
    echo '<div class="note note-warn">SQL: '.h($e->getMessage()).'</div>';
    if($DEBUG) echo '<pre>'.h($sql).'</pre>';
    echo '</div>'; return;
  }

  $counts=[];
  while($row=$rs->fetch_assoc()){
    foreach(split_networks_field($row['networks']) as $n){
      $n=trim($n); if($n==='') continue;
      $counts[$n]=($counts[$n]??0)+1;
    }
  }
  $rs->close();

  if(!$counts){
    echo '<div class="note note-warn">לא נמצאו רשתות בעמודה posters.networks.</div>';
    echo '</div>'; return;
  }

  uksort($counts, function($a,$b) use($counts){
    $ca=$counts[$a]??0; $cb=$counts[$b]??0;
    if($ca===$cb) return strcasecmp($a,$b);
    return ($ca>$cb)?-1:1;
  });

  $with=[]; $without=[];
  foreach($counts as $n=>$cnt){
    if (local_logo($n)) $with[$n]=$cnt; else $without[$n]=$cnt;
  }

  // WITH LOGO
  if(!empty($with)){
    echo '<div class="section">';
    echo '<div class="section-head"><h2>עם לוגו</h2><span class="section-count">'.count($with).'</span></div>';
    echo '<div class="net-grid">';
    foreach($with as $n=>$cnt){
      $href='network.php?name='.urlencode($n);
      $logo=local_logo($n);
      echo '<a class="net-card" href="'.h($href).'">';
        if($logo) echo '<img loading="lazy" alt="'.h($n).'" src="'.h($logo).'">';
        echo '<div class="net-name">'.h($n).' <span class="mini">('.(int)$cnt.')</span></div>';
      echo '</a>';
    }
    echo '</div></div>';
  }

  // WITHOUT LOGO — NO BUTTONS
  if(!empty($without)){
    echo '<div class="section">';
    echo '<div class="section-head"><h2>בלי לוגו</h2><span class="section-count">'.count($without).'</span></div>';
    echo '<div class="net-grid">';
    foreach($without as $n=>$cnt){
      $href='network.php?name='.urlencode($n);
      echo '<a class="net-card no-logo" href="'.h($href).'" title="פתח את עמוד הרשת">';
        echo '<div class="no-logo-box">אין לוגו</div>';
        echo '<div class="net-name">'.h($n).' <span class="mini">('.(int)$cnt.')</span></div>';
      echo '</a>';
    }
    echo '</div></div>';
  } else {
    echo '<div class="note">כל הרשתות מכוסות בלוגו ✅</div>';
  }

  echo '</div>'; // .networks-index
}

/* ===== WHERE for posters listing (selected network) ===== */
$where=[]; $types=''; $params=[];
if($name!==''){ $where[]="p.networks LIKE ?"; $types.='s'; $params[]='%'.$name.'%'; }
if(($g=get_q('genre',''))!==''){ $where[]="p.genres LIKE ?";   $types.='s'; $params[]='%'.$g.'%'; }
if(($yf=get_q('year_from',''))!==''){ $where[]="CAST(SUBSTRING(p.year,1,4) AS UNSIGNED) >= ?"; $types.='i'; $params[]=(int)preg_replace('/\D/','',$yf); }
if(($yt=get_q('year_to',''))!==''){   $where[]="CAST(SUBSTRING(p.year,1,4) AS UNSIGNED) <= ?"; $types.='i'; $params[]=(int)preg_replace('/\D/','',$yt); }
$whereSQL = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* ===== Posters list for a given network ===== */
function render_network_page(mysqli $conn, bool $DEBUG, string $name, string $whereSQL, string $types, array $params, int $page, int $perPage, int $offset){
  $logo=local_logo($name);
  echo '<div class="network-head">'; if($logo) echo '<img class="head-logo" src="'.h($logo).'" alt="'.h($name).'">'; echo '<h1>'.h($name).'</h1></div>';

  echo '<form class="filters" method="GET"><input type="hidden" name="name" value="'.h($name).'"><div class="row">';
  echo '<label>ז׳אנר: <input type="text" name="genre" value="'.h(get_q('genre','')).'" placeholder="למשל: Drama"></label>';
  echo '<label>שנה מ: <input type="number" name="year_from" min="1900" max="2100" value="'.h(get_q('year_from','')).'"></label>';
  echo '<label>עד: <input type="number" name="year_to" min="1900" max="2100" value="'.h(get_q('year_to','')).'"></label>';
  echo '<button type="submit">סינון</button></div></form>';

  $sqlBase="FROM posters p $whereSQL";
  $sqlCount="SELECT COUNT(*) $sqlBase";
  try{ $stmt=$conn->prepare($sqlCount); if($types) $stmt->bind_param($types,...$params); $stmt->execute(); $stmt->bind_result($total); $stmt->fetch(); $stmt->close(); }
  catch(Throwable $e){ echo '<div class="note note-warn">שגיאת SQL (count): '.h($e->getMessage()).'</div>'; if($DEBUG) echo '<pre>'.h($sqlCount).'</pre>'; return; }

  $sqlData="SELECT p.id, p.title_he, p.title_en, p.year, p.genres, p.networks,
                   COALESCE(p.poster_he,p.poster,p.poster_url,p.tmdb_poster,p.imdb_poster,p.image,p.image_url,p.img) AS poster
            $sqlBase
            ORDER BY CAST(SUBSTRING(p.year,1,4) AS UNSIGNED) DESC, p.id DESC
            LIMIT ? OFFSET ?";
  $types2=$types.'ii'; $params2=$params; $params2[]=$perPage; $params2[]=$offset;

  try{ $stmt=$conn->prepare($sqlData); $stmt->bind_param($types2,...$params2); $stmt->execute(); $res=$stmt->get_result(); }
  catch(Throwable $e){ echo '<div class="note note-warn">שגיאת SQL (data): '.h($e->getMessage()).'</div>'; if($DEBUG) echo '<pre>'.h($sqlData).'</pre>'; return; }

  if($res->num_rows===0){ echo '<div class="note note-warn">אין תוצאות לרשת הזו עם הסינון הנוכחי.</div>'; }

  echo '<div class="posters-grid">';
  while($row=$res->fetch_assoc()){
    $img=prefer_poster_url($row); $title=$row['title_he']?:($row['title_en']?:'ללא שם'); $year=$row['year']; $pid=(int)$row['id'];
    $firstNet = split_networks_field($row['networks'])[0] ?? '';
    $nLogo    = local_logo($firstNet);
    echo '<a class="poster-card" href="poster.php?id='.$pid.'"><div class="img-wrap">';
    if($img) echo '<img loading="lazy" src="'.h($img).'" alt="'.h($title).'">';
    if($nLogo) echo '<img class="net-badge" src="'.h($nLogo).'" alt="">';
    echo '</div><div class="meta"><div class="t">'.h($title).'</div><div class="y">'.h($year).'</div></div></a>';
  }
  echo '</div>';

  $stmt->close();
  $pages=max(1,(int)ceil($total/$perPage));
  if($pages>1){ echo '<div class="pager">'; $q=$_GET; unset($q['page']); for($i=1;$i<=$pages;$i++){ $q['page']=$i; $href='network.php?'.http_build_query($q); $cls=($i===$page)?' class="on"':''; echo '<a'.$cls.' href="'.h($href).'">'.$i.'</a>'; } echo '</div>'; }
}

/* ===== Styles ===== */
?>
<style>
:root { --gap:14px; }
*{box-sizing:border-box}
body{direction:rtl}
h1{font-size:28px;margin:10px 0 18px}

/* Sections & notes */
.section{margin:16px 0 22px}
.section-head{display:flex;align-items:center;gap:8px;margin:4px 0 10px}
.section-head h2{margin:0;font-size:20px}
.section-count{background:#f0f0f0;border-radius:999px;padding:2px 8px;font-size:12px;color:#555}
.note{grid-column:1/-1;color:#444;background:#fafafa;border:1px solid #eee;border-radius:10px;padding:10px;margin:8px 0}
.note-warn{color:#b00;background:#fff5f5;border-color:#f1c0c0}

/* Index grid */
.networks-index .net-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:var(--gap)}
.net-card{
  display:flex;flex-direction:column;align-items:center;gap:8px;
  padding:12px;border:1px solid #eee;border-radius:16px;text-decoration:none;color:#222;background:#fff
}
.net-card img{max-width:140px;max-height:70px;object-fit:contain}
.net-card.no-logo{align-items:stretch}
.no-logo-box{
  width:100%; height:70px; display:flex; align-items:center; justify-content:center;
  background:#fafafa; border:1px dashed #ccc; border-radius:10px; color:#888; font-size:13px
}
.net-name{font-weight:600;text-align:center}
.net-name .mini{font-weight:400;color:#666;font-size:12px}

/* Head + filters */
.network-head{display:flex;align-items:center;gap:12px;margin:8px 0 14px}
.head-logo{max-width:160px;max-height:70px;object-fit:contain}
.filters{padding:10px;border:1px solid #eee;border-radius:12px;background:#fafafa;margin:8px 0 16px}
.filters .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.filters input{padding:8px;border:1px solid #ddd;border-radius:10px}
.filters button{padding:9px 14px;border:0;border-radius:10px;background:#222;color:#fff;cursor:pointer}

/* Posters grid */
.posters-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:var(--gap)}
.poster-card{display:block;text-decoration:none;color:#222;border:1px solid #eee;border-radius:14px;overflow:hidden;background:#fff;position:relative}
.poster-card .img-wrap{aspect-ratio:2/3;background:#f3f3f3;display:flex;align-items:center;justify-content:center;position:relative}
.poster-card img{max-width:100%;max-height:100%;object-fit:cover;width:100%;height:100%}
.poster-card .img-wrap .net-badge{
  position:absolute; bottom:6px; left:6px;
  max-width:70px; max-height:28px; object-fit:contain;
  background:rgba(255,255,255,.85); border-radius:8px; padding:2px 4px;
}
.poster-card .meta{padding:8px 10px;display:flex;justify-content:space-between;gap:10px;align-items:center}
.poster-card .meta .t{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.poster-card .meta .y{font-size:12px;color:#666}

.pager{display:flex;gap:6px;justify-content:center;margin:16px 0}
.pager a{padding:8px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;color:#222;background:#fff}
.pager a.on{background:#222;color:#fff;border-color:#222}
</style>

<div class="wrap">
<?php
try{
  if($name===''){ render_networks_index($conn,$DEBUG); }
  else { render_network_page($conn,$DEBUG,$name,$whereSQL,$types,$params,$page,$perPage,$offset); }
}catch(Throwable $e){
  echo '<div class="note note-warn">Fatal: '.h($e->getMessage()).'</div>';
}
?>
</div>