<?php
/****************************************************
 * IMDb_Connections.php — Full page (RTL, עברית)
 * UI כמו המקורי, Next.js תחילה + Browserless fallback
 * BUILD: v2025-10-19-r18
 ****************************************************/
set_time_limit(3000000);
mb_internal_encoding('UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


/* ===================== CONFIG ===================== */
$BROWSERLESS_TOKEN = '2TGHk86XMjii67vd2afc7f277dc3c9144f8e38dfaad0ad56a'; // ← שים כאן את המפתח שלך
$BROWSERLESS_ENDPOINT = 'https://chrome.browserless.io/content';  // content endpoint מחזיר HTML מרונדר
$CONN_ORDER = [
  'Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of','Alternate versions'
];

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function http_get(string $url, array $headers=[], int $timeout=25): ?array {
  $ch = curl_init($url);
  $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
  $hdrs = array_merge([
    'User-Agent: '.$ua,
    'Accept-Language: en-US,en;q=0.9,he;q=0.6',
    'Referer: https://www.imdb.com/',
  ], $headers);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => $hdrs,
  ]);
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($body === false || $code >= 400) return null;
  return ['code'=>$code,'body'=>$body];
}

function http_get_browserless_html(string $url, string $token, int $timeout=45): ?string {
  if (!$token) return null;
  $u = $url;
  // פרמטרים מועילים: להמתין לרגיעה רשתית, לתת קצת זמן, desktop
  $params = [
    'token'       => $token,
    'url'         => $u,
    'gotoTimeout' => 20000,
    'waitUntil'   => 'networkidle0',
    'viewport'    => '1280x960',
    'deviceScaleFactor' => 1,
  ];
  $endpoint = 'https://chrome.browserless.io/content?'.http_build_query($params);
  $res = http_get($endpoint, [], $timeout);
  return $res ? $res['body'] : null;
}

function expand_imdb_href(string $href): string {
  $href = trim($href);
  if ($href === '') return '';
  if (strpos($href, 'https://')===0 || strpos($href, 'http://')===0) return $href;
  if (strpos($href, '//')===0) return 'https:'.$href;
  if ($href[0] !== '/') $href = '/'.ltrim($href,'/');
  return 'https://www.imdb.com'.$href;
}
function extract_tt_from_input(string $raw): ?string {
  if (preg_match('~(tt\d{7,10})~', $raw, $m)) return $m[1];
  return null;
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
function normalize_conn_label(string $s): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  $s = preg_replace('/\s*\(.*$/', '', $s);
  $map = [
    'Follows'=>'Follows','Followed by'=>'Followed by',
    'Remake of'=>'Remake of','Remade as'=>'Remade as',
    'Spin-off'=>'Spin-off','Spin-off from'=>'Spin-off from',
    'Version of'=>'Version of','Alternate versions'=>'Alternate versions',
  ];
  return $map[$s] ?? $s;
}
function imdb_connections_url(string $id): string {
  return "https://www.imdb.com/title/{$id}/movieconnections/";
}

/* ============ NEXT.js parsers (JSON + inline) ============ */
function parse_next_data_inline(string $html): ?array {
  if (preg_match('#<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>#si', $html, $m)) {
    $json = html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
  }
  return null;
}

