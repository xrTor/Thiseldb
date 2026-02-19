<?php
/****************************************************
 * autofetch_network_logos.php
 *
 * ייבוא אוטומטי של לוגואים לרשתות חסרות מה-TMDB
 *
 * מאחד וריאציות של אותה רשת (Disney+, Disney Plus, DisneyPlus)
 * לפי key קנוני שמבוסס רק על a-z, ומוריד לוגו פעם אחת
 * ואז יוצר קובץ PNG לכל basename שחסר.
 *
 * שדרוגים:
 * - עמודה # מימין (מספור)
 * - עמודת לוגו משמאל (תצוגה מקדימה)
 ****************************************************/
mb_internal_encoding('UTF-8');
require_once __DIR__ . '/server.php';
include 'header.php'; 
/* ===== API key ===== */
$TMDB_API_KEY = '';
if (defined('TMDB_API_KEY') && TMDB_API_KEY) {
  $TMDB_API_KEY = TMDB_API_KEY;
} elseif (isset($tmdb_key) && is_string($tmdb_key) && $tmdb_key !== '') {
  $TMDB_API_KEY = $tmdb_key;
}

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && !headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}

if ($TMDB_API_KEY === '') {
  $msg = "TMDB API key is missing. Define TMDB_API_KEY or \$tmdb_key in server.php\n";
  if ($is_cli) { fwrite(STDERR, $msg); exit(1); }
  echo nl2br(h($msg));
  exit(1);
}

/* ===== פרמטרים ===== */
$DRY  = false;
$ONLY = []; // הגבלה לרשתות מסוימות

if ($is_cli) {
  foreach ($argv ?? [] as $a) {
    if ($a === '--dry-run') $DRY = true;
    if (strpos($a,'--only=')===0) {
      $s = trim(substr($a,7));
      if ($s!=='') $ONLY = array_filter(array_map('trim', explode(',', $s)));
    }
  }
} else {
  $DRY = (isset($_GET['dry']) && $_GET['dry']=='1');
  if (!empty($_GET['only'])) {
    $ONLY = array_filter(array_map('trim', explode(',', (string)$_GET['only'])));
  }
}

/* ===== עזר ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * normalization matches poster_networks.php: removes only spaces (Simple Slug)
 * *** FIX: Also removes illegal file system characters (like the colon) ***
 */
function normalize_network_slug_for_simple_check(string $name): string {
  if (!$name) return '';
  
  $slug = mb_strtolower($name, 'UTF-8');
  // 1. הסרת רווחים (הלוגיקה הפשוטה המקורית)
  $slug = preg_replace('/\s+/u', '', $slug);
  // 2. הסרת תווים לא חוקיים במערכות קבצים, כולל הנקודתיים (:)
  $slug = preg_replace('/[\\\\\/:*\?"<>|]/u', '', $slug); 
  
  return $slug ?: '';
}

/**
 * קיים כבר קובץ לוגו עבור שם רשת (שימוש בלוגיקה הפחות מחמירה)
 * זה תואם לוגיקת poster_networks.php (להצגת רשימת החסרים)
 */
function simple_logo_exists(string $network_name): bool {
  $base = normalize_network_slug_for_simple_check($network_name);
  if ($base==='') return false;
  $dir = __DIR__.'/images/networks/';
  foreach(['png','jpg','jpeg','svg','webp'] as $ext){
    if(file_exists($dir.$base.'.'.$ext)) return true;
  }
  return false;
}

/**
 * canon_key: מנרמל שם רשת ל-a-z בלבד.
 * "Disney+" / "Disney Plus" / "DisneyPlus"  -> "disney"
 */
function canon_key(string $name): string {
  $lower = mb_strtolower($name, 'UTF-8');
  return preg_replace('~[^a-z]+~', '', $lower);
}

/**
 * candidate_basenames: (Strict Basenames)
 * יוצר את כל הבסיסים האפשריים לשם קובץ לוגו:
 * 1. אותיות בלבד (a-z)
 * 2. אותיות+ספרות+פלוס ללא רווחים (a-z0-9+)
 */
