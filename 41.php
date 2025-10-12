<?php
// הגדרת כותרת הדף
$page_title = "מפת אתר - Thiseldb";

// מערך המייצג את מבנה הניווט (כפי שנותח)
$sitemap_structure = [
    "דפי ליבה וניווט ראשי" => [
        ["title" => "דף הבית", "url" => "index.php"],
        ["title" => "אודות", "url" => "about.php"],
        ["title" => "חיפוש כללי", "url" => "search.php"],
        ["title" => "כניסת משתמש", "url" => "login.php"],
        ["title" => "צרו קשר", "url" => "contact.php"],
        ["title" => "עזרה / מדריך", "url" => "help.php", "children" => [
            ["title" => "מדריך BBCode", "url" => "bbcode_guide.php"]
        ]]
    ],
    "ניהול אוספים" => [
        ["title" => "רשימת אוספים", "url" => "collections.php", "children" => [
            ["title" => "חיפוש באוספים", "url" => "collections_search.php"]
        ]],
        ["title" => "אוסף ספציפי", "url" => "collection.php", "children" => [
            ["title" => "ייצוא ל-CSV", "url" => "collection_csv.php"]
        ]],
        ["title" => "יצירת אוסף חדש", "url" => "create_collection.php"]
    ],
    "דפי תוכן ופרטי מדיה" => [
        ["title" => "פרטי סרט/פריט", "url" => "movie.php", "children" => [
            ["title" => "טריילרים", "url" => "trailer.php"]
        ]],
        ["title" => "פרטי שחקן", "url" => "actor.php"],
        ["title" => "סינון לפי מדינה", "url" => "country.php"]
    ],
    "פעולות CRUD והוספה" => [
        ["title" => "הוספת פריט (טופס ראשי)", "url" => "add.php", "children" => [
            ["title" => "הוספת פריט חדש", "url" => "add_new.php"],
            ["title" => "הוספה אוטומטית", "url" => "auto-add.php"],
            ["title" => "הוספה לאוסף", "url" => "add_to_collection.php"]
        ]],
        ["title" => "עריכת פריט", "url" => "edit.php"],
        ["title" => "מחיקת פריט", "url" => "delete.php"]
    ],
    "הגדרות, כלים וניהול מערכת" => [
        ["title" => "הגדרות משתמש", "url" => "setting.php"],
        ["title" => "דוחות וסטטיסטיקות", "url" => "report.php", "children" => [
            ["title" => "סטטיסטיקות חיבורים", "url" => "connections_stats.php"]
        ]],
        ["title" => "כלי תחזוקה", "url" => "maintenance.php", "children" => [ // דף תחזוקה היפותטי
            ["title" => "ניקוי כפילויות", "url" => "cleanup_duplicates.php"],
            ["title" => "גיבוי טבלה", "url" => "dump_table.php"]
        ]]
    ]
];

/**
 * פונקציה רקורסיבית ליצירת מבנה ה-HTML של מפת האתר
 * @param array $items מבנה מפת האתר
 */
function renderSitemap($items) {
    echo "<ul>";
    foreach ($items as $item) {
        $url = $item['url'] ?? '#'; // אם אין URL, נשתמש ב-#
        echo "<li><a href='{$url}'>{$item['title']}</a>";
        
        // אם יש דפים מקוננים (children)
        if (isset($item['children']) && !empty($item['children'])) {
            renderSitemap($item['children']);
        }
        echo "</li>";
    }
    echo "</ul>";
}

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1 { border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        h2 { color: #0056b3; margin-top: 30px; }
        ul { list-style-type: none; padding-right: 20px; }
        ul ul { list-style-type: circle; padding-right: 40px; }
        a { text-decoration: none; color: #007bff; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1><?php echo $page_title; ?></h1>

    <?php foreach ($sitemap_structure as $section_title => $items): ?>
        <h2><?php echo $section_title; ?></h2>
        <?php renderSitemap($items); ?>
        <hr>
    <?php endforeach; ?>

</body>
</html>