function try_next_data_endpoints(string $imdbId, ?string &$buildIdOut=null, array &$debug=[]): array {
  $out = ['byCat'=>[], 'items'=>[]];

  // 1) הבא buildId מתוך העמוד הראשי
  $htmlRes = http_get(imdb_connections_url($imdbId));
  if (!$htmlRes) { $debug[]='mainHTML FAIL'; return $out; }
  $debug[] = 'mainHTML '.$htmlRes['code'].' -> '.imdb_connections_url($imdbId);

  $buildId = null;
  if (preg_match('#"_buildManifest".*?/(_next/static/[^/]+/)?_buildManifest\.js#', $htmlRes['body'])) {
    // לא תמיד נגיש… ננסה פשוט לחלץ מתוך __NEXT_DATA__
  }
  $inline = parse_next_data_inline($htmlRes['body']);
  if ($inline) {
    $buildId = $inline['buildId'] ?? ($inline['props']['buildId'] ?? null);
  }
  if (!$buildId && preg_match('#"buildId"\s*:\s*"([^"]+)"#', $htmlRes['body'], $m)) {
    $buildId = $m[1];
  }
  if (!$buildId) {
    $debug[]='no buildId';
    // עדיין אפשר לנסות showMore ישיר בלי buildId (HTML)
  } else {
    $buildIdOut = $buildId;
  }

  // 2) לנסות את נתיבי ה-Next data (עם/בלי שפה, עם showMore)
  $candidates = [];
  if ($buildId) {
    $base = "https://www.imdb.com/_next/data/{$buildId}";
    $candidates[] = "{$base}/title/{$imdbId}/movieconnections.json";
    $candidates[] = "{$base}/title/{$imdbId}/movieconnections.json?showMore=true";
    $candidates[] = "{$base}/title/{$imdbId}/movieconnections.json?showMore=1";
    $candidates[] = "{$base}/en-US/title/{$imdbId}/movieconnections.json";
    $candidates[] = "{$base}/en-US/title/{$imdbId}/movieconnections.json?showMore=true";
    $candidates[] = "{$base}/en-US/title/{$imdbId}/movieconnections.json?showMore=1";
    $candidates[] = "{$base}/en/title/{$imdbId}/movieconnections.json";
    $candidates[] = "{$base}/en/title/{$imdbId}/movieconnections.json?showMore=true";
    $candidates[] = "{$base}/en/title/{$imdbId}/movieconnections.json?showMore=1";
  }

  $allNodes = [];
  foreach ($candidates as $u) {
    $res = http_get($u);
    if (!$res) { $debug[]="try FAIL -> $u"; continue; }
    if ($res['code'] !== 200) { $debug[]="try FAIL {$res['code']} -> $u"; continue; }
    $debug[]="try OK {$res['code']} -> $u";
    $json = json_decode($res['body'], true);
    if (!is_array($json)) continue;

    // נחפש בכל העץ nodes של connections
    $stack = [$json];
    while ($stack) {
      $node = array_pop($stack);
      if (!is_array($node)) continue;
      // כותרת מקטע?
      $label = null;
      foreach (['category','header','sectionTitle','title'] as $k) {
        if (isset($node[$k])) {
          $txt = is_array($node[$k]) ? ($node[$k]['text'] ?? '') : $node[$k];
          $tmp = normalize_conn_label((string)$txt);
          if (in_array($tmp, $GLOBALS['CONN_ORDER'], true)) { $label = $tmp; break; }
        }
      }
      // פריט?
      $id=null;$title=null;$url=null;
      foreach (['id','const'] as $k) {
        if (!$id && isset($node[$k]) && preg_match('/^tt\d+$/', (string)$node[$k])) $id = $node[$k];
      }
      foreach (['link','canonicalLink','url'] as $k) {
        if (!$id && !empty($node[$k]) && preg_match('#/title/(tt\d+)/#', (string)$node[$k], $m)) $id = $m[1];
      }
      if (isset($node['titleText'])) {
        $title = is_array($node['titleText']) ? ($node['titleText']['text'] ?? null) : (is_string($node['titleText']) ? $node['titleText'] : null);
      }
      if (!$title && isset($node['originalTitleText']) && is_array($node['originalTitleText'])) {
        $title = $node['originalTitleText']['text'] ?? null;
      }
      if (!$title && isset($node['displayableTitle'])) $title = is_string($node['displayableTitle']) ? $node['displayableTitle'] : null;
      if (!$title && isset($node['title'])) $title = is_string($node['title']) ? $node['title'] : null;
      foreach (['link','canonicalLink','url'] as $k) {
        if (!$url && !empty($node[$k])) $url = expand_imdb_href((string)$node[$k]);
      }
      if (!$url && $id) $url = "https://www.imdb.com/title/{$id}/";

      if ($label && $id) {
        $allNodes[$label][] = ['id'=>$id,'title'=>$title ?: $id,'url'=>$url,'type'=>$label];
      }

      foreach ($node as $v) if (is_array($v)) $stack[] = $v;
    }
  }

  if ($allNodes) {
    foreach ($GLOBALS['CONN_ORDER'] as $lab) {
      $arr = $allNodes[$lab] ?? [];
      if ($arr) $out['byCat'][$lab] = dedupe_items($arr);
    }
    $flat = [];
    foreach ($out['byCat'] as $lab=>$arr) foreach ($arr as $it){ $flat[]=$it; }
    $out['items'] = $flat;
  }
  return $out;
}