function candidate_basenames(string $name): array {
  $lower = mb_strtolower($name, 'UTF-8');

  $letters_only     = preg_replace('~[^a-z]+~', '', $lower);        // רק אותיות
  $compact_plusnum  = preg_replace('~[^a-z0-9+]+~', '', $lower);    // אותיות/ספרות/+

  $out = [];
  if ($letters_only !== '')     $out[$letters_only] = true;
  if ($compact_plusnum !== '')  $out[$compact_plusnum] = true;

  return array_keys($out);
}

/**
 * קיים כבר קובץ לוגו עבור basename מסוים?
 */
function logo_file_exists_for_basename(string $basename): bool {
  $dir = __DIR__.'/images/networks/';
  foreach(['png','jpg','jpeg','svg','webp'] as $ext){
    if(file_exists($dir.$basename.'.'.$ext)) return true;
  }
  return false;
}

/**
 * all_missing_basenames_for_network: (NEW COMPREHENSIVE CHECK)
 * איזה basenames חסרים כרגע? (כולל גם את הסלאג הפשוט, להבטחת כיסוי מלא)
 */
function all_missing_basenames_for_network(string $net): array {
  $need = [];
  
  // 1. Strict Basenames (for TMDB fetching logic)
  foreach (candidate_basenames($net) as $bn) {
    if (!logo_file_exists_for_basename($bn)) {
      $need[$bn] = true;
    }
  }

  // 2. Simple Slug (for backwards compatibility/the missing list logic)
  $simple_slug = normalize_network_slug_for_simple_check($net);
  if ($simple_slug !== '' && !logo_file_exists_for_basename($simple_slug)) {
      $need[$simple_slug] = true;
  }
  
  return array_keys($need);
}


/**
 * שומר את הבינארי לכל basename חסר בתור PNG
 */
function save_logo_variants(array $basenames, string $binary, string $images_dir, array &$notes_out){
  ensure_dir($images_dir);
  foreach ($basenames as $bn) {
    $dest = $images_dir.$bn.'.png';
    // בדיקה נוספת לפני השמירה לוודא שה-basename חוקי
    if (strpbrk($bn, '\\/:*?"<>|') !== FALSE) {
        $notes_out[] = "SKIP: $bn (Illegal chars)";
        continue;
    }

    $ok = @file_put_contents($dest, $binary);
    if ($ok === false) {
      $notes_out[] = "FAIL:$bn.png";
    } else {
      $notes_out[] = "$bn.png";
    }
  }
}

/** ודא שהתיקיה קיימת */
function ensure_dir($d){
  if(!is_dir($d)) @mkdir($d, 0775, true);
}

/** בקשת JSON */
function http_json($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>7,
    CURLOPT_TIMEOUT=>15,
    CURLOPT_HTTPHEADER=>['Accept: application/json']
  ]);
  $body=curl_exec($ch); $err=curl_error($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err || $code>=400 || !$body) return [null,"HTTP $code $err"];
  $j=json_decode($body,true);
  if (!is_array($j)) return [null,"Bad JSON"];
  return [$j,null];
}

/**
 * הורדת בינארי תמונה
 * *** תיקון: החזרת $data במקום שגיאה במקרה של הצלחה ***
 */
function http_download_binary($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 7,
    CURLOPT_TIMEOUT        => 30,
  ]);
  $data=curl_exec($ch);
  $err=curl_error($ch);
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($err || $code>=400 || $data===false || $data==='') {
      return [null,"download fail ($code) $err"];
  }
  
  // *** התיקון הקריטי: אם הגענו לכאן, ההורדה הצליחה. החזר את הנתונים. ***
  return [$data, null];
}

/**
 * pick_preview_logo_url:
 * בוחר URL מקומי להצגת לוגו בטבלה, אם כבר יש קובץ לתצוגה.
 * מחפש בכל ה-basename האפשריים ובכל סיומת מוכרת.
 */
