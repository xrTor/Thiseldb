<?php
/****************************************************
 * auto-add.php — ייבוא אוטומטי (גרסה סופית ומתוקנת)
 * BUILD v56 — מותאם באופן מלא למבנה הפרויקט
 ****************************************************/

mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/server.php';
include_once __DIR__ . '/imdb.class.php';
include_once __DIR__ . '/imdb.php';

/**
 * =================================================================
 * unify_details_v2 — פונקציה מרכזית לאיחוד מידע לפי הדרישות
 * =================================================================
 */
function unify_details_v2(array $row, $TMDB_KEY, $TVDB_KEY, $OMDB_KEY){
    global $HARDCODE_TVDB;

    $tmdb = $row['tmdb'] ?? [];
    $omdb = $row['omdb'] ?? [];
    $r = $row['rapidapi'] ?? [];

    // 1. זיהוי סוג (סרט/סדרה) לפי סדר עדיפויות: IMDb -> OMDb -> TMDb
    $is_tv = (bool)($row['is_tv'] ?? false); // From imdb.class (IMDb source)
    if (isset($omdb['Type']) && $omdb['Type'] !== 'N/A') {
        $is_tv = (strtolower($omdb['Type']) === 'series');
    } elseif (isset($row['tmdb_type'])) {
        $is_tv = ($row['tmdb_type'] === 'tv');
    }

    $english_title  = first_nonempty($row['title']);
    $original_title = first_nonempty($row['original_title']);

    // 2. TVDB נשלף רק פעם אחת (רק לסדרות)
    $tvdb_id = $tmdb['external_ids']['tvdb_id'] ?? ($HARDCODE_TVDB[$row['imdb']] ?? null);
    $tvdb_he_details = []; $tvdb_core_details = []; $tvdb_locale_details = ['countries' => [], 'languages' => []];
    $genres_tvdb = []; $tvdb_akas = []; $tvdb_nets = [];

    if ($is_tv && function_exists('tvdb_fetch_hebrew_details')) {
        if ($tvdb_id) {
            $tvdb_he_details = tvdb_fetch_hebrew_details($tvdb_id, $TVDB_KEY);
            $tvdb_core_details = tvdb_fetch_core_details($tvdb_id, $TVDB_KEY);
            $tvdb_locale_details = tvdb_fetch_locale_details($tvdb_id, $TVDB_KEY);
            $genres_tvdb = tvdb_fetch_genres_api($tvdb_id, $TVDB_KEY);
            $tvdb_akas = tvdb_fetch_akas_api($tvdb_id, $TVDB_KEY);
            $tvdb_nets = tvdb_fetch_networks_api($tvdb_id, $TVDB_KEY);
        }
    }
    
    // 3. שם עברי: עדיפות ל-TVDB, גיבוי מ-TMDb
    $he_title = first_nonempty($tvdb_he_details['title'] ?? null, tmdb_pick_he_title($tmdb, $row['tmdb_type']));

    // 4. תקציר: רק ממקורות IMDb
    $plot_from_scraper = $row['_plot_en'] ?? null;
    $plot_from_omdb = (!empty($omdb['Plot']) && $omdb['Plot'] !== 'N/A') ? $omdb['Plot'] : null;
    $overview_en = first_nonempty($plot_from_scraper, $plot_from_omdb);
    $overview_he = first_nonempty($tvdb_he_details['overview'] ?? null, tmdb_pick_he_overview($tmdb));

    // 5. פוסטר: עדיפות לפוסטר עברי מ-TMDb, גיבוי מ-IMDb
    $poster = first_nonempty(tmdb_pick_he_poster($row['tmdb_type'], $row['tmdb_id'], $TMDB_KEY), $row['imdb_poster'] ?? null);
    
    // 6. ז'אנרים: IMDb ו-TVDB בלבד
    $genres = merge_unique_lists(normalize_list($row['imdb_genres']), $genres_tvdb);
    
    // 7. OMDb משמש רק לציונים וקישורים
    $rt_score = null; $rt_url = null; $mc_score = null; $mc_url = null;
    if ($omdb) {
        if (!empty($omdb['Ratings'])) {
            foreach ($omdb['Ratings'] as $rr) {
                if (strcasecmp($rr['Source'], 'Rotten Tomatoes') === 0 && preg_match('~(\d+)%~', $rr['Value'], $m)) $rt_score = (int)$m[1];
                if (strcasecmp($rr['Source'], 'Metacritic') === 0 && preg_match('~(\d+)/100~', $rr['Value'], $m)) $mc_score = (int)$m[1];
            }
        }
        if (!empty($omdb['tomatoURL'])) $rt_url = $omdb['tomatoURL'];
        if ($mc_score !== null) $mc_url = mc_url_from_imdb($row['imdb']);
    }

    $tvdb_url = $is_tv ? (tvdb_build_links($tmdb['name'] ?? '', $tvdb_id, $row['imdb'], ($GLOBALS['HARDCODE_TVDB'] ?? []), $TVDB_KEY)[0] ?? null) : null;
    $tmdb_url = (!empty($row['tmdb_id']) && !empty($row['tmdb_type'])) ? 'https://www.themoviedb.org/' . $row['tmdb_type'] . '/' . $row['tmdb_id'] : null;

    return [
      'display_title'    => first_nonempty($english_title, $row['imdb']),
      'original_title'   => $original_title,
      'he_title'         => $he_title,
      'year'             => first_nonempty($row['year'], $tvdb_core_details['year'] ?? null),
      'overview_he'      => $overview_he,
      'overview_en'      => $overview_en,
      'poster'           => $poster,
      'trailer'          => tmdb_pick_youtube_trailer($tmdb),
      'genres'           => $genres,
      'languages'        => languages_from_imdb_only($row['imdb'], $row['language']),
      'countries'        => countries_names_only($row['country']),
      'runtime'          => format_runtime($is_tv ? first_nonempty($tvdb_core_details['runtime'] ?? null, $row['imdb_runtime']) : first_nonempty($row['imdb_runtime'], $tmdb['runtime'] ?? null)),
      'networks'         => merge_unique_lists($tvdb_nets, array_map(fn($n)=>$n['name']??'',$tmdb['networks']??[])),
      'imdb_rating'      => $r['averageRating'] ?? null,
      'imdb_votes'       => $r['numVotes'] ?? null,
      'imdb_id'          => $row['imdb'],
      'is_tv'            => $is_tv,
      'tvdb_url'         => $tvdb_url,
      'tvdb_id'          => $tvdb_id,
      'seasons'          => first_nonempty(($row['tmdb_type']==='tv'?($tmdb['number_of_seasons']??null):null), $tvdb_core_details['seasons']??null),
      'episodes'         => first_nonempty(($row['tmdb_type']==='tv'?($tmdb['number_of_episodes']??null):null), $tvdb_core_details['episodes']??null),
      'akas'             => merge_unique_lists($row['imdb_akas'] ?? [], $row['tmdb_akas'] ?? [], $tvdb_akas),
      'rt_score'         => $rt_score,
      'rt_url'           => $rt_url,
      'mc_score'         => $mc_score,
      'mc_url'           => $mc_url,
      'tmdb_url'         => $tmdb_url,
      'directors'=>[], 'writers'=>[], 'producers'=>[], 'composers'=>[], 'cinematographers'=>[], 'cast'=>[], // Placeholders
    ];
}

