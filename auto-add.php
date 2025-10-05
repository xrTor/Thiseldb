<?php

// set_error_handler(function($severity, $message, $file, $line) {
//     if (!(error_reporting() & $severity)) {
//         return;
//     }
//     throw new ErrorException($message, 0, $severity, $file, $line);
// });

/****************************************************
 * auto-add.php — ייבוא אוטומטי + AKAs + IMDb Connections (מאוחד)
 * BUILD v49 — תיקון מלא וגורף לכל שגיאות "Array to string conversion"
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

/* טען imdb.php אם קיים (ללא HTML) */
ob_start();
if (file_exists(__DIR__ . '/imdb.php')) { require_once __DIR__ . '/imdb.php'; }
ob_end_clean();

/* ========= עזרים ========= */
function to_csv_val($arr){
    if(!$arr) return null;
    $vals=[];
    foreach((array)$arr as $x){
        if (is_array($x) || is_object($x)) $x = json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $t=trim((string)$x);
        if($t!=='') $vals[]=$t;
    }
    $vals=array_values(array_unique($vals));
    return $vals?implode(', ',$vals):null;
}
function H($v){
  if (is_array($v)) {
    $csv = to_csv_val($v);
    $v = $csv !== null ? $csv : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  } elseif (is_object($v)) {
    $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function dbg_to_s($v): string {
    if ($v === null) return '';
    if (is_scalar($v)) return (string)$v;
    if (is_array($v)) {
        $flat = [];
        foreach ($v as $x) $flat[] = is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return implode(', ', $flat);
    }
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
/* תמיד מחזיר מחרוזת בטוחה ל-HTML */
function safe_s($v): string { return H(dbg_to_s($v)); }

function parse_runtime_to_minutes($str) {
    if (!$str) return null;
    $h = 0; $m = 0;
    if (preg_match('/(\d+)\s*(?:h|hr|hour)/i', $str, $h_match)) { $h = (int)$h_match[1]; }
    if (preg_match('/(\d+)\s*(?:m|min|minute)/i', $str, $m_match)) { $m = (int)$m_match[1]; }
    if ($h > 0 || $m > 0) return ($h * 60) + $m;
    $numeric_val = (int)preg_replace('/\D/', '', $str);
    return $numeric_val > 0 ? $numeric_val : null;
}

/* ========== Year span utility (מוסיף "Recent" לסדרה פעילה) ========== */
function is_continuing_status(?string $status): bool {
    if (!$status) return false;
    $s = mb_strtolower($status, 'UTF-8');
    return str_contains($s,'continuing') || str_contains($s,'returning') || str_contains($s,'ongoing') || str_contains($s,'in production');
}
function build_year_span(?string $firstAired, ?string $lastAired, bool $is_tv, ?string $statusName=null): ?string {
    $startY = null; $endY = null;
    if ($firstAired && preg_match('/^\d{4}/', (string)$firstAired, $m)) $startY = (int)$m[0];
    if ($lastAired  && preg_match('/^\d{4}/', (string)$lastAired,  $m)) $endY   = (int)$m[0];

    if ($startY) {
        if ($endY) return $startY . '-' . $endY;
        if ($is_tv) {
            if (!$lastAired || is_continuing_status($statusName)) return $startY . '-Recent';
            return $startY . '-' . (int)date('Y');
        }
        return (string)$startY;
    }
    return null;
}

/* ========= מיפוי ל-posters ========= */
function map_u_to_posters_fields(array $u, array $raw_row): array {
  $m = [];
  $add = function($col,$val) use (&$m){
      if (is_array($val) || is_object($val)) $val = to_csv_val($val) ?? json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $m[$col]=$val;
  };

  $add('imdb_id', $u['imdb_id'] ?? null);
  $add('title_en', $u['display_title'] ?? ($u['title_en'] ?? null));
  $add('original_title', $u['original_title'] ?? ($raw_row['original_title'] ?? null));
  $add('title_he', $u['he_title'] ?? null);

  if (!empty($u['year_span'])) {
      $add('year', (string)$u['year_span']);
      $add('year_span', (string)$u['year_span']);
  } else {
      $add('year', isset($u['year']) ? (string)$u['year'] : null);
  }

  $is_tv = !empty($u['is_tv']);
  $add('is_tv', $is_tv ? 1 : 0);
  $add('type_id', $is_tv ? 4 : 3);

  $add('poster_url', $u['poster'] ?? null);
  $add('image_url',  $u['poster'] ?? null);
  $add('poster',     $u['poster'] ?? null);

  $tr = $u['trailer'] ?? ($u['trailer_url'] ?? ($u['youtube_trailer'] ?? null));
  $add('trailer_url', $tr);
  $add('youtube_trailer', $tr);

  $add('tvdb_url', $u['tvdb_url'] ?? null);
  if (isset($u['tvdb_id'])) $add('tvdb_id', (string)$u['tvdb_id']);
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

  if (empty($u['networks']) && !empty($u['network'])) $u['networks'] = [$u['network']];
  $add('networks', to_csv_val($u['networks'] ?? null));

  $add('seasons_count',  isset($u['seasons'])  ? (string)(int)$u['seasons']  : null);
  $add('episodes_count', isset($u['episodes']) ? (string)(int)$u['episodes'] : null);

  $add('imdb_rating', isset($u['imdb_rating']) ? (string)$u['imdb_rating'] : null);
  $add('imdb_votes',  isset($u['imdb_votes'])  ? (string)$u['imdb_votes']  : null);
  $add('rt_score', isset($u['rt_score']) ? (string)$u['rt_score'] : null);
  $add('rt_url',   $u['rt_url'] ?? null);
  $add('mc_score', isset($u['mc_score']) ? (string)$u['mc_score'] : null);
  $add('mc_url',   $u['mc_url'] ?? null);

  return $m;
}

/* ========= DB ========= */
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
function db_scalarize($v) {
  if (is_array($v)) { $csv = to_csv_val($v); return $csv !== null ? $csv : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
  if (is_bool($v)) return $v ? '1' : '0';
  if (is_object($v)) return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  return $v;
}
function filter_non_empty_for_update(array $data, string $pkField): array {
  $out=[];
  foreach ($data as $k=>$v) {
    if ($k === $pkField) { $out[$k]=$v; continue; }
    if ($v === null) continue;
    if (is_string($v) && trim($v) === '') continue;
    $out[$k]=$v;
  }
  return $out;
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
    } else {
      $data = filter_non_empty_for_update($data, $pkField);
    }
    if (empty($data) || count($data) <= 1) return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'skipped (nothing to update)'];

    $data = array_map('db_scalarize', $data);
    unset($data[$pkField]); $set=[]; $types=''; $vals=[];
    foreach($data as $k=>$v){ $set[]="`{$k}`=?"; $types.='s'; $vals[]=$v; }
    $vals[]=$pkValue; $types.='s';
    $sql="UPDATE `{$table}` SET ".implode(', ',$set)." WHERE `{$pkField}` = ?";
    $st=$conn->prepare($sql); if(!$st) return ['ok'=>false,'error'=>'Prepare failed: '.$conn->error,'id'=>$poster_id,'action'=>'error'];
    $st->bind_param($types, ...$vals);
    if($st->execute()){ $st->close(); return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'updated']; }
    $err=$st->error; $st->close(); return ['ok'=>false,'error'=>'Update failed: '.$err,'id'=>$poster_id,'action'=>'error'];
  } else {
    $data = filter_non_empty_for_update($data, $pkField);
    $data = array_map('db_scalarize', $data);

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
  $processed_akas = [];
  foreach ((array)$akas as $aka_item) {
      if (is_scalar($aka_item)) {
          $trimmed_item = trim((string)$aka_item);
          if ($trimmed_item !== '') {
              $processed_akas[] = $trimmed_item;
          }
      }
  }
  $list = array_values(array_unique($processed_akas));
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

/* ========= IMDb Connections (ללא regex גורף) ========= */
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
    CURLOPT_ENCODING => '',
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
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
  }
  return null;
}
function imdb_connections_url(string $id): string { return "https://www.imdb.com/title/{$id}/movieconnections/"; }
function normalize_conn_label(string $s): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  $s = preg_replace('/\s*\(.*$/', '', $s);
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
      if (!$id && !empty($node[$k]) && is_string($node[$k]) && preg_match('#/title/(tt\d+)/#', $node[$k], $m)) $id = $m[1];
    }
    if (isset($node['titleText'])) $title = is_array($node['titleText']) ? ($node['titleText']['text'] ?? null) : (is_string($node['titleText'])?$node['titleText']:null);
    if (!$title && isset($node['originalTitleText']) && is_array($node['originalTitleText'])) $title = $node['originalTitleText']['text'] ?? null;
    if (!$title && isset($node['displayableTitle'])) $title = is_string($node['displayableTitle']) ? $node['displayableTitle'] : null;
    if (!$title && isset($node['title'])) $title = is_string($node['title']) ? $node['title'] : null;
    foreach (['link','canonicalLink','url'] as $k) {
      if (!$url && !empty($node[$k]) && is_string($node[$k])) {
        $url = expand_imdb_href($node[$k]);
      }
    }
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
        (isset($node['link']) && is_string($node['link']) && preg_match('#/title/tt\d+/#', $node['link'])) ||
        (isset($node['canonicalLink']) && is_string($node['canonicalLink']) && preg_match('#/title/tt\d+/#', $node['canonicalLink']))
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
  if (!preg_match('/^tt\d{6,12}$/', $imdbId)) { $out['_source']='none'; return $out; }

  foreach ([ imdb_connections_url($imdbId), imdb_connections_url($imdbId).'?ref_=tt_trv_cnn' ] as $url) {
    $html = http_get_simple($url);
    if (!$html) continue;

    $next = parse_next_data_json($html);
    if ($next) {
      $ex = extract_connections_from_next($next, $keep);
      $has=false; foreach($ex as $arr){ if (!empty($arr)) { $has=true; break; } }
      if ($has) { $ex['_source']='next'; return $ex; }
    }

    if (class_exists('DOMDocument')) {
      libxml_use_internal_errors(true);
      $dom = new DOMDocument(); $dom->loadHTML($html); libxml_clear_errors();
      $xp = new DOMXPath($dom);
      $domOut=[]; foreach($keep as $k) $domOut[$k]=[];

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
  }

  $out['_source']='none';
  return $out;
}
function imdb_connections_all(string $tt): array {
  $KEEP = ['Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of','Alternate versions'];
  return imdb_connections_all_fetch($tt, $KEEP);
}