function pick_preview_logo_url(string $displayName): string {
  $dirAbs = __DIR__.'/images/networks/';
  $dirRel = 'images/networks/';
  // משתמשים גם בסלאג הפשוט וגם בסלאגים המחמירים
  $all_slugs = array_merge(
      candidate_basenames($displayName),
      [normalize_network_slug_for_simple_check($displayName)]
  );
  
  foreach (array_unique($all_slugs) as $bn) {
    if (empty($bn)) continue;

    foreach (['png','jpg','jpeg','svg','webp'] as $ext) {
      $abs = $dirAbs.$bn.'.'.$ext;
      if (file_exists($abs) && filesize($abs) > 0) {
        return $dirRel.$bn.'.'.$ext;
      }
    }
  }
  return '';
}

/* ===== DB helpers ===== */
function split_networks_field(?string $s): array{
  if(!$s) return [];
  $s=trim($s);
  // JSON style ["Netflix","HBO"]?
  if ($s !== '' && ($s[0]=='[' || $s[0]=='"')) {
    $j=@json_decode($s,true);
    if (is_array($j)) return array_values(array_filter(array_map('trim',$j)));
  }
  // Fallback: פיצול לפי מפרידים
  $parts=preg_split('/[|;,\/]+/u',$s);
  $out=[];
  foreach($parts as $p){
    $p=trim($p," \t\n\r\0\x0B\"'");
    if($p!=='') $out[]=$p;
  }
  return $out;
}

/**
 * בוחר "סדרת עוגן" לפופולריות של רשת
 */
function pick_anchor_series(mysqli $conn, string $networkName): ?array {
  $hasTypes = false;
  try {
    $chk = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='poster_types'");
    if ($chk && $chk->fetch_row()) $hasTypes = true;
    if ($chk) $chk->close();
  } catch (Throwable $e) {}

  $yearExpr = "CAST(SUBSTRING(p.year,1,4) AS UNSIGNED)";

  if ($hasTypes) {
    $sql = "SELECT p.id, p.title_he, p.title_en, p.year, p.imdb_id,
                   COALESCE(p.imdb_votes,0)   AS vv,
                   COALESCE(p.imdb_rating,0)  AS rr
            FROM posters p
            LEFT JOIN poster_types t ON t.id=p.type_id
            WHERE p.networks LIKE ?
              AND (t.code IN ('series','miniseries')
                   OR t.label_en LIKE '%Series%'
                   OR t.label_he LIKE '%סדרה%')
            ORDER BY vv DESC, rr DESC, $yearExpr DESC
            LIMIT 1";
  } else {
    $sql = "SELECT p.id, p.title_he, p.title_en, p.year, p.imdb_id,
                   COALESCE(p.imdb_votes,0)   AS vv,
                   COALESCE(p.imdb_rating,0)  AS rr
            FROM posters p
            WHERE p.networks LIKE ?
            ORDER BY vv DESC, rr DESC, $yearExpr DESC
            LIMIT 1";
  }

  $stmt = $conn->prepare($sql);
  $like = '%'.$networkName.'%';
  $stmt->bind_param('s', $like);
  $stmt->execute();
  $r = $stmt->get_result();
  $row = $r->fetch_assoc() ?: null;
  $stmt->close();
  return $row;
}

/* =========================================================
   שלב 0: אוספים את כל הרשתות מה-DB, מקבצים לפי canon_key,
   וסופרים הופעות.
   ========================================================= */
$sqlAll = "SELECT p.networks FROM posters p WHERE p.networks IS NOT NULL AND p.networks<>''";
$rs = $conn->query($sqlAll);

$rawCounts = []; // שם רשת מקורי => מופעים
while ($row = $rs->fetch_assoc()) {
  foreach (split_networks_field($row['networks']) as $n) {
    $n = trim($n);
    if ($n==='') continue;
    $rawCounts[$n] = ($rawCounts[$n] ?? 0) + 1;
  }
}
$rs->close();

/* בונים קבוצות לפי canon_key */
$groups = []; 
// canon => [
//   'total_cnt' => ...,
//   'names'     => [ ['name'=>..., 'cnt'=>..., 'need'=>[...]] , ... ],
//   'need_set'  => [ basename => true, ... ],
// ]

