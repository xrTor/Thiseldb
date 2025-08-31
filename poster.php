<?php
/****************************************************
 * poster.php — עמוד פוסטר מלא (RTL, עברית)
 ****************************************************/
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }

/* ====== תלויות ====== */
require_once __DIR__ . '/SERVER.php';
include 'header.php';

/* =================== ADD-ONLY BLOCK (poster actions & helpers) =================== */
if (!function_exists('__pa_h')) { function __pa_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$__pa_id = 0;
if (isset($poster['id'])) $__pa_id = (int)$poster['id'];
elseif (isset($row['id'])) $__pa_id = (int)$row['id'];
elseif (isset($id)) $__pa_id = (int)$id;
elseif (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) $__pa_id = (int)$_GET['id'];

$__pa_msgs  = array();
$__pa_token = session_id();

/* helpers */
if (!function_exists('__pa_extract_imdb')) {
  function __pa_extract_imdb($s){ return (preg_match('~tt\d{7,10}~', (string)$s, $m) ? $m[0] : ''); }
}
if (!function_exists('__pa_extract_local')) {
  function __pa_extract_local($s){ return (preg_match('~poster\.php\?id=(\d+)~', (string)$s, $m) ? (int)$m[1] : 0); }
}

/* ====== עזרי תצוגה ====== */
function flatten_strings($v){$o=[];$st=[$v];while($st){$c=array_pop($st);if(is_array($c)){$st=array_merge($st,$c);continue;}if(is_object($c))$c=(string)$c;$t=trim((string)$c);if($t!=='')$o[]=$t;}return $o;}
function safeHtml($v){if(is_array($v)||is_object($v))return htmlspecialchars(implode(', ',flatten_strings($v)),ENT_QUOTES,'UTF-8');return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function safeJoin($arr, $sep=', '){
  if($arr===null) return '';
  if(!is_array($arr)) $arr = [$arr];
  $vals=array_map(fn($t)=>trim((string)$t),flatten_strings($arr));
  $vals=array_values(array_filter($vals,fn($x)=>$x!=='')); 
  return htmlspecialchars(implode($sep,$vals),ENT_QUOTES,'UTF-8');
}
function H($v){return (is_array($v)||is_object($v))?safeJoin($v):safeHtml($v);}
function normalize_list($v){ $o=[]; $split=function($s){return array_map('trim',preg_split('~\s*[,;/]\s*~u',(string)$s,-1,PREG_SPLIT_NO_EMPTY)?:[]);}; $push=function($x)use(&$o,$split){foreach($split($x) as $p){ if($p!=='') $o[]=$p; }}; if(is_array($v)){ $it=new RecursiveIteratorIterator(new RecursiveArrayIterator($v)); foreach($it as $x){ if(!is_array($x)) $push($x); } } elseif($v!==null && $v!==''){ $push($v); } $seen=[]; $u=[]; foreach($o as $i){ $k=mb_strtolower(preg_replace('~\s+~u',' ',$i),'UTF-8'); if(!isset($seen[$k])){$seen[$k]=1; $u[]=$i;} } return $u; }
function parse_any_list_field($raw){ if ($raw===null || $raw==='') return []; if (is_string($raw)) { $j = json_decode($raw,true); if (json_last_error()===JSON_ERROR_NONE && is_array($j)) return normalize_list($j); } if (is_array($raw)) return normalize_list($raw); return normalize_list((string)$raw); }
function format_runtime(int $minutes): string { if ($minutes <= 0) return ''; $h=floor($minutes/60); $m=$minutes%60; $p=[]; if($h>0)$p[]="{$h}h"; if($m>0)$p[]="{$m}m"; return ($p?implode(' ',$p)." ({$minutes} min)":"{$minutes} min"); }

/* ====== הבאת פוסטר מה-DB ====== */
$posterRow = null;
if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
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

/* חשוב: לזהות את ה-id מתוך השורה שנשלפה (מטפל במצב של tt=) */
if ($__pa_id <= 0 && !empty($posterRow['id'])) {
  $__pa_id = (int)$posterRow['id'];
}

/* ====== AKAs ====== */
$akas = [];
if ($__pa_id > 0) {
  $stmt = $conn->prepare("SELECT aka_title FROM poster_akas WHERE poster_id = ? ORDER BY id ASC");
  $stmt->bind_param("i", $__pa_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) { if (trim($r['aka_title'])) $akas[] = trim($r['aka_title']); }
  $stmt->close();
}

/* ====== Connections ====== */
$connections = [];
if ($__pa_id > 0) {
  $stmt = $conn->prepare("SELECT relation_label, related_title, related_imdb_id
                          FROM poster_connections
                          WHERE poster_id = ?
                          ORDER BY relation_label, id");
  $stmt->bind_param("i", $__pa_id);
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

/* ====== עיבוד שדות ====== */
$imdb_id        = $posterRow['imdb_id']         ?? '';
$title_en       = $posterRow['title_en']        ?? '';
$original_title = $posterRow['original_title']  ?? '';
$title_he       = $posterRow['title_he']        ?? '';
$year           = trim((string)($posterRow['year'] ?? ''));
$is_tv          = (int)($posterRow['is_tv']     ?? 0);
$poster_url     = $posterRow['image_url']       ?? ($posterRow['poster_url'] ?? ($posterRow['poster'] ?? ''));
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
/* הוסר TVDb לפי בקשה */
$tvdb_url       = $posterRow['tvdb_url']        ?? null;
$seasons        = $posterRow['seasons_count']   ?? null;
$episodes       = $posterRow['episodes_count']  ?? null;

/* פוסטר ברירת מחדל */
if (!$poster_url) { $poster_url = 'images/no-poster.png'; }

$runtime_formatted = format_runtime((int)($posterRow['runtime'] ?? 0));

$genres         = parse_any_list_field($posterRow['genres'] ?? '');
$languages      = parse_any_list_field($posterRow['languages'] ?? '');
$countries      = parse_any_list_field($posterRow['countries'] ?? '');
$networks       = parse_any_list_field($posterRow['networks'] ?? ($posterRow['network'] ?? ''));
$directors      = parse_any_list_field($posterRow['directors'] ?? '');
$writers        = parse_any_list_field($posterRow['writers'] ?? '');
$producers      = parse_any_list_field($posterRow['producers'] ?? '');
$composers      = parse_any_list_field($posterRow['composers'] ?? '');
$cinematographers = parse_any_list_field($posterRow['cinematographers'] ?? '');
$cast           = parse_any_list_field($posterRow['cast'] ?? ($posterRow['actors'] ?? ''));

$title_kind = $is_tv ? 'TV Series' : 'Movie';
$display_title = $title_en ?: ($original_title ?: $imdb_id);
$CAST_LIMIT = 60;

/* === הפקת מזהה YouTube מטריילר (להטמעה) === */
$ytId = '';
if ($trailer_url) {
  if (preg_match('~(?:v=|/embed/|youtu\.be/)([A-Za-z0-9_-]{11})~', $trailer_url, $m)) $ytId = $m[1];
}

/* ====== POST: לייקים / תגיות / סרטים דומים ====== */
if (isset($conn) && $_SERVER['REQUEST_METHOD']==='POST' && $__pa_id>0) {
  /* votes */
  if (isset($_POST['pv_action'])) {
    $act=$_POST['pv_action'];
    if ($act==='remove') {
      $stmt=$conn->prepare("DELETE FROM poster_votes WHERE poster_id=? AND visitor_token=?");
      $stmt->bind_param("is", $__pa_id, $__pa_token); $stmt->execute(); $stmt->close();
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

  /* user tags */
  if (isset($_POST['ut_add'])) {
    $__val=trim((string)($_POST['ut_value']??'')); if($__val!==''){
      $st=$conn->prepare("SELECT 1 FROM user_tags WHERE poster_id=? AND genre=?");
      $st->bind_param("is", $__pa_id, $__val); $st->execute(); $st->store_result();
      if($st->num_rows==0){
        $st2=$conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?,?)");
        $st2->bind_param("is", $__pa_id, $__val); $st2->execute(); $st2->close();
      }
      $st->close();
    }
  }
  if (isset($_POST['ut_remove'])) {
    $__gid=(int)$_POST['ut_remove'];
    $conn->query("DELETE FROM user_tags WHERE id={$__gid} AND poster_id={$__pa_id}");
  }

  /* similar add/remove */
  if (isset($_POST['sim_add'])) {
    $__in=trim((string)($_POST['sim_value']??'')); $__target=0;
    if($__in!=='' && ctype_digit($__in)) $__target=(int)$__in;
    if(!$__target) $__target=__pa_extract_local($__in);
    if(!$__target){ $__tt=__pa_extract_imdb($__in);
      if($__tt){ $st=$conn->prepare("SELECT id FROM posters WHERE imdb_id=?"); $st->bind_param("s",$__tt); $st->execute();
        $rr=$st->get_result(); if($rr && ($rw=$rr->fetch_assoc())) $__target=(int)$rw['id']; $st->close(); } }
    if($__target>0 && $__target!==$__pa_id){
      $conn->query("INSERT IGNORE INTO poster_similar (poster_id, similar_id) VALUES ({$__pa_id}, {$__target})");
      $conn->query("INSERT IGNORE INTO poster_similar (poster_id, similar_id) VALUES ({$__target}, {$__pa_id})");
    }
  }
  if (isset($_POST['sim_remove'])) {
    $__sid=(int)$_POST['sim_remove'];
    $conn->query("DELETE FROM poster_similar WHERE poster_id={$__pa_id} AND similar_id={$__sid}");
    $conn->query("DELETE FROM poster_similar WHERE poster_id={$__sid} AND similar_id={$__pa_id}");
  }
}

/* לאסוף מחדש אחרי פעולות */
$__pa_like=$__pa_dislike=0; $__pa_user_vote='';
if (isset($conn) && $__pa_id>0){
  if($q=$conn->query("SELECT COUNT(*) c FROM poster_votes WHERE poster_id={$__pa_id} AND vote_type='like'"))    $__pa_like   =(int)($q->fetch_assoc()['c'] ?? 0);
  if($q=$conn->query("SELECT COUNT(*) c FROM poster_votes WHERE poster_id={$__pa_id} AND vote_type='dislike'")) $__pa_dislike=(int)($q->fetch_assoc()['c'] ?? 0);
  $st=$conn->prepare("SELECT vote_type FROM poster_votes WHERE poster_id=? AND visitor_token=?");
  $st->bind_param("is", $__pa_id, $__pa_token); $st->execute(); $re=$st->get_result();
  if($re && $re->num_rows){ $__pa_user_vote=(string)$re->fetch_assoc()['vote_type']; } $st->close();

  $__pa_user_tags=array();
  if($r=$conn->query("SELECT id, genre FROM user_tags WHERE poster_id={$__pa_id} ORDER BY id DESC")){
    while($r && $t=$r->fetch_assoc()){ $__pa_user_tags[]=$t; }
  }

  $__pa_similar=array();
  if($r=$conn->query("SELECT p.id,p.title_en,p.title_he,p.image_url FROM poster_similar ps JOIN posters p ON p.id=ps.similar_id WHERE ps.poster_id={$__pa_id} ORDER BY p.title_en")){
    while($r && $s=$r->fetch_assoc()){ $__pa_similar[]=$s; }
  }

  $__pa_collections=array();
  if($r=$conn->query("SELECT c.id,c.name FROM poster_collections pc JOIN collections c ON c.id=pc.collection_id WHERE pc.poster_id={$__pa_id} ORDER BY c.name")){
    while($r && $c=$r->fetch_assoc()){ $__pa_collections[]=$c; }
  }
}
/* =================== /ADD-ONLY BLOCK =================== */

/* ====== פונקציות לינקים (שרת) ====== */
/* שינוי מינימלי: כל chip/search -> home.php, ושמות (person/cast) -> actor.php?name= */
function make_links(array $items, string $param, array $extra = [], string $sep = ', '): string {
  $out=[];
  foreach ($items as $name) {
    $name = trim((string)$name);
    if ($name==='') continue;
    if ($param==='person' || $param==='cast') {
      $href = 'actor.php?name='.urlencode($name);
    } else {
      $qs = [$param=>$name] + $extra;
      $q  = http_build_query($qs);
      $href = 'home.php' . ($q ? ('?'.$q) : '');
    }
    $out[] = '<a href="'.__pa_h($href).'" target="_self" rel="noopener">'.__pa_h($name).'</a>';
  }
  return implode($sep, $out);
}
function chip_links(array $items, string $param, array $extra = []): string {
  $out=[];
  foreach ($items as $name){
    $name = trim((string)$name);
    if ($name==='') continue;
    $qs = [$param=>$name] + $extra;
    $href = 'home.php?'.http_build_query($qs);
    $out[] = '<a class="chip" href="'.__pa_h($href).'" target="_self" rel="noopener">'.__pa_h($name).'</a>';
  }
  return implode("\n", $out);
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title><?= H($display_title) ?> — עמוד פוסטר</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="style.css">
  <style>
    a {color: #5587ec !important;}
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --chip:#1e2433; --accent:#5b8cff; --line:#22283a; }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;direction:rtl;background:var(--bg);color:var(--text);margin:0;padding:24px}
    .wrap{max-width:1200px;margin:0 auto}
    .card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden}
    .row{display:grid;grid-template-columns:320px 1fr;gap:0}
    .poster{padding:18px;border-inline-end:1px solid var(--line);background:linear-gradient(180deg,#161b26,#131723)}
    img.poster-img{display:block;width:100%;height:auto;border-radius:10px;border:1px solid var(--line)}
    .content{padding:20px 20px 10px}
    .title{display:flex;flex-direction:column;gap:2px}
    .title h3{margin:0;font-size:24px;line-height:1.25}
    .subtitle{color:var(--muted)}
    .chips{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
    .chip{background:var(--chip);border:1px solid var(--line);padding:6px 10px;border-radius:999px;font-size:13px;text-decoration:none;color:inherit}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-top:6px}
    .section{margin-top:14px;padding-top:12px;border-top:1px solid var(--line)}
    .kv{margin:0;font-size:14px}
    .label{color:var(--muted)}
    .links a,.conn-list a{color:var(--accent);text-decoration:none}
    .links a:hover,.conn-list a:hover{text-decoration:underline}
    .ratings{display:flex;flex-wrap:wrap;gap:14px;align-items:center}
    .pill{background:#121623;border:1px solid var(--line);border-radius:12px;padding:8px 12px;font-size:14px;display:inline-block;text-decoration:none;color:inherit}
    .comma-list{margin:0}
    .hidden{display:none}
    .btn, .btn-toggle{ cursor:pointer; border:1px solid var(--line); border-radius:10px; padding:6px 10px; background:transparent; color:inherit; }
    .btn:hover{ filter:brightness(1.05); }
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 14px 0}
    .tag-pill{margin:2px 4px;display:inline-block}

    /* Toggle ניהול יחיד */
    body.mgmt-hidden .mgmt-only { display:none !important; }
    body.mgmt-hidden #mgmt-panel { display:none !important; }
    body.mgmt-open  #mgmt-panel { display:block !important; }
    #mgmt-panel{ border:1px solid var(--line); border-radius:12px; padding:12px; margin-top:10px; background:transparent; }
    #mgmt-panel h4{ margin:0 0 8px }
    #mgmt-panel .row-forms{ display:flex; flex-wrap:wrap; gap:10px; align-items:center }
    #mgmt-panel input[type=text]{ border:1px solid var(--line); background:transparent; color:inherit; border-radius:8px; padding:6px 10px; }

    body {background-color:#161b26 !important; text-align: right !important;}
    .content {text-align: right !important;}
    .content a  {color: #6E8BFC !important;}

    /* דגלים – בלי רקע, ול–silent.gif יוחל class="logo" (פילטר כבר אצלך ב-CSS) */
    .flags-under-poster a{background:transparent !important; border:none !important; color:#fff !important; display:inline-flex; align-items:center; gap:.5rem; padding:.25rem 0; text-decoration:none}
    .flags-under-poster img{ width:18px;height:12px;object-fit:cover;border-radius:2px; }
    .flags-under-poster b{ font-weight:600; color:#fff }

    /* טריילר */
    .trailer-wrap{display:flex;justify-content:center}
    .trailer-embed{position:relative;border-radius:12px;overflow:hidden}
    .trailer-embed.has-yt{aspect-ratio:16/9;max-width:100%;background:#000;box-shadow:0 4px 16px rgba(0,0,0,.2)}
    .trailer-embed.has-yt iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
    .no-trailer-box{display:flex;justify-content:center}
    .no-trailer-box img{display:block;width:500px;max-width:100%;height:auto;border:0}
    
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

      /* ==== Light theme overrides ==== *//* ==== Light theme overrides ==== */
body.theme-light {
  --bg:#ffffff;        /* רקע כללי */
  --card:#ffffff;      /* כרטיסים */
  --text:#222222;      /* טקסט כהה */
  --muted:#555555;     /* טקסט משני */
  --chip:#f5f5f5;      /* שבבים */
  --accent:#1a73e8;    /* כחול לינקים */
  --line:#dddddd;      /* גבולות בהירים */
  background-color:#ffffff !important;
  color:var(--text) !important;
}

body.theme-light a { color: var(--accent) !important; }
body.theme-light .content a { color: var(--accent) !important; }

body.theme-light .poster {
  background:#ffffff;
  border-inline-end:1px solid var(--line);
}

body.theme-light img.poster-img { border-color: var(--line); }

body.theme-light .chip {
  background: var(--chip);
  border-color: var(--line);
  color: var(--text);
}

body.theme-light .pill {
  background: #fff;
  border-color: var(--line);
  color: var(--text);
}

body.theme-light .btn {
  color: var(--text);
  border-color: var(--line);
  background:#fff;
}
body.theme-light .btn:hover {
  background:#f1f1f1;
}

body.theme-light .label { color: var(--muted); }
body.theme-light .section { border-top:1px solid var(--line); }
body.theme-light {
  background-color:#ffffff !important;  /* רקע לבן */
  color:#222222 !important;             /* טקסט כהה */
}
/* תיקון מצב בהיר: לרקע לבן גם בבלוקים כהים עקשנים */
body.theme-light{
  --bg:#ffffff; --card:#ffffff; --text:#222; --muted:#555; --chip:#f5f5f5; --accent:#1a73e8; --line:#d9d9d9;
  background:#ffffff !important;
  color:#222 !important;
}

/* הכרטיס/מסגרת הראשית + אזורי משנה */
body.theme-light .card{ background:#ffffff !important; border-color:var(--line) !important; }
body.theme-light .row{ background:#ffffff !important; }
body.theme-light .poster{ background:#ffffff !important; border-inline-end:1px solid var(--line) !important; }
body.theme-light .content{ background:#ffffff !important; }

/* סרגל עליון וכפתורים */
body.theme-light .toolbar{ background:#ffffff !important; }
body.theme-light .btn, 
body.theme-light .btn-toggle{ background:#ffffff !important; color:var(--text) !important; border-color:var(--line) !important; }
body.theme-light .btn:hover{ background:#f1f1f1 !important; }

/* צ'יפים/תגיות ופילס */
body.theme-light .chip{ background:var(--chip) !important; color:var(--text) !important; border-color:var(--line) !important; }
body.theme-light .pill{ background:#ffffff !important; color:var(--text) !important; border-color:var(--line) !important; }

/* קישורים */
body.theme-light a, 
body.theme-light .content a{ color:var(--accent) !important; }

/* תמונת הפוסטר וקווים */
body.theme-light img.poster-img{ border-color:var(--line) !important; }
body.theme-light .section{ border-top:1px solid var(--line) !important; }

/* אם יש לך .w3-* כהים בראש הדף – להבהיר */
body.theme-light .w3-black, 
body.theme-light .w3-hover-black:hover{ background:#ffffff !important; color:#222 !important; border-color:var(--line) !important; }
/* מצב בהיר – צבע טקסט שחור בתפריט */
body.theme-light nav,
body.theme-light nav a,
body.theme-light .w3-bar .w3-bar-item,
body.theme-light .w3-button {
  color:#000 !important;
}
/* במצב בהיר – אל תגע בלוגו */
body.theme-light .logo {
  filter: none !important;
}
/* מצב פסיקים */
body.view-commas .chips { display:block; }
body.view-commas .chips .chip { 
  display:inline; 
  background:none; 
  border:none; 
  padding:0; 
  margin:0; 
  color:var(--text);
}
body.view-commas .chips .chip::after { content:", "; }
body.view-commas .chips .chip:last-child::after { content:""; }
/* מצב פסיקים */
body.view-commas .chips { display:block; }
body.view-commas .chips .chip { 
  display:inline; 
  background:none; 
  border:none; 
  padding:0; 
  margin:0; 
  color:var(--text);
}
body.view-commas .chips .chip::after { content:", "; }
body.view-commas .chips .chip:last-child::after { content:""; }

/* שבבים סטטיים (סוג/Runtime/Seasons/Episodes) */
.chips-static { margin-top:8px; margin-bottom:8px; }
body.view-commas .chips-static .chip-static { 
  display:inline-block !important;
  background:var(--chip) !important;
  border:1px solid var(--line) !important;
  padding:6px 10px !important;
  margin:2px 4px !important;
}
body.view-commas .chips-static .chip-static::after { content:"" !important; }

  </style>
</head>
<body>
<script>document.addEventListener('DOMContentLoaded',()=>{document.body.classList.add('mgmt-hidden');});</script>
<br>

<div class="wrap">
  <div class="card" style="margin-bottom:16px">
    <div class="row">

      <!-- ===== עמודת תמונת הפוסטר + דגלים + Connections ===== -->
      <div class="poster">
        <img class="poster-img" src="<?= H($poster_url) ?>" alt="Poster" loading="lazy" decoding="async">

        <?php
        /* --- FLAGS מתחת לפוסטר --- */
        $flag_codes = [];
        if ($__pa_id > 0 && isset($conn)) {
          $stf = $conn->prepare("SELECT lang_code FROM poster_languages WHERE poster_id=? ORDER BY lang_code");
          $stf->bind_param("i", $__pa_id); $stf->execute(); $rsf = $stf->get_result();
          while ($rsf && ($ln = $rsf->fetch_assoc())) { $flag_codes[] = strtolower(trim($ln['lang_code'])); }
          $stf->close();
        }
        // מיפוי דגלים מתוך languages.php
        $FLAG = []; $map=[];
        $had = array_key_exists('languages',$GLOBALS);
        $bak = $had ? $GLOBALS['languages'] : null;
        if (is_file(__DIR__ . '/languages.php')) { include __DIR__ . '/languages.php'; if (isset($languages) && is_array($languages)) { $map = $languages; } }
        if ($had) { $GLOBALS['languages'] = $bak; } else { unset($languages); }
        foreach ($map as $x) { if (!empty($x['code'])) { $c=strtolower(trim($x['code'])); $FLAG[$c]=['label'=>$x['label']??strtoupper($c),'flag'=>$x['flag']??'']; } }

        if (!empty($flag_codes)) {
          echo '<div class="flags-under-poster" dir="rtl" style="margin-top:.6rem;">';
          foreach ($flag_codes as $code) {
            $label   = $FLAG[$code]['label'] ?? strtoupper($code);
            $flagSrc = $FLAG[$code]['flag']  ?? ('flags/'.$code.'.gif');
            $flagClass = (preg_match('~(^|/)(silent\.gif)(\?.*)?$~i', (string)$flagSrc) ? 'logo' : '');
            echo '<div style="margin:.25rem 0;">';
            echo '<a class="tag" href="language.php?lang_code='.urlencode($code).'" title="'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'">';
            echo '<img src="'.htmlspecialchars($flagSrc,ENT_QUOTES,'UTF-8').'" alt="'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'"'.($flagClass?' class="'.$flagClass.'"':'').'>';
            echo '<b>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</b>';
            echo '</a></div>';
          }
          echo '</div>';
        }

        /* --- Connections --- */
        if (!empty($connections)) {
          echo '<div class="section" style="text-align:left;direction:ltr;">';
          echo '<h4 style="margin:0 0 8px 0">IMDb Connections</h4>';
          $pref = ['Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of'];
          $seen = [];
          $render_group = function($label, $items){
            $links = [];
            foreach ($items as $it) {
              $tid = trim((string)($it['imdb_id'] ?? ''));
              $t   = trim((string)($it['title']   ?? ''));
              if ($tid !== '') $links[] = '<a href="poster.php?tt='.htmlspecialchars($tid,ENT_QUOTES,'UTF-8').'" target="_blank" rel="noopener">'.htmlspecialchars($t ?: $tid,ENT_QUOTES,'UTF-8').'</a>';
              else $links[] = htmlspecialchars($t,ENT_QUOTES,'UTF-8');
            }
            echo '<p class="kv"><span class="label">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').':</span><br> <span class="conn-list">'.implode('<br> ', $links).'</span></p>';
          };
          foreach ($pref as $p) { if (!empty($connections[$p])) { $render_group($p, $connections[$p]); $seen[$p]=1; } }
          foreach ($connections as $lab => $items) { if (empty($seen[$lab])) $render_group($lab, $items); }
          echo '</div>';
        }
        ?>
      </div>

      <!-- ===== עמודת תוכן ===== -->
      <div class="content">

        <!-- פס עליון: ניהול + לייק -->
        <div class="toolbar">
          <button type="button" id="btn-theme-toggle" class="btn" title="החלף מצב תצוגה">🌞 מצב בהיר</button>
<button type="button" id="btn-view-toggle" class="btn">🔀 מצב פסיקים</button>

          <a class="btn" href="report.php?poster_id=<?= (int)$__pa_id ?>">🚨 דווח</a>
          <a class="btn" href="edit.php?id=<?= (int)$__pa_id ?>">✏️ ערוך</a>
          <a class="btn" href="delete.php?id=<?= (int)$__pa_id ?>" onclick="return confirm('למחוק את הפוסטר?')">🗑️ מחק</a>
          <button type="button" id="btn-mgmt-toggle" class="btn">⚙️ הצג/הסתר ניהול</button>

          <form method="post" style="display:inline-flex;gap:8px;align-items:center;margin-inline-start:8px;">
            <button type="submit" name="pv_action" value="like" class="btn" style="<?= ($__pa_user_vote==='like'?'outline:2px solid #3d6c42;':'') ?>">❤️ אהבתי (<?= (int)$__pa_like ?>)</button>
            <button type="submit" name="pv_action" value="dislike" class="btn" style="<?= ($__pa_user_vote==='dislike'?'outline:2px solid #6c3d3d;':'') ?>">💔 לא אהבתי (<?= (int)$__pa_dislike ?>)</button>
            <?php if ($__pa_user_vote): ?><button type="submit" name="pv_action" value="remove" class="btn">❌ בטל</button><?php endif; ?>
          </form>
        </div>

        <!-- כותרות -->
        <div class="title">
          <h3>
            <?= H($display_title) ?>
            <?php if ($year !== ''): ?>
              <a href="home.php?year=<?= H($year) ?>" class="subtitle" style="text-decoration:none">[<?= H($year) ?>]</a>
            <?php endif; ?>
          </h3>
          <?php if (!empty($title_he)): ?><h3><?= H($title_he) ?></h3><?php endif; ?>
        </div>

        <!-- שיוך לאוספים -->
<?php if (!empty($__pa_collections)): ?>
  <div class="section">
    <p class="kv"><span class="label">משויך לאוספים:</span></p>
    <div class="chips">
      <?php foreach ($__pa_collections as $c): ?>
        <a class="chip" href="collection.php?id=<?= (int)$c['id'] ?>">
          🧩 <?= __pa_h($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
        
<!-- פרטים -->
<div class="section">
  <p class="kv"><span class="label">פרטים:</span></p>
  <div class="chips">
    <span class="chip"><?= H($title_kind) ?></span>
    <?php if (!empty($runtime_formatted)): ?>
      <span class="chip"><?= H($runtime_formatted) ?></span>
    <?php endif; ?>
    <?php if ($is_tv && !empty($seasons) && $seasons > 0): ?>
      <span class="chip">Seasons: <?= H($seasons) ?></span>
    <?php endif; ?>
    <?php if ($is_tv && !empty($episodes) && $episodes > 0): ?>
      <span class="chip">Episodes: <?= H($episodes) ?></span>
    <?php endif; ?>
  </div>
</div>

        <!-- שפות -->
<?php if (!empty($languages)): ?>
  <div class="section">
    <p class="kv"><span class="label">שפות:</span></p>
    <div class="chips">
      <?= chip_links($languages, 'lang_code') ?>
    </div>
  </div>
<?php endif; ?>

<!-- מדינות -->
<?php if (!empty($countries)): ?>
  <div class="section">
    <p class="kv"><span class="label">מדינות:</span></p>
    <div class="chips">
      <?= chip_links($countries, 'country') ?>
    </div>
  </div>
<?php endif; ?>

<!-- רשתות -->
<?php if (!empty($networks)): ?>
  <div class="section">
    <p class="kv"><span class="label">רשתות:</span></p>
    <div class="chips">
      <?php foreach ($networks as $n): ?>
        <a class="chip" href="network.php?name=<?= urlencode($n) ?>"><?= H($n) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>



   

        <!-- ז'אנרים -->
        <?php if (!empty($genres)): ?>
          <div class="section">
            <p class="kv"><span class="label">ז׳אנרים:</span></p>
            <div class="chips">
              <?php foreach($genres as $g): ?>
                <a class="chip tag-pill" href="home.php?genre=<?= urlencode($g) ?>"><?= H($g) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- תגיות משתמשים -->
        <?php if (!empty($__pa_user_tags)): ?>
          <div class="section" style="border-top:none;padding-top:6px;">
            <p class="kv"><span class="label">תגיות:</span></p>
            <div class="chips">
              <?php foreach ($__pa_user_tags as $t): ?>
                <a class="chip tag-pill" href="home.php?user_tag=<?= urlencode($t['genre']) ?>"><?= H($t['genre']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- פאנל ניהול (ללא שינוי עיצוב) -->
        <div id="mgmt-panel" class="mgmt-only">
          <h4>ניהול תגיות וסרטים דומים</h4>

          <div class="section" style="border-top:none;padding-top:0;">
            <p class="kv"><span class="label">הוספת סרט דומה:</span></p>
            <form method="post" class="row-forms">
              <input type="text" name="sim_value" placeholder="מזהה פנימי, tt1234567 או poster.php?id=XX" required>
              <button type="submit" name="sim_add" class="btn">📥 הוסף סרט דומה</button>
            </form>
          </div>

          <div class="section">
            <p class="kv"><span class="label">מחיקת סרט דומה:</span></p>
            <?php if (!empty($__pa_similar)): ?>
              <div style="display:flex; flex-wrap:wrap; gap:16px;">
                <?php foreach ($__pa_similar as $sim): $sim_img = (!empty($sim['image_url'])) ? $sim['image_url'] : 'images/no-poster.png'; ?>
                  <div style="width:110px; text-align:center;">
                    <a href="poster.php?id=<?= (int)$sim['id'] ?>">
                      <img src="<?= H($sim_img) ?>" style="width:100px; border-radius:1px;" loading="lazy" decoding="async"><br>
                      <small><?= H($sim['title_en'] ?: $sim['title_he']) ?></small>
                    </a><br>
                    <form method="post" style="margin-top:6px;"><button type="submit" name="sim_remove" value="<?= (int)$sim['id'] ?>" class="btn mgmt-only">🗑️ הסר</button></form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?><p class="kv" style="color:#9aa4b2">אין מה למחוק כרגע.</p><?php endif; ?>
          </div>

          <div class="section">
            <p class="kv"><span class="label">הוספת תגית:</span></p>
            <form method="post" class="row-forms">
              <input type="text" name="ut_value" placeholder="הוסף תגית" required>
              <button type="submit" name="ut_add" class="btn">➕ הוסף</button>
            </form>
          </div>

          <div class="section">
            <p class="kv"><span class="label">מחיקת תגית:</span></p>
            <?php if (!empty($__pa_user_tags)): ?>
              <div class="chips">
                <?php foreach ($__pa_user_tags as $t): ?>
                  <form method="post" style="display:inline-block;margin:0">
                    <span class="chip tag-pill"><?= H($t['genre']) ?> <button type="submit" name="ut_remove" value="<?= (int)$t['id'] ?>" class="btn mgmt-only" title="מחיקת תגית">🗑️</button></span>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php else: ?><p class="kv" style="color:#9aa4b2">אין תגיות למחיקה.</p><?php endif; ?>
          </div>
        </div>

        <!-- דירוגים וקישורים -->
        <div class="section">
          <div class="ratings">
            <?php if ($imdb_rating): ?>
              <?php $imdb_link = $imdb_id ? ('https://www.imdb.com/title/'.rawurlencode($imdb_id).'/') : ''; ?>
              <?php if ($imdb_link): ?>
                <a class="pill" href="<?= H($imdb_link) ?>" target="_blank" rel="noopener">
                  IMDb: <?= H($imdb_rating) ?>/10<?= $imdb_votes ? ' • '.number_format((int)$imdb_votes).' votes' : '' ?>
                  <img src="images/imdb.png" alt="IMDb" title="IMDb" style="vertical-align:middle" width="33">
                </a>
              <?php else: ?>
                <span class="pill">
                  IMDb: <?= H($imdb_rating) ?>/10<?= $imdb_votes ? ' • '.number_format((int)$imdb_votes).' votes' : '' ?>
                  <img src="images/imdb.png" alt="IMDb" title="IMDb" style="vertical-align:middle" width="33">
                </span>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($rt_score): ?>
              <?php if ($rt_url): ?>
                <a class="pill" href="<?= H($rt_url) ?>" target="_blank" rel="noopener">Rotten Tomatoes: <?= H($rt_score) ?>%
                  <img src="images/rotten-tomatoes.png" style="vertical-align:middle" alt="RT" width="24"></a>
              <?php else: ?><span class="pill">Rotten Tomatoes: <?= H($rt_score) ?>%</span><?php endif; ?>
            <?php endif; ?>

            <?php if ($mc_score): ?>
              <?php if ($mc_url): ?>
                <a class="pill" href="<?= H($mc_url) ?>" target="_blank" rel="noopener">Metacritic: <?= H($mc_score) ?>/100
                  <img src="images/metacritic.png" style="vertical-align:middle" alt="MC" width="28"></a>
              <?php else: ?><span class="pill">Metacritic: <?= H($mc_score) ?>/100</span><?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($tmdb_url)): ?><a class="pill" href="<?= H($tmdb_url) ?>" target="_blank" rel="noopener">TMDb <img src="images/tmdb.png" style="vertical-align:middle" alt="TMDb" width="72"></a><?php endif; ?>
            <?php if (!empty($tvdb_url)): ?>
  <a class="pill" href="<?= H($tvdb_url) ?>" target="_blank" rel="noopener">TVDb <img src="images/tvdb.png" style="vertical-align:middle" alt="TVDb" width="38"></a>
<?php endif; ?>

          </div>
        </div>

        <!-- תקציר/צוות -->
        <?php if ($overview_he || $overview_en): ?>
          <div class="section">
            <?php if ($overview_he): ?><p class="kv"><span class="label">תקציר:</span><br> <?= H($overview_he) ?></p><?php endif; ?>
            <?php if ($overview_en): $cleaned_overview = preg_replace('~\s*(\.\.\.|…)\s*Read all\s*»?$~iu', '', $overview_en); ?>
              <p class="kv"><br><span class="label">תקציר (אנגלית):</span><br> <?= H($cleaned_overview) ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="section">
          <div class="grid">
            <?php if ($directors): ?>
              <p class="kv"><span class="label">Directors:</span> <?= make_links($directors, 'person', ['role'=>'director']) ?></p>
            <?php endif; ?>
            <?php if ($writers): ?>
              <p class="kv"><span class="label">Writers:</span> <?= make_links($writers, 'person', ['role'=>'writer']) ?></p>
            <?php endif; ?>
            <?php if ($producers): ?>
              <p class="kv"><span class="label">Producers:</span> <?= make_links($producers, 'person', ['role'=>'producer']) ?></p>
            <?php endif; ?>
            <?php if ($composers): ?>
              <p class="kv"><span class="label">Composers:</span> <?= make_links($composers, 'person', ['role'=>'composer']) ?></p>
            <?php endif; ?>
            <?php if ($cinematographers): ?>
              <p class="kv"><span class="label">Cinematographers:</span> <?= make_links($cinematographers, 'person', ['role'=>'cinematographer']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($cast)): $items=$cast; $first=array_slice($items,0,$CAST_LIMIT); $rest=array_slice($items,$CAST_LIMIT); $ctid='cast-'.$__pa_id; ?>
          <div class="section">
            <p class="kv"><span class="label">שחקנים:</span></p>
            <p class="comma-list" dir="rtl">
              <?= make_links($first, 'cast', ['role'=>'actor']) ?>
              <?php if ($rest): ?>, <span class="ellipsis" id="ell-<?= H($ctid) ?>">…</span><span id="<?= H($ctid) ?>" class="more hidden">, <?= make_links($rest, 'cast', ['role'=>'actor']) ?></span><?php endif; ?>
            </p>
            <?php if ($rest): ?><button class="btn btn-toggle" type="button" data-target="#<?= H($ctid) ?>" data-ell="#ell-<?= H($ctid) ?>">הצג הכל</button><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($akas)): ?>
          <div class="section">
            <p class="kv"><span class="label">AKAs:</span></p>
            <?php $aid='akas-'.(int)$__pa_id; ?>
            <p class="comma-list" dir="rtl"><span class="ellipsis" id="ell-<?= H($aid) ?>">…</span><span id="<?= H($aid) ?>" class="more hidden"><?= safeJoin($akas) ?></span></p>
            <button class="btn btn-toggle" type="button" data-target="#<?= H($aid) ?>" data-ell="#ell-<?= H($aid) ?>">הצג הכל</button>
          </div>
        <?php endif; ?>

        <!-- סרטים דומים -->
        <?php if (!empty($__pa_similar)): ?>
          <hr>
          <h3>🎬 סרטים דומים:</h3>
          <div style="display:flex; flex-wrap:wrap; gap:16px;">
            <?php foreach ($__pa_similar as $sim): $sim_img = (!empty($sim['image_url'])) ? $sim['image_url'] : 'images/no-poster.png'; ?>
              <div style="width:110px; text-align:center;">
                <form method="post">
                  <a href="poster.php?id=<?= (int)$sim['id'] ?>">
                    <img src="<?= H($sim_img) ?>" style="width:100px; border-radius:1px;" loading="lazy" decoding="async"><br>
                    <small><?= H($sim['title_en'] ?: $sim['title_he']) ?></small>
                  </a><br>
                  <button type="submit" name="sim_remove" value="<?= (int)$sim['id'] ?>" class="btn mgmt-only" style="margin-top:6px;">🗑️ הסר</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- טריילר מתחת לסרטים דומים -->
        <div class="section">
          <h3 style="margin:0 0 8px;">טריילר</h3>
          <?php if (!empty($ytId)): ?>
            <div class="trailer-wrap">
              <div class="trailer-embed has-yt" style="width:600px;">
                <iframe src="https://www.youtube.com/embed/<?= H($ytId) ?>" title="Trailer"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  allowfullscreen></iframe>
              </div>
            </div>
          <?php else: ?>
            <div class="no-trailer-box">
              <img src="images/no-trailer.png" alt="No trailer">
            </div>
          <?php endif; ?>
        </div>

      </div><!-- /content -->

    </div>
  </div>
</div>

<script>
  // טוגל יחיד לכל פעולות הניהול + "הצג הכל / הסתר הכל"
  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('#btn-mgmt-toggle');
    if(btn){
      if(document.body.classList.contains('mgmt-hidden')){
        document.body.classList.remove('mgmt-hidden'); 
        document.body.classList.add('mgmt-open');
      } else {
        document.body.classList.remove('mgmt-open'); 
        document.body.classList.add('mgmt-hidden');
      }
      return;
    }

    var t=e.target.closest && e.target.closest('.btn-toggle[data-target]');
    if(t){
      var targetSel = t.getAttribute('data-target');
      var ellSel    = t.getAttribute('data-ell');
      var box = targetSel && document.querySelector(targetSel);

      if (box){
        var wasHidden = box.classList.contains('hidden'); // מצב לפני לחיצה
        box.classList.toggle('hidden');                   // פתח/סגור

        // טיפול באליפסיס (שלוש נקודות)
        if (ellSel) {
          var ell = document.querySelector(ellSel);
          if (ell) {
            ell.classList.toggle('hidden', wasHidden);
 
          }
        }

        // טקסט הכפתור
        if (!wasHidden) {
          t.textContent = 'הצג הכל';
        } else {
          t.textContent = 'הסתר הכל';
        }
      }
      e.preventDefault();
      return;
    }
  });
</script>
<script>
(function(){
  const KEY = 'poster_theme';
  const btn = document.getElementById('btn-theme-toggle');

  // אתחול: קרא מה-localStorage
  const saved = localStorage.getItem(KEY);
  if (saved === 'light') {
    document.body.classList.add('theme-light');
  }

  // עדכון טקסט כפתור לפי מצב
  function refreshLabel(){
    const light = document.body.classList.contains('theme-light');
    if (btn){
      btn.textContent = light ? '🌙 מצב כהה' : '🌞 מצב בהיר';
      btn.setAttribute('aria-pressed', light ? 'true' : 'false');
    }
  }
  refreshLabel();

  // האזנה לכפתור
  if (btn){
    btn.addEventListener('click', () => {
      document.body.classList.toggle('theme-light');
      const light = document.body.classList.contains('theme-light');
      localStorage.setItem(KEY, light ? 'light' : 'dark');
      refreshLabel();
    });
  }
})();
</script>
<script>
(function(){
  // טוגל מצב בלוקים / פסיקים
  const KEY = 'poster_view';
  const btn = document.getElementById('btn-view-toggle');

  const saved = localStorage.getItem(KEY);
  if (saved === 'commas') {
    document.body.classList.add('view-commas');
  }

  function refreshLabel(){
    const commas = document.body.classList.contains('view-commas');
    btn.textContent = commas ? '🔳 מצב בלוקים' : '🔀 מצב פסיקים';
  }
  refreshLabel();

  if (btn){
    btn.addEventListener('click', () => {
      document.body.classList.toggle('view-commas');
      const commas = document.body.classList.contains('view-commas');
      localStorage.setItem(KEY, commas ? 'commas' : 'blocks');
      refreshLabel();
    });
  }
})();
</script>

</body>
</html>
<?php include 'footer.php'; ?>