/* ========= שמירת Connections ========= */
if (!function_exists('sync_connections')) {
  function sync_connections(mysqli $db, int $poster_id, string $source_imdb_id, array $connections_map): int {
    if ($poster_id <= 0) return 0;

    $flat=[]; $seen=[];
    foreach ((array)$connections_map as $label => $items) {
      if (!is_array($items) || empty($items)) continue;
      foreach ($items as $it) {
        $tt = trim(dbg_to_s($it['id'] ?? ''));
        $title = trim(dbg_to_s($it['title'] ?? ''));
        if ($tt==='' || $title==='') continue;
        $k = $label.'|'.$tt;
        if (isset($seen[$k])) continue;
        $seen[$k]=true;
        $flat[]=['label'=>$label,'tt'=>$tt,'title'=>$title];
      }
    }

    $db->begin_transaction();
    try {
      if ($del = $db->prepare("DELETE FROM poster_connections WHERE poster_id = ?")) {
        $del->bind_param("i", $poster_id);
        $del->execute();
        $del->close();
      }
      if (empty($flat)) { $db->commit(); return 0; }

      $sql = "INSERT IGNORE INTO poster_connections
              (poster_id, relation_label, conn_title, related_imdb_id, conn_imdb_id,
               related_title, relation_type, imdb_id, source, kind, target_tt, target_title)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $st = $db->prepare($sql);
      if (!$st) throw new Exception("Connections SQL Prepare Failed: ".$db->error);

      $saved=0;
      foreach ($flat as $row) {
        $label=(string)$row['label']; $tt=(string)$row['tt']; $title=(string)$row['title'];
        $src='imdb'; $kind=$label;
        $st->bind_param("isssssssssss",
          $poster_id, $label, $title, $tt, $tt, $title, $label, $source_imdb_id, $src, $kind, $tt, $title
        );
        if(!$st->execute()) throw new Exception("Connections Execute Failed: ".$st->error);
        if($st->affected_rows>0) $saved++;
      }
      $st->close(); $db->commit(); return $saved;
    } catch (Throwable $e) {
      $db->rollback();
      throw $e;
    }
  }
}

