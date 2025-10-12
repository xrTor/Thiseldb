<?php
// PHP Sitemap for Thiseldb based on GitHub repository file structure

$sitemap = [
    'דף הבית' => '/',
    'אודות' => '/about.php',
    'קשר' => '/contact.php',
    'שירותים' => [
        'הוספה' => [
            'הוספה' => '/add.php',
            'הוספה (חדש)' => '/add_new.php',
            'הוספה אוטומטית' => '/auto-add.php',
            'הוסף לאוסף' => '/add_to_collection.php',
            'הוסף לאוסף באצווה' => '/add_to_collection_batch.php',
        ],
        'אוספים' => [
            'אוספים' => '/collections.php',
            'צור אוסף' => '/create_collection.php',
            'חיפוש באוספים' => '/collections_search.php',
        ],
        'קישורים' => [
            'קישורים' => '/connections.php',
            'סטטיסטיקות קישורים' => '/connections_stats.php',
        ],
        'פעולות' => [
            'מחיקה' => '/delete.php',
            'מחיקת טריילר' => '/delete_trailer.php',
            'ניקוי כפילויות' => '/cleanup_duplicates.php',
            'שחקן' => '/actor.php',
            'מדינה' => '/country.php',
        ],
    ],
    'תוספים' => [
        'עורך BBCode' => '/bbcode_editor.php',
        'מדריך BBCode' => '/bbcode_guide.php',
    ],
    'אחרים' => [
        'רישיון' => '/LICENSE',
        'README' => '/README.md',
    ],
];

function generateSitemapHTML($items) {
    echo '<ul>';
    foreach ($items as $title => $url) {
        if (is_array($url)) {
            echo '<li>' . $title;
            generateSitemapHTML($url);
            echo '</li>';
        } else {
            echo '<li><a href="' . $url . '">' . $title . '</a></li>';
        }
    }
    echo '</ul>';
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מפת אתר</title>
    <style>
        body {
            direction: rtl;
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
        }
        ul {
            list-style-type: none;
            padding-right: 20px;
        }
        li {
            margin: 5px 0;
        }
        a {
            text-decoration: none;
            color: #007bff;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>מפת אתר</h1>
    <?php generateSitemapHTML($sitemap); ?>
</body>
</html>