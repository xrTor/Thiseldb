<?php
/**
 * bbcode.php — Parser מקיף ל-BBCode (כולל עברית/אנגלית)
 * שימוש:
 * require_once 'bbcode.php';
 * echo bbcode_to_html($raw_text);
 */

if (!function_exists('bbcode_to_html')) {

  /* ---------- עזרי ניקוי/אבטחה ---------- */

  function _bb_norm_eols(string $s): string {
    return str_replace(["\r\n","\r"], "\n", $s);
  }

  function _bb_safe_url(?string $url): ?string {
    if ($url === null) return null;
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '') return null;
    if (preg_match('~^(?:javascript|vbscript|data):~i', $url)) { return null; }
    if (strpos($url, ':') !== false && !preg_match('~^(?:https?|mailto|tel):~i', $url)) { return null; }
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
  }

  function _bb_whitelisted_font(string $font): ?string {
    $font = trim($font);
    $allow = [
        'Rubik', 'Assistant', 'Heebo', 'Arimo', 'Frank Ruhl Libre', 'David Libre', 'Bellefair', 'Open Sans',
        'Roboto', 'Lato', 'Inter', 'Montserrat', 'Merriweather', 'Playfair Display', 'Oswald', 'Lobster',
        'Arial', 'Helvetica', 'Verdana', 'Tahoma', 'Times New Roman', 'Georgia', 'Garamond', 'Cambria',
        'David', 'Narkisim', 'Gisha', 'Lucida Handwriting',
        'Courier New', 'Lucida Console', 'Monaco', 'Fira Code',
        'Impact', 'Comic Sans MS'
    ];
    foreach ($allow as $ok) {
      if (strcasecmp($ok, $font) === 0) return $ok;
    }
    return null;
  }

  function _bb_youtube_id(string $in): ?string {
    $in = trim(html_entity_decode($in, ENT_QUOTES, 'UTF-8'));
    if ($in==='') return null;
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $in)) return $in;
    if (preg_match('~(?:v=|youtu\.be/|/embed/)([A-Za-z0-9_-]{11})~', $in, $m)) return $m[1];
    return null;
  }

  /* ---------- הגנת בלוקים: code/noparse/pre ---------- */

  function _bb_protect_blocks(string $text, array &$store): string {
    $text = preg_replace_callback('~\[code(?:=([^\]]{1,32}))?\](.*?)\[/code\]~is', function($m) use (&$store){
      $lang = isset($m[1]) ? trim($m[1]) : '';
      $raw  = $m[2];
      $key  = '__BBLOCK_CODE_'.count($store).'__';
      $cls  = $lang!=='' ? ' language-'.preg_replace('~[^a-z0-9_-]+~i','', $lang) : '';
      $store[$key] = '<pre class="bb-code"><code class="'.htmlspecialchars($cls,ENT_QUOTES,'UTF-8').'">'.
                     htmlspecialchars($raw, ENT_QUOTES,'UTF-8').'</code></pre>';
      return $key;
    }, $text);

    $text = preg_replace_callback('~\[pre\](.*?)\[/pre\]~is', function($m) use (&$store){
        $key = '__BBLOCK_PRE_'.count($store).'__';
        $store[$key] = '<pre class="bb-pre">'.htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8').'</pre>';
        return $key;
    }, $text);

    $text = preg_replace_callback('~\[noparse\](.*?)\[/noparse\]~is', function($m) use (&$store){
      $key = '__BBLOCK_NOPARSE_'.count($store).'__';
      $store[$key] = nl2br(htmlspecialchars($m[1], ENT_QUOTES,'UTF-8'));
      return $key;
    }, $text);

    return $text;
  }

  function _bb_restore_blocks(string $text, array $store): string {
    if ($store) {
      foreach ($store as $k=>$v) {
        $text = str_replace($k, $v, $text);
      }
    }
    return $text;
  }

  /* ---------- רשימות (כולל קינון בסיסי) ---------- */

  function _bb_process_lists(string $text): string {
    $rx = '~\[list(?:=([1aAiI]))?\](.*?)\[/list\]~is';
    while (preg_match($rx, $text)) {
      $text = preg_replace_callback($rx, function($m){
        $type  = $m[1] ?? '';
        $inner = $m[2];
        $inner = preg_replace_callback('/\[\*\]\s*(.+?)(?=(\[\*\]|$))/s', function($item_match) {
            return '<li>' . trim($item_match[1]) . '</li>';
        }, $inner);
        $inner = trim($inner);
        if ($type==='') {
          return "<ul class=\"bb-list\">\n$inner\n</ul>";
        }
        $tattr = in_array($type, ['1','a','A','i','I'], true) ? ' type="'.$type.'"' : '';
        return "<ol class=\"bb-list\"$tattr>\n$inner\n</ol>";
      }, $text);
    }
    return $text;
  }

  /* ---------- טבלאות ---------- */

  function _bb_process_tables(string $text): string {
    $map = [
      '~\[table\]~i' => '<table class="bb-table">', '~\[/table\]~i' => '</table>',
      '~\[thead\]~i' => '<thead>', '~\[/thead\]~i' => '</thead>',
      '~\[tbody\]~i' => '<tbody>', '~\[/tbody\]~i' => '</tbody>',
      '~\[tr\]~i' => '<tr>', '~\[/tr\]~i' => '</tr>',
      '~\[th\]~i' => '<th>', '~\[/th\]~i' => '</th>',
      '~\[td\]~i' => '<td>', '~\[/td\]~i' => '</td>',
    ];
    foreach ($map as $rx=>$rep) { $text = preg_replace($rx, $rep, $text); }
    $text = preg_replace_callback('~\[td=(\d{1,3})x(\d{1,3})\]~i', function($m){
      $c = max(1, min(50, (int)$m[1]));
      $r = max(1, min(50, (int)$m[2]));
      return '<td colspan="'.$c.'" rowspan="'.$r.'">';
    }, $text);
    return $text;
  }

  /* ---------- דו־לשוני (עברית/אנגלית) ---------- */

  function _bb_bilingual(string $text): string {
    if (preg_match('~\[עברית\](.*?)\[/עברית\]~is', $text, $he, PREG_OFFSET_CAPTURE) &&
        preg_match('~\[אנגלית\](.*?)\[/אנגלית\]~is', $text, $en, PREG_OFFSET_CAPTURE)) {
      $heTxt   = $he[1][0];
      $enTxt   = $en[1][0];
      $firstStart = min($he[0][1], $en[0][1]);
      $lastEnd = max($he[0][1] + strlen($he[0][0]), $en[0][1] + strlen($en[0][0]));
      $before = rtrim(substr($text, 0, $firstStart), "\n\r ");
      $after  = ltrim(substr($text, $lastEnd), "\n\r ");
      $layout = '<div class="desc2-wrap"><table class="desc2-table" role="presentation" dir="ltr"><tr>'.
                '<td class="desc2-td"><div class="desc2-col en">'.$enTxt.'</div></td>'.
                '<td class="desc2-td"><div class="desc2-col he">'.$heTxt.'</div></td>'.
                '</tr></table></div>';
      return $before . $layout . $after;
    }
    $text = preg_replace('~\[עברית\](.*?)\[/עברית\]~is', '<div class="bb-he" dir="rtl">$1</div>', $text);
    $text = preg_replace('~\[אנגלית\](.*?)\[/אנגלית\]~is', '<div class="bb-en" dir="ltr">$1</div>', $text);
    return $text;
  }

  /* ---------- המרה ראשית ---------- */

  function bbcode_to_html(string $text): string {
    if ($text==='') return '';

    $text = _bb_norm_eols($text);
    $store = [];
    $text  = _bb_protect_blocks($text, $store);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $pairs = [
      '~\[b\](.*?)\[/b\]~isu'   => '<strong>$1</strong>', '~\[i\](.*?)\[/i\]~isu'   => '<em>$1</em>',
      '~\[u\](.*?)\[/u\]~isu'   => '<u>$1</u>', '~\[s\](.*?)\[/s\]~isu'   => '<s>$1</s>',
      '~\[sub\](.*?)\[/sub\]~isu' => '<sub>$1</sub>', '~\[sup\](.*?)\[/sup\]~isu' => '<sup>$1</sup>',
      '~\[small\](.*?)\[/small\]~isu' => '<small>$1</small>', '~\[big\](.*?)\[/big\]~isu' => '<span style="font-size:larger">$1</span>',
      '~\[mark\](.*?)\[/mark\]~isu'   => '<mark>$1</mark>', '~\[kbd\](.*?)\[/kbd\]~isu' => '<kbd>$1</kbd>',
      '~\[h1\](.*?)\[/h1\]~isu' => '<h1>$1</h1>', '~\[h2\](.*?)\[/h2\]~isu' => '<h2>$1</h2>',
      '~\[h3\](.*?)\[/h3\]~isu' => '<h3>$1</h3>', '~\[h4\](.*?)\[/h4\]~isu' => '<h4>$1</h4>',
      '~\[h5\](.*?)\[/h5\]~isu' => '<h5>$1</h5>', '~\[h6\](.*?)\[/h6\]~isu' => '<h6>$1</h6>',
      '~\[left\](.*?)\[/left\]~isu' => '<div style="text-align:left">$1</div>', '~\[right\](.*?)\[/right\]~isu' => '<div style="text-align:right">$1</div>',
      '~\[center\](.*?)\[/center\]~isu' => '<div style="text-align:center">$1</div>', '~\[justify\](.*?)\[/justify\]~isu' => '<div style="text-align:justify">$1</div>',
      '~\[indent\](.*?)\[/indent\]~isu'   => '<div class="bb-indent">$1</div>',
      '~\[hr\]~iu' => '<hr>', '~\[br\]~iu' => '<br>',
    ];
    foreach ($pairs as $rx=>$rep) { $text = preg_replace($rx, $rep, $text); }

    $text = preg_replace_callback('~\[align=(left|right|center|justify)\](.*?)\[/align\]~isu', function($m){
      return '<div style="text-align:'.strtolower($m[1]).'">'.$m[2].'</div>';
    }, $text);

    $text = preg_replace_callback('~\[color=(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)\](.*?)\[/color\]~isu', function($m){
      $c = $m[1];
      if (!preg_match('~^#[0-9a-fA-F]{3,6}$~', $c) && !preg_match('~^[a-zA-Z]+$~', $c)) { $c = 'inherit'; }
      return '<span style="color:'.htmlspecialchars($c,ENT_QUOTES,'UTF-8').'">'.$m[2].'</span>';
    }, $text);

    $text = preg_replace_callback('~\[size=(\d{1,3})\](.*?)\[/size\]~isu', function($m){
        $val = (int)$m[1];
        if ($val >= 1 && $val <= 10) { return '<span class="bb-size-'.$val.'">'.$m[2].'</span>'; }
        $px = max(8, min(72, $val));
        return '<span style="font-size:'.$px.'px">'.$m[2].'</span>';
    }, $text);

    $text = preg_replace_callback('~\[font=([^\]]{1,64})\](.*?)\[/font\]~isu', function($m){
      $font = _bb_whitelisted_font($m[1]);
      if (!$font) return $m[2];
      return '<span style="font-family:\''.htmlspecialchars($font,ENT_QUOTES,'UTF-8').'\',sans-serif">'.$m[2].'</span>';
    }, $text);

    $text = preg_replace_callback('~\[bg=(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)\](.*?)\[/bg\]~isu', function($m){
      $bg = $m[1];
      if (!preg_match('~^#[0-9a-fA-F]{3,6}$~', $bg) && !preg_match('~^[a-zA-Z]+$~', $bg)) { $bg = 'inherit'; }
      return '<span style="background:'.htmlspecialchars($bg,ENT_QUOTES,'UTF-8').';color:#000">'.$m[2].'</span>';
    }, $text);

    $text = preg_replace('~\[info\](.*?)\[/info\]~isu', '<div class="bb-info">$1</div>', $text);
    $text = preg_replace('~\[warning\](.*?)\[/warning\]~isu','<div class="bb-warning">$1</div>', $text);
    $text = preg_replace('~\[error\](.*?)\[/error\]~isu', '<div class="bb-error">$1</div>', $text);
    $text = preg_replace('~\[success\](.*?)\[/success\]~isu','<div class="bb-success">$1</div>', $text);

    $text = preg_replace_callback('~\[url\](.*?)\[/url\]~isu', function($m){
      $safe = _bb_safe_url($m[1]);
      return $safe ? '<strong><a href="'.$safe.'" target="_blank" rel="nofollow noopener">'.$safe.'</a></strong>' : $m[1];
    }, $text);
    $text = preg_replace_callback('~\[url=(.*?)\](.*?)\[/url\]~isu', function($m){
      $safe = _bb_safe_url($m[1]);
      return $safe ? '<strong><a href="'.$safe.'" target="_blank" rel="nofollow noopener">'.$m[2].'</a></strong>' : $m[2];
    }, $text);
    $text = preg_replace_callback('~\[email\](.*?)\[/email\]~isu', function($m){
      $addr = trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8'));
      if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) return $m[1];
      $esc = htmlspecialchars($addr, ENT_QUOTES,'UTF-8');
      return '<a href="mailto:'.$esc.'">'.$esc.'</a>';
    }, $text);

    $text = preg_replace_callback('~\[img\](.*?)\[/img\]~isu', function($m){
      $src = _bb_safe_url($m[1]); if (!$src) return '';
      return '<img class="bb-img" src="'.$src.'" alt="">';
    }, $text);
    $text = preg_replace_callback('~\[img=(\d{1,5})x(\d{1,5})\](.*?)\[/img\]~isu', function($m){
      $w = max(1, min(5000, (int)$m[1])); $h = max(1, min(5000, (int)$m[2]));
      $src = _bb_safe_url($m[3]); if (!$src) return '';
      return '<img class="bb-img" src="'.$src.'" width="'.$w.'" height="'.$h.'" alt="">';
    }, $text);
    $text = preg_replace_callback('~\[img\s+(left|right|center)\](.*?)\[/img\]~isu', function($m){
      $pos = strtolower($m[1]); $src = _bb_safe_url($m[2]); if (!$src) return '';
      if ($pos==='center') { return '<div style="text-align:center"><img class="bb-img" src="'.$src.'" alt=""></div>'; }
      $flt = $pos==='left' ? 'left' : 'right';
      return '<img class="bb-img" src="'.$src.'" alt="" style="float:'.$flt.';margin:6px;">';
    }, $text);

    $text = preg_replace_callback('~\[youtube\](.*?)\[/youtube\]~isu', function($m){
      $id = _bb_youtube_id($m[1]); if (!$id) return '';
      $src = 'https://www.youtube.com/embed/'.htmlspecialchars($id,ENT_QUOTES,'UTF-8');
      return '<div class="bb-youtube"><iframe src="'.$src.'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:100%;border:0"></iframe></div>';
    }, $text);
    $text = preg_replace_callback('~\[video\](.*?)\[/video\]~isu', function($m){
      $src = _bb_safe_url($m[1]); if (!$src) return '';
      return '<video class="bb-video" controls preload="metadata" src="'.$src.'" style="max-width:100%;">Your browser does not support the video tag.</video>';
    }, $text);
    $text = preg_replace_callback('~\[audio\](.*?)\[/audio\]~isu', function($m){
      $src = _bb_safe_url($m[1]); if (!$src) return '';
      return '<audio class="bb-audio" controls preload="metadata" src="'.$src.'">Your browser does not support the audio element.</audio>';
    }, $text);

    $text = preg_replace('~\[quote\](.*?)\[/quote\]~isu', '<blockquote class="bb-quote">$1</blockquote>', $text);
    $text = preg_replace_callback('~\[quote=(.*?)\](.*?)\[/quote\]~isu', function($m){
      $who = htmlspecialchars(trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8')), ENT_QUOTES,'UTF-8');
      return '<figure class="bb-quote"><blockquote>'.$m[2].'</blockquote><figcaption>— '.$who.'</figcaption></figure>';
    }, $text);
    
    $text = preg_replace('~\[spoiler\](.*?)\[/spoiler\]~isu', '<span class="spoiler">$1</span>', $text);
    $text = preg_replace_callback('~\[spoiler=(.*?)\](.*?)\[/spoiler\]~isu', function($m){ return '<span class="spoiler" title="'.htmlspecialchars($m[1]).'">'.$m[2].'</span>'; }, $text);
    $text = preg_replace('~\[hide\](.*?)\[/hide\]~isu', '<details class="bb-hide"><summary>הצג/הסתר</summary><div>$1</div></details>', $text);
    $text = preg_replace_callback('~\[hide=(.*?)\](.*?)\[/hide\]~isu', function($m){
      $ttl = htmlspecialchars(trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8')), ENT_QUOTES,'UTF-8');
      if ($ttl === '') $ttl = 'הצג/הסתר';
      return '<details class="bb-hide"><summary>'.$ttl.'</summary><div>'.$m[2].'</div></details>';
    }, $text);

    $text = _bb_process_tables($text);
    $text = _bb_process_lists($text);

    if (strpos($text, '[*]') !== false) {
        $text = preg_replace_callback('/((?:\[\*\].*(?:\n|$))+)/U', function ($m) {
            $li_items = preg_replace_callback('/\[\*\]\s*(.*)/', function($item_match) { return '<li>' . trim($item_match[1]) . '</li>'; }, $m[1]);
            return '<ul class="bb-list">' . str_replace("\n", '', $li_items) . '</ul>';
        }, $text);
    }
    
    $text = _bb_bilingual($text);

    $protected_html = [];
    $text = preg_replace_callback('~<(ul|ol|table) class="bb-[^>"]+"[^>]*>.*?</\1>~is', function($m) use (&$protected_html) {
        $key = '__PROTECTED_HTML_'.count($protected_html).'__';
        $protected_html[$key] = $m[0];
        return $key;
    }, $text);

    $text = nl2br(trim($text), false);

    if ($protected_html) { foreach ($protected_html as $k => $v) { $text = str_replace($k, $v, $text); } }

    $text = _bb_restore_blocks($text, $store);

    return $text;
  }
}
?>