/* ========= TVDB Assist ========= */
const TVDB_ALLOW_INSECURE_SSL = false;
function tvdb_curlJson(string $method,string $url,$payload=null,array $headers=[]):array{
    $ch=curl_init();
    $opts=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>$headers];
    if(TVDB_ALLOW_INSECURE_SSL){$opts[CURLOPT_SSL_VERIFYPEER]=false;$opts[CURLOPT_SSL_VERIFYHOST]=0;}
    $m=strtoupper($method);
    if($m==='POST'){ $opts[CURLOPT_POST]=true; $opts[CURLOPT_POSTFIELDS]=is_string($payload)?$payload:json_encode($payload,JSON_UNESCAPED_UNICODE);
        $hasCT=false; foreach($headers as $h){ if(stripos($h,'content-type:')===0){$hasCT=true;break;} } if(!$hasCT)$opts[CURLOPT_HTTPHEADER][]='Content-Type: application/json';
    } elseif($m!=='GET'){ $opts[CURLOPT_CUSTOMREQUEST]=$m; if($payload!==null)$opts[CURLOPT_POSTFIELDS]=is_string($payload)?$payload:json_encode($payload,JSON_UNESCAPED_UNICODE); }
    curl_setopt_array($ch,$opts); $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
    if($raw===false)return['ok'=>false,'error'=>"cURL error: $err"];
    $json=json_decode($raw,true); $ok=($http>=200&&$http<300);
    return $ok?['ok'=>true,'json'=>$json,'http'=>$http]:['ok'=>false,'error'=>"HTTP $http: $raw"];
}
function tvdbLogin(string $apiKey, ?string $pin = null, &$err=null): ?string{
    $payload=['apikey'=>$apiKey]; if($pin)$payload['pin']=$pin;
    $resp=tvdb_curlJson('POST','https://api4.thetvdb.com/v4/login',$payload,['Content-Type: application/json']);
    if(!$resp['ok']){ $err=$resp['error']; return null; }
    return $resp['json']['data']['token']??null;
}
function tvdbSearchByImdb(string $tt,string $token,&$err=null):?array{
    $resp=tvdb_curlJson('GET',"https://api4.thetvdb.com/v4/search/remoteid/".rawurlencode($tt),null,
        ['Authorization: Bearer '.$token,'Accept: application/json']);
    return $resp['ok']?$resp['json']:null;
}
function tvdbSearchByQuery(string $q,string $type,string $token,&$err=null):?array{
    $resp=tvdb_curlJson('GET',"https://api4.thetvdb.com/v4/search?query=".rawurlencode($q)."&type=$type",null,
        ['Authorization: Bearer '.$token,'Accept: application/json']);
    return $resp['ok']?$resp['json']:null;
}
function deepGet(array $arr,array $path){ $cur=$arr; foreach($path as $k){ if(!is_array($cur)||!array_key_exists($k,$cur)) return null; $cur=$cur[$k]; } return $cur; }
function guessTypeByKeys(array $it): string { foreach(['series','movie','season','episode'] as $k){ if(!empty($it[$k])&&is_array($it[$k])) return $k; } return $it['recordType'] ?? ($it['type'] ?? 'series'); }
function robustTvdbTypeAndId(array $it): array {
    $paths=[['id'],['tvdb_id'],['tvdbId'],['series','id'],['series','tvdb_id'],['movie','id'],['movie','tvdb_id'],['season','id'],['episode','id'],['data','id']];
    $type=strtolower($it['type']??guessTypeByKeys($it)); $id=0; $src='none';
    foreach($paths as $p){ $val=deepGet($it,$p); if(is_numeric($val)&&(int)$val>0){ $id=(int)$val; $src=implode('.',$p); break; } }
    if(!$type) $type=guessTypeByKeys($it)?:'series';
    return [$type,$id,$src];
}
function tvdb_series_details(int $id, string $token): ?array {
    $h=['Authorization: Bearer '.$token,'Accept: application/json'];
    foreach (["https://api4.thetvdb.com/v4/series/$id/extended","https://api4.thetvdb.com/v4/series/$id"] as $u) {
        $resp=tvdb_curlJson('GET',$u,null,$h);
        if($resp['ok'] && !empty($resp['json']['data'])) return $resp['json']['data'];
    }
    return null;
}
function tvdb_series_episodes_summary(int $id, string $token): ?array {
    $h=['Authorization: Bearer '.$token,'Accept: application/json'];
    $u="https://api4.thetvdb.com/v4/series/$id/episodes/summary";
    $resp=tvdb_curlJson('GET',$u,null,$h);
    return ($resp['ok'] && !empty($resp['json']['data'])) ? $resp['json']['data'] : null;
}
/* ספירת פרקים מלאה (דילוג על עונה 0) */
function tvdb_series_episodes_count_paged(int $id, string $token, int $maxPages=50): ?int {
    $h=['Authorization: Bearer '.$token,'Accept: application/json'];
    $pageParam = null; $total = 0; $seen=false;

    for ($i=0; $i<$maxPages; $i++) {
        $u = "https://api4.thetvdb.com/v4/series/$id/episodes/default" . ($pageParam!==null ? "?page=".$pageParam : "");
        $resp=tvdb_curlJson('GET',$u,null,$h);
        if(!$resp['ok']) break;

        $data = $resp['json']['data'] ?? null;
        if (!$data) break;

        $episodesList = [];
        if (is_array($data) && array_is_list($data)) {
            $episodesList = $data;
        } elseif (is_array($data) && !empty($data['episodes']) && is_array($data['episodes'])) {
            $episodesList = $data['episodes'];
        }

        if (!empty($episodesList)) {
            $seen=true;
            foreach ($episodesList as $ep) {
                $sn = $ep['seasonNumber'] ?? ($ep['season'] ?? null);
                if (is_numeric($sn) && (int)$sn === 0) continue; // דילוג על עונה 0
                $total++;
            }
        }

        $links = $resp['json']['links'] ?? null;
        if (!$links || !isset($links['next']) || $links['next'] === null) break;
        $pageParam = (int)$links['next'];
    }
    return $seen ? $total : null;
}
function tvdb_extract_network(array $s): ?string {
    $n = deepGet($s, ['network','name']);
    if (is_string($n) && $n!=='') return $n;
    $cands=[];
    if (!empty($s['networks']) && is_array($s['networks'])) $cands = array_merge($cands,$s['networks']);
    if (!empty($s['companies']) && is_array($s['companies'])) $cands = array_merge($cands,$s['companies']);
    $last=null;
    foreach ($cands as $c) {
        $name = is_array($c) ? ($c['name'] ?? null) : null;
        $role = is_array($c) ? (strtolower($c['role'] ?? ($c['type'] ?? ''))) : '';
        if ($name && ($role==='network' || $role==='broadcaster' || $role==='studio' || $role==='production')) return $name;
        if ($name) $last=$name;
    }
    return $last;
}
/* טריילר מ-TVDB */
function tvdb_extract_trailer(array $details): ?string {
    $arr = $details['trailers'] ?? null;
    if (is_array($arr)) {
        foreach ($arr as $t) {
            $u = is_array($t) ? ($t['url'] ?? ($t['trailer_url'] ?? null)) : null;
            if (is_string($u) && preg_match('~^https?://~',$u)) return $u;
        }
    }
    $stack = [$details];
    while ($stack) {
        $cur = array_pop($stack);
        if (!is_array($cur)) continue;
        foreach ($cur as $k=>$v) {
            if (is_string($v) && preg_match('~^https?://(www\.)?(youtube\.com|youtu\.be|vimeo\.com)/~i', $v)) {
                return $v;
            } elseif (is_array($v)) {
                $stack[] = $v;
            }
        }
    }
    return null;
}
function tvdb_counts_from_details_or_summary(array $s, ?array $sum, int $seriesId, string $token): array {
    $seasons=null; $episodes=null;
    if (isset($s['seasons']) && is_array($s['seasons'])) {
        $uniq = [];
        foreach ($s['seasons'] as $row) {
            $num = null;
            if (isset($row['number']) && is_numeric($row['number'])) $num = (int)$row['number'];
            elseif (isset($row['seasonNumber']) && is_numeric($row['seasonNumber'])) $num = (int)$row['seasonNumber'];
            
            $row_name_str = dbg_to_s($row['name'] ?? '');
            if (!empty($row_name_str) && preg_match('~season\s*(\d+)~i', $row_name_str, $m)) {
                $num = (int)$m[1];
            }

            $name = strtolower($row_name_str);
            $type = strtolower(dbg_to_s($row['type'] ?? ''));
            $is_special = ($num === 0) || str_contains($name,'special') || str_contains($type,'special');
            if ($num !== null && !$is_special) { $uniq[$num] = 1; }
        }
        if (!empty($uniq)) $seasons = count($uniq);
        elseif (!empty($s['seasons'])) $seasons = count($s['seasons']);
    }
    foreach (['episodesCount','episodeCount','totalEpisodes'] as $k) {
        if (isset($s[$k]) && is_numeric($s[$k])) { $episodes = (int)$s[$k]; break; }
    }
    if ($sum && is_array($sum)) {
        foreach (['total','episodeCount','airedEpisodes','episodesTotal','absolute'] as $k) {
            if ($episodes===null && isset($sum[$k]) && is_numeric($sum[$k])) { $episodes = (int)$sum[$k]; }
        }
        if ($episodes===null && !empty($sum['episodes']) && is_array($sum['episodes'])) {
            $episodes = 0;
            foreach($sum['episodes'] as $row){
                $c = null;
                foreach (['count','aired','episodes','numberOfEpisodes'] as $ck) {
                    if (isset($row[$ck]) && is_numeric($row[$ck])) { $c = (int)$row[$ck]; break; }
                }
                $sn = $row['season'] ?? ($row['number'] ?? ($row['seasonNumber'] ?? null));
                if ($c !== null) {
                    if ($sn !== null && (int)$sn === 0) continue;
                    $episodes += $c;
                }
            }
            if ($episodes === 0) $episodes = null;
        }
    }
    if ($episodes === null) $episodes = tvdb_series_episodes_count_paged($seriesId, $token, 50);
    return ['seasons'=>$seasons, 'episodes'=>$episodes];
}
function tvdb_series_compact_from_details(array $s, int $id, string $type, ?array $summary, string $token): array {
    $is_tv = (strtolower($type)!=='movie');
    $out=['is_tv'=>$is_tv];
    $out['title_en']    = $s['name'] ?? null;
    $out['overview_en'] = $s['overview'] ?? null;

    $first = $s['firstAired'] ?? null;
    $last  = $s['lastAired']  ?? null;
    $statusName = $s['status']['name'] ?? ($s['status'] ?? null);

    $span = build_year_span($first, $last, $is_tv, is_string($statusName)?$statusName:null);
    if ($span) { $out['year_span'] = $span; $out['year'] = $span; }

    $net = tvdb_extract_network($s); if ($net) $out['network'] = $net;

    $cnt = tvdb_counts_from_details_or_summary($s, $summary, $id, $token);
    if ($cnt['seasons']  !== null) $out['s_count'] = (int)$cnt['seasons'];
    if ($cnt['episodes'] !== null) $out['e_count'] = (int)$cnt['episodes'];

    if (!empty($s['image'])) $out['poster'] = $s['image'];

    $tr = tvdb_extract_trailer($s);
    if ($tr) $out['trailer'] = $tr;

    $out['tvdb_url'] = tvdbDerefUrl($type,$id);
    $out['tvdb_id']  = $id;
    return $out;
}
function tvdbDerefUrl(string $t,int $id):string{
    switch(strtolower($t)){
        case 'movie':   return "https://www.thetvdb.com/dereferrer/movie/$id";
        case 'season':  return "https://www.thetvdb.com/dereferrer/season/$id";
        case 'episode': return "https://www.thetvdb.com/dereferrer/episode/$id";
        default:        return "https://www.thetvdb.com/dereferrer/series/$id";
    }
}
function merge_tvdb_into_u(array $U, array $T): array {
  $used=false;
  if (!empty($T['title_en']) && (empty($U['display_title']) && empty($U['title_en']))) {
    $U['display_title']=$T['title_en']; $U['title_en']=$T['title_en']; $used=true;
  }
  if (!empty($T['overview_en']) && empty($U['overview_en'])) { $U['overview_en']=$T['overview_en']; $used=true; }

  if (!empty($T['year'])) { $U['year']=$T['year']; $used=true; }
  if (!empty($T['year_span'])) { $U['year_span']=(string)$T['year_span']; $used=true; }

  if (!empty($T['poster']) && empty($U['poster'])) { $U['poster']=$T['poster']; $used=true; }

  if (!empty($T['network'])) {
    if (empty($U['networks'])) { $U['networks']=[$T['network']]; $used=true; }
    elseif (is_array($U['networks']) && !in_array($T['network'],$U['networks'])) { $U['networks'][]=$T['network']; $used=true; }
    elseif (is_string($U['networks']) && stripos($U['networks'],$T['network'])===false) { $U['networks'].=', '.$T['network']; $used=true; }
  }

  if (isset($T['s_count']) && (empty($U['seasons']) || (int)$U['seasons']===0))   { $U['seasons']=(int)$T['s_count']; $used=true; }
  if (isset($T['e_count']) && (empty($U['episodes'])|| (int)$U['episodes']===0)) { $U['episodes']=(int)$T['e_count']; $used=true; }

  if (!empty($T['trailer']) && empty($U['trailer'])) { $U['trailer'] = $T['trailer']; $used=true; }

  if (!empty($T['tvdb_url']) && empty($U['tvdb_url'])) { $U['tvdb_url']=$T['tvdb_url']; $used=true; }
  if (!empty($T['is_tv'])) { $U['is_tv']=true; $U['type_id']=4; $used=true; }
  if (!empty($T['tvdb_id'])) { $U['tvdb_id'] = (int)$T['tvdb_id']; }

  if ($used) $U['_used_tvdb']=true;
  return $U;
}

