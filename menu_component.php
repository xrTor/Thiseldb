<?php
/**
 * menu_component.php (גרסה סופית ומתוקנת)
 * משתמש בשמות המשתנים הנכונים מ-server.php
 */

// מונע טעינה כפולה של הקובץ
if (defined('MENU_COMPONENT_FINAL_CORRECTED')) {
    return;
}
define('MENU_COMPONENT_FINAL_CORRECTED', true);

// יוצרים משתנה גלובלי שיכיל את המידע עבור קבצים אחרים
global $POSTER_TYPES_DATA;
$POSTER_TYPES_DATA = [];

// טוענים את הגדרות החיבור, שמגדירות את המשתנים $servername, $username וכו'.
require_once __DIR__ . '/server.php';

// יוצרים חיבור חדש וזמני עם המשתנים הנכונים
$conn_menu_component = new mysqli($servername, $username, $password, $dbname);

// בודקים אם החיבור הצליח
if (!$conn_menu_component->connect_error) {
    $conn_menu_component->set_charset("utf8mb4");

    $query = "SELECT label_he, icon, image FROM poster_types";
    if ($result = $conn_menu_component->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $label = trim($row['label_he'] ?? '');
            if ($label) {
                $POSTER_TYPES_DATA[$label] = [
                    'icon'  => trim($row['icon'] ?? '🎬'),
                    'image' => trim($row['image'] ?? ''),
                ];
            }
        }
        $result->free();
    }
    
    // סוגרים את החיבור הזמני מיד בסיום השימוש
    $conn_menu_component->close();
}
?>