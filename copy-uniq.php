<?php
// =============================================================
// חלק 1: הגדרות, חיבור לשרת ופונקציות עזר
// =============================================================
set_time_limit(3000000000);
ob_start();

require_once 'server.php';

if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// ====== הגדרות OMDb/Wikidata ======
$wikidataLangs = ['he','en'];
$maxAkasPerTitle = 10;
$wdBatchSize = 40;
$wdTimeout = 12;
$wdConnectTimeout = 5;
$userAgent = 'ThiselDB-Standalone/1.4 (WDQS; contact: you)';
$omdbTimeout = 12;
$omdbConnectTimeout = 5;
$omdbConcurrency = 10;
$imdbTimeout = 12;
$imdbConnectTimeout = 5;

// ====== הגדרות PTP ======
$PTP_COOKIE_FILE = __DIR__ . '/ptp_cookies.txt';
$PTP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

// ====== פונקציות לוגיקה ======

function extract_unique_imdb_ids(string $text): array {
    preg_match_all('/(tt\d{5,12})/i', $text, $m);
    $ids = $m[1] ?? [];
    $seen = [];
    $out = [];
    foreach ($ids as $id) {
        $id = strtolower($id);
        if (!isset($seen[$id])) {
            $seen[$id] = true;
            $out[] = $id;
        }
    }
    return $out;
}

// --- פונקציות PTP ---
function ptp_fetch_html($ptpId, &$httpCode = 0, &$len = 0) {
    global $PTP_COOKIE_FILE, $PTP_UA;
    $url = "https://passthepopcorn.me/torrents.php?id=" . (int)$ptpId;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => $PTP_UA,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,he;q=0.8',
            'Referer: https://passthepopcorn.me/'
        ],
        CURLOPT_COOKIEFILE => $PTP_COOKIE_FILE,
        CURLOPT_COOKIEJAR  => $PTP_COOKIE_FILE,
    ]);
    $html = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $len = is_string($html) ? strlen($html) : 0;
    curl_close($ch);
    return is_string($html) ? $html : '';
}

function ptp_extract_imdb_tt($html) {
    if (preg_match('~imdb\.com/title/(tt\d{7,})~i', $html, $m)) return strtolower($m[1]);
    if (preg_match('~\b(tt\d{7,})\b~i', $html, $m)) return strtolower($m[1]);
    return null;
}
// --- סוף פונקציות PTP ---


function curl_json_request(array $opt): array {
    if (!function_exists('curl_init')) return ['ok'=>false,'data'=>null,'error'=>'cURL לא זמין'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $opt['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $opt['timeout'] ?? 12);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $opt['connect_timeout'] ?? 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $opt['headers'] ?? []);

    if (($opt['method'] ?? 'GET') === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['post_fields'] ?? '');
    }

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $err || $code >= 400) return ['ok'=>false,'data'=>null,'error'=>($err ?: "HTTP $code")];
    $data = json_decode($resp, true);
    if (!is_array($data)) return ['ok'=>false,'data'=>null,'error'=>'JSON לא תקין'];
    return ['ok'=>true,'data'=>$data,'error'=>''];
}

function omdb_fetch_bulk(array $ids, string $omdbKey, int $timeout, int $connectTimeout, int $concurrency): array {
    $out = [];
    foreach ($ids as $id) $out[$id] = ['ok'=>false,'title'=>'','year'=>'','type'=>'','error'=>''];
    if (!$omdbKey) { foreach ($ids as $id) $out[$id]['error'] = 'חסר מפתח OMDb'; return $out; }
    if (!function_exists('curl_multi_init')) { foreach ($ids as $id) $out[$id]['error'] = 'curl_multi לא זמין'; return $out; }

    $mh = curl_multi_init();
    $queue = $ids;
    $handles = [];

    $makeHandle = function($imdbId) use ($omdbKey, $timeout, $connectTimeout) {
        $url = "https://www.omdbapi.com/?apikey=" . urlencode($omdbKey) . "&i=" . urlencode($imdbId) . "&r=json";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: Mozilla/5.0'],
        ]);
        return $ch;
    };

    for ($i=0; $i < $concurrency && !empty($queue); $i++) {
        $id = array_shift($queue);
        $ch = $makeHandle($id);
        $handles[(int)$ch] = $id;
        curl_multi_add_handle($mh, $ch);
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $id = $handles[(int)$ch] ?? null;
            $body = curl_multi_getcontent($ch);
            $err  = curl_error($ch);
            
            if ($id) {
                if ($body === false || $err) {
                    $out[$id]['error'] = 'OMDb Err';
                } else {
                    $d = json_decode($body, true);
                    if (($d['Response'] ?? 'False') !== 'True') {
                        $out[$id]['error'] = $d['Error'] ?? 'לא נמצא';
                    } else {
                        $out[$id] = [
                            'ok'=>true,
                            'title'=>(string)($d['Title'] ?? ''),
                            'year'=>(string)($d['Year'] ?? ''),
                            'type'=>(string)($d['Type'] ?? ''),
                            'error'=>''
                        ];
                    }
                }
            }
            curl_multi_remove_handle($mh, $ch);
            unset($handles[(int)$ch]);
            curl_close($ch);
            if (!empty($queue)) {
                $next = array_shift($queue);
                $ch2 = $makeHandle($next);
                $handles[(int)$ch2] = $next;
                curl_multi_add_handle($mh, $ch2);
            }
        }
    } while ($running && $status === CURLM_OK);

    curl_multi_close($mh);
    return $out;
}

function imdb_parse_year_range(array $ld): string {
    $y = function($v){
        if (!is_string($v)) return '';
        if (preg_match('/^(\d{4})/', $v, $m)) return $m[1];
        return '';
    };
    $dp = $y($ld['datePublished'] ?? '');
    if ($dp) return $dp;
    $s = $y($ld['startDate'] ?? '');
    $e = $y($ld['endDate'] ?? '');
    if ($s && $e && $s !== $e) return $s . '–' . $e;
    return $s ?: $e;
}

function imdb_type_map($t): string {
    if (!is_string($t)) return '';
    $t = strtolower($t);
    if (strpos($t, 'tvseries') !== false) return 'series';
    if (strpos($t, 'tvepisode') !== false) return 'episode';
    if (strpos($t, 'movie') !== false) return 'movie';
    if (strpos($t, 'tvspecial') !== false) return 'movie';
    return '';
}