/* ========= IMDb scrape (Fallback לשם/שנה) ========= */
function imdb_curlSimple(string $m,string $url):array{
    $ch=curl_init();$opts=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>25];
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    if($raw===false)return['ok'=>false,'error'=>"cURL error: $err"];
    return ['ok'=>($http>=200&&$http<300),'body'=>$raw,'http'=>$http];
}
function normalizeTitle(string $s):string{
    $s=mb_strtolower($s,'UTF-8');
    $s=preg_replace('~\s*\(\d{4}.*?\)$~u','',$s);
    $s=preg_replace('~[:\-\–\—]\s*.*$~u','',$s);
    $s=preg_replace('~[^\p{L}\p{N}]+~u','',$s);
    return $s;
}
function scrapeImdbCandidates(string $tt):?array{
    $resp=imdb_curlSimple('GET',"https://www.imdb.com/title/$tt/"); if(!$resp['ok']) return null; $html=$resp['body'];
    $titles=[];$year=null;

    if(preg_match('~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~si',$html,$m)){
        $json=json_decode(html_entity_decode($m[1], ENT_QUOTES|ENT_HTML5,'UTF-8'),true);
        if($json){
            // Helper function to safely add titles from fields that can be a string or an array of strings
            $addTitle = function($field) use (&$titles) {
                if (empty($field)) return;
                foreach((array)$field as $item) {
                    if (is_string($item)) {
                        $titles[] = trim($item);
                    }
                }
            };

            $addTitle($json['name'] ?? null);
            $addTitle($json['alternateName'] ?? null);

            $date=$json['datePublished']??($json['releasedEvent'][0]['startDate']??null);
            if($date && is_string($date)) $year=(int)substr($date,0,4);
        }
    }
    if(preg_match('~<meta\s+property="og:title"\s+content="([^"]+)"~i',$html,$m2)){
        $og=preg_replace('~\s*-\s*IMDb$~i','',trim($m2[1])); if($og!=='') $titles[] = $og;
    }
    if(preg_match('~<title>\s*(.*?)\s*- IMDb\s*</title>~si',$html,$m3)){
        $t=trim($m3[1]); if($t!=='')$titles[]=$t;
        if(!$year && preg_match('~\((\d{4})~',$t,$my))$year=(int)$my[1];
    }

    $seen=[];$clean=[];
    foreach($titles as $t){ $key=mb_strtolower($t,'UTF-8'); if(!isset($seen[$key])){$seen[$key]=1;$clean[]=$t;} }
    return ['titles'=>$clean,'year'=>$year];
}

