<?php
/****************************************************
 * auto-add.php — ייבוא אוטומטי ומתוקן ל-DB
 * BUILD v14: Changed default duplicate handling to "Skip"
 ****************************************************/
set_time_limit(3000000);
mb_internal_encoding('UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

include 'header.php';
require_once __DIR__ . '/server.php';

ob_start();
if (file_exists(__DIR__ . '/imdb.php')) {
    require_once __DIR__ . '/imdb.php'; // מספק build_row, unify_details, imdb_connections_all וכו'
}
ob_end_clean();

if (!isset($conn) || !($conn instanceof mysqli)) { die('DB connection failed'); }
$conn->set_charset('utf8mb4');

/* ========= פונקציות עזר כלליות ========= */
function H($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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
    if ($h > 0 || $m > 0) { return ($h * 60) + $m; }
    $numeric_val = (int)preg_replace('/\D/', '', $str);
    return $numeric_val > 0 ? $numeric_val : null;
}

/* ========= פונקציית מיפוי נתונים ========= */
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
  $add('image_url', $u['poster'] ?? null);
  $add('poster', $u['poster'] ?? null);
  $add('trailer_url', $u['trailer'] ?? null);
  $add('youtube_trailer', $u['trailer'] ?? null);
  $add('tvdb_url', $u['tvdb_url'] ?? null);
  $add('tmdb_url', $u['tmdb_url'] ?? null);
  $add('overview_he', $u['overview_he'] ?? null);
  $add('overview_en', $u['overview_en'] ?? null);
  $add('genres', to_csv_val($u['genres'] ?? null));
  $add('languages', to_csv_val($u['languages'] ?? null));
  $add('countries', to_csv_val($u['countries'] ?? null));
  $runtime_minutes = parse_runtime_to_minutes($u['runtime'] ?? null);
  $add('runtime', $runtime_minutes);
  $add('runtime_minutes', $runtime_minutes);
  $add('directors', to_csv_val($u['directors'] ?? null));
  $add('writers', to_csv_val($u['writers'] ?? null));
  $add('producers', to_csv_val($u['producers'] ?? null));
  $add('composers', to_csv_val($u['composers'] ?? null));
  $add('cinematographers', to_csv_val($u['cinematographers'] ?? null));
  $add('cast', to_csv_val($u['cast'] ?? null));
  $add('networks', to_csv_val($u['networks'] ?? null));
  $add('imdb_rating', isset($u['imdb_rating']) ? (string)$u['imdb_rating'] : null);
  $add('imdb_votes', isset($u['imdb_votes'])  ? (string)$u['imdb_votes']  : null);
  $add('rt_score', isset($u['rt_score']) ? (string)$u['rt_score'] : null);
  $add('rt_url', $u['rt_url'] ?? null);
  $add('mc_score', isset($u['mc_score']) ? (string)$u['mc_score'] : null);
  $add('mc_url', $u['mc_url'] ?? null);
  $add('seasons_count', $u['seasons'] ?? null);
  $add('episodes_count', $u['episodes'] ?? null);
  return $m;
}

