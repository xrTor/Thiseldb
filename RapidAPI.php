<?php
// ===== מפתחות =====
$tmdbKey = '931b94936ba364daf0fd91fb38ecd91e';
$omdbKey = 'f7e4ae0b';
$tvdbKey = '1c93003f-ab80-4baf-b5c4-58c7b96494a2'; // TVDB v4
$rapidApiKey = 'f5d4bd03c8msh29a2dc12893f4bfp157343jsn2b5bfcad5ae1'; // הוספת מפתח RapidAPI

// ✅ ה־IMDB SCRAPER (שים את imdb.class.php ליד הקובץ או עדכן נתיב)
include_once __DIR__ . '/imdb.class.php';

// ===== DEBUG =====
$DEBUG = true; // שנה ל-true כדי לראות דיבאג
function dbg(...$x){ global $DEBUG; if($DEBUG){ echo "\n"; } }

// ===== רשימת כותרים לדוגמה =====
$imdbIDs = [
  'tt2576852', 
  'tt1442462',
  'tt6806448',
  'tt1119644', // Fringe (TV)
  'tt0816692', // Interstellar (Movie)
  'tt1013752', // Fast & Furious (Movie)
];

// ===== עזר =====
function translateGenre($genre) {
  $map = [
    'Action'=>'אקשן','Adventure'=>'הרפתקאות','Comedy'=>'קומדיה','Drama'=>'דרמה',
    'Sci-Fi'=>'מדע בדיוני','Fantasy'=>'פנטזיה','Romance'=>'רומנטי','Crime'=>'פשע',
    'Thriller'=>'מותחן','Mystery'=>'מסתורין','Horror'=>'אימה','History'=>'היסטוריה',
    'Music'=>'מוזיקה','Family'=>'משפחה','War'=>'מלחמה','Western'=>'מערבון',
    'Documentary'=>'דוקומנטרי','Animation'=>'אנימציה'
  ];
  return $map[trim($genre)] ?? trim($genre);
}
function safeHtml($v){ return is_array($v)?array_map('safeHtml',$v):htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function stripAllTags($v){ return is_array($v)?array_map('stripAllTags',$v):trim(strip_tags((string)$v)); }
function imdb_get($IMDB,$method,...$args){ try{ if(method_exists($IMDB,$method)) return $IMDB->$method(...$args);}catch(Throwable $e){} return null; }
function sanitize_html_for_parse(string $html): string {
  $html = preg_replace('~<script[^>]*>.*?</script>~is',' ',$html);
  $html = preg_replace('~<style[^>]*>.*?</style>~is',' ',$html);
  return $html;
}

// ===== HTTP GET (עם Cookie-Jar, ריטריי, ומעבר לדסקטופ/מובייל) =====
function http_get_curl($url,$headers=[],$cookie='lc-main=en-US; session-id=000-0000000-0000000; session-id-time=2082787201l',$mobile=false,$retries=3){
  static $jar = '';
  $uaDesktop='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
  $uaMobile ='Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Mobile Safari/537.36';

  $last=[0,'',''];
  for($attempt=1;$attempt<=$retries;$attempt++){
    $ch=curl_init($url);
    $def=[
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
      'Accept-Language: en-US,en;q=0.9',
      'Referer: https://www.imdb.com/',
      'Sec-Fetch-Dest: document',
      'Sec-Fetch-Mode: navigate',
      'Sec-Fetch-Site: same-origin',
      'User-Agent: '.($mobile?$uaMobile:$uaDesktop),
    ];
    $hdr=array_merge($def,$headers);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_FOLLOWLOCATION=>true,
      CURLOPT_TIMEOUT=>25,
      CURLOPT_CONNECTTIMEOUT=>10,
      CURLOPT_HTTPHEADER=>$hdr,
      CURLOPT_ENCODING=>'',
      CURLOPT_HEADER=>true,
      CURLOPT_COOKIE=> trim($jar.'; '.$cookie, '; ')
    ]);
    $resp=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $headerSize=curl_getinfo($ch,CURLINFO_HEADER_SIZE);
    $rawHeaders=substr($resp,0,$headerSize);
    $body=substr($resp,$headerSize);
    $err=curl_error($ch);
    curl_close($ch);

    if ($rawHeaders && preg_match_all('~^Set-Cookie:\s*([^;\r\n=]+=[^;\r\n]+)~im',$rawHeaders,$m)){
      foreach($m[1] as $ck){
        $parts=explode('=',$ck,2); $name=$parts[0];
        $jar = preg_replace('~(^|;\s*)'.preg_quote($name,'~').'=[^;]+~','',$jar);
        $jar = trim(trim($jar,'; ').'; '.$ck,'; ');
      }
    }

    $last=[$code,$body,$err];
    if ($code>=200 && $code<400 && strlen($body)>500) break;

    usleep(250000);
    if ($attempt==1){ $mobile = !$mobile; }
    if ($attempt==2){ $cookie = 'lc-main=en-US; imdblocale=en-US; da-geo=1;'; }
  }

  if(($last[0] >= 400 || !$last[1]) && function_exists('file_get_contents')){
    $ua = $mobile ? $uaMobile : $uaDesktop;
    $ctx = stream_context_create(['http'=>[
      'method'=>'GET',
      'header'=>implode("\r\n",$headers)."\r\nUser-Agent: $ua\r\nCookie: ".trim($jar.'; '.$cookie,'; ')."\r\n",
      'timeout'=>25
    ]]);
    $body2 = @file_get_contents($url,false,$ctx);
    if($body2){ $last=[200,$body2,'']; }
  }
  return $last;
}


// === פונקציה לקריאה ל-RapidAPI ===
function rapidapi_get_by_id($imdbID, $apiKey) {
    if (empty($imdbID) || empty($apiKey)) {
        return [];
    }
    
    $url = "https://imdb236.p.rapidapi.com/api/imdb/" . urlencode($imdbID);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: imdb236.p.rapidapi.com",
            "x-rapidapi-key: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        dbg("RapidAPI cURL Error:", $err);
        return ['error' => "cURL Error #: " . $err];
    } else {
        return json_decode($response, true) ?: ['error' => 'Failed to decode JSON from RapidAPI'];
    }
}


