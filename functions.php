<?php
// 🛡️ הופך טקסט לבטוח להצגה בדפדפן (למניעת XSS)
function safe($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 🔁 עיבוד ערך שמגיע ממסד או ממשתנה — עם ערך ברירת מחדל אם חסר
function processValue($value, $sNotFound = '—', $sSeparator = ', ') {
    if (is_array($value)) {
        $filtered = array_filter($value, function($v) {
            return $v !== '' && $v !== null;
        });

        if (empty($filtered)) {
            return $sNotFound;
        }

        $mapped = array_map(function($v) use ($sNotFound) {
            return $v ?: $sNotFound;
        }, $filtered); // השתמש ב־$filtered ולא $value

        return implode($sSeparator, $mapped);
    }

    if ($value === '' || $value === null) {
        return $sNotFound;
    }

    return (string)$value;
}

// 🧠 דוגמה לפונקציה שמנקה מזהי פוסטרים מתוך textarea
function parsePosterIds($rawText) {
    $lines = explode("\n", $rawText);
    $clean = array_map('trim', $lines);
    return array_filter($clean, fn($id) => $id !== '');
}

// ✨ דוגמה לפונקציה להמרת תאריך לתצוגה קריאה
function formatDate($timestamp) {
    return date("Y-m-d H:i", strtotime($timestamp));
}

/**
 * מחלץ מזהה וידאו של יוטיוב מתוך כתובת URL.
 * @param string|null $url כתובת היוטיוב המלאה.
 * @return string מזהה הוידאו הנקי, או מחרוזת ריקה אם לא נמצא.
 */
function extractYoutubeId($url) {
  if (preg_match('/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) return $matches[1];
  return '';
}

/**
 * בודק אם קיים URL לפוסטר ומחזיר אותו, או מחזיר נתיב לתמונת ברירת מחדל.
 * @param string|null $image_from_db ה-URL מהשדה image_url.
 * @return string הנתיב הסופי לתמונה.
 */
function get_poster_url($image_from_db) {
    return !empty($image_from_db) ? $image_from_db : 'images/no-poster.png';
}

/**
 * מחלץ מזהה IMDb נקי (למשל tt1234567) מתוך טקסט או כתובת URL "מלוכלכת".
 * @param string|null $input הטקסט שמכיל את המזהה.
 * @return string מזהה ה-IMDb הנקי, או מחרוזת ריקה אם לא נמצא.
 */
function extractImdbId($input) {
  if (preg_match('/tt\d{7,8}/', $input, $matches)) return $matches[0];
  return '';
}

?>