<?php
// conn_min.php — IMDb Connections minimal (V2.1)
// תיקון סינון: איסוף פר־LI ולקיחת הקישור הראשון ל-/title/tt…/ בלבד

$tt    = isset($_GET['tt'])    ? preg_replace('~[^a-z0-9]~i','', $_GET['tt']) : 'tt6806448';
$DEBUG = isset($_GET['debug']) ? (bool)$_GET['debug'] : false;
function dbg($m){ global $DEBUG; if($DEBUG) echo "\n"; }

// ---------- HTTP ----------
function http_get($url, $mobile=false){
  $uaDesktop='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
  $uaMobile ='Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Mobile Safari/537.36';
  $cookie = 'lc-main=en-US; da-geo=1; session-id=000-0000000-0000000; session-id-time=2082787201l';
  $hdr = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
    'Accept-Language: en-US,en;q=0.9',
    'Referer: https://www.imdb.com/',
  ];
  $last=[0,''];
  for($i=0;$i<3;$i++){
    $ch=curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
      CURLOPT_TIMEOUT=>25, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_ENCODING=>'',
      CURLOPT_HTTPHEADER=>$hdr, CURLOPT_USERAGENT=> ($mobile?$uaMobile:$uaDesktop),
      CURLOPT_COOKIE=>$cookie,
    ]);
    $body=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    $last=[$code,(string)$body];
    if($code>=200 && $code<400 && strlen($body)>5000 && stripos($body,'Are you a robot')===false) break;
    $mobile = !$mobile; usleep(250000);
  }
  return $last;
}
function sanitize_for_parse($html){
  if(!$html) return '';
  $html = preg_replace('~<script\b[^>]*>.*?</script>~is',' ',$html);
  $html = preg_replace('~<style\b[^>]*>.*?</style>~is',' ',$html);
  return $html;
}
function normalize_title($t){
  $t = trim(preg_replace('/\s+/', ' ', (string)$t));
  $t = preg_replace('~\s*\(\d+\)\s*$~','',$t);      // "(26)" → ""
  $t = preg_replace('~^Jump to.*$~i','',$t);
  return rtrim($t, ":\x{0589}");
}

// ---------- דפוסים: מותר/חסום ----------
function blocked_group_patterns(){
  return [
    '~\bFeatured in\b~i',
    '~\bEdited into\b~i',
    '~\bFeatures\b~i',
    '~\bReferenced in\b~i',
    '~\bReferences\b~i',
    '~\bSpoofed in\b~i',
    '~\bSpoofs\b~i',
  ];
}
function allowed_group_patterns(){
  return [
    '~\bFollows\b~i',
    '~\bFollowed by\b~i',
    '~\bPrequel\b~i',
    '~\bSequel\b~i',
    '~\bSpin-?off\b~i',          // Spin-off / Spinoff
    '~\bSpin-?off from\b~i',
    '~\bSpun off from\b~i',
    '~\bRemake of\b~i',
    '~\bRemade as\b~i',
    '~\bVersion of\b~i',
    '~\bAlternate versions?\b~i',
    '~\bCompilation of\b~i',
    '~\bContains footage from\b~i',
    '~\bConnections\b~i',
  ];
}
function is_allowed_group($title){
  $title = normalize_title($title);
  if ($title==='') return false;
  foreach (blocked_group_patterns() as $re) if (preg_match($re, $title)) return false;
  foreach (allowed_group_patterns() as $re) if (preg_match($re, $title)) return true;
  return false;
}

function clean_items(array $arr){
  $bad = ['Contribute to this page','Learn more about contributing','Jump to'];
  $out = [];
  foreach($arr as $x){
    $x = trim(preg_replace('/\s+/', ' ', strip_tags((string)$x)));
    if($x==='') continue;
    $skip=false; foreach($bad as $b){ if(stripos($x,$b)!==false){ $skip=true; break; } }
    if(!$skip) $out[]=$x;
  }
  return array_values(array_unique($out));
}
function clean_items_with_tt(array $arr){
  $out = []; $seen = [];
  foreach($arr as $x){
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)($x['text'] ?? ''))));
    $tt   = (string)($x['tt'] ?? '');
    if ($text==='' || isset($seen[$text])) continue;
    if (stripos($text,'Jump to')!==false) continue;
    $out[] = ['text'=>$text,'tt'=>$tt];
    $seen[$text]=1;
  }
  return $out;
}

