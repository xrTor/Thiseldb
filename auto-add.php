<?php
/****************************************************
 * auto-add.php — ייבוא אוטומטי + AKAs + IMDb Connections (מאוחד)
 * BUILD v21:
 * - H() יחידה לאסקפינג (אין h() נוספת)
 * - Posters upsert (אם יש build_row/unify_details)
 * - שמירת AKAs
 * - שמירת Connections עם dedupe + INSERT IGNORE
 * - שליפה עם NEXT / DOM / REGEX + תמיכת gzip
 * - דו"ח דיבאג כולל מקור connections (next/dom/regex/none)
 ****************************************************/
set_time_limit(3000000);
mb_internal_encoding('UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

include __DIR__ . '/header.php';
require_once __DIR__ . '/server.php';

if (!isset($conn) || !($conn instanceof mysqli)) { die('DB connection failed'); }
$conn->set_charset('utf8mb4');

/* טען imdb.php אם קיים (לקבלת build_row/unify_details ומפתחות, מבלי לפלוט HTML) */
ob_start();
if (file_exists(__DIR__ . '/imdb.php')) {
    require_once __DIR__ . '/imdb.php';
}
ob_end_clean();

/* ========= עזר כללי ========= */
function H($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function to_csv_val($arr){
    if(!$arr) return null;
    $vals=[];
    foreach((array)$arr as $x){
        $t=trim((string)$x);
        if($t!=='') $vals[]=$t;
    }
    $vals=array_values(array_unique($vals));
    return $vals?implode(', ',$vals):null;
}
function parse_runtime_to_minutes($str) {
    if (!$str) return null;
    $h = 0; $m = 0;
    if (preg_match('/(\d+)\s*(?:h|hr|hour)/i', $str, $h_match)) { $h = (int)$h_match[1]; }
    if (preg_match('/(\d+)\s*(?:m|min|minute)/i', $str, $m_match)) { $m = (int)$m_match[1]; }
    if ($h > 0 || $m > 0) return ($h * 60) + $m;
    $numeric_val = (int)preg_replace('/\D/', '', $str);
    return $numeric_val > 0 ? $numeric_val : null;
}

/* ========= מיפוי ל-posters ========= */
function map_u_to_posters_fields(array $u, array $raw_row): array {
  $m = [];
  $add = function($col,$val) use (&$m){ $m[$col]=$val; };

  $add('imdb_id', $u['imdb_id'] ?? null);
  $add('title_en', $u['display_title'] ?? null);
  $add('original_title', $raw_row['original_title'] ?? null);
  $add('title_he', $u['he_title'] ?? null);
  $add('year', $u['year'] ?? null);

  $is_tv = !empty($u['is_tv']);
  $add('is_tv', $is_tv ? 1 : 0);
  $add('type_id', $is_tv ? 4 : 3);

  $add('poster_url', $u['poster'] ?? null);
  $add('image_url',  $u['poster'] ?? null);
  $add('poster',     $u['poster'] ?? null);
  $add('trailer_url', $u['trailer'] ?? null);
  $add('youtube_trailer', $u['trailer'] ?? null);

  $add('tvdb_url', $u['tvdb_url'] ?? null);
  $add('tmdb_url', $u['tmdb_url'] ?? null);
  $add('overview_he', $u['overview_he'] ?? null);
  $add('overview_en', $u['overview_en'] ?? null);

  $add('genres',     to_csv_val($u['genres'] ?? null));
  $add('languages',  to_csv_val($u['languages'] ?? null));
  $add('countries',  to_csv_val($u['countries'] ?? null));

  $runtime_minutes = parse_runtime_to_minutes($u['runtime'] ?? null);
  $add('runtime', $runtime_minutes);
  $add('runtime_minutes', $runtime_minutes);

  $add('directors',        to_csv_val($u['directors'] ?? null));
  $add('writers',          to_csv_val($u['writers'] ?? null));
  $add('producers',        to_csv_val($u['producers'] ?? null));
  $add('composers',        to_csv_val($u['composers'] ?? null));
  $add('cinematographers', to_csv_val($u['cinematographers'] ?? null));
  $add('cast',             to_csv_val($u['cast'] ?? null));
  $add('networks',         to_csv_val($u['networks'] ?? null));

  $add('imdb_rating', isset($u['imdb_rating']) ? (string)$u['imdb_rating'] : null);
  $add('imdb_votes',  isset($u['imdb_votes'])  ? (string)$u['imdb_votes']  : null);

  $add('rt_score', isset($u['rt_score']) ? (string)$u['rt_score'] : null);
  $add('rt_url',   $u['rt_url'] ?? null);
  $add('mc_score', isset($u['mc_score']) ? (string)$u['mc_score'] : null);
  $add('mc_url',   $u['mc_url'] ?? null);

  $add('seasons_count',  $u['seasons'] ?? null);
  $add('episodes_count', $u['episodes'] ?? null);

  return $m;
}

/* ========= עזר DB ========= */
function db_get_columns(mysqli $conn, $table){
  $cols=[]; $res=$conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($table)."`");
  if($res) while($r=$res->fetch_assoc()){ $cols[]=$r['Field']; }
  return $cols;
}
function db_find_existing_row(mysqli $conn, $table, $pkField, $pkValue){
  $stmt=$conn->prepare("SELECT * FROM `{$table}` WHERE `{$pkField}` = ? LIMIT 1");
  if(!$stmt) return null;
  $stmt->bind_param('s',$pkValue); $stmt->execute();
  $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
  return $row?:null;
}
function upsert_row(mysqli $conn, string $table, array $data, string $pkField, string $dup_mode = 'skip'): array {
  if (empty($data[$pkField])) return ['ok'=>false,'error'=>'Empty primary key','id'=>0,'action'=>'error'];
  $pkValue = $data[$pkField];

  $existing_row = db_find_existing_row($conn, $table, $pkField, $pkValue);
  $table_cols   = db_get_columns($conn, $table);
  $data         = array_filter($data, fn($k)=>in_array($k, $table_cols, true), ARRAY_FILTER_USE_KEY);

  if ($existing_row) {
    $poster_id = (int)$existing_row['id'];
    if ($dup_mode === 'skip') return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'skipped'];
    if ($dup_mode === 'update-missing') {
      foreach ($existing_row as $k=>$v) {
        if (!empty($v) && $v !== '0' && isset($data[$k])) unset($data[$k]);
      }
    }
    if (empty($data) || count($data) <= 1) return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'skipped (nothing to update)'];
    unset($data[$pkField]); $set=[]; $types=''; $vals=[];
    foreach($data as $k=>$v){ $set[]="`{$k}`=?"; $types.='s'; $vals[]=$v; }
    $vals[]=$pkValue; $types.='s';
    $sql="UPDATE `{$table}` SET ".implode(', ',$set)." WHERE `{$pkField}` = ?";
    $st=$conn->prepare($sql); if(!$st) return ['ok'=>false,'error'=>'Prepare failed: '.$conn->error,'id'=>$poster_id,'action'=>'error'];
    $st->bind_param($types, ...$vals);
    if($st->execute()){ $st->close(); return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'updated']; }
    $err=$st->error; $st->close(); return ['ok'=>false,'error'=>'Update failed: '.$err,'id'=>$poster_id,'action'=>'error'];
  } else {
    $cols=array_keys($data); $ph=implode(',', array_fill(0,count($cols),'?'));
    $sql="INSERT INTO `{$table}` (`".implode('`,`',$cols)."`) VALUES ({$ph})";
    $st=$conn->prepare($sql); if(!$st) return ['ok'=>false,'error'=>'Prepare failed: '.$conn->error,'id'=>0,'action'=>'error'];
    $types=str_repeat('s', count($cols)); $st->bind_param($types, ...array_values($data));
    if($st->execute()){ $id=$conn->insert_id; $st->close(); return ['ok'=>true,'error'=>null,'id'=>$id,'action'=>'inserted']; }
    $err=$st->error; $st->close(); return ['ok'=>false,'error'=>'Insert failed: '.$err,'id'=>0,'action'=>'error'];
  }
}