foreach ($rawCounts as $net=>$cnt) {

  // סינון לפי only
  if ($ONLY && !in_array($net,$ONLY,true)) {
    $found=false;
    foreach ($ONLY as $on) {
      if (canon_key($on) === canon_key($net)) { $found=true; break; }
    }
    if (!$found) continue;
  }

  $ck   = canon_key($net);
  $need = all_missing_basenames_for_network($net); 

  if (!isset($groups[$ck])) {
    $groups[$ck] = [
      'total_cnt' => 0,
      'names'     => [],
      'need_set'  => [],
    ];
  }

  $groups[$ck]['total_cnt'] += $cnt;
  $groups[$ck]['names'][] = [
    'name' => $net,
    'cnt'  => $cnt,
    'need' => $need,
  ];

  foreach ($need as $bn) {
    $groups[$ck]['need_set'][$bn] = true;
  }
}

/* צור targets */
$targets = []; // ['canon','total_cnt','names','need']
foreach ($groups as $canon=>$g) {
  $missing_all = array_keys($g['need_set']);
  if (empty($missing_all)) {
    // כל הווריאציות כבר מכוסות בקבצים — לא צריך למשוך כלום
    continue;
  }

  // מיין וריאציות לפי פופולריות ואז אלפביתית
  usort($g['names'], function($a,$b){
    if ($a['cnt'] === $b['cnt']) {
      return strcasecmp($a['name'],$b['name']);
    }
    return ($a['cnt'] > $b['cnt']) ? -1 : 1;
  });

  $targets[] = [
    'canon'     => $canon,
    'total_cnt' => $g['total_cnt'],
    'names'     => $g['names'],
    'need'      => $missing_all,
  ];
}

/* מיין targets לפי total_cnt יורד */
usort($targets, function($a,$b){
  if ($a['total_cnt'] === $b['total_cnt']) {
    return strcasecmp($a['canon'],$b['canon']);
  }
  return ($a['total_cnt'] > $b['total_cnt']) ? -1 : 1;
});

/* =========================================================
   שלב 1: נסה להוריד לוגו לכל קבוצה ולשמור לכל ה-basename החסר
   ========================================================= */
$base_dir = __DIR__ . '/images/networks/';
ensure_dir($base_dir);

$rows   = [];
$SAVED  = 0;
$SKIPPED= 0;
$FAILED = 0;

