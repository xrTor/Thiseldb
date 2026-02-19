<?php
/****************************************************
 * export.php — גרסה סופית ומתוקנת
 * תומך בריבוי דגלים (למשל: "עברית, מדובב")
 ****************************************************/

mb_internal_encoding("UTF-8");

require_once __DIR__ . "/server.php";

// 1. טעינת קובץ השפות ובניית מילון: קוד -> תווית (Label)
$code_to_label = []; 

if (file_exists(__DIR__ . "/languages.php")) {
    include __DIR__ . "/languages.php";
    if (isset($languages) && is_array($languages)) {
        foreach ($languages as $l) {
            // ניקוי הקוד (אותיות קטנות ובלי רווחים)
            $key = strtolower(trim($l['code'])); 
            $code_to_label[$key] = $l['label']; 
        }
    }
}

if (function_exists('mysqli_set_charset')) {
    @mysqli_set_charset($conn, 'utf8mb4');
}

// 2. שליפת הנתונים
// תיקון: שימוש ב-GROUP_CONCAT גם עבור השפות המשניות כדי לתפוס את כולן
$sql = "
    SELECT 
        p.id,
        p.title_he,
        p.title_en,
        p.year,
        p.imdb_id,
        p.imdb_rating,
        p.genres,
        p.lang_code AS main_code,       -- הקוד בטבלה הראשית (בדרך כלל אחד)
        GROUP_CONCAT(DISTINCT pl.lang_code SEPARATOR ',') AS secondary_codes, -- כל הקודים מטבלת המשנה מופרדים בפסיק
        p.languages,                    
        GROUP_CONCAT(DISTINCT ut.genre ORDER BY ut.genre SEPARATOR ', ') AS user_tags
    FROM posters p
    LEFT JOIN user_tags ut ON ut.poster_id = p.id
    LEFT JOIN poster_languages pl ON pl.poster_id = p.id
    GROUP BY p.id
    ORDER BY p.id DESC
";

$res = $conn->query($sql);

// 3. הגדרות הקובץ
$filename = "Thiseldb.csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

$out = fopen("php://output", "w");
fwrite($out, "\xEF\xBB\xBF"); // BOM לתמיכה בעברית

// כתיבת כותרות
fputcsv($out, [
    "מזהה",
    "שם בעברית",
    "שם באנגלית",
    "שנה",
    "מזהה IMDb",
    "דירוג IMDb",
    "ז'אנרים",
    "תגיות",
    "שפה (IMDb)",
    "דגל (סטטוס)",
]);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        
        // --- איסוף כל קודי הדגלים למערך אחד ---
        $all_codes = [];

        // הוספת הקוד הראשי (אם קיים)
        if (!empty($row['main_code'])) {
            $all_codes[] = trim($row['main_code']);
        }

        // הוספת הקודים המשניים (מפרקים את המחרוזת שנוצרה ב-GROUP_CONCAT)
        if (!empty($row['secondary_codes'])) {
            $seconds = explode(',', $row['secondary_codes']);
            foreach ($seconds as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $all_codes[] = $s;
                }
            }
        }

        // הסרת כפילויות (למשל אם 'hebrew' מופיע גם בראשי וגם במשני)
        $all_codes = array_unique($all_codes);

        // --- תרגום הקודים לתוויות ---
        $translated_labels = [];
        foreach ($all_codes as $code) {
            $clean_code = strtolower($code);
            // בדיקה במילון: אם קיים תרגום קח אותו, אחרת הצג את הקוד המקורי
            if (isset($code_to_label[$clean_code])) {
                $translated_labels[] = $code_to_label[$clean_code];
            } else {
                $translated_labels[] = $clean_code; 
            }
        }

        // חיבור התוויות למחרוזת אחת (למשל: "עברית, מדובב")
        $flag_final_string = implode(', ', $translated_labels);


        // --- כתיבה לקובץ ---
        fputcsv($out, [
            $row['id'],
            $row['title_he'],
            $row['title_en'],
            $row['year'],
            $row['imdb_id'],
            $row['imdb_rating'],
            $row['genres'],
            $row['user_tags'],
            $row['languages'],     // עמודת שפה (IMDb)
            $flag_final_string,    // עמודת דגל (כל התוויות)
        ]);
    }
}

fclose($out);
exit;
?>