// ===== תרגום סוגי קשרים =====
function translateConnectionType($en){
  static $map=[
    'Follows'=>'מוקדם יותר','Followed by'=>'המשך',
    'Prequel'=>'פריקוול','Sequel'=>'סיקוול',
    'References'=>'מתייחס אל','Referenced in'=>'מוזכר ב',
    'Edited into'=>'נערך אל','Edited from'=>'נערך מ',
    'Features'=>'מכיל קטעים מ','Featured in'=>'מופיע ב',
    'Spoofs'=>'פרודיה על','Spoofed in'=>'נעשתה עליו פרודיה ב',
    'Spin-off'=>'ספין-אוף','Spun off from'=>'נגזר מ',
    'Remake of'=>'רימייק של','Remade as'=>'נעשה כרימייק',
    'Version of'=>'גרסה של','Alternate versions'=>'גרסאות חלופיות',
    'Compilation of'=>'אוסף של','Contains footage from'=>'מכיל קטעים מ',
    'Connections'=>'קשרים',
  ];
  foreach($map as $k=>$v){ if(stripos($en,$k)!==false) return $v; }
  return $en;
}
function _known_conn_titles(): array {
  return [
    'Follows','Followed by','Prequel','Sequel',
    'References','Referenced in','Edited into','Edited from',
    'Features','Featured in','Spoofs','Spoofed in',
    'Spin-off','Spun off from','Remake of','Remade as',
    'Version of','Alternate versions','Compilation of',
    'Contains footage from','Connections',
  ];
}
function _is_known_conn_title(string $title): bool {
  $title = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
  foreach (_known_conn_titles() as $k) { if (stripos($title, $k) !== false) return true; }
  return false;
}
function _clean_conn_items(array $items): array {
  $badNeedles = ['Contribute to this page', 'Learn more about contributing'];
  $out = [];
  foreach ($items as $t) {
    $t = preg_replace('/\s+/', ' ', trim($t));
    if ($t === '') continue;
    $skip = false;
    foreach ($badNeedles as $bn) { if (stripos($t, $bn) !== false) { $skip = true; break; } }
    if (!$skip) $out[] = $t;
  }
  return array_values(array_unique($out));
}

// ===== Connections: קודם /reference, אחר-כך דסקטופ, ואז מובייל =====
function imdb_get_connections($imdbID): array {
  $all = [];

  // warm-up (לפעמים עוזר לקוקיז)
  $pre = "https://www.imdb.com/title/{$imdbID}/";
  [$wCode,$wHtml] = http_get_curl($pre);
  dbg("WARM", $imdbID, "code=".$wCode, "len=".strlen((string)$wHtml));
  usleep(200000);

  // 1) reference
  $refUrl = "https://www.imdb.com/title/{$imdbID}/reference";
  [$codeR, $htmlR] = http_get_curl($refUrl);
  dbg("REF", $imdbID, "code=".$codeR, "len=".strlen((string)$htmlR));
  if ($codeR < 400 && $htmlR) {
    $cleanR = sanitize_html_for_parse($htmlR);
    $pRef = parse_conn_reference($cleanR);
    if (!empty($pRef)) $all = array_merge_recursive($all, $pRef);
  }

  // 2) דסקטופ
  if (empty($all)) {
    $desktopUrl = "https://www.imdb.com/title/{$imdbID}/movieconnections/";
    [$codeD, $htmlD] = http_get_curl($desktopUrl);
    dbg("DESKTOP", $imdbID, "code=".$codeD, "len=".strlen((string)$htmlD));
    if ($codeD < 400 && $htmlD) {
      $pNext = parse_conn_next_data($htmlD);
      if (!empty($pNext)) $all = array_merge_recursive($all, $pNext);

      if (empty($pNext)) {
        $clean = sanitize_html_for_parse($htmlD);
        $p2 = parse_conn_dom($clean);
        if (!empty($p2)) $all = array_merge_recursive($all, $p2);
        if (empty($p2)) {
          $p3 = parse_conn_regex($clean);
          if (!empty($p3)) $all = array_merge_recursive($all, $p3);
        }
      }
    }
  }

  // 3) מובייל
  if (empty($all)) {
    $mobileUrl = "https://m.imdb.com/title/{$imdbID}/movieconnections/";
    [$codeM, $htmlM] = http_get_curl(
      $mobileUrl,
      ['Referer: https://m.imdb.com/title/'.$imdbID.'/'],
      'lc-main=en-US; imdblocale=en-US; da-geo=1;',
      true
    );
    dbg("MOBILE", $imdbID, "code=".$codeM, "len=".strlen((string)$htmlM));
    if ($codeM < 400 && $htmlM) {
      $cleanM = sanitize_html_for_parse($htmlM);
      $p4 = parse_conn_dom($cleanM);
      if (!empty($p4)) $all = array_merge_recursive($all, $p4);
      if (empty($p4)) {
        $p5 = parse_conn_regex($cleanM);
        if (!empty($p5)) $all = array_merge_recursive($all, $p5);
      }
      if (empty($p4) && empty($p5)) {
        $p6 = parse_conn_mobile_modern($cleanM);
        if (!empty($p6)) $all = array_merge_recursive($all, $p6);
      }
    }
  }

  // Normalize
  $out = [];
  foreach ($all as $k => $items) {
    $k = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $k));
    $k = rtrim($k, ":\x{0589}");
    $items = array_values(array_unique(array_map(
      fn($t)=>preg_replace('/\s+/', ' ', trim($t)),
      (array)$items
    )));
    if ($k && $items) {
      if (!isset($out[$k])) $out[$k] = [];
      $out[$k] = array_values(array_unique(array_merge($out[$k], $items)));
    }
  }

  if (empty($out)) {
    $peek = substr(preg_replace('/\s+/', ' ', (string)($htmlR ?? $htmlD ?? $htmlM ?? '')), 0, 240);
    dbg("NO CONNECTIONS", $imdbID, "peek:", $peek);
  }
  return $out;
}