foreach ($targets as $group) {
  $namesArr   = $group['names'];
  $needAll    = $group['need']; 
  $total_cnt  = $group['total_cnt'];

  // השם הראשי (זה שיוצג בעמוד ודרכו יהיו קישורים)
  $primaryName = $namesArr[0]['name'];

  $site_url        = 'network.php?name=' . rawurlencode($primaryName);
  $tmdb_search_url = 'https://www.themoviedb.org/search/network?query=' . rawurlencode($primaryName);

  // מוצאים סדרת עוגן לפי הווריאציות
  $anchor = null;
  foreach ($namesArr as $nmInfo) {
    $tryName = $nmInfo['name'];
    $a = pick_anchor_series($conn, $tryName);
    if ($a && (!empty($a['imdb_id']) || !empty($a['title_en']) || !empty($a['title_he']))) {
      $anchor = $a;
      break;
    }
  }

  if (!$anchor) {
    $rows[] = [
      'display_name'      => $primaryName,
      'cnt'               => $total_cnt,
      'site_url'          => $site_url,
      'tmdb_url'          => $tmdb_search_url,
      'status'            => 'SKIP',
      'note'              => 'לא נמצאה סדרת עוגן לקבוצה',
      'saved_variants'    => [],
      'missing_variants'  => $needAll,
    ];
    $SKIPPED++;
    continue;
  }

  // משיגים tv_id מה-TMDB
  $imdb   = $anchor['imdb_id'] ?? '';
  $title  = $anchor['title_en'] ?: ($anchor['title_he'] ?? '');

  $tv_id = 0;
  if ($imdb) {
    $url = 'https://api.themoviedb.org/3/find/'.rawurlencode($imdb)
         .'?external_source=imdb_id&api_key='.rawurlencode($TMDB_API_KEY);
    list($j,$e) = http_json($url);
    if ($j && !empty($j['tv_results'][0]['id'])) {
      $tv_id = (int)$j['tv_results'][0]['id'];
    }
  }
  if (!$tv_id && $title) {
    $url = 'https://api.themoviedb.org/3/search/tv?query='.rawurlencode($title)
         .'&api_key='.rawurlencode($TMDB_API_KEY);
    list($j,$e) = http_json($url);
    if ($j && !empty($j['results'][0]['id'])) {
      $tv_id = (int)$j['results'][0]['id'];
    }
  }
  if (!$tv_id) {
    $rows[] = [
      'display_name'      => $primaryName,
      'cnt'               => $total_cnt,
      'site_url'          => $site_url,
      'tmdb_url'          => $tmdb_search_url,
      'status'            => 'SKIP',
      'note'              => 'לא נמצא tv_id (אין TMDB match)',
      'saved_variants'    => [],
      'missing_variants'  => $needAll,
    ];
    $SKIPPED++;
    continue;
  }

  // עכשיו מביאים פרטי הסדרה כדי להגיע לרשימת הרשתות שלה
  $urlTv = 'https://api.themoviedb.org/3/tv/'.$tv_id
         .'?api_key='.rawurlencode($TMDB_API_KEY);
  list($tv,$e2)=http_json($urlTv);
  if (!$tv || empty($tv['networks'])) {
    $rows[] = [
      'display_name'      => $primaryName,
      'cnt'               => $total_cnt,
      'site_url'          => $site_url,
      'tmdb_url'          => $tmdb_search_url,
      'status'            => 'SKIP',
      'note'              => 'אין networks על ה-TV',
      'saved_variants'    => [],
      'missing_variants'  => $needAll,
    ];
    $SKIPPED++;
    continue;
  }

  // התאמה של שם הרשת לפי canon_key
  $wantCanon = canon_key($primaryName);
  $picked = null;
  foreach ($tv['networks'] as $nw) {
    $nm = is_array($nw) ? ($nw['name'] ?? '') : '';
    if (canon_key($nm) === $wantCanon) {
      $picked = $nw;
      break;
    }
  }
  if (!$picked) {
    $picked = $tv['networks'][0];
  }

  $network_id = (int)($picked['id'] ?? 0);
  $logo_path  = $picked['logo_path'] ?? null;

  // אם אין logo_path ישיר - ננסה /network/{id}/images
  if (!$logo_path && $network_id) {
    $urlN = 'https://api.themoviedb.org/3/network/'.$network_id.'/images'
          .'?api_key='.rawurlencode($TMDB_API_KEY);
    list($imgs,$e3)=http_json($urlN);
    if ($imgs && !empty($imgs['logos'])) {
      usort($imgs['logos'], function($a,$b){
        // עדיפות לשפה null/en ואז ללוגו רחב
        $sa = ($a['iso_639_1']===null || $a['iso_639_1']==='en') ? 1 : 0;
        $sb = ($b['iso_639_1']===null || $b['iso_639_1']==='en') ? 1 : 0;
        if ($sa !== $sb) return ($sa<$sb)?1:-1;
        return ($a['width'] <=> $b['width']) * -1;
      });
      $logo_path = $imgs['logos'][0]['file_path'] ?? null;
    }
  }

  if (!$logo_path) {
    $rows[] = [
      'display_name'      => $primaryName,
      'cnt'               => $total_cnt,
      'site_url'          => $site_url,
      'tmdb_url'          => $tmdb_search_url,
      'status'            => 'SKIP',
      'note'              => 'לא נמצא logo_path',
      'saved_variants'    => [],
      'missing_variants'  => $needAll,
    ];
    $SKIPPED++;
    continue;
  }

  $srcUrl = 'https://image.tmdb.org/t/p/original' . $logo_path;

  if ($DRY) {
    $rows[] = [
      'display_name'      => $primaryName,
      'cnt'               => $total_cnt,
      'site_url'          => $site_url,
      'tmdb_url'          => $tmdb_search_url,
      'status'            => 'DRY',
      'note'              => "would save ".implode(', ',$needAll)." from $srcUrl",
      'saved_variants'    => $needAll,
      'missing_variants'  => [],
    ];
    continue;
  }

  // נוריד את הבינארי
  list($binary,$errBin) = http_download_binary($srcUrl);
  if (!$binary) {
    $rows[] = [
      'display_name'      => $primaryName,
      'cnt'               => $total_cnt,
      'site_url'          => $site_url,
      'tmdb_url'          => $tmdb_search_url,
      'status'            => 'FAIL',
      'note'              => "download failed: $errBin",
      'saved_variants'    => [],
      'missing_variants'  => $needAll,
    ];
    $FAILED++;
    continue;
  }

  // שמירה לכל ה-basename החסר
  $savedNames = [];
  save_logo_variants($needAll, $binary, $base_dir, $savedNames);

  $rows[] = [
    'display_name'      => $primaryName,
    'cnt'               => $total_cnt,
    'site_url'          => $site_url,
    'tmdb_url'          => $tmdb_search_url,
    'status'            => 'OK',
    'note'              => implode(', ',$savedNames),
    'saved_variants'    => $needAll,
    'missing_variants'  => [],
  ];
  $SAVED++;

  // מנוחה בין קריאות
  usleep(250000); // 0.25 שניות
}

