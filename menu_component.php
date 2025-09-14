<?php
/**
 * menu_component.php (גרסה משופרת ובטוחה)
 * מבצע post-processing ל-HTML כדי להוסיף תמונות לסוגי פוסטרים.
 */

if (defined('MENU_TYPES_OUTPUT_FILTER_LOADED')) return;
define('MENU_TYPES_OUTPUT_FILTER_LOADED', true);

function run_menu_types_output_filter() {
    // בודקים אם כבר רץ או אם אין צורך להפעיל
    if (defined('MENU_TYPES_FILTER_ACTIVE')) return;
    define('MENU_TYPES_FILTER_ACTIVE', true);

    /* ===== חיבור למסד ===== */
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $p = __DIR__ . '/server.php';
        if (is_file($p)) {
            require_once $p;
        }
    }
    
    // אם עדיין אין חיבור למסד נתונים, אין טעם להמשיך
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return;
    }

    /* ===== שליפת סוגים מה-DB ===== */
    $types_map = [];
    if ($res = $conn->query("SELECT label_he, icon, image FROM poster_types")) {
        while ($r = $res->fetch_assoc()) {
            $label = trim((string)($r['label_he'] ?? ''));
            if ($label !== '') {
                $types_map[$label] = [
                    'icon' => trim((string)($r['icon'] ?? '🎬')),
                    'image' => trim((string)($r['image'] ?? '')),
                ];
            }
        }
        $res->free();
    }

    // אם אין מידע על סוגים, אין מה לעשות
    if (empty($types_map)) return;
    
    $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $img_base_url = $base_path . '/images/types/';

    /* ===== פילטר הפלט ===== */
    ob_start(function($html) use ($types_map, $img_base_url) {
        // מנסה למנוע שגיאות קריטיות ולטפל בשגיאות בשקט
        try {
            if (stripos($html, '<html') === false || empty($types_map)) {
                return $html;
            }

            // החלפה בטבלאות סטטיסטיקה
            $html = preg_replace_callback(
                '~(<td\b[^>]*>)(.*?)(</td>)~siu',
                function($m) use ($types_map, $img_base_url) {
                    $original_content = $m[2];
                    $text_content = trim(strip_tags($original_content));
                    
                    if (isset($types_map[$text_content]) && $types_map[$text_content]['image'] !== '') {
                        $type = $types_map[$text_content];
                        $img_src = h($img_base_url . $type['image']);
                        $icon_safe = h($type['icon']);
                        $img_html = '<img src="'.$img_src.'" alt="'.h($text_content).'" style="height:24px; vertical-align:middle; margin-left:6px;" onerror="this.replaceWith(document.createTextNode(\''.$icon_safe.'\'));">';
                        return $m[1] . $img_html . h($text_content) . $m[3];
                    }
                    return $m[0];
                },
                $html
            );
            
            // החלפה בתוויות של checkboxes
             $html = preg_replace_callback(
                '~(<label\b[^>]*>)(.*?)(</label>)~siu',
                function($m) use ($types_map, $img_base_url) {
                    $original_content = $m[2];
                    $text_content = trim(strip_tags($original_content));

                    // מציאת הטקסט הנקי (ללא האימוג'י)
                    $clean_text = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text_content);
                    $clean_text = trim(preg_replace('/\(\d+\)\s*$/', '', $clean_text));
                    
                    if (isset($types_map[$clean_text]) && $types_map[$clean_text]['image'] !== '' && strpos($original_content, 'type="checkbox"') !== false) {
                        $type = $types_map[$clean_text];
                        $img_src = h($img_base_url . $type['image']);
                        $icon_safe = h($type['icon']);
                        
                        $img_html = '<img src="'.$img_src.'" alt="'.h($clean_text).'" style="height:24px; vertical-align:middle; margin-left:6px;" onerror="this.replaceWith(document.createTextNode(\''.$icon_safe.'\'));">';
                        
                        // מחליפים רק את הטקסט עצמו, ומשאירים את ה-checkbox
                        return $m[1] . str_replace($clean_text, $img_html . h($clean_text), $original_content) . $m[3];
                    }
                    return $m[0];
                },
                $html
            );

            return $html;
        } catch (Throwable $e) {
            // במקרה של שגיאה כלשהי, פשוט נחזיר את ה-HTML המקורי כדי לא לשבור את העמוד
            return $html;
        }
    });
}

// מפעיל את הפילטר
run_menu_types_output_filter();
?>