// ===== פרסרים =====
function parse_conn_next_data(string $html): array {
  $out = [];
  if (!preg_match('~<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>~is', $html, $m)) return $out;
  $j = json_decode(html_entity_decode($m[1]), true);
  if (!$j) return $out;

  $stack = [$j];
  $buckets = [];
  while ($stack) {
    $node = array_pop($stack);
    if (!is_array($node)) continue;

    $groups = null;
    if (isset($node['connectionsGroups']) && is_array($node['connectionsGroups'])) $groups = $node['connectionsGroups'];
    elseif (isset($node['connections']) && is_array($node['connections']))         $groups = $node['connections'];
    elseif (isset($node['groups']) && is_array($node['groups']))                   $groups = $node['groups'];

    if ($groups) {
      foreach ($groups as $g) {
        $title = $g['category'] ?? $g['title'] ?? $g['name'] ?? '';
        if (!$title || !_is_known_conn_title($title)) continue;

        $rows = $g['items'] ?? $g['titles'] ?? $g['entries'] ?? [];
        $items = [];
        foreach ($rows as $it) {
          $txtParts = [];
          foreach (['titleText','title','titleNameText','seriesTitle','movieTitle','name','primaryText','originalText'] as $k) {
            if (!empty($it[$k])) $txtParts[] = is_array($it[$k]) ? ($it[$k]['text'] ?? '') : $it[$k];
          }
          if (!empty($it['year']))        $txtParts[] = '(' . $it['year'] . ')';
          if (!empty($it['subtitle']))    $txtParts[] = $it['subtitle'];
          if (!empty($it['text']))        $txtParts[] = is_array($it['text']) ? ($it['text']['text'] ?? '') : $it['text'];
          if (!empty($it['description'])) $txtParts[] = $it['description'];
          if (!empty($it['const']))       $txtParts[] = $it['const']; // ttXXXX

          foreach (['title','titleText','primary'] as $subKey) {
            if (!empty($it[$subKey]) && is_array($it[$subKey])) {
              foreach (['text','titleText','title','name'] as $kk) {
                if (!empty($it[$subKey][$kk])) {
                  $txtParts[] = is_array($it[$subKey][$kk]) ? ($it[$subKey][$kk]['text'] ?? '') : $it[$subKey][$kk];
                }
              }
            }
          }
          $txt = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($txtParts))));
          if ($txt !== '') $items[] = $txt;
        }
        $items = _clean_conn_items($items);
        if ($items) {
          $key = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
          $buckets[$key] = isset($buckets[$key]) ? array_merge($buckets[$key], $items) : $items;
        }
      }
    }

    foreach ($node as $v) if (is_array($v)) $stack[] = $v;
  }
  foreach ($buckets as $k=>$arr) $out[$k] = array_values(array_unique($arr));
  return $out;
}
function parse_conn_dom(string $html): array {
  $out = [];
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  @$dom->loadHTML($html);
  $xp = new DOMXPath($dom);
  $headers = $xp->query('//h2|//h3|//h4|//strong|//span');
  foreach ($headers as $h) {
    $raw = $h->textContent ?? '';
    $title = rtrim(trim(preg_replace('/\s+/', ' ', $raw)), ":\x{0589}");
    if ($title === '' || !_is_known_conn_title($title)) continue;

    $ul = null;
    $sib = $h->nextSibling;
    while ($sib && $sib->nodeType !== XML_ELEMENT_NODE) $sib = $sib->nextSibling;
    if ($sib) $ul = $xp->query('.//ul', $sib)->item(0);
    if (!$ul) $ul = $xp->query('.//ul', $h->parentNode)->item(0);
    if (!$ul && $h->parentNode?->parentNode) $ul = $xp->query('.//ul', $h->parentNode->parentNode)->item(0);
    if (!$ul) continue;

    $lis = $xp->query('.//li', $ul);
    if (!$lis || !$lis->length) continue;
    $items = [];
    foreach ($lis as $li) $items[] = $li->textContent;
    $items = _clean_conn_items($items);
    if ($items) {
      $normTitle = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
      $out[$normTitle] = isset($out[$normTitle]) ? array_values(array_unique(array_merge($out[$normTitle], $items))) : $items;
    }
  }
  return $out;
}
function parse_conn_regex(string $html): array {
  $out = [];
  $min = preg_replace('/\s+/', ' ', $html);
  if (preg_match_all('~<(h2|h3|h4|strong|span)[^>]*>(.*?)</\1>~i', $min, $heads, PREG_OFFSET_CAPTURE)) {
    for ($i=0; $i<count($heads[0]); $i++) {
      $title = strip_tags($heads[2][$i][0]);
      $title = rtrim(trim(preg_replace('/\s+/', ' ', $title)), ":\x{0589}");
      if ($title === '' || !_is_known_conn_title($title)) continue;

      $start = $heads[0][$i][1] + strlen($heads[0][$i][0]);
      $end   = isset($heads[0][$i+1]) ? $heads[0][$i+1][1] : strlen($min);
      $slice = substr($min, $start, $end - $start);

      if (preg_match('~<ul[^>]*>(.*?)</ul>~i', $slice, $ulm)) {
        if (preg_match_all('~<li[^>]*>(.*?)</li>~i', $ulm[1], $lis)) {
          $items = array_map(fn($li) => strip_tags($li), $lis[1]);
          $items = _clean_conn_items($items);
          if ($items) {
            $normTitle = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
            $out[$normTitle] = isset($out[$normTitle]) ? array_values(array_unique(array_merge($out[$normTitle], $items))) : $items;
          }
        }
      }
    }
  }
  return $out;
}
function parse_conn_reference(string $html): array {
  $out = [];
  $min = preg_replace('/\s+/', ' ', $html);

  if (!preg_match('~(Connections|Movie connections)\s*</h[1-6]>(.*?)(</h[1-6]>|</body>)~i', $min, $m)) return $out;
  $block = $m[0];

  if (preg_match_all('~<(h[2-6]|strong|span)[^>]*>(.*?)</\1>\s*(<ul[^>]*>.*?</ul>)~i', $block, $parts)) {
    for ($i=0; $i<count($parts[0]); $i++) {
      $title = strip_tags($parts[2][$i] ?? '');
      $title = rtrim(trim(preg_replace('/\s+/', ' ', $title)), ":\x{0589}");
      if ($title === '' || !_is_known_conn_title($title)) continue;
      $ulHtml = $parts[3][$i] ?? '';
      if ($ulHtml && preg_match_all('~<li[^>]*>(.*?)</li>~i', $ulHtml, $lis)) {
        $items = array_map(fn($li)=>strip_tags($li), $lis[1]);
        $items = _clean_conn_items($items);
        if ($items) {
          $normTitle = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
          $out[$normTitle] = isset($out[$normTitle]) ? array_values(array_unique(array_merge($out[$normTitle], $items))) : $items;
        }
      }
    }
    if (!empty($out)) return $out;
  }

  if (preg_match_all('~<(h[2-6]|strong|span)[^>]*>(.*?)</\1>(.*?)(?=<h[2-6]|<strong|<span|</body>)~i', $block, $sec)) {
    for ($i=0; $i<count($sec[0]); $i++) {
      $title = strip_tags($sec[2][$i] ?? '');
      $title = rtrim(trim(preg_replace('/\s+/', ' ', $title)), ":\x{0589}");
      if ($title === '' || !_is_known_conn_title($title)) continue;

      $chunk = $sec[3][$i] ?? '';
      $items = [];

      if (preg_match_all('~<div[^>]*class="[^"]*\bsodatext\b[^"]*"[^>]*>(.*?)</div>~i', $chunk, $sodas)) {
        foreach ($sodas[1] as $s) {
          $txt = preg_replace('/\s+/', ' ', trim(strip_tags($s)));
          if ($txt !== '') $items[] = $txt;
        }
      }
      if (empty($items) && preg_match_all('~<div[^>]*class="[^"]*\bsoda\b[^"]*"[^>]*>(.*?)</div>~i', $chunk, $soda2)) {
        foreach ($soda2[1] as $s) {
          $txt = preg_replace('/\s+/', ' ', trim(strip_tags($s)));
          if ($txt !== '') $items[] = $txt;
        }
      }
      if (preg_match_all('~<li[^>]*>(.*?)</li>~i', $chunk, $lis2)) {
        foreach ($lis2[1] as $li) {
          $txt = preg_replace('/\s+/', ' ', trim(strip_tags($li)));
          if ($txt !== '') $items[] = $txt;
        }
      }

      $items = _clean_conn_items($items);
      if ($items) {
        $normTitle = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
        $out[$normTitle] = isset($out[$normTitle]) ? array_values(array_unique(array_merge($out[$normTitle], $items))) : $items;
      }
    }
    if (!empty($out)) return $out;
  }

  if (preg_match_all('~<li[^>]*>(.*?)</li>~i', $block, $lis)) {
    $items = array_map(fn($li)=>strip_tags($li), $lis[1]);
    $items = _clean_conn_items($items);
    if ($items) { $out['Connections'] = $items; return $out; }
  }

  if (preg_match_all('~/title/(tt\d{7,8})/[^"]*">([^<]+)<~i', $block, $am)) {
    $pairs = [];
    for ($i=0; $i<count($am[0]); $i++) {
      $pairs[] = trim($am[2][$i]).' ('.$am[1][$i].')';
    }
    $pairs = _clean_conn_items($pairs);
    if ($pairs) { $out['Connections'] = $pairs; }
  }
  return $out;
}
function parse_conn_mobile_modern(string $html): array {
  $out = [];
  $min = preg_replace('/\s+/', ' ', $html);
  if (!preg_match('~(Connections|Movie connections)(.*?)(</section>|</main>|</body>)~i', $min, $m)) return $out;
  $block = $m[0];

  if (preg_match_all('~<(h[2-6]|strong|span)[^>]*>(.*?)</\1>(.*?)(?=<h[2-6]|<strong|<span|</section>|</main>|</body>)~i', $block, $sec)) {
    for ($i=0; $i<count($sec[0]); $i++) {
      $title = strip_tags($sec[2][$i] ?? '');
      $title = rtrim(trim(preg_replace('/\s+/', ' ', $title)), ":\x{0589}");
      if ($title === '' || !_is_known_conn_title($title)) continue;

      $chunk = $sec[3][$i] ?? '';
      $items = [];

      if (preg_match_all('~<div[^>]*>(.*?)</div>~i', $chunk, $divs)) {
        foreach ($divs[1] as $d) {
          $txt = preg_replace('/\s+/', ' ', trim(strip_tags($d)));
          if ($txt !== '') $items[] = $txt;
        }
      }
      if (preg_match_all('~<li[^>]*>(.*?)</li>~i', $chunk, $lis)) {
        foreach ($lis[1] as $li) {
          $txt = preg_replace('/\s+/', ' ', trim(strip_tags($li)));
          if ($txt !== '') $items[] = $txt;
        }
      }

      $items = _clean_conn_items($items);
      if ($items) {
        $normTitle = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $title));
        $out[$normTitle] = isset($out[$normTitle]) ? array_values(array_unique(array_merge($out[$normTitle], $items))) : $items;
      }
    }
  }
  return $out;
}

