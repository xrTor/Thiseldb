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
/* ====== עזרי תצוגה ====== */
function flatten_strings($v){$o=[];$st=[$v];while($st){$c=array_pop($st);if(is_array($c)){foreach($c as $x)$st[]=$x;continue;}if(is_object($c))$c=(string)$c;$t=trim((string)$c);if($t!=='')$o[]=$t;}return $o;}
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
  $stmt = $conn->prepare("SELECT aka_title FROM poster_akas WHERE poster_id = ? ORDER BY sort_order ASC, id ASC");
  $stmt->bind_param("i", $poster_id_for_akas);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) { if (trim($r['aka_title'])) $akas[] = trim($r['aka_title']); }
  $stmt->close();
}

// <<< הוספה: הבאת Connections מה-DB
$connections = [];
$poster_id_for_conn = (int)$posterRow['id'];
if ($poster_id_for_conn > 0) {
    $stmt = $conn->prepare("SELECT relation_label, related_title, related_imdb_id FROM poster_connections WHERE poster_id = ? ORDER BY relation_label, id");
    $stmt->bind_param("i", $poster_id_for_conn);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $connections[$r['relation_label']][] = ['title' => $r['related_title'], 'imdb_id' => $r['related_imdb_id']];
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
          <div class="ratings">
            <?php if ($imdb_rating): ?><span class="pill">IMDb: <?= H($imdb_rating) ?>/10<?= $imdb_votes ? ' • '.number_format((int)$imdb_votes).' votes' : '' ?><img src="images/imdb.png" alt="Metacritic Score" title="Metacritic Score" width="24px"></span><?php endif; ?>
            <?php if ($rt_score): ?><?php if ($rt_url): ?><a class="pill" href="<?= H($rt_url) ?>" target="_blank" rel="noopener">Rotten Tomatoes: <?= H($rt_score) ?>%🍅</a><?php else: ?><span class="pill">Rotten Tomatoes: <?= H($rt_score) ?>%</span><?php endif; ?><?php endif; ?>
            <?php if ($mc_score): ?><?php if ($mc_url): ?><a class="pill" href="<?= H($mc_url) ?>" target="_blank" rel="noopener">Metacritic: <?= H($mc_score) ?>/100 <img src="images/metacritic.png" alt="Metacritic Score" title="Metacritic Score" width="24px"></a><?php else: ?><span class="pill">Metacritic: <?= H($mc_score) ?>/100</span><?php endif; ?><?php endif; ?>
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
            <?php if ($overview_en): ?><p class="kv"><span class="label">תקציר (EN):</span> <?= H($overview_en) ?></p><?php endif; ?>
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
            <p class="comma-list" dir="rtl"><?= safeJoin($akas) ?></p>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($connections)): ?>
          <div class="section">
            <h4 style="margin:0 0 8px 0">IMDb Connections</h4>
            <?php foreach ($connections as $label => $items): ?>
              <p class="kv">
                <span class="label"><?= H($label) ?>:</span>
                <span class="conn-list">
                  <?php $links = []; foreach($items as $item) { $links[] = '<a href="?tt='.H($item['imdb_id']).'">'.H($item['title']).'</a>'; } echo implode(', ', $links); ?>
                </span>
              </p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
  function toggleMore(btn){ var id=btn.getAttribute('data-toggle'),more=document.getElementById(id),ell=document.getElementById('ell-'+id),open=btn.getAttribute('data-open')==='true';if(!more)return;if(open){more.classList.add('hidden');if(ell)ell.classList.remove('hidden');btn.textContent='הצג הכל';btn.setAttribute('data-open','false');}else{more.classList.remove('hidden');if(ell)ell.classList.add('hidden');btn.textContent='הצג פחות';btn.setAttribute('data-open','true');}}
  document.addEventListener('click',function(e){var t=e.target.closest&&e.target.closest('.btn-toggle');if(t)toggleMore(t);});
</script>
</body>
</html>
<?php include 'footer.php'; ?>