<?php
/****************************************************
 * poster.php — עמוד פוסטר מלא (RTL, עברית)
 * גרסה מתוקנת עם הצגת Connections, כותרת תקינה ו-runtime משופר
 ****************************************************/
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }

/* ====== תלויות ====== */
require_once __DIR__ . '/SERVER.php'; // מגדיר $conn ועוד
include 'header.php'; 
/* =================== ADD-ONLY BLOCK (poster actions) =================== */
if (!function_exists('__pa_h')) { function __pa_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$__pa_id = 0;
if (isset($poster['id'])) $__pa_id = (int)$poster['id'];
elseif (isset($row['id'])) $__pa_id = (int)$row['id'];
elseif (isset($id)) $__pa_id = (int)$id;
elseif (isset($_GET['id']) && ctype_digit($_GET['id'])) $__pa_id = (int)$_GET['id'];

$__pa_msgs = array();
$__pa_token = session_id();

/* helpers */
if (!function_exists('__pa_extract_imdb')) {
  function __pa_extract_imdb($s){ return (preg_match('~tt\d{7,8}~', (string)$s, $m) ? $m[0] : ''); }
}
if (!function_exists('__pa_extract_local')) {
  function __pa_extract_local($s){ return (preg_match('~poster\.php\?id=(\d+)~', (string)$s, $m) ? (int)$m[1] : 0); }
}

/* votes */
if (isset($conn) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pv_action']) && $__pa_id>0) {
  $act = $_POST['pv_action'];
  if ($act==='remove') {
    $stmt=$conn->prepare("DELETE FROM poster_votes WHERE poster_id=? AND visitor_token=?");
    $stmt->bind_param("is", $__pa_id, $__pa_token); $stmt->execute(); $stmt->close();
    $__pa_msgs[]='הצבעה בוטלה';
  } elseif ($act==='like' || $act==='dislike') {
    $stmt=$conn->prepare("SELECT vote_type FROM poster_votes WHERE poster_id=? AND visitor_token=?");
    $stmt->bind_param("is", $__pa_id, $__pa_token); $stmt->execute(); $res=$stmt->get_result();
    if ($res && $res->num_rows){
      $stmt2=$conn->prepare("UPDATE poster_votes SET vote_type=? WHERE poster_id=? AND visitor_token=?");
      $stmt2->bind_param("sis", $act, $__pa_id, $__pa_token); $stmt2->execute(); $stmt2->close();
    } else {
      $stmt2=$conn->prepare("INSERT INTO poster_votes (poster_id, visitor_token, vote_type) VALUES (?,?,?)");
      $stmt2->bind_param("iss", $__pa_id, $__pa_token, $act); $stmt2->execute(); $stmt2->close();
    }
    $stmt->close();
  }
}
$__pa_like=$__pa_dislike=0; $__pa_user_vote='';
if (isset($conn) && $__pa_id>0){
  $q=$conn->query("SELECT COUNT(*) c FROM poster_votes WHERE poster_id={$__pa_id} AND vote_type='like'");    if($q){$__pa_like=(int)$q->fetch_assoc()['c'];}
  $q=$conn->query("SELECT COUNT(*) c FROM poster_votes WHERE poster_id={$__pa_id} AND vote_type='dislike'"); if($q){$__pa_dislike=(int)$q->fetch_assoc()['c'];}
  $st=$conn->prepare("SELECT vote_type FROM poster_votes WHERE poster_id=? AND visitor_token=?");
  $st->bind_param("is", $__pa_id, $__pa_token); $st->execute(); $re=$st->get_result();
  if($re && $re->num_rows){ $__pa_user_vote=(string)$re->fetch_assoc()['vote_type']; } $st->close();
}

/* user tags */
if (isset($conn) && $_SERVER['REQUEST_METHOD']==='POST' && $__pa_id>0){
  if (isset($_POST['ut_add'])){
    $__val=trim((string)($_POST['ut_value']??'')); if($__val!==''){
      $st=$conn->prepare("SELECT 1 FROM user_tags WHERE poster_id=? AND genre=?");
      $st->bind_param("is", $__pa_id, $__val); $st->execute(); $st->store_result();
      if($st->num_rows==0){
        $st2=$conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?,?)");
        $st2->bind_param("is", $__pa_id, $__val); $st2->execute(); $st2->close();
        $__pa_msgs[]='תגית נוספה';
      } else { $__pa_msgs[]='תגית כבר קיימת'; }
      $st->close();
    }
  }
  if (isset($_POST['ut_remove'])){
    $__gid=(int)$_POST['ut_remove'];
    $conn->query("DELETE FROM user_tags WHERE id={$__gid} AND poster_id={$__pa_id}");
    $__pa_msgs[]='תגית הוסרה';
  }
}
$__pa_user_tags=array();
if (isset($conn)){
  $r=$conn->query("SELECT id, genre FROM user_tags WHERE poster_id={$__pa_id} ORDER BY id DESC");
  while($r && $t=$r->fetch_assoc()){ $__pa_user_tags[]=$t; }
}

