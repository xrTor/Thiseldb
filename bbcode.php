<?php
/**
 * bbcode.php — Parser מקיף ל-BBCode (כולל עברית/אנגלית)
 * שימוש:
 *   require_once 'bbcode.php';
 *   echo bbcode_to_html($raw_text);
 *
 * מאפיינים:
 * - טקסט: [b] [i] [u] [s] [sub] [sup] [small] [big] [mark] [kbd]
 * - כותרות: [h1]..[/h1] עד [h6]
 * - ישור: [left] [right] [center] [justify] וגם [align=...]
 * - צבע/גודל/גופן: [color=red|#f00], [size=12], [font=Arial]
 * - קישורים: [url]..[/url], [url=..]שם[/url], [email]me@x.com[/email]
 * - תמונות: [img]url[/img], [img=200x300]url[/img], [img left]url[/img]
 * - וידאו/אודיו: [youtube]ID/URL[/youtube], [video]URL[/video], [audio]URL[/audio]
 * - ציטוטים: [quote]..[/quote], [quote=שם]..[/quote]
 * - ספוילר: [spoiler]..[/spoiler], [spoiler=כותרת]..[/spoiler]
 * - קוד/ללא פירוק: [code]..[/code], [code=php]..[/code], [noparse]..[/noparse]
 * - רשימות: [list] [*] item [/list], [list=1|a|A|i|I]...
 * - טבלאות: [table][tr][td]...[/td][/tr][/table] (+[th], [thead], [tbody])
 * - דו־לשוני: [עברית]...[/עברית] + [אנגלית]...[/אנגלית] → טבלת 2 עמודות
 *
 * בטיחות:
 * - ברירת מחדל: בוצע htmlspecialchars לכל החומר חוץ מבלוקים שמחופים בפלייסהולדרים (code/noparse).
 * - URLים מסוננים ל-schemes: http/https/mailto/tel בלבד.
 */

