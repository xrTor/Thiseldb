<?php
// security_guard.php - VERSION 2: AGGRESSIVE MODE

// רשימה של מילות מפתח חשודות שאסור להן להופיע בכתובת URL בשיטת GET
$destructive_keywords = [
    'delete', 'remove', 'erase', 'drop', 
    'pin', 'unpin', 'update', 'edit'
];

$is_destructive_action = false;
foreach ($destructive_keywords as $keyword) {
    foreach ($_GET as $key => $value) {
        // בודק אם מילת המפתח מופיעה בשם של פרמטר כלשהו (למשל, "delete_poster")
        if (strpos($key, $keyword) !== false) {
            $is_destructive_action = true;
            break 2; // צא משתי הלולאות, מצאנו פעולה חשודה
        }
    }
}

// אם זוהתה פעולה חשודה בכתובת ה-URL, חסום אותה עבור כולם
if ($is_destructive_action) {
    http_response_code(403); // Forbidden
    die("State-changing actions via GET requests are forbidden for security reasons. Please use POST forms.");
}
?>