/* similar */
if (isset($conn) && $_SERVER['REQUEST_METHOD']==='POST' && $__pa_id>0){
  if (isset($_POST['sim_add'])){
    $__in=trim((string)($_POST['sim_value']??'')); $__target=0;
    if($__in!=='' && ctype_digit($__in)) $__target=(int)$__in;
    if(!$__target) $__target=__pa_extract_local($__in);
    if(!$__target){ $__tt=__pa_extract_imdb($__in);
      if($__tt){ $st=$conn->prepare("SELECT id FROM posters WHERE imdb_id=?"); $st->bind_param("s",$__tt); $st->execute();
        $rr=$st->get_result(); if($rr && ($rw=$rr->fetch_assoc())) $__target=(int)$rw['id']; $st->close(); } }
    if($__target>0 && $__target!==$__pa_id){
      $conn->query("INSERT IGNORE INTO poster_similar (poster_id, similar_id) VALUES ({$__pa_id}, {$__target})");
      $conn->query("INSERT IGNORE INTO poster_similar (poster_id, similar_id) VALUES ({$__target}, {$__pa_id})");
      $__pa_msgs[]='נוסף סרט דומה';
    } else { $__pa_msgs[]='לא נמצא סרט מתאים'; }
  }
  if (isset($_POST['sim_remove'])){
    $__sid=(int)$_POST['sim_remove'];
    $conn->query("DELETE FROM poster_similar WHERE poster_id={$__pa_id} AND similar_id={$__sid}");
    $conn->query("DELETE FROM poster_similar WHERE poster_id={$__sid} AND similar_id={$__pa_id}");
    $__pa_msgs[]='נמחק קשר דומה';
  }
}
$__pa_similar=array();
if (isset($conn)){
  $r=$conn->query("SELECT p.id,p.title_en,p.title_he,p.image_url FROM poster_similar ps JOIN posters p ON p.id=ps.similar_id WHERE ps.poster_id={$__pa_id} ORDER BY p.title_en");
  while($r && $s=$r->fetch_assoc()){ $__pa_similar[]=$s; }
}

/* collections */
if (isset($conn) && $_SERVER['REQUEST_METHOD']==='POST' && $__pa_id>0){
  if (isset($_POST['col_add'])){
    $__cid=(int)($_POST['col_value']??0); if($__cid>0){
      $st=$conn->prepare("INSERT IGNORE INTO poster_collections (poster_id, collection_id) VALUES (?,?)");
      $st->bind_param("ii", $__pa_id, $__cid); $st->execute(); $st->close();
      $__pa_msgs[]='נוסף לאוסף';
    }
  }
  if (isset($_POST['col_remove'])){
    $__cid=(int)$_POST['col_remove'];
    $conn->query("DELETE FROM poster_collections WHERE poster_id={$__pa_id} AND collection_id={$__cid}");
    $__pa_msgs[]='הוסר מהאוסף';
  }
}
$__pa_collections=array(); $__pa_collections_all=array();
if (isset($conn)){
  $r=$conn->query("SELECT c.id,c.name FROM poster_collections pc JOIN collections c ON c.id=pc.collection_id WHERE pc.poster_id={$__pa_id} ORDER BY c.name");
  while($r && $c=$r->fetch_assoc()){ $__pa_collections[]=$c; }
  $r=$conn->query("SELECT id,name FROM collections ORDER BY name ASC");
  while($r && $c=$r->fetch_assoc()){ $__pa_collections_all[]=$c; }
}

