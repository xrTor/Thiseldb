<?php
ob_start();
require_once 'server.php';

// ======================== AJAX HANDLER (שליפה ומיזוג) ========================
if (isset($_GET['ajax'])) {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    header('Content-Type: application/json; charset=utf-8');

    function _json_out($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
    function _http($url, $headers=[]) {
        $ctx = stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,'header'=>implode("\r\n",$headers)]]);
        return @file_get_contents($url, false, $ctx);
    }
    
    // בדיקת עברית
    function is_hebrew($str) {
        return preg_match('/\p{Hebrew}/u', (string)$str);
    }

    // המרת קודי מדינות/שפות נפוצים לשמות מלאים
    function map_code_to_name($code, $type='country') {
        $code = trim($code);
        if(!$code) return '';
        
        // אם הקוד ארוך מ-3 תווים, כנראה שזה כבר שם מלא
        if(strlen($code) > 3) return $code;

        $lowerCode = strtolower($code);
        
        $countries = [
            'isr'=>'Israel', 'il'=>'Israel', 'usa'=>'USA', 'us'=>'USA', 
            'gbr'=>'United Kingdom', 'uk'=>'United Kingdom', 'fra'=>'France', 
            'deu'=>'Germany', 'esp'=>'Spain', 'ita'=>'Italy', 'jpn'=>'Japan'
        ];
        $langs = [
            'heb'=>'Hebrew', 'he'=>'Hebrew', 'eng'=>'English', 'en'=>'English',
            'fra'=>'French', 'spa'=>'Spanish', 'deu'=>'German', 'ita'=>'Italian',
            'jpn'=>'Japanese', 'rus'=>'Russian', 'ara'=>'Arabic'
        ];
        
        $map = ($type=='country') ? $countries : $langs;
        return $map[$lowerCode] ?? strtoupper($code);
    }

    // מפתחות API
    $tmdbKey = defined('TMDB_KEY') ? TMDB_KEY : '';
    $tvdbKey = defined('TVDB_KEY') ? TVDB_KEY : '';

    // --- TVDb Helper ---
    function _tvdb_token($key) {
        if(isset($_SESSION['tvdb_token']) && isset($_SESSION['tvdb_token_exp']) && $_SESSION['tvdb_token_exp'] > time()) {
            return $_SESSION['tvdb_token'];
        }
        
        $ch = curl_init('https://api4.thetvdb.com/v4/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1, 
            CURLOPT_POST => 1, 
            CURLOPT_POSTFIELDS => json_encode(['apikey'=>$key]), 
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $out = curl_exec($ch); 
        curl_close($ch);
        
        $j = json_decode($out, true);
        if(isset($j['data']['token'])) {
            $_SESSION['tvdb_token'] = $j['data']['token'];
            $_SESSION['tvdb_token_exp'] = time() + 86000;
            return $j['data']['token'];
        }
        return null;
    }

    // מיזוג רשימות
    function merge_lists($arr1, $arr2) {
        $res = $arr1;
        $existing = [];
        foreach($arr1 as $v) $existing[mb_strtolower(trim($v))] = true;
        foreach($arr2 as $v) {
            $clean = trim($v);
            if(!isset($existing[mb_strtolower($clean)]) && $clean !== '') {
                $res[] = $clean;
                $existing[mb_strtolower($clean)] = true;
            }
        }
        return $res;
    }

    $act = $_GET['ajax'] ?? '';
    $input = trim($_GET['input'] ?? '');

    // ---------------------- TVDB FETCH (ישיר) ----------------------
    if ($act === 'tvdb') {
        if (!$tvdbKey) _json_out(['ok'=>false, 'err'=>'חסר מפתח TVDb']);
        
        $tvdbId = $input;
        $isSlug = false;

        // זיהוי ID או Slug
        if (preg_match('~/series/(\d+)(?:-.*)?~i', $input, $m)) { $tvdbId = $m[1]; } 
        elseif (preg_match('~^(\d+)$~', $input, $m)) { $tvdbId = $m[1]; }
        elseif (preg_match('~/series/([^/\?]+)~i', $input, $m)) { $tvdbId = $m[1]; $isSlug = true; }
        elseif (!is_numeric($input) && trim($input)!=='') { $tvdbId = trim($input); $isSlug = true; }

        $token = _tvdb_token($tvdbKey);
        if (!$token) _json_out(['ok'=>false, 'err'=>'כישלון בהתחברות ל-TVDb']);
        $headers = ["Authorization: Bearer $token", "Accept: application/json"];

        if ($isSlug) {
            $slugRes = json_decode(_http("https://api4.thetvdb.com/v4/series/slug/" . urlencode($tvdbId), $headers), true);
            if (isset($slugRes['data']['id'])) { $tvdbId = $slugRes['data']['id']; } 
            else { _json_out(['ok'=>false, 'err'=>'לא נמצא מזהה עבור ה-Slug הזה ב-TVDb']); }
        }

        // שליפות מקבילות
        $base = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdbId", $headers), true);
        if (!$base || !isset($base['data'])) _json_out(['ok'=>false, 'err'=>'לא נמצא ב-TVDb']);
        
        $ext = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdbId/extended", $headers), true);
        $transHe = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdbId/translations/heb", $headers), true);
        $transEn = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdbId/translations/eng", $headers), true);

        $b = $base['data'];
        $e = $ext['data'] ?? [];
        $th = $transHe['data'] ?? [];
        $te = $transEn['data'] ?? [];

        // --- כותרות ---
        $title_en = !empty($te['name']) ? $te['name'] : ($b['name'] ?? '');
        if (is_hebrew($title_en)) $title_en = ''; 
        $overview_en = !empty($te['overview']) ? $te['overview'] : ($b['overview'] ?? '');
        if (is_hebrew($overview_en)) $overview_en = '';

        $title_he = !empty($th['name']) ? $th['name'] : '';
        if (!is_hebrew($title_he)) $title_he = ''; 
        $overview_he = !empty($th['overview']) ? $th['overview'] : '';
        if (!is_hebrew($overview_he)) $overview_he = '';

        // --- עונות ---
        $uniqueSeasons = [];
        if (isset($e['seasons']) && is_array($e['seasons'])) {
            foreach ($e['seasons'] as $s) {
                $num = isset($s['number']) ? (int)$s['number'] : 0;
                if ($num > 0) $uniqueSeasons[$num] = true;
            }
        }
        $seasonsCount = count($uniqueSeasons);
        
        // --- חישוב מספר פרקים ---
        $episodesCount = 0;
        
        // 1. נסיון ראשון: מהרשומה המורחבת
        if (isset($e['episodes']) && is_array($e['episodes'])) {
            $episodesCount = count($e['episodes']);
        }
        
        // 2. נסיון שני: אם ריק, נבדוק את השדה airedEpisodes
        if ($episodesCount == 0 && isset($e['airedEpisodes'])) {
            $episodesCount = (int)$e['airedEpisodes'];
        }

        // 3. נסיון שלישי (גלגל הצלה): קריאה ל-Endpoint הייעודי של פרקים
        // זה פותר את הבעיה של "חזי ובניו" וסדרות דומות שהמידע הראשי שלהן חסר
        if ($episodesCount == 0) {
             $epUrl = "https://api4.thetvdb.com/v4/series/$tvdbId/episodes/default";
             $epRes = json_decode(_http($epUrl, $headers), true);
             if (isset($epRes['data']['episodes']) && is_array($epRes['data']['episodes'])) {
                 $episodesCount = count($epRes['data']['episodes']);
             }
        }

        // --- רשימות ---
        $genres = []; foreach($e['genres']??[] as $g) $genres[]=$g['name'];
        $networks = []; foreach($e['companies']??[] as $c) if(($c['companyType']['companyTypeName']??'')==='Network') $networks[]=$c['name'];
        
        $cast = []; 
        if(isset($e['characters']) && is_array($e['characters'])){
            foreach($e['characters'] as $char) {
                $nm = $char['personName'] ?? $char['name'] ?? '';
                if(!empty($nm)) $cast[] = $nm;
            }
        }

        $akas = [];
        if(isset($e['aliases'])){
             foreach($e['aliases'] as $al) if(!empty($al['name'])) $akas[] = $al['name'];
        }

        $countries = [];
        if (!empty($e['originalCountry'])) {
             $countries[] = map_code_to_name($e['originalCountry'], 'country');
        } elseif (!empty($b['originCountry'])) {
             $countries[] = map_code_to_name($b['originCountry'], 'country');
        }
        
        $languages = [];
        if (!empty($b['originalLanguage'])) {
            $languages[] = map_code_to_name($b['originalLanguage'], 'lang');
        }
        
        $out = [
            'ok' => true,
            'tvdb_url' => "https://thetvdb.com/series/" . ($b['slug'] ?? $b['id']),
            'title_en' => $title_en,
            'title_he' => $title_he,
            'overview_en' => $overview_en,
            'overview_he' => $overview_he,
            'year' => substr($b['firstAired'] ?? '', 0, 4),
            'image_url' => $b['image'] ?? $b['poster'] ?? '',
            'genres' => implode(', ', array_unique($genres)),
            'networks' => implode(', ', array_unique($networks)),
            'cast' => implode(', ', array_slice(array_unique($cast),0,20)),
            'akas' => implode("\n", array_unique($akas)),
            'countries' => implode(', ', $countries),
            'languages' => implode(', ', $languages),
            'runtime' => $b['averageRuntime'] ?? '',
            'seasons_count' => $seasonsCount,
            'episodes_count' => $episodesCount, 
            'is_tv' => 1
        ];
        _json_out($out);
    }

    // ---------------------- TMDB FETCH (עם מיזוג TVDb) ----------------------
    if ($act === 'tmdb') {
        if (!$tmdbKey) _json_out(['ok'=>false, 'err'=>'חסר מפתח TMDb']);
        
        $is_tv = (int)($_GET['is_tv'] ?? 0);
        if(preg_match('~/tv/(\d+)~',$input,$m)){ $input=$m[1]; $is_tv=1; }
        elseif(preg_match('~/movie/(\d+)~',$input,$m)){ $input=$m[1]; $is_tv=0; }

        $type = $is_tv ? 'tv' : 'movie';
        $url = "https://api.themoviedb.org/3/$type/$input?api_key=$tmdbKey&append_to_response=external_ids,credits,translations,alternative_titles&language=";
        
        $en = json_decode(_http($url . 'en-US'), true);
        if (!$en || isset($en['success'])) _json_out(['ok'=>false, 'err'=>'לא נמצא ב-TMDb']);
        $he = json_decode(_http($url . 'he-IL'), true);

        // כותרות
        $tmdb_title_en = $en['title'] ?? $en['name'] ?? '';
        if (is_hebrew($tmdb_title_en)) { 
             $orig = $en['original_title'] ?? $en['original_name'] ?? '';
             $tmdb_title_en = is_hebrew($orig) ? '' : $orig; 
        }

        $tmdb_overview_en = $en['overview'] ?? '';
        if (is_hebrew($tmdb_overview_en)) $tmdb_overview_en = '';

        $tmdb_title_he = $he['title'] ?? $he['name'] ?? '';
        if (!is_hebrew($tmdb_title_he)) $tmdb_title_he = '';
        $tmdb_overview_he = $he['overview'] ?? '';
        if (!is_hebrew($tmdb_overview_he)) $tmdb_overview_he = '';

        // נתונים בסיסיים
        $tmdb_genres = array_map(fn($g)=>$g['name'], $en['genres']??[]);
        $tmdb_cast = []; foreach(array_slice($en['credits']['cast']??[],0,15) as $c) $tmdb_cast[]=$c['name'];
        $tmdb_nets = []; foreach($en['networks']??[] as $n) $tmdb_nets[]=$n['name'];
        
        $tmdb_akas = [];
        if(isset($en['alternative_titles']['titles'])) {
            foreach($en['alternative_titles']['titles'] as $at) $tmdb_akas[] = $at['title'];
        } elseif(isset($en['alternative_titles']['results'])) { 
            foreach($en['alternative_titles']['results'] as $at) $tmdb_akas[] = $at['title'];
        }
        $tmdb_akas[] = $en['original_title'] ?? $en['original_name'] ?? '';

        $tvdb_id = $en['external_ids']['tvdb_id'] ?? '';
        $tvdb_url_final = $tvdb_id ? "https://thetvdb.com/series/$tvdb_id" : '';

        $out = [
            'ok' => true,
            'tmdb_url' => "https://www.themoviedb.org/$type/" . $en['id'],
            'imdb_id' => $en['external_ids']['imdb_id'] ?? '',
            'title_en' => $tmdb_title_en,
            'title_he' => $tmdb_title_he,
            'overview_en' => $tmdb_overview_en,
            'overview_he' => $tmdb_overview_he,
            'year' => substr($en['release_date'] ?? $en['first_air_date'] ?? '', 0, 4),
            'image_url' => $en['poster_path'] ? "https://image.tmdb.org/t/p/w500" . $en['poster_path'] : '',
            'runtime' => $en['runtime'] ?? ($en['episode_run_time'][0] ?? ''),
            'is_tv' => $is_tv,
            'tvdb_url' => $tvdb_url_final,
            'seasons_count' => $en['number_of_seasons'] ?? '',
            'episodes_count' => $en['number_of_episodes'] ?? ''
        ];

        // צוות
        $dirs=[]; $writs=[];
        foreach($en['credits']['crew']??[] as $cr){
            $j=strtolower($cr['job']??'');
            if($j==='director') $dirs[]=$cr['name'];
            if(in_array($j,['writer','screenplay','story'])) $writs[]=$cr['name'];
        }
        $out['directors'] = implode(', ', array_unique($dirs));
        $out['writers'] = implode(', ', array_unique($writs));

        // --- מיזוג TVDb (אם קיים ID) ---
        if($is_tv && $tvdb_id && $tvdbKey) {
             $token = _tvdb_token($tvdbKey);
             if($token) {
                 $headers = ["Authorization: Bearer $token"];
                 $ext = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdb_id/extended", $headers), true);
                 $transHe = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdb_id/translations/heb", $headers), true);
                 $transEn = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdb_id/translations/eng", $headers), true);
                 $base = json_decode(_http("https://api4.thetvdb.com/v4/series/$tvdb_id", $headers), true);

                 if(isset($ext['data'])) {
                     $ed = $ext['data'];
                     if(!empty($ed['slug'])) $out['tvdb_url'] = "https://thetvdb.com/series/" . $ed['slug'];

                     // השלמות
                     if(empty($out['title_en']) && !empty($transEn['data']['name']) && !is_hebrew($transEn['data']['name'])) $out['title_en'] = $transEn['data']['name'];
                     if(empty($out['overview_en']) && !empty($transEn['data']['overview']) && !is_hebrew($transEn['data']['overview'])) $out['overview_en'] = $transEn['data']['overview'];
                     if(empty($out['title_he']) && !empty($transHe['data']['name']) && is_hebrew($transHe['data']['name'])) $out['title_he'] = $transHe['data']['name'];
                     if(empty($out['overview_he']) && !empty($transHe['data']['overview']) && is_hebrew($transHe['data']['overview'])) $out['overview_he'] = $transHe['data']['overview'];

                     // עונות
                     $uniqueSeasonsMerged = [];
                     foreach ($ed['seasons']??[] as $s) { 
                         $num = isset($s['number']) ? (int)$s['number'] : 0;
                         if ($num > 0) $uniqueSeasonsMerged[$num] = true;
                     }
                     if (count($uniqueSeasonsMerged) > 0) $out['seasons_count'] = count($uniqueSeasonsMerged);
                     
                     // --- תיקון: פרקים במיזוג ---
                     $epCount = 0;
                     if(isset($ed['episodes']) && is_array($ed['episodes'])) $epCount = count($ed['episodes']);
                     if($epCount == 0 && !empty($ed['airedEpisodes'])) $epCount = (int)$ed['airedEpisodes'];
                     
                     // גלגל הצלה גם במיזוג: אם עדיין 0, נמשוך במיוחד
                     if($epCount == 0 && (empty($out['episodes_count']) || $out['episodes_count'] == 0)) {
                         $epUrl = "https://api4.thetvdb.com/v4/series/$tvdb_id/episodes/default";
                         $epRes = json_decode(_http($epUrl, $headers), true);
                         if(isset($epRes['data']['episodes']) && is_array($epRes['data']['episodes'])) {
                             $epCount = count($epRes['data']['episodes']);
                         }
                     }

                     if($epCount > 0 && (empty($out['episodes_count']) || $out['episodes_count'] == 0)) {
                         $out['episodes_count'] = $epCount;
                     }

                     // רשימות
                     $tvdb_genres = []; foreach($ed['genres']??[] as $g) $tvdb_genres[]=$g['name'];
                     $tvdb_nets = []; foreach($ed['companies']??[] as $c) if(($c['companyType']['companyTypeName']??'')==='Network') $tvdb_nets[]=$c['name'];
                     
                     $tvdb_cast = []; 
                     foreach($ed['characters']??[] as $ch) {
                         $nm = $ch['personName'] ?? $ch['name'] ?? '';
                         if(!empty($nm)) $tvdb_cast[] = $nm;
                     }

                     $tvdb_akas = []; foreach($ed['aliases']??[] as $al) if(!empty($al['name'])) $tvdb_akas[]=$al['name'];

                     $tmdb_genres = merge_lists($tmdb_genres, $tvdb_genres);
                     $tmdb_nets = merge_lists($tmdb_nets, $tvdb_nets);
                     $tmdb_cast = merge_lists($tmdb_cast, $tvdb_cast);
                     $tmdb_akas = merge_lists($tmdb_akas, $tvdb_akas);
                     
                     // מדינה
                     if(empty($out['countries'])) {
                         if(!empty($ed['originalCountry'])) {
                            $out['countries'] = map_code_to_name($ed['originalCountry'], 'country');
                         } elseif(isset($base['data']['originCountry'])) {
                            $out['countries'] = map_code_to_name($base['data']['originCountry'], 'country');
                         }
                     }
                     
                     if(empty($out['languages']) && !empty($base['data']['originalLanguage'])) {
                         $out['languages'] = map_code_to_name($base['data']['originalLanguage'], 'lang');
                     }
                 }
             }
        }

        $out['genres'] = implode(', ', $tmdb_genres);
        $out['networks'] = implode(', ', $tmdb_nets);
        $out['cast'] = implode(', ', array_slice($tmdb_cast, 0, 25));
        $out['akas'] = implode("\n", $tmdb_akas);
        
        if(empty($out['countries'])) {
            $c_arr = []; foreach($en['production_countries']??[] as $c) $c_arr[]=$c['name'];
            $out['countries'] = implode(', ', $c_arr);
        }
        if(empty($out['languages'])) {
            $l_arr = []; foreach($en['spoken_languages']??[] as $l) $l_arr[]=$l['english_name'];
            $out['languages'] = implode(', ', $l_arr);
        }

        _json_out($out);
    }
    exit;
}