// ---------- Parsers ----------
function parse_next_data($html, &$dbgCounts=null){
  $out=[]; if(!$html) return $out;
  if(!preg_match('~<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>~is',$html,$m)) return $out;
  $j = json_decode(html_entity_decode($m[1]), true);
  if(!$j) return $out;

  $stack=[$j]; $buckets=[]; $grpCount=0; $itemCount=0;
  while($stack){
    $node=array_pop($stack);
    if(!is_array($node)) continue;
    $groups=null;
    if(isset($node['connectionsGroups'])) $groups=$node['connectionsGroups'];
    elseif(isset($node['connections']))   $groups=$node['connections'];
    elseif(isset($node['groups']))        $groups=$node['groups'];

    if(is_array($groups)){
      foreach($groups as $g){
        $title = $g['category'] ?? $g['title'] ?? $g['name'] ?? '';
        if(!$title || !is_allowed_group($title)) continue;
        $grpCount++;

        $rows  = $g['items'] ?? $g['titles'] ?? $g['entries'] ?? [];
        $items=[];
        foreach($rows as $r){
          $parts=[]; $const = $r['const'] ?? '';
          foreach(['titleText','title','primaryText','name','seriesTitle','movieTitle','originalText'] as $k){
            if(!empty($r[$k])) $parts[] = is_array($r[$k]) ? ($r[$k]['text'] ?? '') : $r[$k];
          }
          foreach(['title','primary'] as $k){
            if(!empty($r[$k]) && is_array($r[$k])){
              foreach(['text','titleText','title','name'] as $kk){
                if(!empty($r[$k][$kk])) $parts[] = is_array($r[$k][$kk]) ? ($r[$k][$kk]['text'] ?? '') : $r[$k][$kk];
              }
            }
          }
          if(!empty($r['year']))        $parts[]='('.$r['year'].')';
          if(!empty($r['subtitle']))    $parts[]=$r['subtitle'];
          if(!empty($r['description'])) $parts[]=$r['description'];
          if(!empty($r['text']))        $parts[]=(is_array($r['text'])?($r['text']['text']??''):$r['text']);
          if(!empty($const))            $parts[]=$const;

          $txt=trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))));
          if($txt!==''){ $items[] = [ 'text' => $txt, 'tt' => $const ]; $itemCount++; }
        }
        $items=clean_items_with_tt($items);
        if($items){
          $key=normalize_title($title);
          $buckets[$key] = isset($buckets[$key]) ? array_merge($buckets[$key], $items) : $items;
        }
      }
    }
    foreach($node as $v){ if(is_array($v)) $stack[]=$v; }
  }
  foreach($buckets as $k=>$v){ $out[$k]=array_values(array_unique($v, SORT_REGULAR)); }
  if(is_array($dbgCounts)) $dbgCounts['next']=['groups'=>$grpCount,'items'=>$itemCount];
  return $out;
}