/* flags (display only) */
$__pa_langs=$__pa_ctrs=$__pa_genres=array();
$__pa_src = array();
if (isset($poster) && is_array($poster)) $__pa_src=$poster; elseif (isset($row) && is_array($row)) $__pa_src=$row;
if (!empty($__pa_src['languages'])) $__pa_langs = array_filter(array_map('trim', explode(',', (string)$__pa_src['languages'])));
if (!empty($__pa_src['countries'])) $__pa_ctrs  = array_filter(array_map('trim', explode(',', (string)$__pa_src['countries'])));
$__line=''; if (!empty($__pa_src['genre'])) $__line=(string)$__pa_src['genre']; elseif (!empty($__pa_src['genres'])) $__line=(string)$__pa_src['genres'];
if ($__line!=='') $__pa_genres = array_filter(array_map('trim', explode(',', $__line)));
/* =================== /ADD-ONLY BLOCK =================== */
/* ====== עזרי תצוגה ====== */
function flatten_strings($v){$o=[];$st=[$v];while($st){$c=array_pop($st);if(is_array($c)){$st=array_merge($st,$c);continue;}if(is_object($c))$c=(string)$c;$t=trim((string)$c);if($t!=='')$o[]=$t;}return $o;}
function safeHtml($v){if(is_array($v)||is_object($v))return htmlspecialchars(implode(', ',flatten_strings($v)),ENT_QUOTES,'UTF-8');return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function safeJoin($arr,$sep=', '){ if($arr===null) return ''; if(!is_array($arr)) $arr = [$arr]; $vals=array_map(fn($t)=>trim((string)$t),flatten_strings($arr)); $vals=array_values(array_filter($vals,fn($x)=>$x!=='')); return htmlspecialchars(implode($sep,$vals),ENT_QUOTES,'UTF-8');}
function H($v){return (is_array($v)||is_object($v))?safeJoin($v):safeHtml($v);}
function normalize_list($v){ $o=[]; $split=function($s){return array_map('trim',preg_split('~\s*[,;/]\s*~u',(string)$s,-1,PREG_SPLIT_NO_EMPTY)?:[]);}; $push=function($x)use(&$o,$split){foreach($split($x) as $p){ if($p!=='') $o[]=$p; }}; if(is_array($v)){ $it=new RecursiveIteratorIterator(new RecursiveArrayIterator($v)); foreach($it as $x){ if(!is_array($x)) $push($x); } } elseif($v!==null && $v!==''){ $push($v); } $seen=[]; $u=[]; foreach($o as $i){ $k=mb_strtolower(preg_replace('~\s+~u',' ',$i),'UTF-8'); if(!isset($seen[$k])){$seen[$k]=1; $u[]=$i;} } return $u; }
function parse_any_list_field($raw){ if ($raw===null || $raw==='') return []; if (is_string($raw)) { $j = json_decode($raw,true); if (json_last_error()===JSON_ERROR_NONE && is_array($j)) return normalize_list($j); } if (is_array($raw)) return normalize_list($raw); return normalize_list((string)$raw); }

// <<< הוספה: פונקציה לעיצוב זמן ריצה
function format_runtime(int $minutes): string {
    if ($minutes <= 0) return '';
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    $parts = [];
    if ($h > 0) $parts[] = "{$h}h";
    if ($m > 0) $parts[] = "{$m}m";
    if (empty($parts)) return "{$minutes} min";
    return implode(' ', $parts) . " ({$minutes} min)";
}

/* ====== הבאת פוסטר מה-DB ====== */
$posterRow = null;
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  $stmt = $conn->prepare("SELECT * FROM posters WHERE id = ?");
  $stmt->bind_param("i", $_GET['id']);
  $stmt->execute();
  $posterRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
} elseif (!empty($_GET['tt']) && preg_match('~^tt\d{6,10}$~', $_GET['tt'])) {
  $stmt = $conn->prepare("SELECT * FROM posters WHERE imdb_id = ? LIMIT 1");
  $stmt->bind_param("s", $_GET['tt']);
  $stmt->execute();
  $posterRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if (!$posterRow) {
  http_response_code(404);
  die('<!doctype html><html><head><title>404 Not Found</title></head><body><h1>Poster Not Found</h1></body></html>');
}

/* ====== הבאת רשימת AKAs ====== */
$akas = [];
$poster_id_for_akas = (int)$posterRow['id'];
if ($poster_id_for_akas > 0) {
  // ✔ תיקון: בלי sort_order כדי לא לקבל Unknown column 'sort_order'
  $stmt = $conn->prepare("SELECT aka_title FROM poster_akas WHERE poster_id = ? ORDER BY id ASC");
  $stmt->bind_param("i", $poster_id_for_akas);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) { if (trim($r['aka_title'])) $akas[] = trim($r['aka_title']); }
  $stmt->close();
}

/* ====== הבאת Connections מה-DB ====== */
$connections = []; // ['Spin-off' => [ ['title'=>'X','imdb_id'=>'tt123'], ... ], ... ]
$poster_id_for_conn = (int)$posterRow['id'];
if ($poster_id_for_conn > 0) {
    $stmt = $conn->prepare("SELECT relation_label, related_title, related_imdb_id FROM poster_connections WHERE poster_id = ? ORDER BY relation_label, id");
    $stmt->bind_param("i", $poster_id_for_conn);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $lab = (string)$r['relation_label'];
        $ttl = trim((string)$r['related_title']);
        $tid = trim((string)$r['related_imdb_id']);
        if ($lab !== '' && ($ttl !== '' || $tid !== '')) {
            $connections[$lab][] = ['title' => ($ttl ?: $tid), 'imdb_id' => $tid];
        }
    }
    $stmt->close();
}