/* ========= AKAs ========= */
function replace_akas(mysqli $db, int $poster_id, string $imdb_id, array $akas): int {
  if ($poster_id <= 0) return 0;
  $list = array_values(array_unique(array_filter(array_map('trim', (array)$akas))));
  if ($stmt_delete = $db->prepare("DELETE FROM poster_akas WHERE poster_id = ?")) {
      $stmt_delete->bind_param("i", $poster_id);
      $stmt_delete->execute();
      $stmt_delete->close();
  }
  if (empty($list)) return 0;

  $sql = "INSERT IGNORE INTO poster_akas (poster_id, aka_title, aka_lang, source, aka, sort_order, imdb_id)
          VALUES (?, ?, ?, ?, ?, ?, ?)";
  $stmt = $db->prepare($sql);
  if (!$stmt) return 0;

  $saved = 0; $i = 0;
  foreach ($list as $aka_title) {
      $i++; $aka_lang = null; $source = 'imdb'; $aka = $aka_title;
      $stmt->bind_param("issssis", $poster_id, $aka_title, $aka_lang, $source, $aka, $i, $imdb_id);
      if ($stmt->execute()) $saved += $stmt->affected_rows;
  }
  $stmt->close();
  return $saved;
}

/* ========= מחולל Connections — שליפה ========= */
function http_get_simple(string $url, int $timeout=20): ?string {
  if (!function_exists('curl_init')) return null;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_ENCODING => '', // gzip/deflate אוטומטי
    CURLOPT_HTTPHEADER => [
      'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Language: en-US,en;q=0.9,he;q=0.6',
      'Referer: https://www.imdb.com/title/',
      'Cache-Control: no-cache',
      'Pragma: no-cache',
    ],
  ]);
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($body === false || $code >= 400) return null;
  return $body;
}
function expand_imdb_href(string $href): string {
  $href = trim($href);
  if ($href === '') return '';
  if (str_starts_with($href,'https://') || str_starts_with($href,'http://')) return $href;
  if (str_starts_with($href,'//')) return 'https:'.$href;
  if ($href[0] !== '/') $href = '/'.ltrim($href,'/');
  return 'https://www.imdb.com'.$href;
}
function dedupe_items(array $items): array {
  $seen = []; $out = [];
  foreach ($items as $it) {
    $k = ($it['id'] ?? '').'|'.($it['url'] ?? '');
    if ($k === '|') continue;
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $out[] = $it;
  }
  return $out;
}
function parse_next_data_json(string $html): ?array {
  if (preg_match('#<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>#si', $html, $m)) {
    $json = html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    try { $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { $data = json_decode($json, true); }
    return is_array($data) ? $data : null;
  }
  return null;
}
function imdb_connections_url(string $id): string {
  return "https://www.imdb.com/title/{$id}/movieconnections/";
}
function normalize_conn_label(string $s): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  $s = preg_replace('/\s*\(.*$/', '', $s); // drop "(Spoofs)" וכו'
  $map = [
    'Follows'=>'Follows','Followed by'=>'Followed by','References'=>'References','Referenced in'=>'Referenced in',
    'Features'=>'Features','Featured in'=>'Featured in','Edited from'=>'Edited from','Edited into'=>'Edited into',
    'Remake of'=>'Remake of','Remade as'=>'Remade as','Spin-off'=>'Spin-off','Spin-off from'=>'Spin-off from',
    'Version of'=>'Version of','Spoofs'=>'Spoofs','Spoofed in'=>'Spoofed in','Alternate versions'=>'Alternate versions',
  ];
  return $map[$s] ?? $s;
}
function extract_connections_from_next(array $data, array $keep): array {
  $out = []; foreach($keep as $k) $out[$k]=[];
  $keepSet = array_flip($keep);
  $asLabel = function($s) use ($keepSet) {
    if (!is_string($s) || $s==='') return null;
    $lab = normalize_conn_label($s);
    return isset($keepSet[$lab]) ? $lab : null;
  };
  $pushItem = function($cat, $node) use (&$out) {
    if (!$cat || !is_array($node)) return;
    $id=null; $title=null; $url=null;
    foreach (['id','const'] as $k) {
      if (!$id && !empty($node[$k]) && preg_match('/^tt\d+$/', (string)$node[$k])) $id = $node[$k];
    }
    foreach (['link','canonicalLink','url'] as $k) {
      if (!$id && !empty($node[$k]) && preg_match('#/title/(tt\d+)/#', (string)$node[$k], $m)) $id = $m[1];
    }
    if (isset($node['titleText'])) {
      $title = is_array($node['titleText']) ? ($node['titleText']['text'] ?? null) : (is_string($node['titleText'])?$node['titleText']:null);
    }
    if (!$title && isset($node['originalTitleText']) && is_array($node['originalTitleText'])) {
      $title = $node['originalTitleText']['text'] ?? null;
    }
    if (!$title && isset($node['displayableTitle'])) $title = is_string($node['displayableTitle']) ? $node['displayableTitle'] : null;
    if (!$title && isset($node['title'])) $title = is_string($node['title']) ? $node['title'] : null;
    foreach (['link','canonicalLink','url'] as $k) { if (!$url && !empty($node[$k])) $url = expand_imdb_href((string)$node[$k]); }
    if (!$url && $id) $url = "https://www.imdb.com/title/{$id}/";
    if ($id) $out[$cat][] = ['id'=>$id,'title'=>$title ?: $id,'url'=>$url];
  };
  $walk = function($node, $cur=null) use (&$walk, $asLabel, $pushItem) {
    if (is_array($node)) {
      foreach (['category','header','sectionTitle','title'] as $k) {
        if (isset($node[$k])) {
          $lbl = $asLabel(is_array($node[$k]) ? ($node[$k]['text'] ?? '') : $node[$k]);
          if ($lbl) { $cur = $lbl; break; }
        }
      }
      if ($cur && (
        isset($node['titleText']) || isset($node['originalTitleText']) || isset($node['displayableTitle']) ||
        (isset($node['id']) && preg_match('/^tt\d+$/', (string)$node['id'])) ||
        (isset($node['const']) && preg_match('/^tt\d+$/', (string)$node['const'])) ||
        (isset($node['link']) && preg_match('#/title/tt\d+/#', (string)$node['link'])) ||
        (isset($node['canonicalLink']) && preg_match('#/title/tt\d+/#', (string)$node['canonicalLink']))
      )) { $pushItem($cur, $node); }
      foreach ($node as $v) $walk($v, $cur);
    }
  };
  $walk($data, null);
  foreach ($out as $k=>$arr) $out[$k]=dedupe_items($arr);
  return $out;
}
function imdb_connections_all_fetch(string $imdbId, array $keep): array {
  $out = []; foreach ($keep as $k) $out[$k]=[];
  if (!preg_match('/^tt\d{6,10}$/', $imdbId)) { $out['_source']='none'; return $out; }

  $tryUrls = [
    imdb_connections_url($imdbId),
    imdb_connections_url($imdbId).'?ref_=tt_trv_cnn',
  ];

  foreach ($tryUrls as $url) {
    $html = http_get_simple($url);
    if (!$html) continue;

    // 1) NEXT JSON
    $next = parse_next_data_json($html);
    if ($next) {
      $ex = extract_connections_from_next($next, $keep);
      $has=false; foreach($ex as $arr){ if (!empty($arr)) { $has=true; break; } }
      if ($has) { $ex['_source']='next'; return $ex; }
    }

    // 2) DOM fallback (php-xml)
    if (class_exists('DOMDocument')) {
      libxml_use_internal_errors(true);
      $dom = new DOMDocument(); $dom->loadHTML($html); libxml_clear_errors();
      $xp = new DOMXPath($dom);
      $domOut=[]; foreach($keep as $k) $domOut[$k]=[];

      // חיפוש כותרות אפשריות
      $headers = $xp->query("//*[self::h2 or self::h3 or self::h4 or contains(@class,'ipc-title__text')]");
      foreach ($headers as $hnode) {
        $label = normalize_conn_label(trim(preg_replace('/\s+/', ' ', $hnode->textContent ?? '')));
        if (!in_array($label, $keep, true)) continue;

        $items=[];
        for ($n=$hnode->parentNode ? $hnode->parentNode->nextSibling : $hnode->nextSibling; $n; $n=$n->nextSibling) {
          if ($n->nodeType===XML_ELEMENT_NODE) {
            $nm=strtolower($n->nodeName);
            if (in_array($nm, ['h2','h3','h4'])) break;
            foreach ($xp->query(".//a[contains(@href,'/title/tt')]", $n) as $a) {
              $href=(string)$a->getAttribute('href');
              if (preg_match('#/title/(tt\d+)/#',$href,$m)) {
                $tid=$m[1]; $title=trim(preg_replace('/\s+/', ' ', $a->textContent ?? ''));
                if ($tid) $items[]=['id'=>$tid,'title'=>$title ?: $tid,'url'=>expand_imdb_href($href)];
              }
            }
          }
        }
        if ($items) $domOut[$label]=dedupe_items($items);
      }

      $has=false; foreach($domOut as $arr){ if (!empty($arr)) { $has=true; break; } }
      if ($has) { $domOut['_source']='dom'; return $domOut; }
    }

    // 3) REGEX fallback — חיפוש גורף
    $reOut=[]; foreach($keep as $k) $reOut[$k]=[];
    if (preg_match_all('#<section[^>]*>(.*?)</section>#si', $html, $secs)) {
      foreach ($secs[1] as $sec) {
        $label=null;
        if (preg_match('#<h[2-5][^>]*>(.*?)</h[2-5]>#si', $sec, $mh)) {
          $label = normalize_conn_label(strip_tags($mh[1]));
        } elseif (preg_match('#class="[^"]*ipc-title__text[^"]*"[^>]*>(.*?)</#si', $sec, $mh2)) {
          $label = normalize_conn_label(strip_tags($mh2[1]));
        }
        if (!$label || !in_array($label,$keep,true)) continue;

        if (preg_match_all('#/title/(tt\d+)/[^"]*"#i', $sec, $links)) {
          $arr=[];
          foreach ($links[1] as $tid) $arr[]=['id'=>$tid,'title'=>$tid,'url'=>"https://www.imdb.com/title/{$tid}/"];
          if ($arr) $reOut[$label]=dedupe_items($arr);
        }
      }
    } else {
      if (preg_match_all('#/title/(tt\d+)/[^"]*"#i', $html, $links)) {
        $fallback = in_array('Referenced in',$keep,true) ? 'Referenced in' : $keep[0];
        $arr=[]; foreach ($links[1] as $tid) $arr[]=['id'=>$tid,'title'=>$tid,'url'=>"https://www.imdb.com/title/{$tid}/"];
        if ($arr) $reOut[$fallback]=dedupe_items($arr);
      }
    }
    $has=false; foreach($reOut as $arr){ if (!empty($arr)) { $has=true; break; } }
    if ($has) { $reOut['_source']='regex'; return $reOut; }
  }

  $out['_source']='none';
  return $out;
}
function imdb_connections_all(string $tt): array {
  $KEEP = ['Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of','Alternate versions'];
  return imdb_connections_all_fetch($tt, $KEEP);
}