/* ========= השלמה מ-TVDB (ממלא חסרים בלבד) ========= */
function tvdb_full_fetch_and_merge(string $tt, string $apiKey, ?string $pin, array $U): array {
  $err=null; $token = tvdbLogin($apiKey, $pin, $err);
  if (!$token) { return $U; }

  $res = tvdbSearchByImdb($tt, $token, $err);
  if ($res && !empty($res['data'])) {
    $priority=['series'=>1,'movie'=>2,'season'=>3,'episode'=>4]; $pick=null; $best=PHP_INT_MAX;
    foreach($res['data'] as $it){ $t=strtolower($it['type']??guessTypeByKeys($it)); if(!isset($priority[$t]))continue; if($priority[$t]<$best){$best=$priority[$t];$pick=$it;} }
    if ($pick){
      [$type,$id] = robustTvdbTypeAndId($pick);
      if ($id) {
        $details = (strtolower($type)==='series') ? tvdb_series_details($id, $token) : null;
        $summary = (strtolower($type)==='series') ? tvdb_series_episodes_summary($id, $token) : null;
        $T = $details ? tvdb_series_compact_from_details($details,$id,$type,$summary,$token)
                      : ['is_tv'=>true,'tvdb_url'=>tvdbDerefUrl($type,$id),'tvdb_id'=>$id];
        return merge_tvdb_into_u($U, $T);
      }
    }
  }

  $meta = scrapeImdbCandidates($tt);
  if ($meta && !empty($meta['titles'])) {
    $cands = $meta['titles']; $year = $meta['year'] ?? null;
    foreach (['series','movie'] as $typeQ) {
      foreach ($cands as $cand) {
        $found = tvdbSearchByQuery($cand, $typeQ, $token, $err);
        if (!$found || empty($found['data'])) continue;

        $pick=null; $best=1e9; $norm=normalizeTitle($cand);
        foreach($found['data'] as $it){
          $name=$it['name']??($it['series']['name']??($it['movie']['name']??'')); $y=null;
          if(!empty($it['year']))$y=(int)$it['year'];
          elseif(!empty($it['firstAired']))$y=(int)substr($it['firstAired'],0,4);
          elseif(!empty($it['movie']['year']))$y=(int)$it['movie']['year'];
          $score=levenshtein($norm, normalizeTitle(dbg_to_s($name)));
          if($year&&$y)$score+=(abs($year-$y)<=1?-3:+3);
          if($score<$best){ $best=$score; $pick=$it; }
        }
        if ($pick){
          [$t,$id] = robustTvdbTypeAndId($pick);
          if ($id) {
            $details = (strtolower($t)==='series') ? tvdb_series_details($id, $token) : null;
            $summary = (strtolower($t)==='series') ? tvdb_series_episodes_summary($id, $token) : null;
            $T = $details ? tvdb_series_compact_from_details($details,$id,$t,$summary,$token)
                          : ['is_tv'=>true,'tvdb_url'=>tvdbDerefUrl($t,$id),'tvdb_id'=>$id];
            return merge_tvdb_into_u($U, $T);
          }
        }
      }
    }
  }
  return $U;
}