/* ====== עיבוד שדות לתצוגה ====== */
$imdb_id        = $posterRow['imdb_id']         ?? '';
$title_en       = $posterRow['title_en']        ?? '';
$original_title = $posterRow['original_title']  ?? '';
$title_he       = $posterRow['title_he']        ?? '';
$year           = $posterRow['year']            ?? '';
$is_tv          = (int)($posterRow['is_tv']     ?? 0);
$poster_url     = $posterRow['poster_url']      ?? ($posterRow['poster'] ?? '');
$trailer_url    = $posterRow['trailer_url']     ?? '';
$overview_he    = $posterRow['overview_he']     ?? '';
$overview_en    = $posterRow['overview_en']     ?? '';
$imdb_rating    = $posterRow['imdb_rating']     ?? null;
$imdb_votes     = $posterRow['imdb_votes']      ?? null;
$rt_score       = $posterRow['rt_score']        ?? null;
$rt_url         = $posterRow['rt_url']          ?? null;
$mc_score       = $posterRow['mc_score']        ?? null;
$mc_url         = $posterRow['mc_url']          ?? null;
$tmdb_url       = $posterRow['tmdb_url']        ?? null;
$tvdb_url       = $posterRow['tvdb_url']        ?? null;
// <<< תיקון: קריאת נתונים מהעמודות הנכונות
$seasons        = $posterRow['seasons_count']   ?? null;
$episodes       = $posterRow['episodes_count']  ?? null;

// <<< תיקון: שימוש בפונקציה החדשה לעיצוב זמן הריצה
$runtime_formatted = format_runtime((int)($posterRow['runtime'] ?? 0));

$genres         = parse_any_list_field($posterRow['genres'] ?? '');
$languages      = parse_any_list_field($posterRow['languages'] ?? '');
$countries      = parse_any_list_field($posterRow['countries'] ?? '');
$networks       = parse_any_list_field($posterRow['networks'] ?? '');
$directors      = parse_any_list_field($posterRow['directors'] ?? '');
$writers        = parse_any_list_field($posterRow['writers'] ?? '');
$producers      = parse_any_list_field($posterRow['producers'] ?? '');
$composers      = parse_any_list_field($posterRow['composers'] ?? '');
$cinematographers = parse_any_list_field($posterRow['cinematographers'] ?? '');
$cast           = parse_any_list_field($posterRow['cast'] ?? '');

$title_kind = $is_tv ? 'TV Series' : 'Movie';

// <<< תיקון: שימוש בכותרת הנקייה כפי שנשמרה ב-DB
$display_title = $title_en ?: ($original_title ?: $imdb_id);