/* ------------------------------------------------------------------
 * פונקציות עזר (מבנה מלא)
 * ------------------------------------------------------------------ */
if (!function_exists('get_full_credits_csv')) {
    function get_full_credits($tt) {
        $out = [ 'director'=>[], 'writer'=>[], 'producer'=>[], 'composer'=>[], 'cinematographer'=>[], 'cast'=>[] ];
        try {
            if (!class_exists('imdb')) { if (file_exists(__DIR__ . '/imdb.class.php')) include_once __DIR__ . '/imdb.class.php'; }
            if (class_exists('imdb')) {
                $IMDB = new imdb($tt);
                $out['director']       = extract_imdb_directors($IMDB, $tt);
                $out['writer']         = extract_imdb_writers($IMDB, $tt);
                $out['producer']       = extract_imdb_producers($tt);
                $out['composer']       = extract_imdb_composers($IMDB, $tt);
                $out['cinematographer']= extract_imdb_cinematographers($tt);
                $out['cast']           = extract_imdb_cast_names($IMDB, $tt);
            }
        } catch (Throwable $e) {}
        return $out;
    }
    function to_csv_names($arr){ if (!$arr) return null; $seen=[]; $out=[]; foreach ((array)$arr as $x){ $name = is_array($x) ? ($x['name'] ?? ($x['title'] ?? ($x['value'] ?? null))) : $x; $t = trim((string)$name); if ($t==='' || $t === 'n/A') continue; $k = mb_strtolower(preg_replace('~\s+~u',' ',$t),'UTF-8'); if (!isset($seen[$k])){ $seen[$k]=1; $out[]=$t; } } return $out ? implode(', ', $out) : null; }
    function get_full_credits_csv($tt){ $c = get_full_credits($tt); return [ 'directors'=>to_csv_names($c['director']??[]), 'writers'=>to_csv_names($c['writer']??[]), 'producers'=>to_csv_names($c['producer']??[]), 'composers'=>to_csv_names($c['composer']??[]), 'cinematographers'=>to_csv_names($c['cinematographer']??[]), 'cast'=>to_csv_names($c['cast']??[]), ]; }
}
function to_csv_val($arr){ if(!$arr) return null; $vals=[]; foreach((array)$arr as $x){ if (is_array($x) || is_object($x)) $x = json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $t=trim((string)$x); if($t!=='') $vals[]=$t; } $vals=array_values(array_unique($vals)); return $vals?implode(', ',$vals):null; }
function H($v){ if (is_array($v)) { $csv = to_csv_val($v); $v = $csv !== null ? $csv : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } elseif (is_object($v)) { $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
function dbg_to_s($v): string { if ($v === null) return ''; if (is_scalar($v)) return (string)$v; if (is_array($v)) { $flat = []; foreach ($v as $x) $flat[] = is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); return implode(', ', $flat); } return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
function safe_s($v): string { return H(dbg_to_s($v)); }
function parse_runtime_to_minutes($str) { if (!$str) return null; $h = 0; $m = 0; if (preg_match('/(\d+)\s*(?:h|hr|hour)/i', $str, $h_match)) { $h = (int)$h_match[1]; } if (preg_match('/(\d+)\s*(?:m|min|minute)/i', $str, $m_match)) { $m = (int)$m_match[1]; } if ($h > 0 || $m > 0) return ($h * 60) + $m; $numeric_val = (int)preg_replace('/\D/', '', $str); return $numeric_val > 0 ? $numeric_val : null;}
function map_u_to_posters_fields(array $u, array $raw_row): array { $m = []; $add = function($col,$val) use (&$m){ if (is_array($val) || is_object($val)) $val = to_csv_val($val) ?? json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $m[$col]=$val; }; $add('imdb_id', $u['imdb_id'] ?? null); $add('title_en', $u['display_title'] ?? ($u['title_en'] ?? null)); $add('original_title', $u['original_title'] ?? ($raw_row['original_title'] ?? null)); $add('title_he', $u['he_title'] ?? null); $add('year', isset($u['year'])?(string)$u['year']:null); $is_tv=!empty($u['is_tv']);$add('is_tv',$is_tv?1:0);$add('type_id',$is_tv?4:3); $add('poster_url', $u['poster'] ?? null); $add('image_url',  $u['poster'] ?? null); $add('poster',     $u['poster'] ?? null); $tr = $u['trailer'] ?? ($u['trailer_url'] ?? ($u['youtube_trailer'] ?? null)); $add('trailer_url', $tr); $add('youtube_trailer', $tr); $add('tvdb_url', $u['tvdb_url'] ?? null); if (isset($u['tvdb_id'])) $add('tvdb_id', (string)$u['tvdb_id']); $add('tmdb_url', $u['tmdb_url'] ?? null); $add('overview_he', $u['overview_he'] ?? null); $add('overview_en', $u['overview_en'] ?? null); $add('genres', to_csv_val($u['genres'] ?? null)); $add('languages', to_csv_val($u['languages'] ?? null)); $add('countries', to_csv_val($u['countries'] ?? null)); $runtime_minutes = parse_runtime_to_minutes($u['runtime'] ?? null); $add('runtime', $runtime_minutes); $add('runtime_minutes', $runtime_minutes); $add('directors', to_csv_val($u['directors'] ?? null)); $add('writers', to_csv_val($u['writers'] ?? null)); $add('producers', to_csv_val($u['producers'] ?? null)); $add('composers', to_csv_val($u['composers'] ?? null)); $add('cinematographers', to_csv_val($u['cinematographers'] ?? null)); $add('cast', to_csv_val($u['cast'] ?? null)); if (empty($u['networks']) && !empty($u['network'])) $u['networks'] = [$u['network']]; $add('networks', to_csv_val($u['networks'] ?? null)); $add('seasons_count',  isset($u['seasons'])  ? (string)(int)$u['seasons']  : null); $add('episodes_count', isset($u['episodes']) ? (string)(int)$u['episodes'] : null); $add('imdb_rating', isset($u['imdb_rating']) ? (string)$u['imdb_rating'] : null); $add('imdb_votes',  isset($u['imdb_votes'])  ? (string)$u['imdb_votes']  : null); $add('rt_score', isset($u['rt_score']) ? (string)$u['rt_score'] : null); $add('rt_url',   $u['rt_url'] ?? null); $add('mc_score', isset($u['mc_score']) ? (string)$u['mc_score'] : null); $add('mc_url',   $u['mc_url'] ?? null); return $m;}
function db_get_columns(mysqli $conn, $table){ $cols=[]; $res=$conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($table)."`"); if($res) while($r=$res->fetch_assoc()){ $cols[]=$r['Field']; } return $cols;}
function db_find_existing_row(mysqli $conn, $table, $pkField, $pkValue){ $stmt=$conn->prepare("SELECT * FROM `{$table}` WHERE `{$pkField}` = ? LIMIT 1"); if(!$stmt) return null; $stmt->bind_param('s',$pkValue); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close(); return $row?:null;}
function db_scalarize($v) { if (is_array($v)) { $csv = to_csv_val($v); return $csv !== null ? $csv : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } if (is_bool($v)) return $v ? '1' : '0'; if (is_object($v)) return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); return $v;}
function upsert_row(mysqli $conn, string $table, array $data, string $pkField, string $dup_mode = 'skip'): array { if (empty($data[$pkField])) return ['ok'=>false,'error'=>'Empty primary key','id'=>0,'action'=>'error']; $pkValue = $data[$pkField]; $existing_row = db_find_existing_row($conn, $table, $pkField, $pkValue); $table_cols = db_get_columns($conn, $table); $data = array_filter($data, fn($k)=>in_array($k, $table_cols, true), ARRAY_FILTER_USE_KEY); if ($existing_row) { $poster_id = (int)$existing_row['id']; if ($dup_mode === 'skip') return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'skipped']; if ($dup_mode === 'update-missing') { foreach ($existing_row as $k=>$v) { if (!empty($v) && $v !== '0' && isset($data[$k])) unset($data[$k]); } } if (count($data) <= 1) return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'skipped (nothing to update)']; $data = array_map('db_scalarize', $data); unset($data[$pkField]); $set=[]; $types=''; $vals=[]; foreach($data as $k=>$v){ if($v!==null){$set[]="`{$k}`=?";$types.='s';$vals[]=$v;} } if(empty($set)){return ['ok'=>true,'id'=>$poster_id,'action'=>'skipped (no data to update)'];} $vals[]=$pkValue; $types.='s'; $sql="UPDATE `{$table}` SET ".implode(', ',$set)." WHERE `{$pkField}` = ?"; $st=$conn->prepare($sql); if(!$st) return ['ok'=>false,'error'=>'Prepare failed: '.$conn->error,'id'=>$poster_id,'action'=>'error']; $st->bind_param($types, ...$vals); if($st->execute()){ $st->close(); return ['ok'=>true,'error'=>null,'id'=>$poster_id,'action'=>'updated']; } $err=$st->error; $st->close(); return ['ok'=>false,'error'=>'Update failed: '.$err,'id'=>$poster_id,'action'=>'error']; } else { $data = array_map('db_scalarize', array_filter($data, fn($v) => $v !== null)); $cols=array_keys($data); $ph=implode(',', array_fill(0,count($cols),'?')); $sql="INSERT INTO `{$table}` (`".implode('`,`',$cols)."`) VALUES ({$ph})"; $st=$conn->prepare($sql); if(!$st) return ['ok'=>false,'error'=>'Prepare failed: '.$conn->error,'id'=>0,'action'=>'error']; $types=str_repeat('s', count($cols)); $st->bind_param($types, ...array_values($data)); if($st->execute()){ $id=$conn->insert_id; $st->close(); return ['ok'=>true,'error'=>null,'id'=>$id,'action'=>'inserted']; } $err=$st->error; $st->close(); return ['ok'=>false,'error'=>'Insert failed: '.$err,'id'=>0,'action'=>'error']; }}
function replace_akas(mysqli $db, int $poster_id, string $imdb_id, array $akas): int { if ($poster_id <= 0) return 0; $list = array_values(array_unique(array_filter(array_map('trim',(array)$akas)))); if ($stmt_delete = $db->prepare("DELETE FROM poster_akas WHERE poster_id = ?")) { $stmt_delete->bind_param("i", $poster_id); $stmt_delete->execute(); $stmt_delete->close(); } if (empty($list)) return 0; $sql = "INSERT IGNORE INTO poster_akas (poster_id, aka_title, imdb_id) VALUES (?, ?, ?)"; $stmt = $db->prepare($sql); if (!$stmt) return 0; $saved = 0; foreach ($list as $aka_title) { $stmt->bind_param("iss", $poster_id, $aka_title, $imdb_id); if ($stmt->execute()) $saved += $stmt->affected_rows; } $stmt->close(); return $saved;}
function sync_connections(mysqli $db, int $poster_id, string $source_imdb_id, array $connections_map): int { if ($poster_id <= 0) return 0; $flat=[]; $seen=[]; foreach ((array)$connections_map as $label => $items) { if (!is_array($items) || empty($items)) continue; foreach ($items as $it) { $tt = trim(dbg_to_s($it['id'] ?? '')); $title = trim(dbg_to_s($it['title'] ?? '')); if ($tt==='' || $title==='') continue; $k = $label.'|'.$tt; if (isset($seen[$k])) continue; $seen[$k]=true; $flat[]=['label'=>$label,'tt'=>$tt,'title'=>$title]; } } $db->begin_transaction(); try { if ($del = $db->prepare("DELETE FROM poster_connections WHERE poster_id = ?")) { $del->bind_param("i", $poster_id); $del->execute(); $del->close(); } if (empty($flat)) { $db->commit(); return 0; } $sql = "INSERT IGNORE INTO poster_connections (poster_id, relation_label, related_imdb_id, related_title, imdb_id) VALUES (?, ?, ?, ?, ?)"; $st = $db->prepare($sql); if (!$st) throw new Exception("Connections SQL Prepare Failed: ".$db->error); $saved=0; foreach ($flat as $row) { $st->bind_param("issss", $poster_id, $row['label'], $row['tt'], $row['title'], $source_imdb_id); if(!$st->execute()) throw new Exception("Connections Execute Failed: ".$st->error); if($st->affected_rows>0) $saved++; } $st->close(); $db->commit(); return $saved; } catch (Throwable $e) { $db->rollback(); throw $e; }}
function parse_ids($raw_input): array { preg_match_all('~(tt\d{6,12})~', (string)$raw_input, $m); return array_values(array_unique($m[1]??[])); }