// ======================== HTML PAGE ========================
include 'header.php';

$data=[
  'type_id'=>3,'is_tv'=>0,
  'title_en'=>'','title_he'=>'','year'=>'','imdb_id'=>'','tmdb_url'=>'','tvdb_url'=>'',
  'mc_score'=>'','mc_url'=>'','rt_score'=>'','rt_url'=>'',
  'image_url'=>'','trailer_url'=>'',
  'plot'=>'','plot_he'=>'','overview_en'=>'','overview_he'=>'',
  'directors'=>'','writers'=>'','producers'=>'','composers'=>'','cinematographers'=>'','cast'=>'',
  'genres'=>'','languages'=>'','countries'=>'','networks'=>'',
  'runtime'=>'','seasons_count'=>'','episodes_count'=>'',
  'has_subtitles'=>0,'is_dubbed'=>0,
  'user_tags'=>'','akas'=>''
];

$types=[]; $resT=$conn->query("SELECT id,icon,label_he,code,sort_order,image FROM poster_types ORDER BY sort_order,id");
while($r=$resT->fetch_assoc()) $types[]=$r;
$typeCodeById=[]; foreach($types as $t) $typeCodeById[(int)$t['id']]=trim((string)$t['code']);

$message='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  foreach(['has_subtitles','is_dubbed'] as $cb) $data[$cb]=!empty($_POST[$cb])?1:0;
  foreach($data as $k=>$v) if(!in_array($k,['has_subtitles','is_dubbed'])) $data[$k]=trim((string)($_POST[$k]??''));
  
  $selected_flags=(isset($_POST['lang_flags'])&&is_array($_POST['lang_flags']))?$_POST['lang_flags']:[];

  if($data['imdb_id']!==''){
      $st=$conn->prepare("SELECT id FROM posters WHERE imdb_id=?"); $st->bind_param("s",$data['imdb_id']);
      $st->execute(); $st->store_result();
      if($st->num_rows>0) $message="❌ קיים כבר פוסטר עם IMDb ID זה.";
      $st->close();
  }

  if(!$message){
    $typeId=(int)$data['type_id']; 
    $code=strtolower(trim((string)($typeCodeById[$typeId]??'')));
    $data['is_tv']=in_array($code,['series','miniseries'])?1:0;
    
    $dbCols = array_keys($data);
    $dbCols = array_diff($dbCols, ['user_tags','akas']); 
    
    $colsStr = implode(',', $dbCols);
    $placeholders = implode(',', array_fill(0, count($dbCols), '?'));
    
    $sql="INSERT INTO posters ($colsStr) VALUES ($placeholders)";
    $stmt=$conn->prepare($sql);
    
    $typesStr=''; $vals=[];
    foreach($dbCols as $col){
        $val=$data[$col];
        if(in_array($col,['type_id','is_tv','mc_score','runtime','seasons_count','episodes_count','has_subtitles','is_dubbed'])) {
            $typesStr.='i'; $vals[]=($val==='')?null:(int)$val;
        } else {
            $typesStr.='s'; $vals[]=($val==='')?null:$val;
        }
    }
    $stmt->bind_param($typesStr,...$vals);
    
    if($stmt->execute()){
        $new_id = $stmt->insert_id;
        
        $tags=preg_split('~[,\n]+~u',(string)$data['user_tags']);
        foreach($tags as $t){ if(trim($t)){ $conn->query("INSERT INTO user_tags (poster_id,genre) VALUES ($new_id,'".$conn->real_escape_string(trim($t))."')"); } }

        foreach($selected_flags as $lc){ if(trim($lc)){ $conn->query("INSERT INTO poster_languages (poster_id,lang_code) VALUES ($new_id,'".$conn->real_escape_string(trim($lc))."')"); } }

        $akas=preg_split('~\R~u',(string)$data['akas']);
        $insAka=$conn->prepare("INSERT INTO poster_akas (poster_id, aka_title, aka, imdb_id, source) VALUES (?, ?, ?, ?, 'manual')");
        foreach($akas as $a){ if(trim($a)){ $insAka->bind_param("isss",$new_id,$a,$a,$data['imdb_id']); $insAka->execute(); } }
        $insAka->close();

        header("Location: poster.php?id=$new_id");
        exit;
    } else {
        $message="❌ שגיאה בשמירה: ".$stmt->error;
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8"><title>➕ הוספת פוסטר חדש</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f2f4f6;color:#222}
  .wrap{max-width:1200px;margin:24px auto}
  .card{background:#fff;border:1px solid #e3e7ef;border-radius:12px;box-shadow:0 1px 8px rgba(0,0,0,.05)}
  .hdr{padding:14px 18px;border-bottom:1px solid #e9edf5;display:flex;align-items:center;justify-content:center;gap:18px}
  .hdr h2{margin:0;font-size:20px}
  .actions-bar{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:8px 0}
  .btn{appearance:none;border:1px solid #cfd7e6;background:#fff;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:13px}
  .btn.primary{background:linear-gradient(90deg,#53c1f8,#2274bb);color:#fff;border:none}
  .msg-ok{background:#e9ffe7;border:1px solid #bfe8b6;color:#25674b;padding:8px 10px;border-radius:8px;margin:12px 18px}
  .msg-err{background:#ffe9e9;border:1px solid #efb3b3;color:#7a1f1f;padding:8px 10px;border-radius:8px;margin:12px 18px}
  .page-pad{padding:16px 18px}
  .section{border-top:1px dashed #e4e8f2;padding-top:16px;margin-top:16px}
  .section:first-child{border-top:0;padding-top:0;margin-top:0}
  .section h3{margin:0 0 8px;font-size:16px;color:#0e2a63}
  label{display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#314056}
  input[type="text"],input[type="number"],textarea{width:100%;padding:7px 9px;font-size:13px;border:1px solid #cfd7e6;border-radius:8px;background:#fbfdff;resize:vertical;direction:ltr;text-align:left}
  textarea{min-height:64px}
  input[name="title_he"]{direction:rtl;text-align:right}
  textarea[name="overview_he"]{direction:rtl;text-align:right}
  .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 22px}
  .full{grid-column:1/-1}
  .types{display:flex;flex-wrap:wrap;gap:10px}
  .types label{background:#eef5ff;border:1px solid #bfd7ff;border-radius:999px;padding:6px 10px;cursor:pointer;font-weight:500;display:flex;align-items:center;gap:8px}
  .types input[type="radio"]{margin-left:5px}
  .type-img{height:22px;width:auto;border-radius:6px;display:inline-block}
  .chips{margin-top:6px}
  .chip{display:inline-block;padding:2px 10px;background:#eef3fb;color:#295687;border:1px solid #d7e3f9;border-radius:999px;font-size:12px;margin:2px 2px 0 0}
  .preview-box{display:none;margin-top:8px}
  .img-prev{width:220px;height:auto;border:1px solid #e2e6ef;border-radius:8px;background:#f5f7fb;display:block}
  .trailer-frame{width:100%;max-width:640px;aspect-ratio:16/9;border:0;border-radius:8px;background:transparent}
  .series-only{display:none}.series-only.show{display:block}
  .preview-panel{margin:20px 0 10px;border:1px dashed #d6deee;background:#fafcff;border-radius:12px;padding:12px;text-align:right}
  .preview-panel h3{margin:0 0 10px;font-size:15px;color:#0e2a63;text-align:right}
  .pv-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 18px}
  .pv-item label{font-size:12px;color:#51607a;display:block;margin-bottom:3px}
  .pv-item .val{font-size:13px;color:#0d2a63}
  .pv-links a{margin-inline-start:8px;font-size:12px;color:#0d5dc1;text-decoration:underline}
  .btn-toggle {display:inline-block;margin-top:6px;padding:6px 12px;border:1px solid #bbb;background:#f4f7fb;border-radius:6px;cursor:pointer;font-size:13px;}
  .btn-toggle:hover { background:#e3e8f2; }
  .fetch-box {background:#eef5ff; border:1px solid #d0e1f5; border-radius:8px; padding:15px; margin-bottom:20px; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;}
  .fetch-box > div {flex:1; min-width:250px;}
  .btn.fetch {background:#0d6efd; color:#fff; border-color:#0d6efd;}
</style>
<script>
  function escapeHtml(s){return (s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function ytId(url){var m=(url||'').match(/(?:youtu\.be\/|v=)([A-Za-z0-9_-]{6,})/i);return m?m[1]:'';}
  function vimeoId(url){var m=(url||'').match(/vimeo\.com\/(\d+)/i);return m?m[1]:'';}
  function makeTrailerEmbed(url){
    url=(url||'').trim();var id,html='';
    if(!url) return '<img src="images/no-trailer.png" alt="No trailer" class="trailer-frame">';
    if(id=ytId(url)) html='<iframe class="trailer-frame" loading="lazy" allowfullscreen src="https://www.youtube.com/embed/'+id+'?rel=0"></iframe>';
    else if(id=vimeoId(url)) html='<iframe class="trailer-frame" loading="lazy" allow="fullscreen; picture-in-picture" src="https://player.vimeo.com/video/'+id+'"></iframe>';
    else if(/\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) html='<video class="trailer-frame" controls src="'+url.replace(/"/g,'&quot;')+'"></video>';
    else html='<a target="_blank" rel="noopener" href="'+url.replace(/"/g,'&quot;')+'">פתח טריילר בקישור חיצוני</a>';
    return html;
  }
  function linkOrDash(url,label){url=(url||'').trim();if(!url)return'<span style="color:#888">—</span>';var s=escapeHtml(url),t=label?escapeHtml(label):s;return'<a href="'+s+'" target="_blank" rel="noopener">'+t+'</a>';}
  function buildImdbTitleUrl(imdbId){imdbId=(imdbId||'').trim();return imdbId?('https://www.imdb.com/title/'+encodeURIComponent(imdbId)+'/'):'';}

  function renderChips(fieldId){
    var input=document.getElementById(fieldId),box=document.getElementById(fieldId+'_chips'); if(!input||!box) return;
    var parts=(input.value||'').split(/,|\n/).map(function(s){return s.trim();}).filter(function(x){return x;});
    box.innerHTML=parts.map(function(p){return'<span class="chip">'+escapeHtml(p)+'</span>';}).join(' ');
  }

  function setPreview(id,html){var el=document.getElementById(id); if(el) el.innerHTML=html||'<span style="color:#888">—</span>';}

  function refreshPreview(){
    var titleEn=document.querySelector('input[name="title_en"]').value;
    var titleHe=document.querySelector('input[name="title_he"]').value;
    var year=document.querySelector('input[name="year"]').value;
    var imdbId=document.querySelector('input[name="imdb_id"]').value;
    var tmdbUrl=document.querySelector('input[name="tmdb_url"]').value;
    var tvdbInputs=document.querySelectorAll('input[name="tvdb_url"]');
    var tvdbUrl = (tvdbInputs.length > 0) ? tvdbInputs[0].value : '';
    
    setPreview('pv_title_en',escapeHtml(titleEn));
    setPreview('pv_title_he',escapeHtml(titleHe));
    setPreview('pv_year',escapeHtml(year));

    var links='',imdbLink=buildImdbTitleUrl(imdbId);
    if(imdbLink) links+=linkOrDash(imdbLink,'IMDb');
    if(tmdbUrl) links+=' '+linkOrDash(tmdbUrl,'TMDb');
    if(tvdbUrl) links+=' '+linkOrDash(tvdbUrl,'TVDb');
    if(!links) links='<span style="color:#888">—</span>';
    setPreview('pv_links',links);

    ['genres','cast','directors','writers','producers','composers','cinematographers','languages','countries','user_tags','networks','akas'].forEach(function(fid){
      var inp=document.getElementById(fid),out=document.getElementById('pv_'+fid);
      if(!inp||!out) return;
      var parts=(inp.value||'').split(/,|\n/).map(function(s){return s.trim();}).filter(function(x){return x;});
      out.innerHTML=parts.length?parts.map(function(p){return'<span class="chip">'+escapeHtml(p)+'</span>';}).join(' '):'<span style="color:#888">—</span>';
    });
  }

  function isSeriesCode(code){if(!code)return false;code=(code+'').toLowerCase();return(code==='series'||code==='miniseries');}
  function currentTypeCode(){var r=document.querySelector('input[name="type_id"]:checked');return r?(r.dataset.code||''):'';}
  function applySeriesToggle(){var code=currentTypeCode(),block=document.getElementById('seriesBlock');if(!block)return; if(isSeriesCode(code)) block.classList.add('show'); else block.classList.remove('show'); refreshPreview();}

  function updateImagePreview(){
      var input=document.querySelector('input[name="image_url"]'),img=document.getElementById('imgPreview'),box=document.getElementById('imgPrevBox');
      if(!input||!img)return;
      var v=(input.value||'').trim();
      img.src=v||'images/no-poster.png';
      if(v) box.style.display='block'; else box.style.display='none';
  }
  function updateTrailerPreview(){
      var input=document.querySelector('input[name="trailer_url"]'),slot=document.getElementById('trailerFrameBox'),box=document.getElementById('trailerPrevBox');
      if(!input||!slot)return;
      var v=(input.value||'').trim();
      slot.innerHTML=makeTrailerEmbed(v);
      if(v) box.style.display='block'; else box.style.display='none';
  }
  function toggleBox(id){ var el=document.getElementById(id); if(el) el.style.display=(el.style.display==='none'?'block':'none'); }

  // AJAX Fetching
  async function fetchAuto(type) {
      var input = document.getElementById(type+'_input').value.trim();
      if(!input) return alert('נא להזין ערך');
      
      var btn = document.getElementById('btn_'+type);
      var oldText = btn.innerText;
      btn.innerText = 'טוען...';
      
      var is_tv_chk = 0;
      var radios = document.getElementsByName('type_id');
      for(var i=0; i<radios.length; i++){
          if(radios[i].checked){
              var code = radios[i].getAttribute('data-code') || '';
              if(code.includes('series')) is_tv_chk = 1;
          }
      }

      try {
          var url = `add.php?ajax=${type}&input=${encodeURIComponent(input)}&is_tv=${is_tv_chk}`;
          var res = await fetch(url);
          var data = await res.json();
          
          if(!data.ok) throw new Error(data.err || 'Error');
          
          function set(name, val) { 
              if(val) { 
                  var els = document.querySelectorAll(`[name="${name}"]`);
                  els.forEach(el => el.value = val);
              } 
          }
          
          set('title_en', data.title_en);
          set('title_he', data.title_he);
          set('overview_en', data.overview_en);
          set('overview_he', data.overview_he);
          set('year', data.year);
          set('image_url', data.image_url);
          set('imdb_id', data.imdb_id);
          set('tmdb_url', data.tmdb_url);
          set('tvdb_url', data.tvdb_url);
          set('runtime', data.runtime);
          
          set('genres', data.genres);
          set('cast', data.cast);
          set('directors', data.directors);
          set('writers', data.writers);
          set('networks', data.networks);
          set('countries', data.countries);
          set('akas', data.akas);
          set('languages', data.languages);
          
          if(data.is_tv) {
              set('seasons_count', data.seasons_count);
              set('episodes_count', data.episodes_count);
              for(var i=0; i<radios.length; i++){ if(radios[i].getAttribute('data-code').includes('series')) { radios[i].checked=true; break; } }
          } else {
               for(var i=0; i<radios.length; i++){ if(!radios[i].getAttribute('data-code').includes('series')) { radios[i].checked=true; break; } }
          }

          applySeriesToggle();
          updateImagePreview();
          updateTrailerPreview();
          rehydrate();
          
      } catch(e) {
          alert('שגיאה: ' + e.message);
      } finally {
          btn.innerText = oldText;
      }
  }

  function rehydrate(){
    ['genres','cast','directors','writers','producers','composers','cinematographers','languages','countries','user_tags','networks','akas'].forEach(function(fid){
      var el=document.getElementById(fid); renderChips(fid);
      if(el && !el.dataset.bound){ el.addEventListener('input',function(){ renderChips(fid); refreshPreview(); }); el.dataset.bound='1'; }
    });
    document.querySelectorAll('input[name="type_id"]').forEach(function(r){ if(!r.dataset.bound){ r.addEventListener('change',applySeriesToggle); r.dataset.bound='1'; } });
    applySeriesToggle();
    var imgInput=document.querySelector('input[name="image_url"]'); if(imgInput && !imgInput.dataset.bound){ imgInput.addEventListener('input',updateImagePreview); imgInput.dataset.bound='1'; }
    var trInput=document.querySelector('input[name="trailer_url"]'); if(trInput && !trInput.dataset.bound){ trInput.addEventListener('input',updateTrailerPreview); trInput.dataset.bound='1'; }
    
    ['image_url','trailer_url'].forEach(function(name){
      var el=document.querySelector('input[name="'+name+'"]'); if(!el||el.dataset.boundHover)return;
      el.addEventListener('mouseover',function(){try{this.select();}catch(e){}});
      el.dataset.boundHover='1';
    });

    var tvdbInputs = document.querySelectorAll('input[name="tvdb_url"]');
    tvdbInputs.forEach(inp => {
        if(!inp.dataset.syncBound){
            inp.addEventListener('input', function(){
                var v = this.value;
                tvdbInputs.forEach(other => { if(other!==this) other.value = v; });
                refreshPreview();
            });
            inp.dataset.syncBound = '1';
        }
    });
    
    refreshPreview();
  }
  document.addEventListener('DOMContentLoaded',rehydrate);
</script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="hdr" style="justify-content:space-between;">
      <h2>➕ הוספת פוסטר חדש</h2>
      <div class="actions-bar" style="margin:0;">
        <button type="submit" form="addForm" class="btn primary">💾 שמור</button>
        <button type="reset" form="addForm" class="btn" onclick="setTimeout(rehydrate,0)">איפוס</button>
      </div>
    </div>
    
    <div style="padding:16px 18px 0;">
      <div class="fetch-box">
          <div>
              <label>TMDb (ID/URL) - מזהה סדרות אוטומטית</label>
              <div style="display:flex; gap:5px;">
                  <input type="text" id="tmdb_input" placeholder="למשל: 550 או https://...">
                  <button type="button" id="btn_tmdb" class="btn fetch" onclick="fetchAuto('tmdb')">📥 משוך TMDb</button>
              </div>
          </div>
          <div>
              <label>TVDb (ID/URL) - לסדרות בלבד</label>
              <div style="display:flex; gap:5px;">
                  <input type="text" id="tvdb_input" placeholder="למשל: 339372 או קישור מלא">
                  <button type="button" id="btn_tvdb" class="btn fetch" onclick="fetchAuto('tvdb')">📥 משוך TVDb</button>
              </div>
          </div>
      </div>
    </div>

    <form method="post" id="addForm" autocomplete="off" class="page-pad">
      
      <?php if($message): ?>
        <div class="<?= (strpos($message,'✅')===0)?'msg-ok':'msg-err' ?>"><?= $message ?></div>
      <?php endif; ?>
      <div class="section">
        <h3>סוג פוסטר</h3>
        <div class="types">
          <?php foreach($types as $t):
            $img=trim((string)($t['image']??'')); $imgUrl=$img!==''?'images/types/'.htmlspecialchars($img,ENT_QUOTES,'UTF-8'):'';
          ?>
          <label class="type-option">
            <input type="radio" name="type_id" value="<?= (int)$t['id'] ?>"
                   data-code="<?= htmlspecialchars($t['code']??'',ENT_QUOTES,'UTF-8') ?>"
                   <?= ((int)$data['type_id']===(int)$t['id'])?'checked':'' ?>>
            <?php if($imgUrl): ?>
              <img src="<?= $imgUrl ?>" alt="" width="28" height="28" class="type-img"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <?php endif; ?>
            <span class="type-icon" style="display:<?= $imgUrl?'none':'inline-block' ?>;font-size:18px;">
              <?= htmlspecialchars($t['icon']??'',ENT_QUOTES,'UTF-8') ?>
            </span>
            <span><?= htmlspecialchars(($t['label_he']??''),ENT_QUOTES,'UTF-8') ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section">
        <h3>קישורים חיצוניים וזיהוי</h3>
        <div class="grid">
          <input type="hidden" name="imdb_id" id="imdb_id" value="<?= htmlspecialchars($data['imdb_id']) ?>">
          <div>
            <label>TMDb URL</label>
            <input type="text" name="tmdb_url" value="<?= htmlspecialchars($data['tmdb_url']) ?>">
          </div>
          <div>
            <label>TVDb URL</label>
            <input type="text" name="tvdb_url" value="<?= htmlspecialchars($data['tvdb_url']) ?>">
          </div>
        </div>
      </div>

      <div class="section">
        <h3>כותרות וזיהוי</h3>
        <div class="grid">
          <div>
            <label>שם באנגלית</label>
            <input type="text" name="title_en" value="<?= htmlspecialchars($data['title_en']) ?>">
          </div>
          <div>
            <label>שם בעברית</label>
            <input type="text" name="title_he" value="<?= htmlspecialchars($data['title_he']) ?>">
          </div>
          <div>
            <label>שנה</label>
            <input type="text" name="year" value="<?= htmlspecialchars($data['year']) ?>">
          </div>
          <div>
            <label>אורך (דקות)</label>
            <input type="number" name="runtime" value="<?= htmlspecialchars($data['runtime']) ?>">
          </div>
        </div>
      </div>

      <div class="section">
        <h3>דירוגים (נוספים)</h3>
        <div class="grid">
          <div>
            <label>Metacritic URL</label>
            <input type="text" name="mc_url" value="<?= htmlspecialchars($data['mc_url']) ?>">
          </div>
          <div>
            <label>Metacritic (מספר)</label>
            <input type="number" name="mc_score" value="<?= htmlspecialchars($data['mc_score']) ?>">
          </div>
          <div>
            <label>Rotten Tomatoes URL</label>
            <input type="text" name="rt_url" value="<?= htmlspecialchars($data['rt_url']) ?>">
          </div>
          <div>
            <label>Rotten Tomatoes (אחוזים)</label>
            <input type="text" name="rt_score" value="<?= htmlspecialchars($data['rt_score']) ?>">
          </div>
        </div>
      </div>

      <div class="section">
        <h3>תקציר/עלילה</h3>
        <div class="grid">
          <div class="full">
            <label>תקציר אנגלית</label>
            <textarea name="overview_en" rows="5"><?= htmlspecialchars($data['overview_en']) ?></textarea>
          </div>
          <div class="full">
            <label>תקציר עברית</label>
            <textarea name="overview_he" rows="5"><?= htmlspecialchars($data['overview_he']) ?></textarea>
          </div>
        </div>
      </div>

      <div class="section">
        <h3>אנשי צוות</h3>
        <div class="grid">
          <div><label>במאים:</label><input type="text" name="directors" id="directors" value="<?= htmlspecialchars($data['directors']) ?>"><div id="directors_chips" class="chips"></div></div>
          <div><label>תסריטאים:</label><input type="text" name="writers" id="writers" value="<?= htmlspecialchars($data['writers']) ?>"><div id="writers_chips" class="chips"></div></div>
          <div><label>מפיקים:</label><input type="text" name="producers" id="producers" value="<?= htmlspecialchars($data['producers']) ?>"><div id="producers_chips" class="chips"></div></div>
          <div><label>מלחינים:</label><input type="text" name="composers" id="composers" value="<?= htmlspecialchars($data['composers']) ?>"><div id="composers_chips" class="chips"></div></div>
          <div><label>צלמים:</label><input type="text" name="cinematographers" id="cinematographers" value="<?= htmlspecialchars($data['cinematographers']) ?>"><div id="cinematographers_chips" class="chips"></div></div>
          <div><label>שחקנים</label><input type="text" name="cast" id="cast" value="<?= htmlspecialchars($data['cast']) ?>"><div id="cast_chips" class="chips"></div></div>
        </div>
      </div>

      <div class="section">
        <h3>רשימות (פסיקים)</h3>
        <div class="grid">
          <div><label>ז׳אנרים</label><input type="text" name="genres" id="genres" value="<?= htmlspecialchars($data['genres']) ?>"><div id="genres_chips" class="chips"></div></div>
          <div><label>תגיות משתמש</label><input type="text" name="user_tags" id="user_tags" value="<?= htmlspecialchars($data['user_tags']) ?>"><div id="user_tags_chips" class="chips"></div></div>
          <div><label>שפות</label><input type="text" name="languages" id="languages" value="<?= htmlspecialchars($data['languages']) ?>"><div id="languages_chips" class="chips"></div></div>
          <div><label>מדינות</label><input type="text" name="countries" id="countries" value="<?= htmlspecialchars($data['countries']) ?>"><div id="countries_chips" class="chips"></div></div>
          <div class="full">
            <label>AKAs (שמות נוספים)</label>
            <textarea name="akas" id="akas" rows="3"><?= htmlspecialchars($data['akas']) ?></textarea>
            <div id="akas_chips" class="chips"></div>
          </div>
        </div>
      </div>

      <div class="section series-only" id="seriesBlock">
        <h3>פרטי סדרה</h3>
        <div class="grid">
          <div><label>TVDb URL</label><input type="text" name="tvdb_url" value="<?= htmlspecialchars($data['tvdb_url']) ?>"></div>
          <div>
            <label>רשתות</label>
            <input type="text" name="networks" id="networks" value="<?= htmlspecialchars($data['networks']) ?>">
            <div id="networks_chips" class="chips"></div>
          </div>

          <div><label>מספר עונות</label><input type="number" name="seasons_count" value="<?= htmlspecialchars($data['seasons_count']) ?>"></div>
          <div><label>מספר פרקים</label><input type="number" name="episodes_count" value="<?= htmlspecialchars($data['episodes_count']) ?>"></div>
        </div>
      </div>

      <div class="section">
        <h3>מדיה</h3>
        <div class="grid">
          <div>
            <label>תמונה (Image URL)</label>
            <input type="text" name="image_url" value="<?= htmlspecialchars($data['image_url']) ?>" onmouseover="this.select()">
            <div id="imgPrevBox" class="preview-box">
              <img id="imgPreview" class="img-prev" alt="Preview" src="">
            </div>
            <button type="button" class="btn-toggle" onclick="toggleBox('imgPrevBox')">הצג/הסתר תמונה</button>
          </div>
          <div>
            <label>טריילר (YouTube/Vimeo/MP4)</label>
            <input type="text" name="trailer_url" value="<?= htmlspecialchars($data['trailer_url']) ?>" onmouseover="this.select()">
            <div id="trailerPrevBox" class="preview-box">
              <div id="trailerFrameBox"></div>
            </div>
            <button type="button" class="btn-toggle" onclick="toggleBox('trailerPrevBox')">הצג/הסתר טריילר</button>
          </div>
        </div>
      </div>

      <div class="section">
        <h3>דגלים/שפות</h3>
        <div class="flags-box">
          <?php
            ob_start(); include 'flags.php'; $flags_html=ob_get_clean();
            $flags_html = str_replace('name="languages[]"','name="lang_flags[]"',$flags_html);
            $flags_html = str_replace("name='languages[]'","name='lang_flags[]'",$flags_html);
            echo $flags_html;
          ?>
        </div>
      </div>

      <input type="hidden" name="plot" value="">
      <input type="hidden" name="plot_he" value="">

      <div class="section">
        <div class="actions-bar">
          <button type="submit" class="btn primary">💾 שמור</button>
          <button type="reset" class="btn" onclick="setTimeout(rehydrate,0)">איפוס</button>
        </div>
      </div>

      <div class="preview-panel">
        <h3>תצוגה מקדימה</h3>
        <div class="pv-grid">
          <div class="pv-item"><label>שם באנגלית</label><div id="pv_title_en" class="val"></div></div>
          <div class="pv-item"><label>שם בעברית</label><div id="pv_title_he" class="val"></div></div>
          <div class="pv-item"><label>שנה</label><div id="pv_year" class="val"></div></div>
          <div class="pv-item"><label>קישורים</label><div id="pv_links" class="val pv-links"></div></div>

          <div class="pv-item full"><label>ז׳אנרים</label><div id="pv_genres" class="val"></div></div>
          <div class="pv-item full"><label>שחקנים</label><div id="pv_cast" class="val"></div></div>
          <div class="pv-item"><label>במאים</label><div id="pv_directors" class="val"></div></div>
          <div class="pv-item"><label>תסריטאים</label><div id="pv_writers" class="val"></div></div>
          <div class="pv-item"><label>מפיקים</label><div id="pv_producers" class="val"></div></div>
          <div class="pv-item"><label>מלחינים</label><div id="pv_composers" class="val"></div></div>
          <div class="pv-item full"><label>צלמים</label><div id="pv_cinematographers" class="val"></div></div>
          <div class="pv-item"><label>שפות</label><div id="pv_languages" class="val"></div></div>
          <div class="pv-item"><label>מדינות</label><div id="pv_countries" class="val"></div></div>
          <div class="pv-item full"><label>תגיות משתמש</label><div id="pv_user_tags" class="val"></div></div>
          <div class="pv-item full"><label>שמות נוספים (AKAs)</label><div id="pv_akas" class="val"></div></div>
          </div>
      </div>

    </form>
  </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>