function imdb_fetch_title_year_jsonld(string $imdbId, int $timeout, int $connectTimeout): array {
    $url = "https://m.imdb.com/title/" . urlencode($imdbId) . "/";
    if (!function_exists('curl_init')) return ['ok'=>false,'error'=>'cURL missing'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: text/html', 'User-Agent: Mozilla/5.0'],
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return ['ok'=>false,'error'=>'IMDb fetch fail'];
    if (!preg_match('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $m)) {
        return ['ok'=>false,'error'=>'JSON-LD missing'];
    }
    $ld = json_decode(trim($m[1]), true);
    if (!is_array($ld)) return ['ok'=>false,'error'=>'JSON parse fail'];

    $title = (string)($ld['name'] ?? '');
    $year  = imdb_parse_year_range($ld);
    $type  = imdb_type_map($ld['@type'] ?? '');

    if (!$title) return ['ok'=>false,'error'=>'No title'];
    return ['ok'=>true,'title'=>$title,'year'=>$year,'type'=>$type,'error'=>''];
}

function wikidata_fetch_akas_batched(array $ids, array $langs, string $userAgent, int $batchSize, int $timeout, int $connectTimeout): array {
    $out = [];
    foreach ($ids as $id) $out[$id] = [];
    if (empty($ids)) return $out;

    $langList = implode('","', array_map(fn($l)=>preg_replace('/[^a-z\-]/i','',$l), $langs));

    foreach (array_chunk($ids, max(1,$batchSize)) as $chunk) {
        $values = implode(' ', array_map(fn($id) => '"' . addslashes($id) . '"', $chunk));
        $sparql = <<<SPARQL
SELECT ?imdb (GROUP_CONCAT(DISTINCT ?altLabel; separator=" ||| ") AS ?akas) WHERE {
  VALUES ?imdb { $values }
  ?item wdt:P345 ?imdb .
  ?item skos:altLabel ?altLabel .
  FILTER(LANG(?altLabel) IN ("$langList"))
}
GROUP BY ?imdb
SPARQL;
        $r = curl_json_request([
            'url' => 'https://query.wikidata.org/sparql',
            'method' => 'POST',
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'headers' => ['Accept: application/sparql-results+json','Content-Type: application/x-www-form-urlencoded','User-Agent: '.$userAgent],
            'post_fields' => http_build_query(['format'=>'json','query'=>$sparql]),
        ]);

        if (!$r['ok']) continue;
        $bindings = $r['data']['results']['bindings'] ?? [];
        foreach ($bindings as $b) {
            $imdb = $b['imdb']['value'] ?? '';
            if (!$imdb) continue;
            $akasStr = $b['akas']['value'] ?? '';
            $akas = $akasStr ? array_values(array_unique(array_filter(array_map('trim', explode('|||', $akasStr))))) : [];
            $out[$imdb] = $akas;
        }
    }
    return $out;
}

// ===============================
// 2. טיפול ב-POST (AJAX)
// ===============================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    $action = $_POST['action'] ?? '';
    $response = ['status' => 'error', 'html' => '', 'msg' => ''];

    // --- PTP Single Lookup ---
    if ($action === 'ptp_lookup') {
        if (!is_file($PTP_COOKIE_FILE)) {
            echo json_encode(['ok'=>false, 'err'=>'NO_COOKIES']);
            exit;
        }
        $ptpId = $_POST['ptp_id'] ?? 0;
        $http = 0; $len = 0;
        $html = ptp_fetch_html($ptpId, $http, $len);
        $tt = ptp_extract_imdb_tt($html);

        echo json_encode([
            'status' => 'ok',
            'ok'     => (bool)$tt,
            'tt'     => $tt,
            'http'   => $http,
            'len'    => $len
        ]);
        exit;
    }

    // --- SQL Sandbox ---
    if ($action === 'run_sql') {
        $sqlInput = trim($_POST['sql_query'] ?? '');
        $cleanSql = preg_replace('/\s+/', ' ', strtoupper($sqlInput));
        if (strpos($cleanSql, 'SELECT') !== 0) {
            echo json_encode(['status'=>'error', 'msg'=>'רק שאילתות SELECT מותרות.']); exit;
        }
        if (preg_match('/(UPDATE|DELETE|INSERT|DROP|ALTER|TRUNCATE|CREATE|REPLACE)/i', $sqlInput)) {
            echo json_encode(['status'=>'error', 'msg'=>'פקודות שינוי מסד אסורות.']); exit;
        }
        $sqlTrim = rtrim($sqlInput, ";\r\n\t ");
        if (stripos($sqlTrim, ' limit ') === false) $sqlTrim .= ' LIMIT 1000';

        $start = microtime(true);
        try {
            $res = $conn->query($sqlTrim);
            $queryTime = round(microtime(true) - $start, 4);

            if ($res instanceof mysqli_result) {
                $rows = [];
                while ($r = $res->fetch_assoc()) $rows[] = $r;
                $res->free();
                $count = count($rows);
                if ($count > 0) {
                    $cols = array_keys($rows[0]);
                    $html = '<div style="margin-top:20px;">';
                    $html .= '<div style="display:flex; justify-content:space-between; margin-bottom:10px;">';
                    $html .= '<strong>תוצאות ('.$count.' שורות, '.$queryTime.' שניות)</strong>';
                    $html .= '<button type="button" class="btn btn-outline" onclick="exportTableToCSV(\'sql_results.csv\')">📥 הורד CSV</button>';
                    $html .= '</div>';
                    $html .= '<div style="overflow-x:auto; max-height:500px; border:1px solid #eee;">';
                    $html .= '<table id="sqlTable"><thead><tr>';
                    foreach ($cols as $c) $html .= '<th>'.h($c).'</th>';
                    $html .= '</tr></thead><tbody>';
                    foreach ($rows as $row) {
                        $html .= '<tr>';
                        foreach ($cols as $c) {
                            $val = $row[$c] ?? '';
                            if ($c === 'id' && is_numeric($val)) {
                                $val = '<a href="poster.php?id='.$val.'" target="_blank">'.$val.'</a>';
                            } elseif ($c === 'imdb_id' && strpos($val, 'tt')===0) {
                                $val = '<a href="https://www.imdb.com/title/'.$val.'" target="_blank">'.$val.'</a>';
                            } else {
                                $val = h($val);
                            }
                            $html .= '<td>'.$val.'</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table></div></div>';
                    $response['status'] = 'ok';
                    $response['html'] = $html;
                } else {
                    $response['status'] = 'ok';
                    $response['html'] = '<div class="msg-box msg-success">השאילתה רצה בהצלחה אך לא החזירה תוצאות (0 שורות).</div>';
                }
            } else {
                $response['msg'] = 'שגיאה: לא התקבל result set.';
            }
        } catch (Throwable $e) { $response['msg'] = 'שגיאה: ' . $e->getMessage(); }
        echo json_encode($response);
        exit;
    }

    // --- IMDb Checker ---
    if ($action === 'check_imdb_list') {
        $imdbInput = trim($_POST['imdb_list'] ?? '');
        $lines = preg_split("/\r\n|\n|\r/", $imdbInput);
        $ids = [];
        foreach ($lines as $line) {
            if (preg_match_all('/tt\d{5,}/i', $line, $m)) {
                foreach ($m[0] as $tt) $ids[] = strtolower($tt);
            }
        }
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            echo json_encode(['status'=>'error', 'msg'=>'לא נמצאו מזהי tt חוקיים.']); exit;
        }

        $found = [];
        $missing = [];

        try {
            $stmt = $conn->prepare("SELECT id, title_he, title_en, year FROM posters WHERE imdb_id = ? LIMIT 1");
            foreach ($ids as $tt) {
                $stmt->bind_param('s', $tt);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $found[$tt] = $row;
                } else {
                    $missing[] = $tt;
                }
            }
            $stmt->close();

            $html = '<div class="two-cols" style="margin-top:20px;">';
            $html .= '<div class="col"><div class="data-box" style="background:#f0fff4; border:1px solid #c3e6cb;">';
            $html .= '<h4 style="color:#155724; margin-top:0;">✅ קיימים ('.count($found).')</h4>';
            if (!empty($found)) {
                $html .= '<div style="max-height:300px; overflow:auto;"><table>';
                foreach ($found as $tt => $inf) {
                    $title = h($inf['title_he'] ?: $inf['title_en']);
                    $html .= '<tr><td><a href="https://www.imdb.com/title/'.$tt.'/" target="_blank" style="text-decoration:none; color:#155724; font-family:monospace;">'.$tt.'</a></td><td><a href="poster.php?id='.$inf['id'].'" target="_blank">'.$title.'</a></td></tr>';
                }
                $html .= '</table></div>';
            } else { $html .= '<p>אין תוצאות.</p>'; }
            $html .= '</div></div>';

            $html .= '<div class="col"><div class="data-box" style="background:#fff5f5; border:1px solid #f5c6cb;">';
            $html .= '<h4 style="color:#721c24; margin-top:0;">❌ חסרים ('.count($missing).')</h4>';
            if (!empty($missing)) {
                $txt = implode("\n", $missing);
                $html .= '<textarea id="missingList" style="width:100%; height:150px; border:1px solid #ccc; font-family:monospace;">'.$txt.'</textarea>';
                $html .= '<button type="button" class="btn btn-outline" style="margin-top:5px;" onclick="copyMissing()">📋 העתק חסרים</button>';
                $html .= '<div style="margin-top:15px; border-top:1px solid rgba(0,0,0,0.1); padding-top:10px;"><h5 style="margin:0 0 5px 0; font-size:13px; color:#721c24;">🔗 קישורים מהירים:</h5><div style="display:flex; flex-wrap:wrap; gap:5px; max-height:150px; overflow-y:auto;">';
                foreach ($missing as $tt) {
                    $html .= '<a href="https://www.imdb.com/title/'.$tt.'/" target="_blank" style="text-decoration:none; color:#721c24; background:white; border:1px solid #f5c6cb; padding:2px 6px; border-radius:3px; font-size:11px; transition:0.2s;">'.$tt.'</a>';
                }
                $html .= '</div></div>';
            } else { $html .= '<p>הכל קיים!</p>'; }
            $html .= '</div></div></div>';

            $response['status'] = 'ok';
            $response['html'] = $html;
        } catch (Throwable $e) { $response['msg'] = 'שגיאה: ' . $e->getMessage(); }
        echo json_encode($response);
        exit;
    }
}

// ב. טיפול ב-POST של המרת OMDb
$omdb_input = $_POST['omdb_input'] ?? '';
$omdb_useAka = isset($_POST['omdb_use_aka']) ? true : true;
$omdb_fallback = isset($_POST['omdb_fallback']) ? true : true;
$omdb_rows = [];
$omdb_errors = [];
$omdb_executed = false;
$omdb_elapsed = '';
$omdb_copyText = '';
$omdb_csvText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_omdb_tool'])) {
    $t0 = microtime(true);
    $omdb_ids = extract_unique_imdb_ids($omdb_input);
    $omdb_input = implode("\n", $omdb_ids);

    if (empty($omdb_ids)) {
        $omdb_errors[] = 'לא נמצאו מזהי IMDb (tt1234567).';
    } else {
        $omdbMap = omdb_fetch_bulk($omdb_ids, $omdbKey, $omdbTimeout, $omdbConnectTimeout, $omdbConcurrency);
        $akaMap = [];
        if ($omdb_useAka) {
            $akaMap = wikidata_fetch_akas_batched($omdb_ids, $wikidataLangs, $userAgent, $wdBatchSize, $wdTimeout, $wdConnectTimeout);
        }

        foreach ($omdb_ids as $imdbId) {
            $om = $omdbMap[$imdbId] ?? ['ok'=>false,'title'=>'','year'=>'','type'=>'','error'=>'OMDb: שגיאה'];
            $title = $om['ok'] ? $om['title'] : '';
            $year  = $om['ok'] ? $om['year']  : '';
            $type  = $om['ok'] ? $om['type']  : '';
            $source = 'OMDb';
            $error = $om['ok'] ? '' : ($om['error'] ?? 'OMDb: שגיאה');

            if ((!$om['ok'] || $title === '') && $omdb_fallback) {
                $im = imdb_fetch_title_year_jsonld($imdbId, $imdbTimeout, $imdbConnectTimeout);
                if ($im['ok']) {
                    if ($title === '') $title = $im['title'];
                    if ($year  === '') $year  = $im['year'];
                    if ($type  === '') $type  = $im['type'];
                    $source = 'IMDb (fallback)';
                    $error = '';
                }
            }

            $akas = $akaMap[$imdbId] ?? [];
            if ($title !== '' && !empty($akas)) {
                $main = mb_strtolower(trim($title));
                $akas = array_values(array_filter($akas, fn($a)=> mb_strtolower(trim($a)) !== $main));
            }
            if (count($akas) > $maxAkasPerTitle) $akas = array_slice($akas, 0, $maxAkasPerTitle);

            $omdb_rows[] = [
                'imdb_id' => $imdbId,
                'title'   => $title,
                'year'    => $year,
                'type'    => $type,
                'akas'    => $akas,
                'error'   => $error,
                'source'  => $source,
            ];
        }
    }
    $omdb_executed = true;
    $omdb_elapsed = round(microtime(true) - $t0, 2);

    $copyLines = [];
    $csvLines = ["imdb_id,title,year,type,aka,source"];
    foreach ($omdb_rows as $r) {
        if ($r['error']) {
            $copyLines[] = "{$r['imdb_id']} - ERROR: {$r['error']}";
            $csvLines[]  = "{$r['imdb_id']},\"\",\"\",\"\",\"\",\"\"";
        } else {
            $akaTxt = (!empty($r['akas'])) ? (' | AKA: ' . implode(', ', $r['akas'])) : '';
            $yr = (trim($r['year']) !== '') ? " [{$r['year']}]" : '';
            $copyLines[] = "{$r['title']}{$yr} ({$r['imdb_id']}){$akaTxt}";
            
            $akaCsv = implode(' | ', $r['akas']);
            $csvLines[] = $r['imdb_id']
                . ",\"" . str_replace('"','""',$r['title']) . "\""
                . ",\"" . str_replace('"','""',$r['year']) . "\""
                . ",\"" . str_replace('"','""',$r['type']) . "\""
                . ",\"" . str_replace('"','""',$akaCsv) . "\""
                . ",\"" . str_replace('"','""',$r['source']) . "\"";
        }
    }
    $omdb_copyText = implode("\n", $copyLines);
    $omdb_csvText = implode("\n", $csvLines);
}