/* ========= שמירת Connections לטבלה ========= */
function sync_connections(mysqli $db, int $poster_id, string $source_imdb_id, array $connections_map): int {
  if ($poster_id <= 0) return 0;

  // flatten + dedupe לפי label|tt כדי למנוע כפילויות באותה ריצה
  $flat=[]; $seen=[];
  foreach ((array)$connections_map as $label => $items) {
    if (!is_array($items) || empty($items)) continue;
    foreach ($items as $it) {
      $tt = trim((string)($it['id'] ?? ''));
      $title = trim((string)($it['title'] ?? ''));
      if ($tt==='' || $title==='') continue;
      $k = $label.'|'.$tt;
      if (isset($seen[$k])) continue;
      $seen[$k]=true;
      $flat[]=['label'=>$label,'tt'=>$tt,'title'=>$title];
    }
  }

  $db->begin_transaction();
  try {
    // נקה connections קיימים לפוסטר הזה (כמו בעמוד שעובד לך)
    if ($del = $db->prepare("DELETE FROM poster_connections WHERE poster_id = ?")) {
      $del->bind_param("i", $poster_id);
      $del->execute();
      $del->close();
    }
    if (empty($flat)) { $db->commit(); return 0; }

    // IGNORE כדי לא להתרסק על uniq_conn אם עדיין יש כפילויות "לוגיות"
    $sql = "INSERT IGNORE INTO poster_connections
            (poster_id, relation_label, conn_title, related_imdb_id, conn_imdb_id,
             related_title, relation_type, imdb_id, source, kind, target_tt, target_title)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    if (!$st) throw new Exception("Connections SQL Prepare Failed: ".$db->error);

    $saved=0;
    foreach ($flat as $row) {
      $label = (string)$row['label']; $tt=(string)$row['tt']; $title=(string)$row['title'];
      $conn_title=$title; $related_imdb=$tt; $conn_imdb_id=$tt; $related_title=$title;
      $relation_type=$label; $source='imdb'; $kind=$label; $target_tt=$tt; $target_title=$title;

      $st->bind_param(
        "isssssssssss",
        $poster_id, $label, $conn_title, $related_imdb, $conn_imdb_id,
        $related_title, $relation_type, $source_imdb_id, $source, $kind, $target_tt, $target_title
      );
      if (!$st->execute()) {
        $dump = json_encode($row, JSON_UNESCAPED_UNICODE);
        throw new Exception("Connections Execute Failed: ".$st->error." | Data: ".$dump);
      }
      if ($st->affected_rows > 0) $saved++;
    }
    $st->close(); $db->commit();
    return $saved;
  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }
}