// ===== TMDb: External IDs לסדרות (tvdb_id יציב) =====
function tmdb_tv_external_ids($tmdbId, $tmdbKey){
  if(!$tmdbId) return [];
  $u = "https://api.themoviedb.org/3/tv/$tmdbId/external_ids?api_key=$tmdbKey";
  $json = @file_get_contents($u);
  return $json ? (json_decode($json, true) ?: []) : [];
}

// ===== TVDB API (v4) =====
function tvdb_login($apiKey){
  if (!$apiKey) return null;
  $ch = curl_init('https://api4.thetvdb.com/v4/login');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['apikey' => $apiKey]),
  ]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($resp, true);
  return $data['data']['token'] ?? null;
}
function tvdb_search_remoteid($token, $imdbId){
  $ch = curl_init("https://api4.thetvdb.com/v4/search/remoteid/$imdbId");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
  ]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($resp, true);
  $items = $data['data'] ?? [];
  if (!is_array($items) || !$items) return null;
  foreach ($items as $it) { if (strtolower($it['type'] ?? '') === 'series') return $it; }
  return $items[0];
}
function tvdb_pick_id($match){
  if (!$match || !is_array($match)) return null;
  if (!empty($match['tvdb_id']))     return $match['tvdb_id'];
  if (!empty($match['id']))          return $match['id'];
  if (!empty($match['item']['id']))  return $match['item']['id'];
  return null;
}
function tvdb_get_series($token, $tvdbId){
  $ch = curl_init("https://api4.thetvdb.com/v4/series/$tvdbId");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
  ]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($resp, true);
  return $data['data'] ?? [];
}
function tvdb_get_series_extended($token, $tvdbId){
  $ch = curl_init("https://api4.thetvdb.com/v4/series/$tvdbId/extended");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
  ]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($resp, true);
  return $data['data'] ?? [];
}