/* ========= פונקציות DB משודרגות ========= */
function db_get_columns(mysqli $conn, $table){
  $cols=[];
  $res=$conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($table)."`");
  if($res) while($r=$res->fetch_assoc()){ $cols[] = $r['Field']; }
  return $cols;
}
function db_find_existing_row(mysqli $conn, $table, $pkField, $pkValue){
    $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$pkField}` = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $pkValue);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}
function upsert_row(mysqli $conn, string $table, array $data, string $pkField, string $dup_mode = 'upsert'): array {
    if (empty($data[$pkField])) return ['ok' => false, 'error' => 'Empty primary key', 'id' => 0, 'action' => 'error'];

    $pkValue = $data[$pkField];
    $existing_row = db_find_existing_row($conn, $table, $pkField, $pkValue);
    $table_cols = db_get_columns($conn, $table);
    $data = array_filter($data, fn($k) => in_array($k, $table_cols), ARRAY_FILTER_USE_KEY);

    if ($existing_row) {
        $poster_id = $existing_row['id'];
        if ($dup_mode === 'skip') {
            return ['ok' => true, 'error' => null, 'id' => $poster_id, 'action' => 'skipped'];
        }

        if ($dup_mode === 'update-missing') {
            foreach ($existing_row as $key => $value) {
                if (!empty($value) && $value != '0' && isset($data[$key])) {
                    unset($data[$key]);
                }
            }
        }

        if (empty($data) || count($data) <= 1) {
             return ['ok' => true, 'error' => null, 'id' => $poster_id, 'action' => 'skipped (nothing to update)'];
        }

        unset($data[$pkField]);
        $set_parts = []; $types = ''; $values = [];
        foreach ($data as $col => $val) {
            $set_parts[] = "`{$col}` = ?"; $types .= 's'; $values[] = $val;
        }
        $values[] = $pkValue; $types .= 's';

        $sql = "UPDATE `{$table}` SET " . implode(', ', $set_parts) . " WHERE `{$pkField}` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
            $stmt->close();
            return ['ok' => true, 'error' => null, 'id' => $poster_id, 'action' => 'updated'];
        } else {
            $error = $stmt->error; $stmt->close();
            return ['ok' => false, 'error' => 'Update failed: ' . $error, 'id' => $poster_id, 'action' => 'error'];
        }
    } else {
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES ({$placeholders})";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($data));
        $stmt->bind_param($types, ...array_values($data));

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            return ['ok' => true, 'error' => null, 'id' => $new_id, 'action' => 'inserted'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'error' => 'Insert failed: ' . $error, 'id' => 0, 'action' => 'error'];
        }
    }
}

/* ========= כתיבה של AKAs (מותאם לסכמת poster_akas) ========= */
function replace_akas(mysqli $db, int $poster_id, string $imdb_id, array $akas): int {
    if ($poster_id <= 0) return 0;

    // ניקוי/ייחוד
    $list = [];
    foreach ((array)$akas as $aka) {
        $t = trim((string)$aka);
        if ($t === '' || $t === '-' || $t === '—') continue;
        $list[] = $t;
    }
    $list = array_values(array_unique($list));

    $db->begin_transaction();
    try {
        // מחיקה קודמת לפוסטר
        if ($stmt_delete = $db->prepare("DELETE FROM poster_akas WHERE poster_id = ?")) {
            $stmt_delete->bind_param("i", $poster_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }

        if (empty($list)) { $db->commit(); return 0; }

        // הכנסת כל השדות הנדרשים לפי הסכמה
        $sql = "INSERT INTO poster_akas 
                (poster_id, aka_title, aka_lang, source, aka, sort_order, imdb_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt) { $db->rollback(); return 0; }

        $saved = 0; $i = 0;
        foreach ($list as $aka_title) {
            $i++;
            $aka_lang = null;        // אין לנו שפה — נשאיר NULL
            $source   = 'imdb';      // לפי הדרישה
            $aka      = $aka_title;  // לפי הסכמה, שדה נוסף ל-UNIQUE
            $stmt->bind_param("issssis", $poster_id, $aka_title, $aka_lang, $source, $aka, $i, $imdb_id);
            if ($stmt->execute()) $saved++;
        }
        $stmt->close();
        $db->commit();
        return $saved;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/* ========= כתיבה של Connections (מותאם לסכמת poster_connections) ========= */
function sync_connections(mysqli $db, int $poster_id, string $source_imdb_id, array $connections_map): int {
    if ($poster_id <= 0) return 0;

    // flatten/clean
    $flat = [];
    foreach ((array)$connections_map as $label => $items) {
        if (empty($items) || !is_array($items)) continue;
        foreach ($items as $it) {
            $tt = trim((string)($it['id'] ?? ''));
            $title = trim((string)($it['title'] ?? ''));
            if ($tt === '' || $title === '') continue;
            $flat[] = ['label' => $label, 'tt' => $tt, 'title' => $title];
        }
    }

    $db->begin_transaction();
    try {
        if ($stmt_delete = $db->prepare("DELETE FROM poster_connections WHERE poster_id = ?")) {
            $stmt_delete->bind_param("i", $poster_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }

        if (empty($flat)) { $db->commit(); return 0; }

        // הכנסת כל השדות הנדרשים לפי הסכמה
        $sql = "INSERT INTO poster_connections
                (poster_id, relation_label, conn_title, related_imdb_id, conn_imdb_id, related_title, relation_type, imdb_id, source, kind, target_tt, target_title)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt) { $db->rollback(); return 0; }

        $saved = 0;
        foreach ($flat as $row) {
            $label = (string)$row['label'];
            $tt    = (string)$row['tt'];
            $title = (string)$row['title'];

            $conn_title    = $title;         // אפשר להשאיר זהה
            $related_imdb  = $tt;
            $conn_imdb_id  = $tt;            // לפי ה־UNIQUE
            $related_title = $title;
            $relation_type = $label;         // ע"פ enum שלך
            $source        = 'imdb';
            $kind          = $label;         // שדה טקסטואלי אצלך
            $target_tt     = $tt;
            $target_title  = $title;

            $stmt->bind_param(
                "isssssssssss",
                $poster_id, $label, $conn_title, $related_imdb, $conn_imdb_id,
                $related_title, $relation_type, $source_imdb_id, $source,
                $kind, $target_tt, $target_title
            );
            if ($stmt->execute()) $saved++;
        }

        $stmt->close();
        $db->commit();
        return $saved;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/* ========= פירוק קלט מזהי tt ========= */
function parse_ids($raw_input): array {
    $out = [];
    $items = preg_split('~[\s,;]+~', $raw_input, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($items as $item) {
        if (preg_match('~(tt\d{6,10})~', $item, $matches)) {
            $out[] = $matches[1];
        }
    }
    return array_values(array_unique($out));
}

/* ========= לוגיקת POST ========= */
$done=false; $results=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $raw_input = '';
  if (isset($_FILES['id_file']) && $_FILES['id_file']['error'] === UPLOAD_ERR_OK) {
      $raw_input = file_get_contents($_FILES['id_file']['tmp_name']);
  }
  if (empty($raw_input)) {
      $raw_input = $_POST['ids'] ?? '';
  }

  // ברירת מחדל: skip
  $dup_mode = $_POST['dup_mode'] ?? 'skip';

  $ids = parse_ids($raw_input);
  if(!$ids){
      $results[]=['tt'=>'N/A', 'ok'=>false, 'error'=>'לא נמצאו מזהי IMDb תקינים בקלט שהוזן.'];
  } else {
    foreach($ids as $tt){
      $current_res = ['tt'=>$tt, 'ok'=>true, 'error'=>null];
      try{
        if(!function_exists('build_row')){ throw new Exception("Function build_row is not defined. Check imdb.php include.");}
        // בניית נתונים גולמיים ממקורות
        $rawRow = build_row($tt, $TMDB_KEY, $RAPIDAPI_KEY);
        // איחוד לשכבת תצוגה
        $U = unify_details($rawRow, $TMDB_KEY, $TVDB_KEY);
        if(empty($U['imdb_id'])){ throw new Exception("לא נמצאו נתונים בסיסיים."); }

        // ניקוי תצוגה של Display Title (AKA דופליקטים)
        if (isset($U['display_title']) && is_string($U['display_title'])) {
            $parts = preg_split('~\s+AKA\s+~i', $U['display_title']);
            $parts = array_filter(array_map('trim', $parts));
            $U['display_title'] = implode(' AKA ', array_unique($parts));
        }

        // מיפוי לעמודת posters
        $mapped = map_u_to_posters_fields($U, $rawRow);

        // UPSERT לטבלת posters
        $res = upsert_row($conn, 'posters', $mapped, 'imdb_id', $dup_mode);
        if(!$res['ok']){ throw new Exception($res['error']); }

        $poster_id = (int)$res['id'];
        $current_res['action'] = $res['action'];
        $current_res['poster_id'] = $poster_id;

        // AKAs מתוך $U
        $akasFetched = $U['akas'] ?? [];
        $current_res['akas_count'] = is_array($akasFetched) ? count($akasFetched) : 0;

        // Connections — נשלפים ישירות מ-IMDb וממוינים ע"י imdb.php
        $connectionsFetched = imdb_connections_all($tt);
        $current_res['conn_count'] = array_sum(array_map('count', array_filter($connectionsFetched, 'is_array')));

        // שמירה לטבלאות המשנה כאשר לא דילגנו
        if ($poster_id > 0 && strpos((string)$res['action'], 'skipped') === false) {
            replace_akas($conn, $poster_id, $tt, $akasFetched);
            sync_connections($conn, $poster_id, $tt, $connectionsFetched);
        }
      } catch(Throwable $e){
        $current_res['ok'] = false;
        $current_res['error'] = $e->getMessage();
      }
      $results[] = $current_res;
    }
  }
  $done=true;
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>ייבוא פוסטרים אוטומטי</title>
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
  <h1 style="margin:0 0 16px">ייבוא פוסטרים אוטומטי</h1>
  <div class="card">
    <form method="post" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label for="ids">הדבק מזהי IMDb או לינקים:</label>
            <textarea id="ids" name="ids" placeholder="tt0111161, tt2576852&#10;https://www.imdb.com/title/tt6806448/"><?php echo htmlspecialchars($_POST['ids']??'',ENT_QUOTES,'UTF-8'); ?></textarea>
        </div>

        <hr style="border-color: var(--line); margin: 20px 0;">

        <div class="grid">
            <div class="form-group">
                <label for="id_file">העלה קובץ TXT/CSV עם מזהים:</label>
                <input type="file" name="id_file" id="id_file" accept=".txt,.csv" style="width: 100%;">
            </div>

            <div class="form-group">
                <label for="dup_mode">מנוע כפילויות:</label>
                <select name="dup_mode" id="dup_mode">
                  <option value="upsert">עדכן/דרוס ערכים קיימים</option>
                  <option value="update-missing">השלמת שדות חסרים בלבד</option>
                  <option value="skip" selected>דלג אם קיים</option>
                </select>
            </div>
        </div>

        <div class="center-text" style="margin-top:24px">
            <button class="btn" type="submit">ייבוא</button>
        </div>
    </form>
  </div>

  <?php if($done): ?>
    <div class="summary">
      <h3 style="margin-top:24px;">תוצאות ייבוא:</h3>
      <?php foreach($results as $res): ?>
        <?php
            $status_class = 'res-ok';
            $status_text = $res['action'] ?? 'הצלחה';
            if (!$res['ok']) {
                $status_class = 'res-err';
                $status_text = 'כישלון';
            } elseif (strpos((string)$status_text, 'skipped') !== false) {
                $status_class = 'res-skip';
                $status_text = 'דילוג';
            }
        ?>
        <div class="res <?= $status_class ?>">
          <strong><?= H($res['tt']) ?>:</strong>
          <?= H(ucfirst((string)$status_text)) ?>
          <?php if(!empty($res['error'])): ?>
            <div style="font-size:13px; color: var(--err); margin-top:4px;">שגיאה: <?= H($res['error']) ?></div>
          <?php endif; ?>
          <?php if(!empty($res['ok']) && !empty($res['poster_id'])): ?>
            <div style="font-size:13px; color: var(--muted); margin-top:4px;">
              <a href="poster.php?id=<?= H($res['poster_id']) ?>" target="_blank">הצג פוסטר</a> |
              AKAs: <?= H((string)($res['akas_count'] ?? 0)) ?> |
              Connections: <?= H((string)($res['conn_count'] ?? 0)) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