/* ========= POST ========= */
$done=false; $results=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $raw_input = $_POST['ids'] ?? '';
  if (isset($_FILES['id_file']) && !empty($_FILES['id_file']['tmp_name']) && $_FILES['id_file']['error']===UPLOAD_ERR_OK) { $fileText = file_get_contents($_FILES['id_file']['tmp_name']); if ($fileText !== false && $fileText !== '') $raw_input = $fileText; }
  $dup_mode = $_POST['dup_mode'] ?? 'skip';
  $ids = parse_ids($raw_input);

  if(!$ids){
    $results[]=['tt'=>'N/A','ok'=>false,'error'=>'לא נמצאו מזהי IMDb תקינים בקלט שהוזן.'];
    $done=true;
  } else {
    foreach($ids as $tt){
      $current=['tt'=>$tt,'ok'=>true,'error'=>null,'action'=>'', 'poster_id'=>0,'akas_saved'=>0, 'conn_saved'=>0];
      try{
        // שלב 1: איסוף ואיחוד נתונים עם לוגיקה מותאמת
        $rawRow = build_row($tt, $TMDB_KEY, $RAPIDAPI_KEY, $TVDB_KEY, $OMDB_KEY);
        $U = unify_details_v2($rawRow, $TMDB_KEY, $TVDB_KEY, $OMDB_KEY);
        
        // TVDB Assist — ממלא חסרים (רץ רק אם unify_details_v2 לא מצא מזהה TVDB)
        if ($U['is_tv'] && empty($U['tvdb_id']) && !empty($TVDB_KEY) && function_exists('tvdb_full_fetch_and_merge')) {
            $U = tvdb_full_fetch_and_merge($tt, $TVDB_KEY, ($TVDB_PIN ?? null), $U);
        }

        // ✅ שלב 2: דריסה עם צוות ושחקנים מ-IMDb בלבד
        $U = array_merge($U, get_full_credits_csv($tt));

        // שלב 3: מיפוי לשדות המסד ושמירה
        $mapped = map_u_to_posters_fields($U, $rawRow);
        $res = upsert_row($conn,'posters',$mapped,'imdb_id',$dup_mode);
        if(!$res['ok']) throw new Exception($res['error']);
        $current['poster_id']=(int)$res['id']; $current['action']=$res['action'];

        // שלב 4: שמירת AKAs ו-Connections
        if ($current['poster_id']>0) {
          $current['akas_saved'] = replace_akas($conn, $current['poster_id'], $tt, $U['akas'] ?? []);
          if(function_exists('imdb_connections_all')) {
              $map = imdb_connections_all($tt);
              unset($map['_source']);
              $current['conn_saved'] = sync_connections($conn, $current['poster_id'], $tt, $map);
          }
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
  <title>ייבוא פוסטרים (מאוחד) — IMDb Only Crew</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --line:#22283a; --accent:#5b8cff; --ok:#6fffbe; --err:#ff7d7d; --warn:#f59e0b;}
    *{box-sizing:border-box} body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;direction:rtl;background:var(--bg);color:var(--text);margin:0;padding:24px} .wrap{max-width:980px;margin:0 auto} .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px} textarea{width:100%;min-height:150px;border:1px solid var(--line);background:#0f1422;color:var(--text);border-radius:10px;padding:10px} .btn{background:var(--accent);color:#fff;border:none;border-radius:10px;padding:10px 16px;cursor:pointer;font-weight:700} .summary{margin-top:18px;} .res{border:1px solid var(--line); padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; } .res-ok{border-left: 4px solid var(--ok); background: #182928; } .res-err{border-left: 4px solid var(--err); background: #2d1c24; } .res-skip{border-left: 4px solid var(--warn); background: #382e1c;}
    input[type="file"] { background: var(--chip); border: 1px solid var(--line); border-radius: 8px; padding: 8px; color: var(--text); }
    body {background-color:#161b26 !important; text-align: right !important;}
    .center-text { text-align: center; width: 100%; } .form-group { margin-bottom: 16px; } .form-group label { display: block; margin-bottom: 6px; font-size: 14px; }
    select { width: 100%; background: #0f1320; color: #e7ecff; border: 1px solid #2a3148; border-radius: 10px; padding: 10px; outline: none; font-family: inherit; font-size: 14px; }
    .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; align-items: end; }
    .content a  {color: #6E8BFC !important;}
  </style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 16px">ייבוא פוסטרים (מאוחד) — IMDb Only Crew</h1>
  <div class="card">
    <form method="post" action="" enctype="multipart/form-data">
      <label for="ids">הדבק מזהי IMDb או לינקים (למשל: tt1013752):</label>
      <textarea name="ids" id="ids"><?= safe_s($_POST['ids']??''); ?></textarea>
      <div class="grid" style="margin-top:12px">
        <div><label for="id_file">או העלה TXT/CSV:</label><input type="file" name="id_file" id="id_file" accept=".txt,.csv" style="width:100%"></div>
        <div><label for="dup_mode">מנוע כפילויות:</label>
          <select name="dup_mode" id="dup_mode">
            <option value="upsert" <?= (($_POST['dup_mode']??'')==='upsert')?'selected':''; ?>>עדכן/דרוס ערכים קיימים</option>
            <option value="update-missing" <?= (($_POST['dup_mode']??'')==='update-missing')?'selected':''; ?>>השלמת שדות חסרים בלבד</option>
            <option value="skip" <?= (!isset($_POST['dup_mode']) || ($_POST['dup_mode']??'')==='skip')?'selected':''; ?>>דלג אם קיים</option>
          </select>
        </div>
      </div>
      <div style="text-align:center; margin-top:16px"><button class="btn" type="submit">ייבוא</button></div>
    </form>
  </div>

  <?php if ($done): ?>
    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 10px">תוצאות:</h3>
      <?php foreach ($results as $r): ?>
        <div class="res <?= empty($r['ok']) ? 'res-err' : (str_starts_with($r['action'],'skipped') ? 'res-skip' : 'res-ok'); ?>">
          <div><b><?= safe_s($r['tt']) ?></b> — <?= safe_s($r['action']) ?></div>
          <?php if(!empty($r['error'])): ?>
            <div style="color:#ffb3b3; font-size:13px; margin-top:4px; white-space:pre-wrap; direction:ltr; text-align:left">Error:<br><?= H($r['error']) ?></div>
          <?php endif; ?>
          <?php if($r['poster_id']>0): ?>
            <div style="font-size:13px; color:var(--muted); margin-top:4px">
              Poster ID: <?= (int)$r['poster_id'] ?> |
              AKAs Saved: <?= (int)($r['akas_saved'] ?? 0) ?> |
              Connections Saved: <?= (int)($r['conn_saved'] ?? 0) ?>
              &nbsp;|&nbsp;<a href="poster.php?id=<?= (int)$r['poster_id'] ?>" target="_blank">פתח פוסטר</a>
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