/* ============ HTML parsers (regular + Browserless) ============ */
function parse_connections_from_html(string $html, array $keep): array {
  $out = []; foreach ($keep as $k) $out[$k]=[];
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  $dom->loadHTML($html);
  libxml_clear_errors();
  $xp = new DOMXPath($dom);

  // כותרות מקטעים (IPC headers)
  $headers = $xp->query("//*[self::h2 or self::h3 or self::h4 or self::h5 or contains(@class,'ipc-title__text')]");
  foreach ($headers as $h) {
    $label = normalize_conn_label(trim(preg_replace('/\s+/', ' ', $h->textContent ?? '')));
    if (!in_array($label, $keep, true)) continue;

    // נחפש בלוקים הסמוכים שמכילים קישורים ל־/title/tt
    $items = [];
    // טייל קדימה עד בלוק רשימות/קארדס
    for ($n = $h->parentNode ? $h->parentNode->nextSibling : $h->nextSibling; $n; $n = $n->nextSibling) {
      if ($n->nodeType !== XML_ELEMENT_NODE) continue;
      $nm = strtolower($n->nodeName);
      // עצירה בהגעה לכותרת חדשה
      if (in_array($nm, ['h2','h3','h4','h5'])) break;

      $links = $xp->query(".//a[contains(@href,'/title/tt')]", $n);
      foreach ($links as $a) {
        /** @var DOMElement $a */
        $href = (string)$a->getAttribute('href');
        if (!preg_match('#/title/(tt\d+)/#', $href, $m)) continue;
        $id = $m[1];
        $t  = trim(preg_replace('/\s+/', ' ', $a->textContent ?? ''));
        $url = expand_imdb_href($href);
        if ($id) $items[] = ['id'=>$id,'title'=>($t ?: $id),'url'=>$url,'type'=>$label];
      }
    }
    if ($items) $out[$label] = dedupe_items($items);
  }
  return $out;
}

/* ============ Master fetcher ============ */
function fetch_connections(string $imdbId, array $keep, string $browserlessToken, array &$debug=[]): array {
  $result = [
    'ok'=>true,'id'=>$imdbId,'source'=>imdb_connections_url($imdbId),
    'categories'=>$keep,'byCategory'=>array_fill_keys($keep,[]),'items'=>[],
    'mode'=>null,'note'=>null,'debug'=>[]
  ];

  // 1) Next.js (כולל showMore) — ראשי
  $buildId=null;
  $nx = try_next_data_endpoints($imdbId, $buildId, $debug);
  if (!empty($nx['items'])) {
    // אם קיבלנו מעט — ננסה להרחיב עוד רגע; קודם נשמור את מה שיש
    foreach ($nx['byCat'] as $lab=>$arr) $result['byCategory'][$lab] = $arr;
    $result['items'] = [];
    foreach ($result['byCategory'] as $lab=>$arr) foreach ($arr as $it) $result['items'][] = $it;
    $result['mode'] = 'next';
  }

  // האם יש חוסר ברור? (למשל כלום, או רק 5 כשבפועל יש עוד הרבה)
  $needFallback = false;
  if (count($result['items']) === 0) $needFallback = true;

  // 2) Browserless fallback (HTML מרונדר) — גם main וגם showMore
  if ($needFallback && $browserlessToken) {
    $main = http_get_browserless_html(imdb_connections_url($imdbId), $browserlessToken);
    $show = http_get_browserless_html(imdb_connections_url($imdbId).'?showMore=true', $browserlessToken);
    $debug[] = 'BL main='.( $main ? 'OK' : 'NULL' ).' show='.( $show ? 'OK':'NULL' );

    $byA = $main ? parse_connections_from_html($main, $keep) : [];
    $byB = $show ? parse_connections_from_html($show, $keep) : [];
    // מיזוג
    $merged = [];
    foreach ($keep as $k) {
      $merged[$k] = [];
      if (!empty($byA[$k])) $merged[$k] = array_merge($merged[$k], $byA[$k]);
      if (!empty($byB[$k])) $merged[$k] = array_merge($merged[$k], $byB[$k]);
      $merged[$k] = dedupe_items($merged[$k]);
    }
    $has=false; foreach ($merged as $arr) if ($arr){ $has=true; break; }
    if ($has) {
      $result['byCategory'] = $merged;
      $result['items'] = [];
      foreach ($merged as $lab=>$arr) foreach ($arr as $it) $result['items'][]=$it;
      $result['mode'] = $result['mode'] ? ($result['mode'].'+bl') : 'bl';
    }
  }

  // ניקוי סופי: רק שמונה קטגוריות, רק tt*, כותרת לא ריקה
  foreach ($keep as $lab) {
    $clean=[];
    foreach ($result['byCategory'][$lab] ?? [] as $it) {
      if (empty($it['id']) || !preg_match('/^tt\d+$/', $it['id'])) continue;
      $it['title'] = trim((string)($it['title'] ?? '')) ?: $it['id'];
      $it['url']   = $it['url'] ?: "https://www.imdb.com/title/{$it['id']}/";
      $it['type']  = $lab;
      $clean[] = $it;
    }
    $result['byCategory'][$lab] = dedupe_items($clean);
  }
  $result['items'] = [];
  foreach ($keep as $lab) foreach ($result['byCategory'][$lab] as $it) $result['items'][]=$it;

  $result['debug'] = $debug;
  return $result;
}

/* ===================== Controller ===================== */
$raw = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$id  = $raw !== '' ? extract_tt_from_input($raw) : null;

