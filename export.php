<?php
/****************************************************
 * export.php — ייצוא נתונים מהטבלה posters
 * כולל ז'אנרים (genres) ותגיות משתמש (user_tags)
 * שם הקובץ: Thiseldb.csv
 ****************************************************/

mb_internal_encoding("UTF-8");

require_once __DIR__ . "/server.php";

if (function_exists('mysqli_set_charset')) {
    @mysqli_set_charset($conn, 'utf8mb4');
}

// === שליפת נתונים (כולל ז'אנרים ותגיות) ===
$sql = "
    SELECT 
        p.id,
        p.title_he,
        p.title_en,
        p.year,
        p.imdb_id,
        p.imdb_rating,
        p.genres,
        GROUP_CONCAT(DISTINCT ut.genre ORDER BY ut.genre SEPARATOR ', ') AS user_tags
    FROM posters p
    LEFT JOIN user_tags ut ON ut.poster_id = p.id
    GROUP BY p.id
    ORDER BY p.id DESC

    

";
// ORDER BY p.id ASC
$res = $conn->query($sql);

// === ייצוא ל־CSV ===
$filename = "Thiseldb.csv";

// כותרות HTTP
header("Content-Type: text/csv; charset=UTF-8");
// שם קובץ קבוע באנגלית
header("Content-Disposition: attachment; filename=\"$filename\"");

// פתיחת פלט
$out = fopen("php://output", "w");

// כתיבת BOM כדי לאפשר תמיכה בעברית ב-Excel
fwrite($out, "\xEF\xBB\xBF");

// כתיבת שורת כותרות בעברית
fputcsv($out, [
    "מזהה",
    "שם בעברית",
    "שם באנגלית",
    "שנה",
    "מזהה IMDb",
    "דירוג IMDb",
    "ז'אנרים",
    "תגיות משתמש"
]);

// כתיבת נתונים
if ($res) {
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['title_he'],
            $row['title_en'],
            $row['year'],
            $row['imdb_id'],
            $row['imdb_rating'],
            $row['genres'],
            $row['user_tags']
        ]);
    }
}

fclose($out);
exit;