if (!function_exists('bbcode_to_html')) {

  /* ---------- עזרי ניקוי/אבטחה ---------- */

  function _bb_norm_eols(string $s): string {
    return str_replace(["\r\n","\r"], "\n", $s);
  }

  function _bb_safe_url(?string $url): ?string {
    if ($url===null) return null;
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url==='') return null;
    // הרשאה: http/https/mailto/tel בלבד
    if (!preg_match('~^(?:https?://|mailto:|tel:)~i', $url)) return null;
    if (preg_match('~^(?:javascript|vbscript|data):~i', $url)) return null;
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
  }

  function _bb_whitelisted_font(string $font): ?string {
    $font = trim($font);
    $allow = [
      'Arial','Helvetica','Tahoma','Verdana','Trebuchet MS',
      'Times New Roman','Georgia','Courier New','Monaco',
      'Rubik','Assistant','Noto Sans Hebrew','Open Sans','Inter'
    ];
    foreach ($allow as $ok) {
      if (strcasecmp($ok, $font)===0) return $ok;
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

  /* ---------- הגנת בלוקים: code/noparse ---------- */

  function _bb_protect_blocks(string $text, array &$store): string {
    // [code] ו-[code=lang]
    $text = preg_replace_callback('~\[code(?:=([^\]]{1,32}))?\](.*?)\[/code\]~is', function($m) use (&$store){
      $lang = isset($m[1]) ? trim($m[1]) : '';
      $raw  = $m[2];
      $key  = '__BBLOCK_CODE_'.count($store).'__';
      $cls  = $lang!=='' ? ' language-'.preg_replace('~[^a-z0-9_-]+~i','', $lang) : '';
      $store[$key] = '<pre class="bb-code"><code class="'.htmlspecialchars($cls,ENT_QUOTES,'UTF-8').'">'.
                     htmlspecialchars($raw, ENT_QUOTES,'UTF-8').'</code></pre>';
      return $key;
    }, $text);

    // [noparse]
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
    // מעבד את הכי פנימי החוצה
    $rx = '~\[list(?:=([1aAiI]))?\](.*?)\[/list\]~is';
    while (preg_match($rx, $text)) {
      $text = preg_replace_callback($rx, function($m){
        $type  = $m[1] ?? '';
        $inner = $m[2];

        // הפוך [*] לפריטים (עד [*] הבא או סוף הרשימה)
        $inner = preg_replace('/\[\*\]\s*(.+?)(?=(\[\*\]|$))/s', '<li>$1</li>', $inner);
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
      '~\[table\]~i' => '<table class="bb-table">',
      '~\[/table\]~i' => '</table>',
      '~\[thead\]~i' => '<thead>',
      '~\[/thead\]~i' => '</thead>',
      '~\[tbody\]~i' => '<tbody>',
      '~\[/tbody\]~i' => '</tbody>',
      '~\[tr\]~i' => '<tr>',
      '~\[/tr\]~i' => '</tr>',
      '~\[th\]~i' => '<th>',
      '~\[/th\]~i' => '</th>',
      '~\[td\]~i' => '<td>',
      '~\[/td\]~i' => '</td>',
    ];
    foreach ($map as $rx=>$rep) {
      $text = preg_replace($rx, $rep, $text);
    }
    // colspan/rowspan בסיסי: [td=2x3] → colspan=2 rowspan=3
    $text = preg_replace_callback('~\[td=(\d{1,3})x(\d{1,3})\]~i', function($m){
      $c = max(1, min(50, (int)$m[1]));
      $r = max(1, min(50, (int)$m[2]));
      return '<td colspan="'.$c.'" rowspan="'.$r.'">';
    }, $text);
    return $text;
  }

  /* ---------- דו־לשוני (עברית/אנגלית) ---------- */

  function _bb_bilingual(string $text): string {
    // אם יש גם וגם – נבנה טבלה דו־עמודתית ונשמר שאר הטקסט סביב
    if (preg_match('~\[עברית\](.*?)\[/עברית\]~is', $text, $he, PREG_OFFSET_CAPTURE) &&
        preg_match('~\[אנגלית\](.*?)\[/אנגלית\]~is', $text, $en, PREG_OFFSET_CAPTURE)) {

      $heTxt   = $he[1][0];
      $heStart = $he[0][1];
      $heEnd   = $heStart + strlen($he[0][0]);

      $enTxt   = $en[1][0];
      $enStart = $en[0][1];
      $enEnd   = $enStart + strlen($en[0][0]);

      $firstStart = min($he[0][1], $en[0][1]);
      $lastEnd    = max($heEnd, $enEnd);

    $before = rtrim(substr($text, 0, $firstStart), "\n\r ");
$after  = ltrim(substr($text, $lastEnd), "\n\r ");


      $layout = '<div class="desc2-wrap">
  <table class="desc2-table" role="presentation" dir="ltr">
    <tr>
      <td class="desc2-td"><div class="desc2-col en">'.$enTxt.'</div></td>
      <td class="desc2-td"><div class="desc2-col he">'.$heTxt.'</div></td>
    </tr>
  </table>
</div>';

      return $before . $layout . $after;
    }

    // רק עברית
    $text = preg_replace('~\[עברית\](.*?)\[/עברית\]~is', '<div class="bb-he" dir="rtl">$1</div>', $text);
    // רק אנגלית
    $text = preg_replace('~\[אנגלית\](.*?)\[/אנגלית\]~is', '<div class="bb-en" dir="ltr">$1</div>', $text);

    return $text;
  }

  /* ---------- המרה ראשית ---------- */

  function bbcode_to_html(string $text): string {
    if ($text==='') return '';

    // 0) נירמול שורות
    $text = _bb_norm_eols($text);

    // 1) הגנת בלוקים (code/noparse) ושמירתם
    $store = [];
    $text  = _bb_protect_blocks($text, $store);

    // 2) בריחה בטוחה של HTML בכל היתר
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 3) תגים בסיסיים/טיפוגרפיה
    $pairs = [
      // טקסט
      '~\[b\](.*?)\[/b\]~isu'   => '<strong>$1</strong>',
      '~\[i\](.*?)\[/i\]~isu'   => '<em>$1</em>',
      '~\[u\](.*?)\[/u\]~isu'   => '<u>$1</u>',
      '~\[s\](.*?)\[/s\]~isu'   => '<s>$1</s>',
      '~\[sub\](.*?)\[/sub\]~isu' => '<sub>$1</sub>',
      '~\[sup\](.*?)\[/sup\]~isu' => '<sup>$1</sup>',
      '~\[small\](.*?)\[/small\]~isu' => '<small>$1</small>',
      '~\[big\](.*?)\[/big\]~isu'     => '<span style="font-size:larger">$1</span>',
      '~\[mark\](.*?)\[/mark\]~isu'   => '<mark>$1</mark>',
      '~\[kbd\](.*?)\[/kbd\]~isu'     => '<kbd>$1</kbd>',
      // כותרות
      '~\[h1\](.*?)\[/h1\]~isu' => '<h1>$1</h1>',
      '~\[h2\](.*?)\[/h2\]~isu' => '<h2>$1</h2>',
      '~\[h3\](.*?)\[/h3\]~isu' => '<h3>$1</h3>',
      '~\[h4\](.*?)\[/h4\]~isu' => '<h4>$1</h4>',
      '~\[h5\](.*?)\[/h5\]~isu' => '<h5>$1</h5>',
      '~\[h6\](.*?)\[/h6\]~isu' => '<h6>$1</h6>',
      // יישור
      '~\[left\](.*?)\[/left\]~isu'     => '<div style="text-align:left">$1</div>',
      '~\[right\](.*?)\[/right\]~isu'   => '<div style="text-align:right">$1</div>',
      '~\[center\](.*?)\[/center\]~isu' => '<div style="text-align:center">$1</div>',
      '~\[justify\](.*?)\[/justify\]~isu' => '<div style="text-align:justify">$1</div>',
      // קו מפריד / שורה חדשה
      '~\[hr\]~iu' => '<hr>',
      '~\[br\]~iu' => '<br>',
    ];
    foreach ($pairs as $rx=>$rep) { $text = preg_replace($rx, $rep, $text); }

    // 4) [align=...]
    $text = preg_replace_callback('~\[align=(left|right|center|justify)\](.*?)\[/align\]~isu', function($m){
      $side = strtolower($m[1]);
      return '<div style="text-align:'.$side.'">'.$m[2].'</div>';
    }, $text);

    // 5) צבע/גודל/גופן
    $text = preg_replace_callback('~\[color=(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)\](.*?)\[/color\]~isu', function($m){
      $c = $m[1];
      if (!preg_match('~^#[0-9a-fA-F]{3,6}$~', $c) && !preg_match('~^[a-zA-Z]+$~', $c)) {
        $c = 'inherit';
      }
      return '<span style="color:'.htmlspecialchars($c,ENT_QUOTES,'UTF-8').'">'.$m[2].'</span>';
    }, $text);

    $text = preg_replace_callback('~\[size=(\d{1,3})\](.*?)\[/size\]~isu', function($m){
      $px = max(8, min(72, (int)$m[1]));
      return '<span style="font-size:'.$px.'px">'.$m[2].'</span>';
    }, $text);

    $text = preg_replace_callback('~\[font=([^\]]{1,64})\](.*?)\[/font\]~isu', function($m){
      $font = _bb_whitelisted_font($m[1]);
      if (!$font) return $m[2];
      return '<span style="font-family:\''.htmlspecialchars($font,ENT_QUOTES,'UTF-8').'\',sans-serif">'.$m[2].'</span>';
    }, $text);

    // 6) לינקים/מייל
    $text = preg_replace_callback('~\[url\](.*?)\[/url\]~isu', function($m){
      $safe = _bb_safe_url($m[1]);
      return $safe ? '<a href="'.$safe.'" target="_blank" rel="nofollow noopener">'.$safe.'</a>' : $m[1];
    }, $text);
    $text = preg_replace_callback('~\[url=(.*?)\](.*?)\[/url\]~isu', function($m){
      $safe = _bb_safe_url($m[1]);
      return $safe ? '<a href="'.$safe.'" target="_blank" rel="nofollow noopener">'.$m[2].'</a>' : $m[2];
    }, $text);
    $text = preg_replace_callback('~\[email\](.*?)\[/email\]~isu', function($m){
      $addr = trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8'));
      if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) return $m[1];
      $esc = htmlspecialchars($addr, ENT_QUOTES,'UTF-8');
      return '<a href="mailto:'.$esc.'">'.$esc.'</a>';
    }, $text);

    // 7) תמונות
    // [img]URL[/img]
    $text = preg_replace_callback('~\[img\](.*?)\[/img\]~isu', function($m){
      $src = _bb_safe_url($m[1]); if (!$src) return '';
      return '<img class="bb-img" src="'.$src.'" alt="">';
    }, $text);
    // [img=WxH]URL[/img]
    $text = preg_replace_callback('~\[img=(\d{1,5})x(\d{1,5})\](.*?)\[/img\]~isu', function($m){
      $w = max(1, min(5000, (int)$m[1]));
      $h = max(1, min(5000, (int)$m[2]));
      $src = _bb_safe_url($m[3]); if (!$src) return '';
      return '<img class="bb-img" src="'.$src.'" width="'.$w.'" height="'.$h.'" alt="">';
    }, $text);
    // [img left]URL[/img] | [img right]URL[/img] | [img center]URL[/img]
    $text = preg_replace_callback('~\[img\s+(left|right|center)\](.*?)\[/img\]~isu', function($m){
      $pos = strtolower($m[1]);
      $src = _bb_safe_url($m[2]); if (!$src) return '';
      if ($pos==='center') {
        return '<div style="text-align:center"><img class="bb-img" src="'.$src.'" alt=""></div>';
      }
      $flt = $pos==='left' ? 'left' : 'right';
      return '<img class="bb-img" src="'.$src.'" alt="" style="float:'.$flt.';margin:6px;">';
    }, $text);

    // 8) וידאו/אודיו
    $text = preg_replace_callback('~\[youtube\](.*?)\[/youtube\]~isu', function($m){
      $id = _bb_youtube_id($m[1]); if (!$id) return '';
      $src = 'https://www.youtube.com/embed/'.htmlspecialchars($id,ENT_QUOTES,'UTF-8');
      return '<div class="bb-youtube" style="aspect-ratio:16/9;max-width:720px;margin:10px auto;"><iframe src="'.$src.'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:100%;border:0"></iframe></div>';
    }, $text);
    $text = preg_replace_callback('~\[video\](.*?)\[/video\]~isu', function($m){
      $src = _bb_safe_url($m[1]); if (!$src) return '';
      return '<video class="bb-video" controls preload="metadata" src="'.$src.'" style="max-width:100%;">Your browser does not support the video tag.</video>';
    }, $text);
    $text = preg_replace_callback('~\[audio\](.*?)\[/audio\]~isu', function($m){
      $src = _bb_safe_url($m[1]); if (!$src) return '';
      return '<audio class="bb-audio" controls preload="metadata" src="'.$src.'">Your browser does not support the audio element.</audio>';
    }, $text);

    // 9) ציטוט/ספוילר
    $text = preg_replace('~\[quote\](.*?)\[/quote\]~isu', '<blockquote class="bb-quote">$1</blockquote>', $text);
    $text = preg_replace_callback('~\[quote=(.*?)\](.*?)\[/quote\]~isu', function($m){
      $who = htmlspecialchars(trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8')), ENT_QUOTES,'UTF-8');
      return '<figure class="bb-quote"><blockquote>'.$m[2].'</blockquote><figcaption>— '.$who.'</figcaption></figure>';
    }, $text);
    $text = preg_replace('~\[spoiler\](.*?)\[/spoiler\]~isu', '<details class="bb-spoiler"><summary>ספוילר</summary><div>$1</div></details>', $text);
    $text = preg_replace_callback('~\[spoiler=(.*?)\](.*?)\[/spoiler\]~isu', function($m){
      $ttl = htmlspecialchars(trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8')), ENT_QUOTES,'UTF-8');
      if ($ttl==='') $ttl='ספוילר';
      return '<details class="bb-spoiler"><summary>'.$ttl.'</summary><div>'.$m[2].'</div></details>';
    }, $text);

    // 10) טבלאות
    $text = _bb_process_tables($text);

    // 11) רשימות (קינון פנימי)
    $text = _bb_process_lists($text);
// Fallback: אם יש [*] מחוץ ל-[list], נהפוך ל-<ul>
if (strpos($text, '[*]') !== false) {
    $text = preg_replace_callback(
        '/((?:\[\*\].*(?:\n|$))+)/U',
        function ($m) {
            $block = $m[1];
            $block = preg_replace('/\[\*\]\s*(.+)/', '<li>$1</li>', $block);
            return "<ul>$block</ul>";
        },
        $text
    );
}

    // 12) דו-לשוני
    $text = _bb_bilingual($text);

    // 13) פסקאות ושבירות שורה
// קודם מנקים \n אחרי <li> ו-לפני </ul>/<ol>
$text = preg_replace('/<li>(.*?)<\/li>\s*/s', '<li>$1</li>', $text);
$text = preg_replace('/<\/li>\s*<li>/', '</li><li>', $text);

// ✅ נירמול ירידות שורה: לא יותר מ־2 רצופות, והסרת רווחים מיותרים
$text = trim($text);
$text = preg_replace("/(\n\s*){3,}/", "\n\n", $text);

// עכשיו ממירים שורות רגילות ל-<br>
// עכשיו ממירים שורות רגילות ל-<br>, אבל לא סביב בלוקים
$lines = explode("\n", $text);
$out = '';
foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '') continue; // מתעלם משורות ריקות לגמרי

    // אם השורה היא תגית בלוק — לא מוסיפים <br>
    if (preg_match('/^<\/?(div|table|tr|td|th|ul|ol|li|blockquote|details|summary|figure|h[1-6])\b/i', $trim)) {
        $out .= $trim;
    } else {
        $out .= $trim . "<br>";
    }
}
$text = $out;


    // 14) שחזור בלוקים (code/noparse)
    $text = _bb_restore_blocks($text, $store);

    return $text;
  }
}

/* ---------- CSS מומלץ (לשילוב חופשי) ----------
.bb-quote{border-inline-start:4px solid #ccc;padding:8px 12px;margin:8px 0;background:#f7f7f7}
.bb-code{background:#111;color:#eee;padding:10px;border-radius:6px;overflow:auto}
.bb-list{margin:.5rem 1.25rem}
.bb-img{max-width:100%;height:auto;border-radius:6px}
.bb-table{border-collapse:collapse;width:100%;margin:10px 0}
.bb-table td,.bb-table th{border:1px solid #ccc;padding:6px 8px}
.bb-spoiler{margin:8px 0}
.bb-youtube{width:100%;max-width:720px}
.bb-he{direction:rtl;text-align:right}
.bb-en{direction:ltr;text-align:left}
.desc2-table{border-collapse:separate;border-spacing:24px 0;width:auto;max-width:1100px}
.desc2-td{vertical-align:top;padding:0 20px;max-width:520px}
.desc2-col{line-height:1.45;font-size:15px;text-align:justify;text-justify:inter-word}
.desc2-col.en{direction:ltr;text-align-last:left}
.desc2-col.he{direction:rtl;text-align-last:right}
------------------------------------------------- */
?>
