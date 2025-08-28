<?php
/**
 * menu_component.php (גרסת עיבוד-צד-שרת)
 * קובץ יחיד שמבצע post-processing ל-HTML לפני שליחה לדפדפן:
 * מחליף בטקסטים של סוגים (poster_types) את האמוג'י/אייקון בתמונת הסוג + טקסט.
 */

if (defined('MENU_TYPES_OUTPUT_FILTER')) return;
define('MENU_TYPES_OUTPUT_FILTER', true);

/* ===== Escape בטוח ===== */
if (!function_exists('h')) {
  function h($v) {
    if ($v === null) return '';
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }
}

/* ===== חיבור למסד אם צריך ===== */
if (!isset($conn) || !($conn instanceof mysqli)) {
  $p = __DIR__ . '/server.php';
  if (is_file($p)) require_once $p;
}

/* ===== בסיס URL לתמונות ===== */
if (!defined('TYPE_IMG_URL_BASE')) {
    $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    define('TYPE_IMG_URL_BASE', $base_path . '/images/types/');
}

/* ===== שליפת מפה מה-DB: שם/קוד סוג → [image, icon] + מפה קאנונית ===== */
$__types_map = [];
$__canon_map = [];

if (isset($conn) && $conn instanceof mysqli) {
  if ($res = $conn->query("SELECT code,label_he,label_en,icon,image FROM poster_types")) {
    while ($r = $res->fetch_assoc()) {
      $icon = (string)($r['icon'] ?? '🎬');
      $img  = trim((string)($r['image'] ?? ''));
      $entry = ['icon'=>$icon, 'image'=>$img];

      foreach (['code','label_he','label_en'] as $k) {
        $key = trim((string)($r[$k] ?? ''));
        if ($key === '') continue;
        $__types_map[$key] = $entry;

        $canon = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(
                  preg_replace('/[🎬🎞🎥📺📽️📼❓]+/u','', $key), 'UTF-8'
                ));
        $canon = preg_replace('/\s+/u',' ', trim($canon));
        if ($canon !== '' && !isset($__canon_map[$canon])) $__canon_map[$canon] = $key;
      }
    }
    $res->free();
  }
}

if (!$__types_map) return;

/* ===== פונקציות עזר ל־filter ===== */
$__BASE = TYPE_IMG_URL_BASE;

$__canon = static function(string $s): string {
  $s = preg_replace('/[🎬🎞🎥📺📽️📼❓]+/u','', $s);
  $s = preg_replace('/\(\d+\)\s*$/u','', $s);
  $s = preg_replace('/[‐-‒–—―־-]+/u',' ', $s);
  $s = preg_replace('/[^\p{L}\p{N}]+/u',' ', $s);
  $s = preg_replace('/\s+/u',' ', trim($s));
  return mb_strtolower($s, 'UTF-8');
};

$__img_html = static function(string $file, string $alt, string $icon='🎬') use ($__BASE): string {
  $src = $__BASE . rawurlencode($file);
  $styles = 'width:64px;object-fit:cover;border-radius:3px;vertical-align:middle;margin-left:6px;';
  $iconJs = str_replace("'", "\\'", $icon);
  return '<img src="'.h($src).'" alt="'.h($alt).'" style="'.$styles.'" '.
         'onerror="this.replaceWith(document.createTextNode(\''.$iconJs.' \'));">';
};

$__replace_content = static function(string $innerHtml) use ($__canon, $__canon_map, $__types_map, $__img_html): string {
  $plain = trim(strip_tags($innerHtml));
  if ($plain === '') return $innerHtml;

  $canon = $__canon($plain);
  if ($canon === '' || !isset($__canon_map[$canon])) return $innerHtml;

  $key   = $__canon_map[$canon];
  $entry = $__types_map[$key] ?? null;
  if (!$entry) return $innerHtml;

  $img   = trim((string)$entry['image'] ?? '');
  $icon  = (string)($entry['icon'] ?? '🎬');

  $displayText = preg_replace('/[🎬🎞🎥📺📽️📼❓]+/u','', $plain);
  $displayText = preg_replace('/\s+/u',' ', trim($displayText));

  $prefix = '';
  if ($img !== '') {
    $prefix = $__img_html($img, $key, $icon);
  } else {
    $prefix = h($icon.' ');
  }
  
  // מחליף רק את הטקסט, שומר על אלמנטים פנימיים כמו checkbox
  if(strpos($innerHtml, $plain) !== false) {
      return str_replace($plain, $prefix . h($displayText), $innerHtml);
  }
  // Fallback
  return $prefix . h($displayText);
};

/* ===== פילטר הפלט ===== */
ob_start(function(string $html) use ($__replace_content) {
  if (stripos($html, '<html') === false) return $html;

  // 1) תאי טבלה ב-stats.php
  $html = preg_replace_callback(
    '~(<td\b[^>]*>)(.*?)(</td>)~siu',
    function($m) use ($__replace_content){
      return $m[1] . $__replace_content($m[2]) . $m[3];
    },
    $html
  );
  
  // >> הוספת חוק חדש עבור התוויות ב-bar.php <<
  // 2) תוויות (labels) עם checkbox ב-bar.php
  $html = preg_replace_callback(
    '~(<label\b[^>]*>)(.*?)(</label>)~siu',
    function($m) use ($__replace_content){
      // החלף רק אם התווית מכילה checkbox, כדי לא לפגוע בתוויות אחרות
      if (strpos($m[2], 'type="checkbox"') !== false) {
        return $m[1] . $__replace_content($m[2]) . $m[3];
      }
      return $m[0]; // החזר את המקור ללא שינוי
    },
    $html
  );
  
  // ניתן להוסיף כאן חוקים נוספים בעתיד באותה הדרך

  return $html;
});
?>