// ===== עיבוד =====
$movies=[];
$tvdbToken = tvdb_login($tvdbKey);

foreach($imdbIDs as $imdbID){
  // OMDb
  $omdbUrl="http://www.omdbapi.com/?i=$imdbID&apikey=$omdbKey&plot=full";
  $omdbJson=@file_get_contents($omdbUrl);
  $omdbData=$omdbJson?json_decode($omdbJson,true):[];

  // TMDb find
  $findUrl="https://api.themoviedb.org/3/find/$imdbID?api_key=$tmdbKey&external_source=imdb_id";
  $findJson=@file_get_contents($findUrl);
  $findData=$findJson?json_decode($findJson,true):[];

  $tmdbType=null; $tmdbID=null;
  if(!empty($findData['movie_results'][0]['id'])){ $tmdbType='movie'; $tmdbID=$findData['movie_results'][0]['id']; }
  if(!empty($findData['tv_results'][0]['id']))   { $tmdbType='tv';    $tmdbID=$findData['tv_results'][0]['id']; }

  // פרטי TMDb (אם יש ID)
  $details=[]; $credits=['cast'=>[]];
  if($tmdbID && $tmdbType==='movie'){
    $detailsJson=@file_get_contents("https://api.themoviedb.org/3/movie/$tmdbID?api_key=$tmdbKey&language=he");
    $details=$detailsJson?json_decode($detailsJson,true):[];
    $creditsJson=@file_get_contents("https://api.themoviedb.org/3/movie/$tmdbID/credits?api_key=$tmdbKey");
    $credits=$creditsJson?json_decode($creditsJson,true):['cast'=>[]];
  } elseif($tmdbID && $tmdbType==='tv'){
    $detailsJson=@file_get_contents("https://api.themoviedb.org/3/tv/$tmdbID?api_key=$tmdbKey&language=he");
    $details=$detailsJson?json_decode($detailsJson,true):[];
    $creditsJson=@file_get_contents("https://api.themoviedb.org/3/tv/$tmdbID/credits?api_key=$tmdbKey");
    $credits=$creditsJson?json_decode($creditsJson,true):['cast'=>[]];
  }

  if(!empty($omdbData['Title'])){
    // IMDb Scraper
    $IMDB=new IMDB("https://www.imdb.com/title/{$imdbID}/");
    $scr=[];
    if(!empty($IMDB->isReady)){
      $scr['title']        = imdb_get($IMDB,'getTitle');
      $scr['year']         = imdb_get($IMDB,'getYear');
      $scr['type']         = imdb_get($IMDB,'getType'); // "TV Series" וכו'
      $scr['url']          = imdb_get($IMDB,'getUrl');
      $scr['description']  = imdb_get($IMDB,'getDescription');
      $scr['plot']         = imdb_get($IMDB,'getPlot',0);
      $scr['rating']       = imdb_get($IMDB,'getRating');
      $scr['runtime']      = imdb_get($IMDB,'getRuntime');
      $scr['release_date'] = imdb_get($IMDB,'getReleaseDate');
      $scr['release_dates']= imdb_get($IMDB,'getReleaseDates');

      $scr['director']     = imdb_get($IMDB,'getDirector');
      $scr['writer']       = imdb_get($IMDB,'getWriter');
      $scr['creator']      = imdb_get($IMDB,'getCreator');
      $scr['music']        = imdb_get($IMDB,'getMusic');
      $scr['cast']         = imdb_get($IMDB,'getCast',10,false);

      $scr['genre']        = imdb_get($IMDB,'getGenre');
      $scr['aka']          = imdb_get($IMDB,'getAka');
      $scr['akas']         = imdb_get($IMDB,'getAkas');

      $scr['country']      = imdb_get($IMDB,'getCountry');
      $scr['language']     = imdb_get($IMDB,'getLanguage');

      $scr['company']      = imdb_get($IMDB,'getCompany');

      $scr['poster_small'] = imdb_get($IMDB,'getPoster','small',false);
      $scr['poster_big']   = imdb_get($IMDB,'getPoster','big',false);

      $scr['seasons']      = imdb_get($IMDB,'getSeasons');

      $scr['metascore']    = imdb_get($IMDB,'getMetaScore');
      $scr['metacritics']  = imdb_get($IMDB,'getMetaCritics');
    }

    // זיהוי אם TV
    $isTv = false;
    if (!empty($findData['tv_results'][0]['id'])) $isTv = true;
    if (strtolower($omdbData['Type'] ?? '') === 'series') $isTv = true;
    $imdbTypeStr = $scr['type'] ?? '';
    if (!$isTv && stripos($imdbTypeStr, 'TV') !== false) $isTv = true;
    if ($isTv) $tmdbType = 'tv';

    // Genres
    $tmdbGenres=array_map(fn($g)=>$g['name'],$details['genres'] ?? []);
    $omdbGenresRaw=isset($omdbData['Genre'])?explode(',',$omdbData['Genre']):[];
    $omdbGenres=array_map('translateGenre',$omdbGenresRaw);
    $genres=array_values(array_unique(array_filter(array_merge($tmdbGenres,$omdbGenres))));

    // Actors (עד 10)
    $actors=array_slice($credits['cast']??[],0,10);
    $actorNames=array_map(fn($a)=>$a['name'],$actors);

    // Ratings
    $ratings=[];
    if(!empty($omdbData['Ratings'])){
      foreach($omdbData['Ratings'] as $r){
        if(!empty($r['Source']) && !empty($r['Value'])) $ratings[$r['Source']]=$r['Value'];
      }
    }

    // Connections
    $scr['connections']=imdb_get_connections($imdbID);

    // ===== TVDB (לסדרות) =====
    $tvdb = [];
    if($isTv && $tvdbToken){
      $tvdbId = null;

      if (!empty($tmdbID)) {
        $ext = tmdb_tv_external_ids($tmdbID, $tmdbKey);
        if (!empty($ext['tvdb_id'])) $tvdbId = $ext['tvdb_id'];
      }

      if (!$tvdbId) {
        $match  = tvdb_search_remoteid($tvdbToken, $imdbID);
        $tvdbId = tvdb_pick_id($match);
      }

      if($tvdbId){
        $series   = tvdb_get_series($tvdbToken, $tvdbId);
        $extended = tvdb_get_series_extended($tvdbToken, $tvdbId);
        $tvdb = [
          'tvdb_id'       => $tvdbId,
          'name'          => $series['name'] ?? ($extended['name'] ?? null),
          'overview'      => $series['overview'] ?? ($extended['overview'] ?? null),
          'firstAired'    => $series['firstAired'] ?? ($extended['firstAired'] ?? null),
          'status'        => $series['status']['name'] ?? ($extended['status']['name'] ?? null),
          'genres'        => $series['genres'] ?? ($extended['genres'] ?? []),
          'originalLang'  => $series['originalLanguage'] ?? ($extended['originalLanguage'] ?? null),
          'network'       => $series['originalNetwork']['name'] ?? ($extended['originalNetwork']['name'] ?? null),
          'runtime'       => $series['averageRuntime'] ?? ($extended['averageRuntime'] ?? null),
        ];
        if (empty($details['overview']) && !empty($tvdb['overview'])) $details['overview'] = $tvdb['overview'];
        if (empty($omdbData['Plot']) && !empty($tvdb['overview']))    $omdbData['Plot']   = $tvdb['overview'];
        if (empty($omdbData['Language']) && !empty($tvdb['originalLang'])) $omdbData['Language']=$tvdb['originalLang'];
        if (empty($details['first_air_date']) && !empty($tvdb['firstAired'])) $details['first_air_date']=$tvdb['firstAired'];
        if (empty($details['episode_run_time']) && !empty($tvdb['runtime']))  $details['episode_run_time']=[$tvdb['runtime']];
      }
    }

    // fallback ליהוק מ-IMDb אם אין מ-TMDb
    if(empty($actorNames) && !empty($scr['cast'])) $actorNames=array_map('trim',explode('/',(string)$scr['cast']));

    // כותרת/שנה/תקציר
    if(!$isTv){
      $uiTitle=$details['title'] ?? $omdbData['Title'] ?? '';
      $uiPlot =$details['overview'] ?? $omdbData['Plot'] ?? '';
      $uiYear =$omdbData['Year'] ?? ($details['release_date'] ?? '');
    } else {
      $uiTitle=$details['name'] ?? $omdbData['Title'] ?? ($tvdb['name'] ?? '');
      $uiPlot =$details['overview'] ?? $omdbData['Plot'] ?? ($tvdb['overview'] ?? '');
      $first=$details['first_air_date'] ?? ($tvdb['firstAired'] ?? '');
      $last =$details['last_air_date'] ?? '';
      $y1=$first?substr($first,0,4):''; $y2=$last?substr($last,0,4):'';
      $uiYear = ($y1 && $y2) ? ($y1 === $y2 ? $y1 : ($y1 . '–' . $y2)) : ($y1 ?: ($omdbData['Year'] ?? ''));
    }

    // במאי/יוצר לסדרה
    $director=$omdbData['Director'] ?? '';
    if($isTv && !$director && !empty($details['created_by'])){
      $director=implode(', ',array_map(fn($x)=>$x['name'],$details['created_by']));
    }

    // משך לסדרה
    $runtime=$omdbData['Runtime'] ?? '';
    if($isTv && empty($runtime) && !empty($details['episode_run_time'])){
      $rt=is_array($details['episode_run_time'])?implode('/',$details['episode_run_time']):$details['episode_run_time'];
      if($rt) $runtime=$rt.' min/ep';
    }

    // מדינה לסדרה
    $country=$omdbData['Country'] ?? '';
    if($isTv && empty($country) && !empty($details['origin_country'])){
      $country=implode(', ',$details['origin_country']);
    }

    $rapidData = rapidapi_get_by_id($imdbID, $rapidApiKey);

    $movies[]=[
      'title'=>$uiTitle,
      'year'=>$uiYear,
      'poster'=>$omdbData['Poster'] ?? '',
      'plot'=>$uiPlot,
      'genres'=>array_map('translateGenre',$genres),
      'actors'=>implode(', ',array_filter($actorNames)),
      'director'=>$director,
      'runtime'=>$runtime,
      'country'=>$country,
      'language'=>$omdbData['Language'] ?? '',
      'ratings'=>$ratings,
      'imdbID'=>$imdbID,
      'imdb_scraper'=>$scr, // כולל aka/akas וה- connections
      'tvdb'=>$tvdb ?? [],
      'is_tv'=>$isTv,
      'rapidapi' => $rapidData,
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🎬 תצוגת סרטים/סדרות</title>
  <style>
    body { font-family:"Segoe UI", Arial, sans-serif; background:#f8f8f8; padding:40px; direction:rtl; text-align:right; }
    h2 { text-align:center; color:#333; margin-bottom:50px; }
    .movie { display:flex; flex-direction:row-reverse; gap:30px; margin:0 auto 50px; padding:20px; background:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.07); max-width:1000px; flex-wrap:wrap; }
    .movie img { max-width:220px; border-radius:10px; flex-shrink:0; background:#eee; }
    .info { flex:1; min-width:260px; }
    .info h3 { margin:0 0 10px; font-size:22px; color:#222; }
    .info p { margin:6px 0; font-size:15px; line-height:1.5; }
    .label { font-weight:bold; color:#444; }
    .rating { padding:4px 8px; border-radius:6px; display:inline-block; margin-left:6px; font-weight:bold; }
    .IMDb { background:#f0f8ff; color:#004080; }
    .RottenTomatoes { background:#ffe4e1; color:#cc0000; }
    .Metacritic { background:#f5f5dc; color:#5c5c00; }
    .imdb-scraper { margin-top:14px; padding:12px; background:#f7fbff; border:1px solid #e5f0ff; border-radius:10px; }
    .imdb-scraper h4 { margin:0 0 8px; font-size:16px; color:#0c3d7a; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:8px 18px; }
    .kv { margin:0; }
    .kv .label { display:inline-block; min-width:120px; }
    a { color:#0066cc; text-decoration:none; }
    a:hover { text-decoration:underline; }
    @media (max-width:700px){ .movie{flex-direction:column; align-items:center; text-align:center;} .info{text-align:center;} .kv .label{min-width:auto;} }
    .tvdb-box { margin-top:10px; padding:12px; background:#fffdf7; border:1px solid #ffe7b5; border-radius:10px; }
    .tvdb-box h4 { margin:0 0 8px; font-size:16px; color:#7a5a0c; }
    .rapidapi-box { margin-top:10px; padding:12px; background:#f7fff8; border:1px solid #b5ffd6; border-radius:10px; }
    .rapidapi-box h4 { margin:0 0 8px; font-size:16px; color:#0c7a2b; }
    .rapidapi-box pre { background:#eee; padding:10px; border-radius:6px; white-space:pre-wrap; word-wrap:break-word; text-align:left; direction:ltr; }
  </style>
</head>
<body>

<h2>🎬 תצוגת סרטים/סדרות בעברית</h2>

<?php foreach($movies as $movie): ?>
  <div class="movie">
    <img src="<?= htmlspecialchars($movie['poster']) ?>" alt="פוסטר">
    <div class="info">
      <h3><?= htmlspecialchars($movie['title']) ?> (<?= htmlspecialchars($movie['year']) ?>)</h3>
      <p><span class="label">ז'אנרים:</span> <?= htmlspecialchars(implode(', ', $movie['genres'])) ?></p>
      <p><span class="label">שחקנים:</span> <?= htmlspecialchars($movie['actors']) ?></p>
      <p><span class="label">במאי/יוצר:</span> <?= htmlspecialchars($movie['director']) ?></p>
      <p><span class="label">משך זמן:</span> <?= htmlspecialchars($movie['runtime']) ?></p>
      <p><span class="label">שפה:</span> <?= htmlspecialchars($movie['language']) ?></p>
      <p><span class="label">מדינה:</span> <?= htmlspecialchars($movie['country']) ?></p>

      <?php if(!empty($movie['ratings'])): ?>
        <p><span class="label">דירוגים:</span><br>
          <?php foreach($movie['ratings'] as $source=>$value): ?>
            <?php
              $class = match($source){
                'Internet Movie Database'=>'IMDb',
                'Rotten Tomatoes'=>'RottenTomatoes',
                'Metacritic'=>'Metacritic',
                default=>''
              };
              $icon = match($source){
                'Internet Movie Database'=>'🎬',
                'Rotten Tomatoes'=>'🍅',
                'Metacritic'=>'🎯',
                default=>'⭐'
              };
            ?>
            <span class="rating <?= $class ?>"><?= $icon ?> <?= htmlspecialchars($source) ?>: <?= htmlspecialchars($value) ?></span><br>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>

      <p><span class="label">תקציר:</span><br><?= nl2br(htmlspecialchars($movie['plot'])) ?></p>
      <p><span class="label">קישור ל־IMDb:</span>
        <a href="https://www.imdb.com/title/<?= htmlspecialchars($movie['imdbID']) ?>" target="_blank">🔗 IMDb</a>
      </p>

      <?php if(!empty($movie['imdb_scraper'])): $s=$movie['imdb_scraper']; ?>
        <div class="imdb-scraper">
          <h4>תוספות מ־IMDb (Scraper)</h4>
          <div class="grid">
            <?php
              $printKV=function($label,$value,$strip=false){
                if($value===null||$value===''||(is_array($value)&&count(array_filter($value))===0)) return;
                if($strip) $value=stripAllTags($value);
                if(is_array($value)) $value=implode(' | ',array_map(fn($x)=>is_array($x)?json_encode($x,JSON_UNESCAPED_UNICODE):$x,$value));
                echo '<p class="kv"><span class="label">'.safeHtml($label).':</span> '.safeHtml($value).'</p>';
              };

              // ליבה (הכל מ־IMDb)
              $printKV('כותרת (IMDb)',$s['title']??null);
              $printKV('שנה (IMDb)',$s['year']??null);
              $printKV('סוג כותר',$s['type']??null);
              $printKV('קישור מלא',$s['url']??null,true);
              $printKV('תיאור',$s['description']??null);
              $printKV('עלילה מלאה',$s['plot']??null);
              $printKV('דירוג IMDb',$s['rating']??null);
              $printKV('משך (IMDb)',$s['runtime']??null);
              $printKV('תאריך יציאה',$s['release_date']??null);
              $printKV('תאריכי יציאה (מדינות)',$s['release_dates']??null);

              // צוות
              $printKV('במאי',$s['director']??null);
              $printKV('תסריטאי',$s['writer']??null);
              $printKV('יוצר',$s['creator']??null);
              $printKV('מלחין',$s['music']??null);
              $printKV('ליהוק',$s['cast']??null);

              // תוכן
              $printKV('ז׳אנרים (IMDb)',$s['genre']??null);
              $printKV('AKA (IMDb)',$s['aka']??null);
              $printKV('AKAs (IMDb)',$s['akas']??null);

              // מדינה/שפה
              $printKV('מדינה (IMDb)',$s['country']??null);
              $printKV('שפה (IMDb)',$s['language']??null);

              // חברה
              $printKV('חברה',$s['company']??null);

              // תמונות
              $printKV('פוסטר (קטן)',$s['poster_small']??null,true);
              $printKV('פוסטר (גדול)',$s['poster_big']??null,true);

              // טלוויזיה
              if (!empty($movie['is_tv'])) $printKV('מספר עונות',$s['seasons']??null);

              // ביקורות
              $printKV('Metascore',$s['metascore']??null);
              $printKV('ביקורות מבקרים',$s['metacritics']??null);
            ?>
          </div>

          <?php if(!empty($s['connections'])): ?>
            <div class="grid" style="margin-top:10px">
              <h4 style="grid-column:1/-1;margin:0 0 8px;color:#0c3d7a">
                קשרים ב־IMDb (Connections)
                <span style="font-weight:normal;color:#666">(<?= array_sum(array_map('count', $s['connections'])) ?> פריטים)</span>
              </h4>
              <?php foreach($s['connections'] as $type=>$items): ?>
                <p class="kv" title="<?= safeHtml($type) ?>">
                  <span class="label"><?= safeHtml(translateConnectionType($type)) ?>:</span>
                  <?= safeHtml(implode(' | ', $items)) ?>
                </p>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="grid" style="margin-top:10px">
              <p class="kv"><span class="label">קשרים:</span> לא נמצאו (או נחסמה הגישה ע״י IMDb)</p>
            </div>
          <?php endif; ?>

          <?php if(!empty($movie['is_tv']) && !empty($movie['tvdb'])): $t=$movie['tvdb']; ?>
            <div class="tvdb-box">
              <h4>נתונים מ־TVDB</h4>
              <div class="grid">
                <?php
                  $printKV('שם (TVDB)', $t['name'] ?? null);
                  $printKV('תקציר (TVDB)', $t['overview'] ?? null);
                  $printKV('שפת מקור (TVDB)', $t['originalLang'] ?? null);
                  $printKV('שנת התחלה (TVDB)', isset($t['firstAired']) && $t['firstAired'] ? substr($t['firstAired'],0,4) : null);
                  $printKV('סטטוס (TVDB)', $t['status'] ?? null);
                  $printKV('רשת שידור (TVDB)', $t['network'] ?? null);
                  if (!empty($t['genres'])) {
                    $printKV('ז׳אנרים (TVDB)', is_array($t['genres']) ? implode(' | ', array_map(fn($g)=>is_array($g)?($g['name']??json_encode($g,JSON_UNESCAPED_UNICODE)):$g, $t['genres'])) : $t['genres']);
                  }
                  $printKV('משך ממוצע (TVDB)', $t['runtime'] ? ($t['runtime'].' min/ep') : null);
                  $printKV('TVDB ID', $t['tvdb_id'] ?? null);
                ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if(!empty($movie['rapidapi']) && empty($movie['rapidapi']['error'])): $r = $movie['rapidapi']; ?>
            <div class="rapidapi-box">
                <h4>נתונים מ-RapidAPI (imdb236)</h4>
                <div class="grid">
                <?php
                  $printKV('כותרת ראשית', $r['primaryTitle'] ?? null);
                  $printKV('כותרת מקורית', $r['originalTitle'] ?? null);
                  $printKV('סוג', $r['type'] ?? null);
                  $printKV('שנת יציאה', isset($r['releaseDate']) ? substr($r['releaseDate'], 0, 4) : null);
                  $printKV('דירוג תוכן', $r['contentRating'] ?? null);
                  $printKV('דירוג ממוצע', $r['averageRating'] ?? null);
                  $printKV('מספר מצביעים', isset($r['numVotes']) ? number_format($r['numVotes']) : null);
                   if (!empty($r['genres'])) {
                      $printKV('ז׳אנרים', implode(' | ', $r['genres']));
                  }
                  if (!empty($r['countriesOfOrigin'])) {
                      $printKV('מדינות', implode(' | ', $r['countriesOfOrigin']));
                  }
                  if (!empty($r['spokenLanguages'])) {
                      $printKV('שפות', implode(' | ', $r['spokenLanguages']));
                  }
                  
                  if ($movie['is_tv']) {
                      $printKV('סה"כ עונות', $r['totalSeasons'] ?? null);
                      $printKV('סה"כ פרקים', $r['totalEpisodes'] ?? null);
                  }

                  if (!empty($r['directors'])) {
                      $directorNames = array_map(fn($d) => $d['fullName'], $r['directors']);
                      $printKV('במאים', implode(' | ', $directorNames));
                  }
                  
                  if (!empty($r['writers'])) {
                      $writerNames = array_map(fn($w) => $w['fullName'], $r['writers']);
                      $printKV('כותבים', implode(' | ', $writerNames));
                  }

                  if (!empty($r['cast'])) {
                      $castNames = array_map(fn($actor) => $actor['fullName'], $r['cast']);
                      $printKV('שחקנים', implode(' | ', $castNames));
                  }

                  if (!empty($r['trailer'])) {
                    echo '<p class="kv" style="grid-column: 1 / -1;"><span class="label">טריילר:</span> <a href="' . safeHtml($r['trailer']) . '" target="_blank">🔗 צפה ב-YouTube</a></p>';
                  }
                ?>
                </div>
                <?php if (!empty($r['description'])): ?>
                    <p class="kv" style="grid-column: 1 / -1; margin-top:10px;"><span class="label">תיאור:</span> <?= safeHtml($r['description']) ?></p>
                <?php endif; ?>
            </div>
          <?php elseif(!empty($movie['rapidapi']['error'])): ?>
            <div class="rapidapi-box">
                <h4>שגיאה מ-RapidAPI (imdb236)</h4>
                <p><?= safeHtml(json_encode($movie['rapidapi'])) ?></p>
            </div>
          <?php endif; ?>
          </div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

</body>
</html>