// ===============================
// 3. טעינת ה-HTML
// ===============================
include 'header.php';
?>

<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; direction: rtl; margin: 0; }
    
    .nav-header { background: #2c3e50; color: #fff; padding: 15px 20px; display:flex; justify-content:space-between; align-items:center; }
    .nav-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
    
    .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }

    /* Tabs */
    .tabs { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 20px; gap:4px; }
    .tab-btn { 
        padding: 10px 20px; cursor: pointer; background: #e9ecef; border: none; border-radius: 5px 5px 0 0; 
        font-weight:600; color:#495057; transition: all 0.2s;
    }
    .tab-btn:hover { background: #dee2e6; }
    .tab-btn.active { background: #fff; border: 2px solid #ddd; border-bottom: 2px solid #fff; color: #2c3e50; margin-bottom: -2px; }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }

    .data-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
    
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: right; }
    th { background: #f8f9fa; color: #666; font-weight: 600; }
    tr:hover { background: #f1f1f1; }
    
    .btn { padding: 6px 12px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items:center; gap:5px; transition:0.2s; }
    .btn-primary { background: #3498db; color: white; border-color:#2980b9; }
    .btn-primary:hover { background: #2980b9; }
    .btn-outline { border: 1px solid #ced4da; background: white; color: #495057; }
    .btn-outline:hover { background: #e2e6ea; }
    
    .toolbar { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; margin-bottom:5px; align-items:center; }
    .sql-editor { width: 100%; height: 150px; padding: 10px; font-family: monospace; border: 1px solid #ced4da; border-radius: 4px; background: #2c3e50; color: #ecf0f1; font-size: 14px; }
    
    .ajax-line { margin-top: 10px; padding: 8px 10px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.1); font-size: 13px; display: none; }
    .ajax-line.ok { background:#e9fbe9; color:#114b1b; }
    .ajax-line.warn { background:#fff8e6; color:#4a3b00; }
    .ajax-line.err { background:#fbe9e9; color:#b11; }
    .ajax-line.info { background:#e9f4fb; color:#114b7b; }

    .two-cols { display: flex; gap: 20px; }
    .col { flex: 1; }
    @media (max-width: 768px) { .two-cols { flex-direction: column; } }
    
    .tag { display:inline-block; padding:2px 8px; border-radius:12px; border:1px solid #ddd; background:#f8f9fa; font-size:11px; }

    /* PTP Log specifics */
    .log-box { font-size:12px; color:#555; margin-top:8px; white-space:pre-wrap; max-height:200px; overflow-y:auto; background:#f9f9f9; padding:10px; border:1px solid #eee; border-radius:4px; font-family:monospace; }
</style>

<div class="nav-header">
    <div><h1>🛠️ Sandbox & DB Tools</h1></div>
    <div></div>
</div>

<div class="container">

    <div class="tabs">
        <button class="tab-btn <?= (!$omdb_executed) ? 'active' : '' ?>" onclick="openTab('imdb')">🎬 IMDb Checker</button>
        <button class="tab-btn" onclick="openTab('sql')">💻 SQL Sandbox</button>
        <button class="tab-btn <?= ($omdb_executed) ? 'active' : '' ?>" onclick="openTab('omdb_tool')">🌍 המרה: כותרות ⭢ IMDb</button>
        <button class="tab-btn" onclick="openTab('dedupe_tool')">🧽 ניקוי כפילויות</button>
        <button class="tab-btn" onclick="openTab('ptp_tool')">🍿 PTP ⭢ IMDb</button>
    </div>

    <div id="imdb" class="tab-content <?= (!$omdb_executed) ? 'active' : '' ?>">
        <div class="data-box">
            <h3>בדיקת רשימת IMDb</h3>
            <p style="font-size:13px; color:#666; margin-bottom:10px;">הדבק כאן רשימת קודים (tt1234567).</p>
            <form onsubmit="handleAjax(event, 'check_imdb_list', 'imdb_feedback', 'imdb_results')">
                <textarea id="imdb_list" name="imdb_list" class="sql-editor" style="height:160px" placeholder="tt1234567..." onpaste="sandboxHandlePasteTt(event)"></textarea>
                <div class="toolbar">
                    <button class="btn btn-outline" type="button" onclick="sandboxPasteFromClipboardTt()">📋 הדבק tt</button>
                    <input id="imdb_file" type="file" accept=".txt,.csv" style="display:none">
                    <button class="btn btn-outline" type="button" onclick="sandboxOpenFileImport()">📥 ייבוא קובץ CSV/TXT</button>
                    <button class="btn btn-outline" type="button" onclick="sandboxResetIMDb()">🧽 נקה</button>
                    <button class="btn btn-primary" type="submit">🔎 בדוק במסד</button>
                </div>
                <div id="imdb_feedback" class="ajax-line" aria-live="polite"></div>
            </form>
            <div id="imdb_results"></div>
            <hr style="margin:18px 0; opacity:0.25;">
            
            <div class="card">
                <div class="card-title" style="font-weight:bold; margin-bottom:5px;">🧹 ניקוי tt כפולים</div>
                <div style="margin:10px 0 8px; opacity:0.9; font-size:13px;">הדבק כאן טקסט/נתיב עם tt.</div>
                <textarea id="tt_dups_input" class="sql-editor" style="height:160px" placeholder="tt1234567..." onpaste="sandboxHandlePasteTtAllowDups(event, 'tt_dups_input')"></textarea>
                <div class="toolbar" style="margin-top:10px;">
                    <button class="btn btn-outline" type="button" onclick="sandboxPasteDupsFromClipboard()">📋 הדבק tt</button>
                    <input id="tt_dups_file" type="file" accept=".txt,.csv" style="display:none" onchange="sandboxImportTtDupsFile(this)">
                    <button class="btn btn-outline" type="button" onclick="sandboxOpenFileImportTtDups()">📥 ייבוא קובץ CSV/TXT</button>
                    <button class="btn btn-outline" type="button" onclick="splitTtUniqDup()">✨ פצל לייחודיים/כפולים</button>
                    <button class="btn btn-outline" type="button" onclick="clearTtUniqDup()">🧽 נקה</button>
                </div>
                <div id="tt_dups_feedback" class="ajax-line info" style="display:none; margin-top:10px;"></div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <div>
                        <div style="font-weight:700; margin-bottom:6px;">✅ ייחודיים <span id="tt_dups_unique_count" style="opacity:0.7;"></span></div>
                        <textarea id="tt_dups_unique" class="sql-editor" style="height:160px"></textarea>
                        <div style="margin-top:8px;"><button class="btn btn-outline" type="button" onclick="copyTextArea('tt_dups_unique')">📋 העתק</button></div>
                    </div>
                    <div>
                        <div style="font-weight:700; margin-bottom:6px;">♻️ כפולים <span id="tt_dups_dup_count" style="opacity:0.7;"></span></div>
                        <textarea id="tt_dups_duplicates" class="sql-editor" style="height:160px"></textarea>
                        <div style="margin-top:8px;"><button class="btn btn-outline" type="button" onclick="copyTextArea('tt_dups_duplicates')">📋 העתק</button></div>
                    </div>
                </div>
                <div id="tt_dups_stats" style="margin-top:10px; opacity:0.85;"></div>
            </div>
        </div>
    </div>

    <div id="sql" class="tab-content">
        <div class="data-box">
            <h3>הרצת שאילתות (SELECT בלבד)</h3>
            <form onsubmit="handleAjax(event, 'run_sql', 'sql_feedback', 'sql_results')">
                <textarea name="sql_query" class="sql-editor" placeholder="SELECT id, title_he FROM posters LIMIT 10"></textarea>
                <div style="margin-top:10px;">
                    <button type="submit" class="btn btn-primary">▶ הרץ</button>
                </div>
                <div id="sql_feedback" class="ajax-line"></div>
            </form>
            <div id="sql_results"></div>
        </div>
    </div>

    <div id="omdb_tool" class="tab-content <?= ($omdb_executed) ? 'active' : '' ?>">
        <div class="data-box">
            <h3>Textarea ⭢ כותרות + שנה + AKA (OMDb/Wikidata)</h3>
            <p style="font-size:13px; color:#666; margin-bottom:10px;">
                רק מזהים בפנים (tt#######). אין כפילויות. 
                <?php if($omdb_elapsed): ?> <span class="tag">זמן ריצה אחרון: <?= h($omdb_elapsed) ?>s</span><?php endif; ?>
            </p>

            <form method="post" id="omdbForm">
                <input type="hidden" name="run_omdb_tool" value="1">
                <textarea id="omdb_input" name="omdb_input" class="sql-editor" style="height:220px;" placeholder="tt0816692&#10;tt0111161"><?= h($omdb_input) ?></textarea>

                <div class="toolbar" style="margin-top:12px;">
                    <button class="btn btn-primary" type="submit">🚀 המר לרשימה</button>
                    <button class="btn btn-outline" type="button" id="btnOmdbPaste">📋 הדבק</button>
                    <button class="btn btn-outline" type="button" id="btnOmdbImport">📥 ייבוא CSV/TXT</button>
                    <button class="btn btn-outline" type="button" id="btnOmdbClear">🧽 נקה</button>
                    
                    <label class="btn btn-outline" style="cursor:pointer;">
                        <input type="checkbox" name="omdb_use_aka" <?= $omdb_useAka ? 'checked' : '' ?>>
                        <span style="margin-right:5px;">כולל AKA (Wikidata)</span>
                    </label>

                    <label class="btn btn-outline" style="cursor:pointer;">
                        <input type="checkbox" name="omdb_fallback" <?= $omdb_fallback ? 'checked' : '' ?>>
                        <span style="margin-right:5px;">Fallback ל-IMDb</span>
                    </label>
                    
                    <span class="btn btn-outline" style="background:#f8f9fa; cursor:default;">
                        IDs: <span id="omdbIdsCount" style="font-weight:bold; margin-right:5px; color:#007bff;">0</span>
                    </span>
                </div>
                
                <div id="omdbStatusLine" class="ajax-line info" style="display:none;"></div>
                <input type="file" id="omdbFileInput" accept=".txt,.csv,text/plain,text/csv" style="display:none">
            </form>

            <?php if (!empty($omdb_errors)): ?>
                <div class="ajax-line err" style="display:block;">
                    <?php foreach ($omdb_errors as $e): ?><div>❌ <?= h($e) ?></div><?php endforeach; ?>
                </div>
            <?php elseif ($omdb_executed): ?>
                <div class="ajax-line ok" style="display:block;">✅ נמצאו <?= count($omdb_ids) ?> מזהים ייחודיים.</div>
            <?php endif; ?>

            <?php if (!empty($omdb_rows)): ?>
                <div style="overflow-x:auto; margin-top:20px; border:1px solid #eee;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px">#</th>
                                <th style="width:120px">IMDb</th>
                                <th>שם + שנה</th>
                                <th style="width:90px">סוג</th>
                                <th>AKA</th>
                                <th style="width:100px">מקור</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($omdb_rows as $i => $r): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td>
                                    <b><?= h($r['imdb_id']) ?></b><br>
                                    <a target="_blank" href="https://www.imdb.com/title/<?= h($r['imdb_id']) ?>/" style="font-size:11px; color:#3498db;">פתח IMDb</a>
                                </td>
                                <td>
                                    <?php if ($r['error']): ?>
                                        <div style="color:#d9534f; font-weight:bold;">שגיאה</div>
                                        <div style="font-size:12px; color:#888;"><?= h($r['error']) ?></div>
                                    <?php else: ?>
                                        <div style="font-weight:bold; font-size:15px; color:#2c3e50;">
                                            <?= h($r['title']) ?>
                                            <?php if (trim($r['year']) !== ''): ?>
                                                <span style="color:#7f8c8d;">[<?= h($r['year']) ?>]</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="tag"><?= h($r['type'] ?: '-') ?></span></td>
                                <td><?= !empty($r['akas']) ? h(implode(', ', $r['akas'])) : '<span style="opacity:0.5">-</span>' ?></td>
                                <td><span class="tag"><?= h($r['source']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:20px; background:#f9f9f9; padding:15px; border-radius:8px; border:1px solid #eee;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <strong>טקסט להעתקה</strong>
                        <div class="toolbar" style="margin:0;">
                            <button class="btn btn-outline" type="button" onclick="copyOmdbOut()">📋 העתק</button>
                            <button class="btn btn-outline" type="button" onclick="downloadOmdbTxt()">📥 הורד TXT</button>
                            <button class="btn btn-outline" type="button" onclick="downloadOmdbCsv()">📥 הורד CSV</button>
                        </div>
                    </div>
                    <textarea id="omdbOut" class="sql-editor" readonly style="height:150px; background:#fff; color:#333;"><?= h($omdb_copyText) ?></textarea>
                    <textarea id="omdbCsv" style="display:none"><?= h($omdb_csvText) ?></textarea>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="dedupe_tool" class="tab-content">
        <div class="data-box" id="global_dedupe_box">
            <h2>ניקוי כפילויות — להשאיר לינק אחד מכל כפילות</h2>
            <div style="opacity:0.75; margin-top:-6px; margin-bottom:10px; font-size:13px;">
                מדביקים כל רשימה (לינקים / שורות טקסט). נשמור סדר, נוציא ייחודיים, ובנפרד נציג את כל השורות שהופיעו יותר מפעם אחת.
            </div>
            <textarea id="global_dups_input" class="sql-editor" style="height:180px" placeholder="הדבק כאן..."></textarea>
            <div class="toolbar" style="margin-top:10px;">
                <button class="btn btn-outline" type="button" onclick="globalPasteFromClipboard()">📋 הדבק</button>
                <button class="btn btn-outline" type="button" onclick="splitGlobalUniqDup()">✨ פצל לייחודיים/כפולים</button>
                <button class="btn btn-outline" type="button" onclick="clearGlobalUniqDup()">🧽 נקה</button>
            </div>
            <div id="global_dups_feedback" class="ajax-line info" style="display:none; margin-top:10px;"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                <div>
                    <div style="font-weight:700; margin-bottom:6px;">
                        ייחודיים <span id="global_unique_count" style="opacity:0.7; font-weight:600;"></span>
                    </div>
                    <textarea id="global_dups_unique" class="sql-editor" style="height:160px" placeholder="כאן יופיעו ייחודיים"></textarea>
                    <div style="margin-top:8px;">
                        <button class="btn btn-outline" type="button" onclick="copyTextArea('global_dups_unique')">📋 העתק ייחודיים</button>
                    </div>
                </div>
                <div>
                    <div style="font-weight:700; margin-bottom:6px;">
                        אלו שהיה להם כפילות <span id="global_dup_count" style="opacity:0.7; font-weight:600;"></span>
                    </div>
                    <textarea id="global_dups_duplicates" class="sql-editor" style="height:160px" placeholder="כאן יופיעו כפולים"></textarea>
                    <div style="margin-top:8px;">
                        <button class="btn btn-outline" type="button" onclick="copyTextArea('global_dups_duplicates')">📋 העתק כפולים</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="ptp_tool" class="tab-content">
        <div class="data-box">
            <h2>PTP → IMDb ID (tt...)</h2>
            <div style="opacity:0.75; margin-top:-6px; margin-bottom:10px; font-size:13px;">
                ממיר רשימת כתובות/IDs של PTP ל־tt באמצעות cURL + cookies.<br>
                <?php if (!is_file($PTP_COOKIE_FILE)): ?>
                    <span style="color:#d9534f; font-weight:bold;">⚠️ לא נמצא הקובץ ptp_cookies.txt בתיקייה.</span>
                <?php else: ?>
                    <span style="color:#2ecc71;">✅ קובץ Cookies מזוהה.</span>
                <?php endif; ?>
            </div>

            <textarea id="ptp_input" class="sql-editor" style="height:180px" placeholder="הדבק כאן כתובות PTP / id=12345 / מספרים..."></textarea>
            
            <div class="toolbar" style="margin-top:10px;">
                <button class="btn btn-primary" type="button" onclick="runPtpConversion()">🔄 המר</button>
                <button class="btn btn-outline" type="button" onclick="clearPtpTool()">🧹 נקה</button>
            </div>

            <div style="margin-top:15px;">
                <label style="font-weight:700; display:block; margin-bottom:5px;">תוצאות (tt בלבד, ייחודיים)</label>
                <textarea id="ptp_output" class="sql-editor" readonly style="height:150px; background:#fff; color:#333;" placeholder="כאן תופיע הרשימה הסופית"></textarea>
                <button class="btn btn-outline" type="button" style="margin-top:5px;" onclick="copyTextArea('ptp_output')">📋 העתק תוצאה</button>
            </div>

            <div id="ptp_log" class="log-box" style="display:none; margin-top:15px;"></div>
        </div>
    </div>

</div>

<script>
// --- JS Helper Functions ---
async function handleAjax(e, action, feedbackId, resultId) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', action);
    const feedback = document.getElementById(feedbackId);
    const results = document.getElementById(resultId);
    feedback.className = 'ajax-line info';
    feedback.textContent = 'מעבד נתונים';
    feedback.style.display = 'block';
    results.style.opacity = '0.5';
    try {
        const res = await fetch('', { method: 'POST', body: formData });
        const data = await res.json();
        results.style.opacity = '1';
        if (data.status === 'ok') {
            feedback.style.display = 'none';
            results.innerHTML = data.html;
        } else {
            feedback.className = 'ajax-line err';
            feedback.textContent = data.msg || 'שגיאה';
        }
    } catch (err) {
        results.style.opacity = '1';
        feedback.className = 'ajax-line err';
        feedback.textContent = 'שגיאה: ' + err.message;
    }
}

function openTab(id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.currentTarget.classList.add('active');
}

function sandboxShowLine(msg, type = 'ok') {
    const activeTab = document.querySelector('.tab-content.active');
    let feedbackId = 'imdb_feedback'; // default
    if (activeTab && activeTab.id === 'sql') feedbackId = 'sql_feedback';
    // if activeTab is omdb or dedupe, we might want to alert or ignore
    const el = document.getElementById(feedbackId);
    if (!el) return;
    el.className = 'ajax-line ' + type;
    el.textContent = msg;
    el.style.display = 'block';
}

function sandboxExtractTt(text) {
    if (!text) return '';
    const matches = String(text).match(/tt\d{5,}/gi) || [];
    const uniq = Array.from(new Set(matches.map(x => x.toLowerCase())));
    return uniq.join("\n");
}
function mergeTtLists(existingText, incomingText) {
    const existing = (sandboxExtractTt(existingText) || '').split('\n').filter(Boolean);
    const incoming = (sandboxExtractTt(incomingText) || '').split('\n').filter(Boolean);
    const seen = new Set(existing.map(x => x.toLowerCase()));
    const added = [];
    for (const id of incoming) {
        const k = id.toLowerCase();
        if (!seen.has(k)) { seen.add(k); added.push(k); }
    }
    const merged = existing.concat(added);
    return { mergedText: merged.join('\n'), addedCount: added.length, totalCount: merged.length };
}
function sandboxHandlePasteTt(e) {
    const text = (e.clipboardData || window.clipboardData).getData('text');
    if (!text || !sandboxExtractTt(text)) return;
    const ta = e.target;
    const res = mergeTtLists(ta.value, text);
    e.preventDefault();
    ta.value = res.mergedText;
    sandboxShowLine('נוספו ' + res.addedCount + ' מזהים.', 'ok');
}
async function sandboxPasteFromClipboardTt() {
    try {
        const text = await navigator.clipboard.readText();
        if (!text || !sandboxExtractTt(text)) { sandboxShowLine('הלוח ריק/ללא tt.', 'warn'); return; }
        const ta = document.querySelector('textarea[name="imdb_list"]');
        const res = mergeTtLists(ta.value, text);
        ta.value = res.mergedText;
        sandboxShowLine('נוספו ' + res.addedCount + ' מזהים.', 'ok');
    } catch (e) {}
}
function sandboxResetIMDb() {
    document.querySelector('textarea[name="imdb_list"]').value = '';
    document.getElementById('imdb_results').innerHTML = '';
}
function sandboxOpenFileImport() { const f=document.getElementById('imdb_file'); if(f){f.value=''; f.click();} }
document.getElementById('imdb_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const val = sandboxExtractTt(e.target.result);
        document.querySelector('textarea[name="imdb_list"]').value = val;
        sandboxShowLine('יובאו ' + val.split('\n').length + ' מזהים.', 'ok');
    };
    reader.readAsText(file);
});
function copyMissing() {
    const el = document.getElementById("missingList");
    if(el) { el.select(); document.execCommand("copy"); sandboxShowLine('הועתק!', 'ok'); }
}
function exportTableToCSV(filename) {
    var csv = [], rows = document.querySelectorAll("#sqlTable tr");
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        for (var j = 0; j < cols.length; j++) row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        csv.push(row.join(","));
    }
    var blob = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
// TT Dups
function sandboxShowLineTo(elId, msg, type = 'ok') {
  const el = document.getElementById(elId);
  if (el) { el.className = 'ajax-line ' + type; el.textContent = msg; el.style.display = 'block'; }
}
function sandboxHandlePasteTtAllowDups(e, targetId) {
  try {
    const clip = (e.clipboardData || window.clipboardData).getData('text') || '';
    const target = document.getElementById(targetId);
    const mm = clip.match(/tt\d{5,}/ig);
    const ids = mm ? mm.map(x => x.toLowerCase()) : [];
    if (ids.length && target) {
       e.preventDefault();
       const existing = (target.value || '').trim();
       target.value = (existing ? existing + "\n" : "") + ids.join("\n");
       sandboxShowLineTo('tt_dups_feedback', 'הודבקו ' + ids.length + ' מזהים.', 'ok');
    }
  } catch(err) {}
}
async function sandboxPasteDupsFromClipboard() {
  const target = document.getElementById('tt_dups_input');
  try {
    const clip = await navigator.clipboard.readText();
    const mm = (clip||'').match(/tt\d{5,}/ig);
    if(mm) {
       target.value = (target.value ? target.value.trim()+"\n" : "") + mm.join("\n");
       sandboxShowLineTo('tt_dups_feedback', 'הודבק.', 'ok');
    }
  } catch(e){}
}
function sandboxOpenFileImportTtDups() { document.getElementById('tt_dups_file').click(); }
function sandboxImportTtDupsFile(inputEl) {
    const file = inputEl.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function() {
        const mm = (reader.result||'').match(/tt\d{5,}/ig);
        if(mm) {
             const target = document.getElementById('tt_dups_input');
             target.value = (target.value ? target.value.trim()+"\n" : "") + mm.join("\n");
             sandboxShowLineTo('tt_dups_feedback', 'נטען.', 'ok');
        }
    };
    reader.readAsText(file);
}
function splitTtUniqDup() {
  const input = document.getElementById('tt_dups_input');
  const mm = (input.value || '').match(/tt\d{5,}/ig);
  const ids = mm ? mm.map(x => x.toLowerCase()) : [];
  const counts = {};
  ids.forEach(x => counts[x] = (counts[x]||0)+1);
  const u = [], d = [];
  Object.keys(counts).forEach(k => { if(counts[k]===1) u.push(k); else d.push(k); });
  document.getElementById('tt_dups_unique').value = u.join("\n");
  document.getElementById('tt_dups_duplicates').value = d.join("\n");
  document.getElementById('tt_dups_unique_count').textContent = '('+u.length+')';
  document.getElementById('tt_dups_dup_count').textContent = '('+d.length+')';
}
function clearTtUniqDup() {
    ['tt_dups_input','tt_dups_unique','tt_dups_duplicates'].forEach(x=>document.getElementById(x).value='');
    ['tt_dups_unique_count','tt_dups_dup_count','tt_dups_stats'].forEach(x=>document.getElementById(x).textContent='');
}
// Global Dedupe
async function globalPasteFromClipboard() {
    try {
        const t = await navigator.clipboard.readText();
        if(t) document.getElementById('global_dups_input').value += t;
    } catch(e){}
}
function splitGlobalUniqDup() {
    const lines = document.getElementById('global_dups_input').value.split(/\r?\n/).map(x=>x.trim()).filter(Boolean);
    const counts = {};
    lines.forEach(x=>counts[x]=(counts[x]||0)+1);
    const seen = new Set(), u=[], d=[], dSeen=new Set();
    lines.forEach(l => {
        if(!seen.has(l)) { seen.add(l); u.push(l); }
        if(counts[l]>1 && !dSeen.has(l)) { dSeen.add(l); d.push(l); }
    });
    document.getElementById('global_dups_unique').value = u.join("\n");
    document.getElementById('global_dups_duplicates').value = d.join("\n");
    document.getElementById('global_unique_count').textContent = '('+u.length+')';
    document.getElementById('global_dup_count').textContent = '('+d.length+')';
}
function clearGlobalUniqDup() {
    ['global_dups_input','global_dups_unique','global_dups_duplicates'].forEach(x=>document.getElementById(x).value='');
    ['global_unique_count','global_dup_count'].forEach(x=>document.getElementById(x).textContent='');
}
function copyTextArea(id) {
    const el = document.getElementById(id);
    if(el) { el.select(); document.execCommand('copy'); }
}

// === PTP Tool JS ===
async function runPtpConversion() {
    const input = document.getElementById('ptp_input');
    const output = document.getElementById('ptp_output');
    const logEl = document.getElementById('ptp_log');
    
    logEl.style.display = 'block';
    
    const lines = input.value.replace(/\r/g,'').split('\n').map(x=>x.trim()).filter(Boolean);
    const ttSet = new Set();
    logEl.textContent = 'מתחיל עיבוד...\n';

    const extractTT = t => (t.match(/\b(tt\d{7,})\b/i) || [])[1]?.toLowerCase() || null;
    const extractPTP = t => (t.match(/(?:\?|&)id=(\d+)/i) || t.match(/^\s*(\d{1,12})\s*$/) || t.match(/\bid\s*=\s*(\d+)/i))?.[1] || null;

    for (let i=0; i<lines.length; i++) {
        const line = lines[i];
        logEl.textContent += `#${i+1} ${line.substring(0,50)}... `;

        const directTT = extractTT(line);
        if (directTT) {
            ttSet.add(directTT);
            logEl.textContent += `[קיים TT] -> ${directTT}\n`;
            continue;
        }

        const ptpId = extractPTP(line);
        if (!ptpId) {
            logEl.textContent += `[לא זוהה ID]\n`;
            continue;
        }

        try {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'ptp_lookup');
            formData.append('ptp_id', ptpId);
            
            const res = await fetch('', { method:'POST', body: formData });
            const j = await res.json();

            if (!j.ok) {
                if (j.err === 'NO_COOKIES') {
                    logEl.textContent += ` [שגיאה: חסר ptp_cookies.txt]\n`;
                    break;
                }
                logEl.textContent += ` [לא נמצא ב-PTP] (HTTP ${j.http})\n`;
            } else {
                ttSet.add(j.tt);
                logEl.textContent += ` [נמצא] -> ${j.tt}\n`;
            }
        } catch (e) {
            logEl.textContent += ` [שגיאת רשת]\n`;
        }
        
        // גלילה אוטומטית ללוג
        logEl.scrollTop = logEl.scrollHeight;
    }

    output.value = Array.from(ttSet).join('\n');
    logEl.textContent += `\n=== סיום. נמצאו ${ttSet.size} מזהים ייחודיים. ===`;
}

function clearPtpTool() {
    document.getElementById('ptp_input').value = '';
    document.getElementById('ptp_output').value = '';
    document.getElementById('ptp_log').textContent = '';
    document.getElementById('ptp_log').style.display = 'none';
}
// === End PTP JS ===

// OMDb JS
(function(){
  const ta = document.getElementById('omdb_input');
  if(!ta) return;
  const statusLine = document.getElementById('omdbStatusLine');
  const idsCount = document.getElementById('omdbIdsCount');
  const fileInput = document.getElementById('omdbFileInput');
  function extractIds(text){
    const m = text.match(/tt\d{5,12}/gi) || [];
    const seen = new Set();
    const out = [];
    for (const raw of m){
      const id = raw.toLowerCase();
      if (!seen.has(id)){ seen.add(id); out.push(id); }
    }
    return out;
  }
  function updateCount() { if(idsCount) idsCount.textContent = String(extractIds(ta.value).length); }
  function mergeIds(newIds){
    const current = extractIds(ta.value);
    const set = new Set(current);
    for (const id of newIds){ if (!set.has(id)){ set.add(id); current.push(id); } }
    ta.value = current.join('\n');
    updateCount();
  }
  const btnPaste = document.getElementById('btnOmdbPaste');
  if(btnPaste) btnPaste.addEventListener('click', async () => {
      try {
        const txt = await navigator.clipboard.readText();
        mergeIds(extractIds(txt));
      } catch(e) {}
  });
  const btnImport = document.getElementById('btnOmdbImport');
  if(btnImport) btnImport.addEventListener('click', () => fileInput.click());
  const btnClear = document.getElementById('btnOmdbClear');
  if(btnClear) btnClear.addEventListener('click', () => { ta.value = ''; updateCount(); });
  if(fileInput) fileInput.addEventListener('change', () => {
      const f = fileInput.files[0];
      if(f) {
          const r = new FileReader();
          r.onload = () => mergeIds(extractIds(r.result||''));
          r.readAsText(f);
      }
      fileInput.value = '';
  });
  ta.addEventListener('input', () => updateCount());
  updateCount();
})();
function copyOmdbOut(){ const el=document.getElementById('omdbOut'); if(el){el.select();document.execCommand('copy');} }
function downloadOmdbTxt(){ const el=document.getElementById('omdbOut'); if(!el)return; const b=new Blob([el.value],{type:'text/plain;charset=utf-8'}); const a=document.createElement('a'); a.href=URL.createObjectURL(b); a.download='omdb_list.txt'; document.body.appendChild(a); a.click(); a.remove(); }
function downloadOmdbCsv(){ const el=document.getElementById('omdbCsv'); if(!el)return; const b=new Blob([el.value],{type:'text/csv;charset=utf-8'}); const a=document.createElement('a'); a.href=URL.createObjectURL(b); a.download='omdb_list.csv'; document.body.appendChild(a); a.click(); a.remove(); }
</script>

<?php include 'footer.php'; ?>