$CAST_LIMIT = 60;
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title><?= H($display_title) ?> — עמוד פוסטר</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --chip:#1e2433; --accent:#5b8cff; --line:#22283a; }
    *{box-sizing:border-box} body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;direction:rtl;background:var(--bg);color:var(--text);margin:0;padding:24px} .wrap{max-width:1100px;margin:0 auto} h2{margin:0 0 18px;font-weight:700;letter-spacing:.2px} .card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden} .row{display:grid;grid-template-columns:320px 1fr;gap:0} .poster{padding:18px;border-inline-end:1px solid var(--line);background:linear-gradient(180deg,#161b26,#131723)} img.poster-img{display:block;width:100%;height:auto;border-radius:10px;border:1px solid var(--line)} .content{padding:20px 20px 10px} .title{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px} .title h3{margin:0;font-size:24px;line-height:1.25} .subtitle{color:var(--muted)} .chips{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0} .chip{background:var(--chip);border:1px solid var(--line);padding:6px 10px;border-radius:999px;font-size:13px} .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-top:6px} .section{margin-top:14px;padding-top:12px;border-top:1px solid var(--line)} .kv{margin:0;font-size:14px} .label{color:var(--muted)} .links a,.conn-list a{color:var(--accent);text-decoration:none} .links a:hover,.conn-list a:hover{text-decoration:underline} .ratings{display:flex;flex-wrap:wrap;gap:14px} .pill{background:#121623;border:1px solid var(--line);border-radius:12px;padding:8px 12px;font-size:14px;display:inline-block} .comma-list{margin:0} .hidden{display:none} .btn-toggle{cursor:pointer;background:#121623;border:1px solid var(--line);color:#e7ecff;border-radius:10px;padding:6px 10px;margin-top:8px} .ellipsis{color:var(--muted)}
  body {background-color:#161b26 !important; text-align: right !important;}
    .content {text-align: right !important;}
    .content a  {color: #6E8BFC !important;}

    /* .w3-bar */
    .w3-bar {
        width: 100%;
        overflow: hidden;
    }
    .w3-bar .w3-bar-item {
        padding: 8px 16px;
        float: left; /* הדפדפן הופך אוטומטית לימין ב-RTL */
        width: auto;
        border: none;
        display: block;
        outline: 0;
    }
    .w3-bar .w3-button {
        color: white !important;;
        white-space: normal;
    }
    .w3-bar:before, .w3-bar:after {
        content: "";
        display: table;
        clear: both;
    }

    /* .w3-padding */
    .w3-padding {
        padding: 8px 16px !important;
    }

    /* .w3-button */
    .w3-button {
        border: none;
        display: inline-block;
        padding: 8px 16px;
        vertical-align: middle;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        text-align: center;
        cursor: pointer;
        white-space: nowrap;
    }
    

    /* צבעים */
    .w3-black, .w3-hover-black:hover {
        color: #fff !important;
        background-color: white;
    }
    .w3-white, .w3-hover-white:hover {
        color: #000 !important;
        background-color: #fff !important;
    }
    .white {color: #f1f1f1 !important;}
    .w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover{color:#000!important;background-color:#f1f1f1!important}
  
     .logo {  filter: saturate(500%) contrast(800%) brightness(500%) 
      invert(100%) sepia(50%) hue-rotate(120deg); }
        filter: saturate(500%) contrast(800%) brightness(500%) 
      invert(80%) sepia(50%) hue-rotate(120deg); } 
  </style>
</head>
<body><br>
<div class="wrap">
  <div class="card" style="margin-bottom:16px">
    <div class="row">
      <div class="poster">
        <?php if (!empty($poster_url)): ?>
          <img class="poster-img" src="<?= H($poster_url) ?>" alt="Poster" loading="lazy" decoding="async">
        <?php endif; ?>
      </div>
      <div class="content">
        <div class="title">
          <h3><?= H($display_title) ?></h3>
          <?php if (!empty($year)): ?><span class="subtitle">(<?= H($year) ?>)</span><?php endif; ?>
        </div>
      <div class="chips">
    <span class="chip"><?= H($title_kind) ?></span>
    <?php if (!empty($languages)): ?><span class="chip"><?= safeJoin($languages) ?></span><?php endif; ?>
    <?php if (!empty($countries)): ?><span class="chip"><?= safeJoin($countries) ?></span><?php endif; ?>
    <?php if (!empty($runtime_formatted)): ?><span class="chip"><?= H($runtime_formatted) ?></span><?php endif; ?>
    
    <?php if ($is_tv && !empty($seasons) && $seasons > 0): ?><span class="chip">Seasons: <?= H($seasons) ?></span><?php endif; ?>
    <?php if ($is_tv && !empty($episodes) && $episodes > 0): ?><span class="chip">Episodes: <?= H($episodes) ?></span><?php endif; ?>

    <?php if ($is_tv && !empty($networks)): ?><span class="chip"><?= safeJoin($networks) ?></span><?php endif; ?>
</div>
        <?php if (!empty($title_he)): ?><p class="kv"><span class="label">שם בעברית:</span> <?= H($title_he) ?></p><?php endif; ?>
        <?php if (!empty($genres)): ?><p class="kv"><span class="label">ז׳אנרים:</span> <?= safeJoin($genres) ?></p><?php endif; ?>
        <div class="section">
          <div class="ratings" >
            <?php if ($imdb_rating): ?><span class="pill">IMDb: <?= H($imdb_rating) ?>/10<?= $imdb_votes ? ' • '.number_format((int)$imdb_votes).' votes' : '' ?><img src="images/imdb.png" style="vertical-align: middle;" alt="IMDB Score" title="IMDB Score" width="33px"></span><?php endif; ?>
            <?php if ($rt_score): ?><?php if ($rt_url): ?><a class="pill" href="<?= H($rt_url) ?>" target="_blank" rel="noopener">Rotten Tomatoes: <?= H($rt_score) ?>%&nbsp;<img src="images/rotten-tomatoes.png" style="vertical-align: middle;" alt="Rotten-Tomatoes Score" title="Rotten-Tomatoes Score" width="24px"></a> <?php else: ?><span class="pill">Rotten Tomatoes: <?= H($rt_score) ?>%</span><?php endif; ?><?php endif; ?>
            <?php if ($mc_score): ?><?php if ($mc_url): ?><a class="pill" href="<?= H($mc_url) ?>" target="_blank" rel="noopener">Metacritic: <?= H($mc_score) ?>/100 <img src="images/metacritic.png" style="vertical-align: middle;" alt="Metacritic Score" title="Metacritic Score" width="28px"></a><?php else: ?><span class="pill">Metacritic: <?= H($mc_score) ?>/100</span><?php endif; ?><?php endif; ?>
          </div>
                 </div>
        <div class="section links">
          <div class="grid">
            <?php if ($imdb_id): ?><p class="kv"><span class="label">IMDb ID:</span> <?= H($imdb_id) ?> — <a href="<?= H('https://www.imdb.com/title/'.$imdb_id.'/') ?>" target="_blank" rel="noopener">Open</a></p><?php endif; ?>
            <?php if ($tvdb_url): ?><p class="kv"><span class="label">TVDB:</span> <a href="<?= H($tvdb_url) ?>" target="_blank" rel="noopener"><?= H($tvdb_url) ?></a></p><?php endif; ?>
            <?php if ($tmdb_url): ?><p class="kv"><span class="label">TMDb:</span> <a href="<?= H($tmdb_url) ?>" target="_blank" rel="noopener"><?= H($tmdb_url) ?></a></p><?php endif; ?>
            <?php if ($trailer_url): ?><p class="kv"><span class="label">טריילר:</span> <a href="<?= H($trailer_url) ?>" target="_blank" rel="noopener"><?= H($trailer_url) ?></a></p><?php endif; ?>
          </div>
        </div>
        <?php if ($overview_he || $overview_en): ?>
  <div class="section">
    <?php if ($overview_he): ?><p class="kv"><span class="label">תקציר:</span> <?= H($overview_he) ?></p><?php endif; ?>
    <?php 
      if ($overview_en) {
        // ניקוי הסיומת "...Read all" מהתקציר האנגלי לפני ההדפסה
        $cleaned_overview = preg_replace('~\s*(\.\.\.|…)\s*Read all\s*»?$~iu', '', $overview_en);
        echo '<p class="kv"><span class="label">תקציר (EN):</span> ' . H($cleaned_overview) . '</p>';
      }
    ?>
  </div>
<?php endif; ?>
        <div class="section">
          <div class="grid">
            <?php if ($directors): ?><p class="kv"><span class="label">Directors:</span> <?= safeJoin($directors) ?></p><?php endif; ?>
            <?php if ($writers): ?><p class="kv"><span class="label">Writers:</span> <?= safeJoin($writers) ?></p><?php endif; ?>
            <?php if ($producers): ?><p class="kv"><span class="label">Producers:</span> <?= safeJoin($producers) ?></p><?php endif; ?>
            <?php if ($composers): ?><p class="kv"><span class="label">Composers:</span> <?= safeJoin($composers) ?></p><?php endif; ?>
            <?php if ($cinematographers): ?><p class="kv"><span class="label">Cinematographers:</span> <?= safeJoin($cinematographers) ?></p><?php endif; ?>
          </div>
        </div>
        <?php if (!empty($cast)): $items = $cast; $first = array_slice($items, 0, $CAST_LIMIT); $rest = array_slice($items, $CAST_LIMIT); $ctid = 'cast-'.$posterRow['id']; ?>
          <div class="section">
            <p class="kv"><span class="label">שחקנים:</span></p>
            <p class="comma-list" dir="rtl"><?= safeJoin($first) ?><?php if ($rest): ?>, <span class="ellipsis" id="ell-<?= H($ctid) ?>">…</span><span id="<?= H($ctid) ?>" class="more hidden">, <?= safeJoin($rest) ?></span><?php endif; ?></p>
            <?php if ($rest): ?><button class="btn-toggle" type="button" data-toggle="<?= H($ctid) ?>" data-open="false">הצג הכל</button><?php endif; ?>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($akas)): ?>
          <div class="section">
            <p class="kv"><span class="label">AKAs:</span></p>
            <?php $aid = 'akas-'.(int)$posterRow['id']; ?>
            <p class="comma-list" dir="rtl">
              <span class="ellipsis" id="ell-<?= H($aid) ?>">…</span>
              <span id="<?= H($aid) ?>" class="more hidden"><?= safeJoin($akas) ?></span>
            </p>
            <button class="btn-toggle" type="button" data-toggle="<?= H($aid) ?>" data-open="false">הצג הכל</button>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($connections)): ?>
          <div class="section">
            <h4 style="margin:0 0 8px 0">IMDb Connections</h4>
            <?php
              // סדר מועדף כמו ב-new-movie
              $pref = ['Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of'];
              $seen = [];
              $render_group = function($label, $items){
                  $links = [];
                  foreach ($items as $it) {
                      $tid = trim((string)($it['imdb_id'] ?? ''));
                      $t   = trim((string)($it['title']   ?? ''));
                      if ($tid !== '') {
                          $links[] = '<a href="poster.php?tt='.H($tid).'" target="_blank" rel="noopener">'.H($t ?: $tid).'</a>';
                      } else {
                          $links[] = H($t);
                      }
                  }
                  echo '<p class="kv"><span class="label">'.H($label).':</span> <span class="conn-list">'.implode(', ', $links).'</span></p>';
              };
              foreach ($pref as $p) {
                  if (!empty($connections[$p])) { $render_group($p, $connections[$p]); $seen[$p]=1; }
              }
              // כל שאר הקטגוריות שלא הופיעו בסדר המועדף
              foreach ($connections as $lab => $items) {
                  if (!empty($seen[$lab])) continue;
                  $render_group($lab, $items);
              }
            ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
  function toggleMore(btn){
    var id=btn.getAttribute('data-toggle'),
        more=document.getElementById(id),
        ell=document.getElementById('ell-'+id),
        open=btn.getAttribute('data-open')==='true';
    if(!more) return;
    if(open){
      more.classList.add('hidden');
      if(ell) ell.classList.remove('hidden');
      btn.textContent='הצג הכל';
      btn.setAttribute('data-open','false');
    }else{
      more.classList.remove('hidden');
      if(ell) ell.classList.add('hidden');
      btn.textContent='הצג פחות';
      btn.setAttribute('data-open','true');
    }
  }
  document.addEventListener('click',function(e){
    var t=e.target.closest&&e.target.closest('.btn-toggle');
    if(t) toggleMore(t);
  });
</script>
<!-- ===== ADD-ONLY: poster actions addon ===== -->
<style>
  /* ממוקף – לא נוגע בשאר האתר */
  #poster-actions-addon{margin:24px 0}
  #poster-actions-addon .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center}
  #poster-actions-addon .section-title{margin:18px 0 8px;font-weight:700}
  #poster-actions-addon .btn,
  #poster-actions-addon input[type=text],
  #poster-actions-addon select{
    border-radius:999px;
    padding:8px 14px;
    border:1px solid #444;
    background:rgba(255,255,255,.04);
    color:#eee;
    outline:none
  }
  #poster-actions-addon .btn{cursor:pointer}
  #poster-actions-addon .btn:hover{background:rgba(255,255,255,.08)}
  #poster-actions-addon .btn--solid{background:#2a2a2a;border-color:#555}
  #poster-actions-addon .btn--liked{background:#1f3b20;border-color:#2e6b35}
  #poster-actions-addon .btn--disliked{background:#3b1f20;border-color:#6b2e35}
  #poster-actions-addon .chips{display:flex;flex-wrap:wrap;gap:8px}
  #poster-actions-addon .chip{display:flex;gap:6px;align-items:center;border:1px solid #444;background:rgba(255,255,255,.04);color:#eee;border-radius:999px;padding:6px 10px}
  #poster-actions-addon .chip .x{background:transparent;border:0;color:#bbb;cursor:pointer}
  #poster-actions-addon .msgbox{margin:10px auto 0;max-width:760px;border:1px solid #555;border-radius:10px;background:#1b1b1b;color:#ddd;padding:10px 14px}
  #poster-actions-addon .stack{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
  #poster-actions-addon .ltr{direction:ltr;text-align:left}
</style>

<div id="poster-actions-addon" dir="rtl">
  <!-- פעולות על הפוסטר -->
  <div class="row">
    <a class="btn" href="report.php?poster_id=<?= (int)$__pa_id ?>">🚨 דווח</a>
    <a class="btn" href="edit_poster.php?id=<?= (int)$__pa_id ?>">✏️ ערוך</a>
    <a class="btn" href="delete_poster.php?id=<?= (int)$__pa_id ?>" onclick="return confirm('למחוק את הפוסטר?')">🗑️ מחק</a>
  </div>

  <!-- אהבתי / לא אהבתי -->
  <div class="row" style="margin-top:8px">
    <form method="post" class="stack">
      <input type="hidden" name="pv_action" value="like">
      <button type="submit" class="btn <?= ($__pa_user_vote==='like'?'btn--liked':'') ?>">❤️ אהבתי (<?= (int)$__pa_like ?>)</button>
    </form>
    <form method="post" class="stack">
      <input type="hidden" name="pv_action" value="dislike">
      <button type="submit" class="btn <?= ($__pa_user_vote==='dislike'?'btn--disliked':'') ?>">💔 לא אהבתי (<?= (int)$__pa_dislike ?>)</button>
    </form>
    <?php if (!empty($__pa_user_vote)): ?>
      <form method="post" class="stack">
        <input type="hidden" name="pv_action" value="remove">
        <button type="submit" class="btn">❌ בטל הצבעה</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!empty($__pa_msgs)): ?>
    <div class="msgbox">
      <ul style="margin:0;padding-inline-start:18px">
        <?php foreach ($__pa_msgs as $__m): ?><li><?= __pa_h($__m) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- תגיות משתמש -->
  <div class="section-title">תגיות משתמש</div>
  <?php if (!empty($__pa_user_tags)): ?>
    <div class="chips" style="justify-content:center">
      <?php foreach ($__pa_user_tags as $__t): ?>
        <form method="post" class="chip">
          <span><?= __pa_h($__t['genre']) ?></span>
          <button class="x" type="submit" name="ut_remove" value="<?= (int)$__t['id'] ?>" title="מחק תגית">✕</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="text-align:center;color:#9a9a9a">אין תגיות עדיין</div>
  <?php endif; ?>
  <form method="post" class="row" style="margin-top:8px">
    <input type="text" name="ut_value" placeholder="הוסף תגית" required>
    <button type="submit" name="ut_add" class="btn btn--solid">➕ הוסף תגית</button>
  </form>

  <!-- סרטים דומים -->
  <div class="section-title">סרטים דומים</div>
  <?php if (!empty($__pa_similar)): ?>
    <ul style="max-width:760px;margin:0 auto 8px;list-style:disc inside;color:#ddd">
      <?php foreach ($__pa_similar as $__s): ?>
        <li style="margin:4px 0;">
          <a href="poster.php?id=<?= (int)$__s['id'] ?>" style="color:#ffd265"><?= __pa_h($__s['title_en'] ?: $__s['title_he']) ?></a>
          <form method="post" style="display:inline">
            <button type="submit" name="sim_remove" value="<?= (int)$__s['id'] ?>" class="btn" title="מחק קישור דומה">✕</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div style="text-align:center;color:#9a9a9a">אין סרטים דומים כרגע</div>
  <?php endif; ?>
  <form method="post" class="row">
    <input class="ltr" type="text" name="sim_value" placeholder="ID מקומי / poster.php?id=XX / tt1234567">
    <button type="submit" name="sim_add" class="btn btn--solid">➕ הוסף סרט דומה</button>
  </form>

  <!-- אוספים -->
  <div class="section-title">אוספים</div>
  <?php if (!empty($__pa_collections)): ?>
    <div class="chips" style="justify-content:center">
      <?php foreach ($__pa_collections as $__c): ?>
        <form method="post" class="chip">
          <a href="universe.php?collection_id=<?= (int)$__c['id'] ?>" style="color:#ffd265"><?= __pa_h($__c['name']) ?></a>
          <button class="x" type="submit" name="col_remove" value="<?= (int)$__c['id'] ?>" title="הסר מהאוסף">✕</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="text-align:center;color:#9a9a9a">לא משויך עדיין לאף אוסף</div>
  <?php endif; ?>
  <form method="post" class="row" style="margin-top:8px">
    <select name="col_value">
      <option value="">בחר אוסף…</option>
      <?php foreach ($__pa_collections_all as $__opt): ?>
        <option value="<?= (int)$__opt['id'] ?>"><?= __pa_h($__opt['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" name="col_add" class="btn btn--solid">➕ הוסף לאוסף</button>
  </form>

  <!-- סיווג דגלים -->
  <?php if (!empty($__pa_langs) || !empty($__pa_ctrs) || !empty($__pa_genres)): ?>
    <div class="section-title">סיווג דגלים</div>
    <div class="chips" style="justify-content:center">
      <?php foreach ($__pa_langs as $__lng): ?>
        <a class="chip" href="language_imdb.php?lang_code=<?= urlencode($__lng) ?>"><?= __pa_h($__lng) ?></a>
      <?php endforeach; ?>
      <?php foreach ($__pa_ctrs as $__ct): ?>
        <a class="chip" href="country.php?country=<?= urlencode($__ct) ?>"><?= __pa_h($__ct) ?></a>
      <?php endforeach; ?>
      <?php foreach ($__pa_genres as $__g): ?>
        <a class="chip" href="genre.php?name=<?= urlencode($__g) ?>"><?= __pa_h($__g) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<!-- ===== /ADD-ONLY ===== -->

</body>
</html>
<?php include 'footer.php'; ?>
