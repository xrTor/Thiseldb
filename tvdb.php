<?php
/****************************************************
 * tvdb.php — IMDb → TVDB Converter (links only)
 * ללא TMDb. כולל Fallback: כותרת/AKAs מ־IMDb → חיפוש ב-TVDB.
 * POST ⇒ text/plain (links only). GET ⇒ טופס.
 ****************************************************/

mb_internal_encoding('UTF-8');
$TVDB_KEY = '1c93003f-ab80-4baf-b5c4-58c7b96494a2';
const ALLOW_INSECURE_SSL = false;

$DEBUG_MODE = (isset($_GET['debug']) && $_GET['debug'] == '1')
           || (isset($_POST['debug']) && $_POST['debug'] == '1');

/* ======================= POST ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = $_POST['input'] ?? '';
    $pin      = $_POST['pin']   ?? null;
    $ids = extractAllImdbIds($rawInput);

    header('Content-Type: text/plain; charset=UTF-8');
    if ($DEBUG_MODE) {
        echo "DEBUG MODE ON\n";
        echo "Raw count(tt): " . count($ids) . "\n";
    }
    if (empty($ids)) exit;

    $err = null;
    $token = tvdbLogin($TVDB_KEY, $pin, $err);
    if (!$token) { if ($DEBUG_MODE) echo "TVDB login failed: $err\n"; exit; }
    if ($DEBUG_MODE) echo "TVDB login: OK\n";

    $printed = 0;
    foreach ($ids as $tt) {
        $res = tvdbSearchByImdb($tt, $token, $err);
        if ($DEBUG_MODE) echo "Search remoteid {$tt}: " . ($res ? "HTTP OK" : "FAIL") . "\n";
        $url = null;

        if ($res && !empty($res['data'])) {
            $pick = pickBestTvdbMatch($res['data']);
            if ($pick) {
                [$type, $id] = normalizeTvdbTypeAndId($pick);
                if ($id) $url = tvdbDerefUrl($type, $id);
            }
        }

        if (!$url) {
            if ($DEBUG_MODE) echo "Fallback: scrape IMDb title/year/AKAs for {$tt}\n";
            $meta = scrapeImdbCandidates($tt);
            if ($DEBUG_MODE && $meta) {
                echo "Candidates: " . implode(' | ', $meta['titles']) . "; year=" . ($meta['year'] ?? 'null') . "\n";
            }
            if ($meta && !empty($meta['titles'])) {
                $cands = $meta['titles']; $year = $meta['year'] ?? null; $foundPick = null;
                foreach (['series','movie'] as $type) {
                    foreach ($cands as $cand) {
                        $found = tvdbSearchByQuery($cand, $type, $token, $err);
                        $pick  = $found ? pickBestByTitleYear($found['data'] ?? [], $cand, $year) : null;
                        if ($DEBUG_MODE) echo "Query($type) '$cand': " . ($pick ? "→ PICK" : "NO") . "\n";
                        if ($pick) { $foundPick = $pick; break 2; }
                    }
                }
                if ($foundPick) {
                    [$type, $id] = normalizeTvdbTypeAndId($foundPick);
                    if ($id) $url = tvdbDerefUrl($type, $id);
                    if ($DEBUG_MODE && $url) echo "Fallback match → {$url}\n";
                }
            }
        }

        if ($url) {
            if (!$DEBUG_MODE) echo $url . "\n";
            $printed++;
        }
    }

    if ($DEBUG_MODE && $printed === 0) echo "No matches produced any TVDB link.\n";
    exit;
}

/* ======================= GET (Form) ======================= */
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>IMDb → TVDB (קישור בלבד)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; margin:24px; line-height:1.4; background:#fafafa;}
    .wrap{max-width:900px; margin:auto; background:#fff; padding:24px; border-radius:16px; box-shadow:0 6px 24px rgba(0,0,0,.08)}
    h1{margin:0 0 8px}
    textarea,input{width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-family:ui-monospace,Consolas,monospace}
    button{padding:12px 18px;border:0;border-radius:12px;background:#0d6efd;color:#fff;font-size:16px;cursor:pointer}
  </style>
</head>
<body>
<div class="wrap">
  <h1>IMDb → TVDB</h1>
  <form method="post">
    <label>קלט IMDb:</label>
    <textarea name="input" rows="8"></textarea>
    <label>PIN (אופציונלי):</label>
    <input type="text" name="pin">
    <label>Debug (1/0):</label>
    <input type="text" name="debug">
    <div style="margin-top:16px"><button type="submit">המרה</button></div>
  </form>
</div>
</body>
</html>
<?php
/* ======================= Helpers ======================= */

function extractAllImdbIds(string $blob): array {
    preg_match_all('~tt\d{6,12}~i',$blob,$m);
    $out=[];$seen=[];
    foreach($m[0]??[] as $tt){$tt=strtolower($tt);if(!isset($seen[$tt])){$seen[$tt]=1;$out[]=$tt;}}
    return $out;
}
function tvdbLogin(string $apiKey,?string $pin,&$err=null):?string{
    $payload=['apikey'=>$apiKey]; if($pin)$payload['pin']=$pin;
    $resp=curlJson('POST','https://api4.thetvdb.com/v4/login',$payload,['Content-Type: application/json']);
    if(!$resp['ok']){$err=$resp['error'];return null;}
    return $resp['json']['data']['token']??null;
}
function tvdbSearchByImdb(string $tt,string $token,&$err=null):?array{
    $resp=curlJson('GET',"https://api4.thetvdb.com/v4/search/remoteid/$tt",null,
        ['Authorization: Bearer '.$token,'Accept: application/json']);
    return $resp['ok']?$resp['json']:null;
}
function tvdbSearchByQuery(string $q,string $type,string $token,&$err=null):?array{
    $resp=curlJson('GET',"https://api4.thetvdb.com/v4/search?query=".rawurlencode($q)."&type=$type",null,
        ['Authorization: Bearer '.$token,'Accept: application/json']);
    return $resp['ok']?$resp['json']:null;
}
function pickBestTvdbMatch(array $items):?array{
    $priority=['series'=>1,'movie'=>2,'season'=>3,'episode'=>4];$best=null;$score=999;
    foreach($items as $it){$t=strtolower($it['type']??'');if(!isset($priority[$t]))continue;
        if($priority[$t]<$score){$best=$it;$score=$priority[$t];}}
    return $best;
}
function normalizeTvdbTypeAndId(array $it):array{
    $t=strtolower($it['type']??'');$id=(int)($it['id']??0);
    if(!$id){foreach(['series','movie','season','episode'] as $k){if(!empty($it[$k]['id'])){$t=$k;$id=(int)$it[$k]['id'];break;}}}
    return [$t?:'series',$id];
}
function tvdbDerefUrl(string $t,int $id):string{
    switch($t){case'movie':return"https://www.thetvdb.com/dereferrer/movie/$id";
        case'season':return"https://www.thetvdb.com/dereferrer/season/$id";
        case'episode':return"https://www.thetvdb.com/dereferrer/episode/$id";
        default:return"https://www.thetvdb.com/dereferrer/series/$id";}
}
function pickBestByTitleYear(array $items,string $title,?int $year):?array{
    if(!$items)return null; $best=null;$bestScore=1e9;$norm=normalizeTitle($title);
    foreach($items as $it){
        $name=$it['name']??($it['series']['name']??($it['movie']['name']??''));$y=null;
        if(!empty($it['year']))$y=(int)$it['year'];elseif(!empty($it['firstAired']))$y=(int)substr($it['firstAired'],0,4);
        $score=levenshtein($norm,normalizeTitle($name));
        if($year&&$y)$score+=(abs($year-$y)<=1?-3:+3);
        if($score<$bestScore){$best=$it;$bestScore=$score;}
    }
    return $bestScore<=15?$best:null;
}
function normalizeTitle(string $s):string{
    $s=mb_strtolower($s,'UTF-8');
    $s=preg_replace('~\s*\(\d{4}.*?\)$~u','',$s);
    $s=preg_replace('~[:\-\–\—]\s*.*$~u','',$s);   // ← כאן היה הבאג, תוקן
    $s=preg_replace('~[^\p{L}\p{N}]+~u','',$s);
    return $s;
}
function scrapeImdbCandidates(string $tt):?array{
    $resp=curlSimple('GET',"https://www.imdb.com/title/$tt/"); if(!$resp['ok']) return null; $html=$resp['body'];
    $titles=[];$year=null;
    if(preg_match('~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~si',$html,$m)){
        $json=json_decode($m[1],true); if($json){if(!empty($json['name']))$titles[]=$json['name']; if(!empty($json['alternateName']))$titles[]=$json['alternateName'];
            $date=$json['datePublished']??null; if($date)$year=(int)substr($date,0,4);}}
    if(preg_match('~<meta property="og:title" content="([^"]+)"~i',$html,$m2)) $titles[]=trim(preg_replace('~ - IMDb$~i','',$m2[1]));
    if(preg_match('~<title>\s*(.*?)\s*- IMDb~i',$html,$m3)){ $t=trim($m3[1]); $titles[]=$t; if(!$year&&preg_match('~\((\d{4})~',$t,$my))$year=(int)$my[1];}
    return ['titles'=>array_unique($titles),'year'=>$year];
}
function curlJson(string $method,string $url,$payload=null,array $headers=[]):array{
    $ch=curl_init();$opts=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>$headers];
    if(ALLOW_INSECURE_SSL){$opts[CURLOPT_SSL_VERIFYPEER]=false;$opts[CURLOPT_SSL_VERIFYHOST]=0;}
    if(strtoupper($method)==='POST'){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($payload);}
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    if($raw===false)return['ok'=>false,'error'=>$err];$json=json_decode($raw,true);return($http>=200&&$http<300)?['ok'=>true,'json'=>$json]:['ok'=>false,'error'=>$raw];
}
function curlSimple(string $m,string $url):array{
    $ch=curl_init();$opts=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20];
    if(ALLOW_INSECURE_SSL){$opts[CURLOPT_SSL_VERIFYPEER]=false;$opts[CURLOPT_SSL_VERIFYHOST]=0;}
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return($raw===false)?['ok'=>false]:['ok'=>($http>=200&&$http<300),'body'=>$raw];
}