function parse_dom_lists_and_ipc($html, &$dbgCounts=null){
  $out=[]; if(!$html) return $out;
  $dom = new DOMDocument();
  libxml_use_internal_errors(true); @$dom->loadHTML($html); libxml_clear_errors();
  $xp = new DOMXPath($dom);

  $itemsMap = [];
  // מאתרים כותרות (h2/h3), ולכל כותרת מוצאים את ה־UL הראשון שאחריה (אחים עוקבים)
  $heads = $xp->query('//h2|//h3');
  $foundHeads = 0; $foundLi = 0;

  foreach ($heads as $h) {
    $title = normalize_title($h->textContent ?? '');
    if (!$title || !is_allowed_group($title)) continue;
    $foundHeads++;

    // מצא את ה־UL הקרוב ביותר אחרי הכותרת
    $ul = null;
    $sib = $h;
    for ($i=0; $i<8 && $sib; $i++) { // נתקדם עד 8 אחים קדימה לכל היותר
      $sib = $sib->nextSibling;
      while ($sib && $sib->nodeType !== XML_ELEMENT_NODE) $sib = $sib->nextSibling;
      if ($sib && strtolower($sib->nodeName) === 'ul') { $ul = $sib; break; }
    }
    if (!$ul) continue;

    // איסוף פר־LI: רק הקישור הראשון ל-/title/tt…/
    $lis = $xp->query('.//li', $ul);
    foreach ($lis as $li) {
      $a = $xp->query('.//a[contains(@href,"/title/tt")]', $li)->item(0);
      if (!$a) continue;
      if (!preg_match('~/title/(tt\d{7,8})/~', (string)$a->getAttribute('href'), $m)) continue;

      $tt_code = $m[1];
      $text = trim($a->textContent ?? '');
      if ($text==='') continue;

      $itemsMap[$title][] = ['text'=>$text, 'tt'=>$tt_code];
      $foundLi++;
    }
  }

  foreach($itemsMap as $t=>$items){
    // דה־דופליקציה לפי tt
    $unique=[]; $seen=[];
    foreach($items as $it){
      $tt=$it['tt'];
      if(isset($seen[$tt])) continue;
      $seen[$tt]=1; $unique[]=$it;
    }
    $cleaned = clean_items_with_tt($unique);
    if($cleaned) $out[$t]=$cleaned;
  }

  if(is_array($dbgCounts)) $dbgCounts['dom']=['heads'=>$foundHeads,'items'=>$foundLi];
  return $out;
}

function parse_reference($html, &$dbgCounts=null){
  $out=[]; if(!$html) return $out;
  $min = sanitize_for_parse($html);
  if(!preg_match('~(Connections|Movie connections)\s*</h[1-6]>(.*?)(</h[1-6]>|</body>)~is',$min,$m)){ if(is_array($dbgCounts)) $dbgCounts['ref']=['blocks'=>0,'items'=>0]; return $out; }
  $block=$m[0]; $blkItems=0;

  if(preg_match_all('~<(h[2-6]|strong|span)[^>]*>(.*?)</\1>\s*(<ul[^>]*>.*?</ul>)~is',$block,$parts)){
    for($i=0;$i<count($parts[0]);$i++){
      $title = normalize_title(strip_tags($parts[2][$i] ?? ''));
      if(!$title || !is_allowed_group($title)) continue;
      $ul = $parts[3][$i] ?? '';
      if($ul && preg_match_all('~<li[^>]*>(.*?)</li>~is',$ul,$lis)){
        $items = clean_items($lis[1]);
        $blkItems += count($items);
        if($items) $out[$title]=isset($out[$title])?array_values(array_unique(array_merge($out[$title],$items))):$items;
      }
    }
  }
  if(is_array($dbgCounts)) $dbgCounts['ref']=['blocks'=>1,'items'=>$blkItems];
  return $out;
}