/* ===== פלט CLI ===== */
if ($is_cli) {
  $i = 0;
  foreach ($rows as $r) {
    $i++;
    echo "#$i\t{$r['status']}\t{$r['display_name']}\t({$r['cnt']})\t{$r['note']}\t{$r['site_url']}\t{$r['tmdb_url']}\n";
  }
  echo "\nSummary: OK={$SAVED}, SKIP={$SKIPPED}, FAIL={$FAILED}\n";
  exit(0);
}

// =========================================================
// שלב 5: מכינים את רשימת הרשתות החסרות להצגה (כמו ב-poster_networks.php)
// הקוד הזה רץ רק במצב WEB
// =========================================================
$networks_without_logo = [];
foreach (array_keys($rawCounts) as $net_name) {
    // בודק קיום לוגו לפי הלוגיקה הפשוטה (שם ללא רווחים ועם הסרת תווים לא חוקיים)
    if (!simple_logo_exists($net_name)) {
        $networks_without_logo[] = $net_name;
    }
}
// מוודאים ששמות הרשתות ייחודיים וממוינים
$networks_without_logo = array_keys(array_flip($networks_without_logo));
sort($networks_without_logo, SORT_NATURAL | SORT_FLAG_CASE);

?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>Autofetch Network Logos (TMDB)</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;direction:rtl;margin:20px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #eee;padding:8px;vertical-align:top}
    th{background:white;text-align:right}
    td a{color:white;text-decoration:none}
    td a:hover{text-decoration:underline}
    .s-OK{color:#0a0}
    .s-FAIL{color:#a00}
    .s-SKIP{color:#777}
    .s-DRY{color:#444}
    .hint{color:#555;margin-bottom:12px;line-height:1.5}
    .badge{display:inline-block;padding:2px 6px;border:1px solid #ddd;border-radius:999px;font-size:12px}
    code{background:#f6f6f6;border:1px solid #eee;border-radius:4px;padding:0 4px;font-size:12px}
    ul{margin:4px 0 0 0;padding-right:20px}
    li{font-size:12px;color:#444;line-height:1.4}
    th.idx,td.idx{width:1%;white-space:nowrap;text-align:center;color:#666}
    .logoCell{text-align:center;min-width:140px}
    .logoCell img{
      max-height:40px;
      max-width:120px;
      object-fit:contain;
      background:#fff;
      border:1px solid #eee;
      border-radius:6px;
      padding:4px;
    }
    /* --- סגנונות רשימת רשתות חסרות (הועתק מ-poster_networks.php) --- */
    .no-logo-list {
         margin-top: 40px;
         padding-top: 20px;
         border-top: 1px solid #eee;
    }
    .no-logo-list h2 {
         text-align: center;
         color: #555;
         margin-bottom: 15px;
    }
     .no-logo-list ul {
         list-style: none;
         padding: 0;
         columns: 4; /* מספר טורים */
         column-gap: 20px;
         max-width: 1000px;
         margin: 0 auto;
     }
      .no-logo-list li a {
          display: block;
          padding: 5px 0;
          text-decoration: none;
          color: #007bff;
          font-size: 14px;
      }
      .no-logo-list li a:hover {
          text-decoration: underline;
      }
       @media (max-width: 768px) { .no-logo-list ul { columns: 2; } }
    /* --- סוף סגנונות --- */

    table {background: white}
    body table a, .no-logo-list a {color:deepskyblue !important}
    h1 {color:green;}
  </style>
</head>
<body><br>
  <h1>Autofetch Network Logos</h1>
  <div class="hint"><br>
    מצב:
    <?= $DRY ? 'הרצה יבשה (לא שומר קבצים)' : 'שומר קבצים' ?><br>
    כל שורה = קבוצה של כל הווריאציות לאותה רשת (למשל "Disney+", "Disney Plus", "DisneyPlus").<br>
    אם מחקת ידנית קובץ כמו <code>disneyplus.png</code> — הכלי ישחזר אותו.
  </div>

  <table>
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>רשת (שם מייצג)</th>
        <th>כמות במסד (כל הווריאציות)</th>
        <th>סטטוס</th>
        <th>נשמרו עכשיו</th>
        <th>וריאציות שחיכו ללוגו</th>
        <th>עמוד רשת</th>
        <th>TMDB רשת</th>
        <th>לוגו</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=0; foreach ($rows as $r): $i++; ?>
        <?php
          $preview = pick_preview_logo_url($r['display_name']);
        ?>
        <tr>
          <td class="idx"><?= $i ?></td>
          <td><?= h($r['display_name']) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
          <td class="s-<?= h($r['status']) ?>"><?= h($r['status']) ?></td>
          <td><?= h($r['note']) ?></td>
          <td>
            <?php if (!empty($r['saved_variants'])): ?>
              <div><b>נשמרו/יסתדרו עכשיו:</b></div>
              <ul>
              <?php foreach ($r['saved_variants'] as $bn): ?>
                <li><?= h($bn) ?>.png</li>
              <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if (!empty($r['missing_variants'])): ?>
              <div><b>עדיין חסר:</b></div>
              <ul>
              <?php foreach ($r['missing_variants'] as $bn): ?>
                <li><?= h($bn) ?>.png</li>
              <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
          <td><a href="<?= h($r['site_url']) ?>" target="_blank">פתח</a></td>
          <td><a href="<?= h($r['tmdb_url']) ?>" target="_blank">TMDB</a></td>
          <td class="logoCell">
            <?php if ($preview): ?>
              <img src="<?= h($preview) ?>" alt="<?= h($r['display_name']) ?>">
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p>
    סיכום:
    <b class="s-OK">OK=<?= (int)$SAVED ?></b>,
    <span class="s-SKIP">SKIP=<?= (int)$SKIPPED ?></span>,
    <span class="s-FAIL">FAIL=<?= (int)$FAILED ?></span>
  </p>

  <p class="hint">
    פרמטרים:
    <code>?dry=1</code> להרצה יבשה בלבד,
    <code>?only=ITV1,Disney%2B</code> כדי לטפל בקבוצות מסוימות.
  </p>

  <?php
  // הצגת רשימת הרשתות החסרות, בדומה ל-poster_networks.php
  if (!empty($networks_without_logo)): ?>
    <div class="no-logo-list">
        <h2>רשתות נוספות (ללא לוגו)</h2>
        <p class="hint">
            זוהי רשימה של רשתות שאינן מופיעות בטבלה למעלה, ועדיין אין להן לוגו בתיקיית
            <code>images/networks</code>
            (לפי הכלל הפשוט: שם רשת ללא רווחים ועם הסרת תווים לא חוקיים).
        </p>
        <ul>
            <?php foreach ($networks_without_logo as $name): ?>
                <li>
                    <a href="network.php?name=<?= urlencode($name) ?>"><?= h($name) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
  <?php endif; ?>

</body>
<?php include 'footer.php'; ?>
</html>