$debug = [];
$jsonOut = null;
if ($id) {
  $jsonOut = fetch_connections($id, $CONN_ORDER, $BROWSERLESS_TOKEN, $debug);
}

/* ===================== View ===================== */
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>🎬 IMDb Connections</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --line:#22283a; --accent:#8ab4ff; }
    *{ box-sizing:border-box }
    body{ font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial; background:var(--bg); color:var(--text); margin:0; }
    header{ padding:16px; background:#101521; position:sticky; top:0; z-index:10; border-bottom:1px solid var(--line); }
    h1{ margin:0 0 8px; font-size:18px; }
    form{ display:flex; gap:8px; }
    input[type=text]{ flex:1; padding:10px 12px; border-radius:10px; border:1px solid var(--line); background:#0f1117; color:var(--text); }
    button{ padding:10px 14px; border-radius:10px; border:1px solid var(--line); background:#172036; color:var(--text); cursor:pointer; }
    button:hover{ background:#1c2742; }
    .container{ max-width:1100px; margin:24px auto; padding:0 16px; }
    .card{ background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px; margin-bottom:16px; }
    .grid{ display:grid; grid-template-columns: 1fr; gap:16px; }
    .list{ list-style:none; padding:0; margin:0; display:flex; gap:10px; flex-wrap:wrap; }
    .list li{ background:#0f1117; border:1px solid var(--line); border-radius:10px; padding:8px 10px; }
    a{ color:var(--accent); text-decoration:none; }
    a:hover{ text-decoration:underline; }
    .muted{ color:var(--muted); }
    .sec{ margin-top:14px; }
    .sec h3{ margin:0 0 8px; font-size:16px; }
    .bar{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .pill{ font-size:12px; padding:4px 8px; border:1px solid var(--line); border-radius:999px; color:var(--muted); }
    pre.json{ max-height:360px; overflow:auto; background:#0b0e14; border:1px solid var(--line); padding:12px; border-radius:12px; }
    .warn{ color:#ffce7a }
  </style>
</head>
<body>
<header>
  <h1>🔗 IMDb Connections</h1>
  <form method="get">
    <input type="text" name="id" placeholder="הדבק מזהה כמו tt6806448 או URL מ-IMDb" value="<?=h($raw)?>" />
    <button type="submit">חפש</button>
  </form>
</header>

<div class="container">
  <?php if ($id && $jsonOut): ?>
    <div class="card">
      <div class="bar">
        <div><b>Title ID:</b> <?=h($id)?></div>
        <div class="pill">Mode: <?=h($jsonOut['mode'] ?: 'none')?></div>
        <div><a target="_blank" href="<?=h(imdb_connections_url($id))?>">Open on IMDb</a></div>
        <div> | </div>
        <div><a href="?id=<?=h($id)?>&fmt=json" target="_blank">JSON</a></div>
      </div>

      <?php
        if (isset($_GET['fmt']) && $_GET['fmt']==='json') {
          // הדפסה בלבד — לא שולח כותרות כדי לא לשבור את ה-UI
          echo '<pre class="json">'.h(json_encode($jsonOut, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';
        }
      ?>

      <?php
        $any=false;
        foreach ($CONN_ORDER as $lab) {
          $items = $jsonOut['byCategory'][$lab] ?? [];
          if (!$items) continue;
          $any=true;
          echo '<div class="sec">';
          echo '<h3>'.h($lab).' ('.count($items).')</h3>';
          echo '<ul class="list">';
          foreach ($items as $it) {
            $tt = $it['id'] ?? null;
            $t  = $it['title'] ?? $tt ?? '';
            $href = $it['url'] ?? ($tt ? "https://www.imdb.com/title/{$tt}/" : '#');
            echo '<li><a href="'.h($href).'" target="_blank">'.h($t).'</a></li>';
          }
          echo '</ul>';
          echo '</div>';
        }
        if (!$any) {
          echo '<div class="muted">לא נמצאו פריטים. אם הדף נטען ב-JavaScript בלבד — נדרש רנדרר. (במקרה שלנו יש Fallback ל-Browserless בעת צורך)</div>';
        }
      ?>

      <details style="margin-top:14px">
        <summary>JSON</summary>
        <pre class="json"><?=h(json_encode($jsonOut, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
      </details>

      <?php if (!empty($jsonOut['debug'])): ?>
        <details style="margin-top:12px">
          <summary>DEBUG</summary>
          <div class="muted"><?=h(implode(' | ', $jsonOut['debug']))?></div>
        </details>
      <?php endif; ?>
    </div>
  <?php elseif ($raw !== '' && !$id): ?>
    <div class="card"><span class="warn">❌ יש להזין מזהה IMDb חוקי כמו tt5950044</span></div>
  <?php else: ?>
    <!-- דף פתיחה נקי -->
  <?php endif; ?>
</div>

</body>
</html>