// ---------- Pipeline ----------
function get_connections($tt, &$dbg=null){
  $all=[]; $dbg = ['next'=>['groups'=>0,'items'=>0],'dom'=>['heads'=>0,'items'=>0],'ref'=>['blocks'=>0,'items'=>0]];

  [$c1,$h1]=http_get("https://www.imdb.com/title/$tt/movieconnections/");
  dbg("desk $c1 ".strlen($h1));
  if($c1>=200 && $c1<400 && $h1 && stripos($h1,'Are you a robot')===false){
    $nx = parse_next_data($h1, $dbg);                           if($nx)  $all=array_merge_recursive($all,$nx);
    $dm = parse_dom_lists_and_ipc(sanitize_for_parse($h1),$dbg); if($dm)  $all=array_merge_recursive($all,$dm);
  }
  [$c2,$h2]=http_get("https://m.imdb.com/title/$tt/movieconnections/", true);
  dbg("mobi $c2 ".strlen($h2));
  if($c2>=200 && $c2<400 && $h2 && stripos($h2,'Are you a robot')===false){
    $nx2 = parse_next_data($h2, $dbg);                           if($nx2) $all=array_merge_recursive($all,$nx2);
    $dm2 = parse_dom_lists_and_ipc(sanitize_for_parse($h2),$dbg); if($dm2) $all=array_merge_recursive($all,$dm2);
  }
  [$c3,$h3]=http_get("https://www.imdb.com/title/$tt/reference");
  dbg("ref  $c3 ".strlen($h3));
  if($c3>=200 && $c3<400 && $h3){
    $rf = parse_reference($h3,$dbg); if($rf) $all=array_merge_recursive($all,$rf);
  }

  // Normalize (וגם חסימה נוספת ליתר בטחון)
  $out=[];
  foreach($all as $k=>$items){
    $k = normalize_title($k);
    if($k==='') continue;
    foreach (blocked_group_patterns() as $re) { if (preg_match($re,$k)) continue 2; }

    $flat=[];
    foreach((array)$items as $it){
      if(is_array($it)) $flat[]=$it; else $flat[]=['text'=>$it,'tt'=>''];
    }
    $flat = array_map(function($x){ $x['text']=trim(preg_replace('/\s+/', ' ', $x['text'])); return $x; }, $flat);
    $flat = array_values(array_unique(array_filter($flat, fn($x)=>$x['text']!==''), SORT_REGULAR));
    if($flat) $out[$k]=$flat;
  }
  ksort($out);
  return $out;
}

// ---------- Run ----------
$dbgCounts=[];
$cons = get_connections($tt, $dbgCounts);
$total = 0; foreach($cons as $items) $total += count($items);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="he" dir="rtl">
<meta charset="utf-8">
<title>קשרים עבור <?= htmlspecialchars($tt) ?></title>
<body style="font-family:Arial,Segoe UI;direction:rtl;text-align:right;padding:20px">
<h2>קשרים עבור <?= htmlspecialchars($tt) ?></h2>
<p>סך הכל: <b><?= (int)$total ?></b></p>

<?php if($total>0): ?>
  <h3>קשרים ב־IMDb (Connections)</h3>
  <?php foreach($cons as $type=>$items): ?>
    <p><b><?= htmlspecialchars($type) ?> (<?= count($items) ?>):</b><br>
      <?php
        $links=[];
        foreach($items as $item){
          $text = htmlspecialchars($item['text']);
          $ttc  = htmlspecialchars($item['tt']);
          if($ttc!=='') $links[] = "<a href='https://www.imdb.com/title/$ttc/' target='_blank'>$text</a>";
          else          $links[] = $text;
        }
        echo implode(' | ', $links);
      ?>
    </p>
  <?php endforeach; ?>
<?php else: ?>
  <p><i>לא נמצאו קשרים (יתכן חסימה ע״י IMDb/שינוי מבנה עמוד)</i></p>
<?php endif; ?>

<?php if($DEBUG): ?>
  <hr>
  <pre style="white-space:pre-wrap;line-height:1.4">
NEXT_DATA: groups=<?php echo (int)($dbgCounts['next']['groups']??0); ?>, items=<?php echo (int)($dbgCounts['next']['items']??0); ?>

DOM/IPС:   heads=<?php echo (int)($dbgCounts['dom']['heads']??0); ?>, items=<?php echo (int)($dbgCounts['dom']['items']??0); ?>

REF:       blocks=<?php echo (int)($dbgCounts['ref']['blocks']??0); ?>, items=<?php echo (int)($dbgCounts['ref']['items']??0); ?>
  </pre>
<?php endif; ?>
</body>
</html>