/* ========= פירוק קלט מזהי tt ========= */
function parse_ids($raw_input): array {
  $out=[]; $items=preg_split('~[\s,;]+~',(string)$raw_input,-1,PREG_SPLIT_NO_EMPTY);
  foreach ($items as $item) if (preg_match('~(tt\d{6,12})~',$item,$m)) $out[]=$m[1];
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
      $current=[
        'tt'=>$tt,'ok'=>true,'error'=>null,'action'=>'',
        'poster_id'=>0,'akas_count'=>0,'akas_saved'=>0,
        'conn_count'=>0,'conn_saved'=>0,'conn_source'=>'',
        'used_tvdb'=>false,
        'dbg_year'=>null,'dbg_seasons'=>null,'dbg_episodes'=>null,'dbg_network'=>null,
        'link_imdb'=>"https://www.imdb.com/title/$tt/",'link_tvdb'=>null
      ];
      try{
        // בסיס מ-imdb.php
        $rawRow=$U=[];
        if (function_exists('build_row')) {
          if (!isset($TMDB_KEY)) $TMDB_KEY='';
          if (!isset($RAPIDAPI_KEY)) $RAPIDAPI_KEY='';
          if (!isset($TVDB_KEY)) $TVDB_KEY='';
          if (!isset($OMDB_KEY)) $OMDB_KEY='';
          $rawRow = build_row($tt, $TMDB_KEY, $RAPIDAPI_KEY, $TVDB_KEY, $OMDB_KEY);
        }
        if (function_exists('unify_details')) {
          if (!isset($TMDB_KEY)) $TMDB_KEY='';
          if (!isset($TVDB_KEY)) $TVDB_KEY='';
          $U = unify_details($rawRow, $TMDB_KEY, $TVDB_KEY);
        } else {
          $U = [];
        }
        $U['imdb_id'] = $U['imdb_id'] ?? $tt;

        // TVDB Assist — ממלא חסרים (כולל טריילר)
        if (!empty($TVDB_KEY)) {
          $pin = isset($TVDB_PIN) ? (string)$TVDB_PIN : null;
          $U = tvdb_full_fetch_and_merge($tt, $TVDB_KEY, $pin, $U);
          if (!empty($U['_used_tvdb'])) { $current['used_tvdb']=true; unset($U['_used_tvdb']); }
        }
        if (!empty($U['tvdb_url'])) $current['link_tvdb'] = $U['tvdb_url'];

        // posters (upsert)
        $mapped = map_u_to_posters_fields($U, $rawRow);

        // דיבאג - גרסה משוריינת ובטוחה
        $current['dbg_year']     = dbg_to_s($U['year_span'] ?? $U['year'] ?? $mapped['year'] ?? null);
        $current['dbg_seasons']  = $U['seasons'] ?? $mapped['seasons_count'] ?? null;
        $current['dbg_episodes'] = $U['episodes'] ?? $mapped['episodes_count'] ?? null;
        $current['dbg_network']  = dbg_to_s($U['networks'] ?? $mapped['networks'] ?? null);

        $res = upsert_row($conn,'posters',$mapped,'imdb_id',$dup_mode);
        if(!$res['ok']) throw new Exception($res['error']);
        $current['poster_id']=(int)$res['id']; $current['action']=$res['action'];

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
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --line:#22283a; --accent:#5b8cff; --ok:#6fffbe; --err:#ff7d7d; --warn:#f59e0b;}
    *{box-sizing:border-box} body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;direction:rtl;background:var(--bg);color:var(--text);margin:0;padding:24px} .wrap{max-width:980px;margin:0 auto} .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px} textarea{width:100%;min-height:150px;border:1px solid var(--line);background:#0f1422;color:var(--text);border-radius:10px;padding:10px} .btn{background:var(--accent);color:#fff;border:none;border-radius:10px;padding:10px 16px;cursor:pointer;font-weight:700} .summary{margin-top:18px;} .res{border:1px solid var(--line); padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; } .res-ok{border-left: 4px solid var(--ok); background: #182928; } .res-err{border-left: 4px solid var(--err); background: #2d1c24; } .res-skip{border-left: 4px solid var(--warn); background: #382e1c;}
    input[type="file"] { background: var(--chip); border: 1px solid var(--line); border-radius: 8px; padding: 8px; color: var(--text); }
    body {background-color:#161b26 !important; text-align: right !important;}
    .center-text { text-align: center; width: 100%; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-size: 14px; }
    select { width: 100%; background: #0f1320; color: #e7ecff; border: 1px solid #2a3148; border-radius: 10px; padding: 10px; outline: none; font-family: inherit; font-size: 14px; }
    .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; align-items: end; }
    .w3-bar { width: 100%; overflow: hidden; }
    .w3-bar .w3-bar-item { padding: 8px 16px; float: left; width: auto; border: none; display: block; outline: 0; }
    .w3-bar .w3-button { color: white !important; white-space: normal; }
    .w3-bar:before, .w3-bar:after { content: ""; display: table; clear: both; }
    .w3-padding { padding: 8px 16px !important; }
    .w3-button { border: none; display: inline-block; padding: 8px 16px; vertical-align: middle; overflow: hidden; text-decoration: none; color: inherit; text-align: center; cursor: pointer; white-space: nowrap; }
    .content {text-align: right !important;}
    .content a  {color: #6E8BFC !important;}
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
      <textarea name="ids" id="ids"><?= safe_s($_POST['ids']??''); ?></textarea>

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
        * אם קיימים build_row/unify_details ב־imdb.php, אמלא גם את שדות הפוסטר; בנוסף—אשלים חסרים מ־TVDB (רשת/שנים/עונות/פרקים/פוסטר/טריילר/קישורים).
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

          // קישורים — בנייה לתוך מחרוזת בלבד (למנוע Array→string)
          $im = (!empty($r['link_imdb'])) ? '<a href="'.H((string)$r['link_imdb']).'" target="_blank">IMDb</a>' : '';
          $tv = (!empty($r['link_tvdb'])) ? '<a href="'.H((string)$r['link_tvdb']).'" target="_blank">TVDb</a>' : '';
          $links_s = ($im && $tv) ? ($im.' / '.$tv) : ($im ?: $tv);
        ?>
        <div class="res <?= $cls ?>">
          <div>
            <b><?= safe_s($r['tt']) ?></b> — <?= safe_s($act) ?>
            <?php if(!empty($r['used_tvdb'])): ?><span style="padding:2px 6px;border-radius:6px;background:#24324f;color:#9fc2ff;font-size:12px">[TVDB Assist]</span><?php endif; ?>
            <?php if ($links_s !== ''): ?> &nbsp; <span style="color:var(--muted)">| קישורים:</span> <?= $links_s ?><?php endif; ?>
          </div>

          <?php if(!empty($r['error'])): ?>
            <div style="color:#ffb3b3; font-size:13px; margin-top:4px; white-space:pre-wrap; direction:ltr; text-align:left">
              Error:<br><?= H($r['error']) ?>
            </div>
          <?php endif; ?>

          <?php if($pid>0): ?>
            <div style="font-size:13px; color:var(--muted); margin-top:4px">
              Poster ID: <?= (int)$pid ?> |
              AKAs: נשלפו <?= (int)($r['akas_count'] ?? 0) ?>, נשמרו <?= (int)($r['akas_saved'] ?? 0) ?> |
              Connections: נשלפו <?= (int)($r['conn_count'] ?? 0) ?>, נשמרו <?= (int)($r['conn_saved'] ?? 0) ?>
              <?php if (!empty($r['conn_source'])): ?><span style="opacity:.7">[src: <?= safe_s($r['conn_source']) ?>]</span><?php endif; ?>

        <?php
                $dbgParts = [];
                if (isset($r['dbg_year']) && $r['dbg_year'] !== null && $r['dbg_year'] !== '') {
                  // ✅ שימוש בפונקציה שמטפלת במערכים
                  $dbgParts[] = 'Year: ' . dbg_to_s($r['dbg_year']);
                }
                if (isset($r['dbg_seasons']) && $r['dbg_seasons'] !== null) {
                  $dbgParts[] = 'Seasons: ' . (string)(int)$r['dbg_seasons'];
                }
                if (isset($r['dbg_episodes']) && $r['dbg_episodes'] !== null) {
                  $dbgParts[] = 'Episodes: ' . (string)(int)$r['dbg_episodes'];
                }
                if (isset($r['dbg_network']) && $r['dbg_network'] !== null && $r['dbg_network'] !== '') {
                  // ✅ שימוש בפונקציה שמטפלת במערכים
                  $dbgParts[] = 'Network: ' . dbg_to_s($r['dbg_network']);
                }
                if (!empty($dbgParts)) {
                  // ✅ שימוש בפונקציה לקידוד HTML
                  echo ' | ' . H(implode(' • ', $dbgParts));
                }
              ?>

              &nbsp;|&nbsp;<a href="poster.php?id=<?= (int)$pid ?>" target="_blank">פתח פוסטר</a>
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