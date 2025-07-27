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
?>