/* ========= פירוק קלט מזהי tt ========= */
function parse_ids($raw_input): array {
  $out=[]; $items=preg_split('~[\s,;]+~',(string)$raw_input,-1,PREG_SPLIT_NO_EMPTY);
  foreach ($items as $item) if (preg_match('~(tt\d{6,10})~',$item,$m)) $out[]=$m[1];
  return array_values(array_unique($out));
}

/* ========= POST ========= */
$done=false; $results=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $raw_input = $_POST['ids'] ?? '';
  if (isset($_FILES['id_file']) && !empty($_FILES['id_file']['tmp_name']) && $_FILES['id_file']['error']===UPLOAD_ERR_OK) {
    $fileText = file_get_contents($_FILES['id_file']['tmp_name']);
    if ($fileText !== false && $fileText !== '') $raw_input = $fileText;
  }
  $dup_mode = $_POST['dup_mode'] ?? 'skip';
  $ids = parse_ids($raw_input);

  if(!$ids){
    $results[]=['tt'=>'N/A','ok'=>false,'error'=>'לא נמצאו מזהי IMDb תקינים בקלט שהוזן.'];
    $done=true;
  } else {
    foreach($ids as $tt){
      $current=['tt'=>$tt,'ok'=>true,'error'=>null,'action'=>'','poster_id'=>0,'akas_count'=>0,'akas_saved'=>0,'conn_count'=>0,'conn_saved'=>0,'conn_source'=>''];
      try{
        // קבלת נתונים מלאים אם יש imdb.php
        $rawRow=$U=[];
        if (function_exists('build_row')) {
          if (!isset($TMDB_KEY)) $TMDB_KEY='';
          if (!isset($RAPIDAPI_KEY)) $RAPIDAPI_KEY='';
          $rawRow = build_row($tt, $TMDB_KEY, $RAPIDAPI_KEY);
        }
        if (function_exists('unify_details')) {
          if (!isset($TMDB_KEY)) $TMDB_KEY='';
          if (!isset($TVDB_KEY)) $TVDB_KEY='';
          $U = unify_details($rawRow, $TMDB_KEY, $TVDB_KEY);
        }

        // posters
        if (empty($U['imdb_id'])) {
          $existing = db_find_existing_row($conn, 'posters', 'imdb_id', $tt);
          if ($existing) { $current['poster_id']=(int)$existing['id']; $current['action']='skipped'; }
          else {
            $minimal=['imdb_id'=>$tt,'title_en'=>$tt];
            $res = upsert_row($conn,'posters',$minimal,'imdb_id','skip');
            if(!$res['ok']) throw new Exception($res['error']);
            $current['poster_id']=(int)$res['id']; $current['action']=$res['action'];
          }
        } else {
          $mapped = map_u_to_posters_fields($U, $rawRow);
          $res = upsert_row($conn,'posters',$mapped,'imdb_id',$dup_mode);
          if(!$res['ok']) throw new Exception($res['error']);
          $current['poster_id']=(int)$res['id']; $current['action']=$res['action'];
        }

        // AKAs
        if ($current['poster_id']>0) {
          $akasFetched = $U['akas'] ?? [];
          $current['akas_count'] = is_array($akasFetched) ? count($akasFetched) : 0;
          $current['akas_saved'] = replace_akas($conn, $current['poster_id'], $tt, (array)$akasFetched);
        }

        // Connections
        if ($current['poster_id']>0) {
          $map = imdb_connections_all($tt);
          $current['conn_source'] = $map['_source'] ?? '';
          unset($map['_source']);
          $current['conn_count'] = array_sum(array_map('count', array_filter($map, 'is_array')));
          $current['conn_saved'] = sync_connections($conn, $current['poster_id'], $tt, $map);
        }

      } catch(Throwable $e){
        $current['ok']=false;
        $current['error']=$e->getMessage()."\nFile: ".$e->getFile()."\nLine: ".$e->getLine();
      }
      $results[]=$current;
    }
    $done=true;
  }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>ייבוא פוסטרים (מאוחד) — Debug + Connections</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --line:#22283a; --accent:#5b8cff; --ok:#6fffbe; --err:#ff7d7d; --warn: #f59e0b;}
    *{box-sizing:border-box} body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;direction:rtl;background:var(--bg);color:var(--text);margin:0;padding:24px} .wrap{max-width:980px;margin:0 auto} .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px} textarea{width:100%;min-height:150px;border:1px solid var(--line);background:#0f1422;color:var(--text);border-radius:10px;padding:10px} .btn{background:var(--accent);color:#fff;border:none;border-radius:10px;padding:10px 16px;cursor:pointer;font-weight:700} .summary{margin-top:18px;} .res{border:1px solid var(--line); padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; } .res-ok{border-left: 4px solid var(--ok); background: #182928; } .res-err{border-left: 4px solid var(--err); background: #2d1c24; } .res-skip{border-left: 4px solid var(--warn); background: #382e1c;}
    input[type="file"] { background: var(--chip); border: 1px solid var(--line); border-radius: 8px; padding: 8px; color: var(--text); }
    body {background-color:#161b26 !important; text-align: right !important;}
    .center-text { text-align: center; width: 100%; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-size: 14px; }
    select { width: 100%; background: #0f1320; color: #e7ecff; border: 1px solid #2a3148; border-radius: 10px; padding: 10px; outline: none; font-family: inherit; font-size: 14px; }
    .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; align-items: end; }

    /* .w3-bar */
    .w3-bar { width: 100%; overflow: hidden; }
    .w3-bar .w3-bar-item { padding: 8px 16px; float: left; width: auto; border: none; display: block; outline: 0; }
    .w3-bar .w3-button { color: white !important; white-space: normal; }
    .w3-bar:before, .w3-bar:after { content: ""; display: table; clear: both; }

    /* .w3-padding */
    .w3-padding { padding: 8px 16px !important; }

    /* .w3-button */
    .w3-button { border: none; display: inline-block; padding: 8px 16px; vertical-align: middle; overflow: hidden; text-decoration: none; color: inherit; text-align: center; cursor: pointer; white-space: nowrap; }

    .content {text-align: right !important;}
    .content a  {color: #6E8BFC !important;}

    /* צבעים */
    .w3-black, .w3-hover-black:hover { color: #fff !important; background-color: white; }
    .w3-white, .w3-hover-white:hover { color: #000 !important; background-color: #fff !important; }
    .white {color: #f1f1f1 !important;}
    .w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover{color:#000!important;background-color:#f1f1f1!important}
  </style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 16px">ייבוא פוסטרים (מאוחד) — Debug + Connections</h1>

  <div class="card">
    <form method="post" action="" enctype="multipart/form-data">
      <label for="ids">הדבק מזהי IMDb או לינקים (למשל: tt1013752, https://www.imdb.com/title/tt0120667/):</label>
      <textarea name="ids" id="ids"><?= H($_POST['ids']??''); ?></textarea>

      <div class="grid" style="margin-top:12px">
        <div>
          <label for="id_file">או העלה TXT/CSV:</label>
          <input type="file" name="id_file" id="id_file" accept=".txt,.csv" style="width:100%">
        </div>
        <div>
          <label for="dup_mode">מנוע כפילויות:</label>
          <select name="dup_mode" id="dup_mode">
            <option value="upsert" <?= (($_POST['dup_mode']??'')==='upsert')?'selected':''; ?>>עדכן/דרוס ערכים קיימים</option>
            <option value="update-missing" <?= (($_POST['dup_mode']??'')==='update-missing')?'selected':''; ?>>השלמת שדות חסרים בלבד</option>
            <option value="skip" <?= (!isset($_POST['dup_mode']) || ($_POST['dup_mode']??'')==='skip')?'selected':''; ?>>דלג אם קיים</option>
          </select>
        </div>
      </div>

      <div style="text-align:center; margin-top:16px">
        <button class="btn" type="submit">ייבוא</button>
      </div>
      <div style="margin-top:8px; color:var(--muted); font-size:13px">
        * אם קיימים build_row/unify_details ב־imdb.php, אמלא גם את שדות הפוסטר; בכל מקרה אשמור Connections ו-AKAs.
      </div>
    </form>
  </div>

  <?php if ($done): ?>
    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 10px">תוצאות:</h3>
      <?php foreach ($results as $r): ?>
        <?php
          $act = (string)($r['action'] ?? '');
          $cls = empty($r['ok']) ? 'res-err' : (str_starts_with($act,'skipped') ? 'res-skip' : 'res-ok');
          $pid = (int)($r['poster_id'] ?? 0);
        ?>
        <div class="res <?= $cls ?>">
          <div><b><?= H($r['tt']) ?></b> — <?= H($act) ?></div>

          <?php if(!empty($r['error'])): ?>
            <div style="color:#ffb3b3; font-size:13px; margin-top:4px; white-space:pre-wrap; direction:ltr; text-align:left">
              Error:<br><?= H($r['error']) ?>
            </div>
          <?php endif; ?>

          <?php if($pid>0): ?>
            <div style="font-size:13px; color:var(--muted); margin-top:4px">
              Poster ID: <?= H($pid) ?> |
              AKAs: נשלפו <?= H($r['akas_count'] ?? 0) ?>, נשמרו <?= H($r['akas_saved'] ?? 0) ?> |
              Connections: נשלפו <?= H($r['conn_count'] ?? 0) ?>, נשמרו <?= H($r['conn_saved'] ?? 0) ?>
              <?php if (!empty($r['conn_source'])): ?>
                <span style="opacity:.7">[src: <?= H($r['conn_source']) ?>]</span>
              <?php endif; ?>
              &nbsp;|&nbsp;<a href="poster.php?id=<?= H($pid) ?>" target="_blank">פתח פוסטר</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__.'/footer.php'; ?>
</body>
</html>
