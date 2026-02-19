<?php
require_once 'server.php';

// 1. קבלת מזהה האוסף ובדיקות תקינות
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) die("❌ אוסף לא צוין");

// שליפת שם האוסף בלבד (בשביל שם הקובץ)
$stmt = $conn->prepare("SELECT name FROM collections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("❌ האוסף לא נמצא");
$collection = $result->fetch_assoc();
$stmt->close();

// 2. טעינת קובץ השפות ובניית מילון (כמו ב-export.php)
$code_to_label = []; 
if (file_exists(__DIR__ . "/languages.php")) {
    include __DIR__ . "/languages.php";
    if (isset($languages) && is_array($languages)) {
        foreach ($languages as $l) {
            $key = strtolower(trim($l['code'])); 
            $code_to_label[$key] = $l['label']; 
        }
    }
}

if (function_exists('mysqli_set_charset')) {
    @mysqli_set_charset($conn, 'utf8mb4');
}

// פונקציה לניקוי שם הקובץ
function safe_filename($name) {
    $name = preg_replace('/[\/\\\\\?\%\*\:\|\"\'<>]/u', '', $name);
    $name = str_replace(' ', '_', $name);
    return $name;
}

// 3. הגדרת שם הקובץ (Thiseldb + שם האוסף)
$filename = "Thiseldb_" . safe_filename($collection['name']) . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen("php://output", "w");
fwrite($out, "\xEF\xBB\xBF"); // BOM לתמיכה בעברית

// כתיבת כותרות (זהות ל-export.php)
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

// 4. שליפת הנתונים - מותאם לאוסף ספציפי
// הוספנו JOIN ל-poster_collections ו-WHERE לפי ה-ID של האוסף
$sql = "
    SELECT 
        p.id,
        p.title_he,
        p.title_en,
        p.year,
        p.imdb_id,
        p.imdb_rating,
        p.genres,
        p.lang_code AS main_code,
        GROUP_CONCAT(DISTINCT pl.lang_code SEPARATOR ',') AS secondary_codes,
        p.languages,
        GROUP_CONCAT(DISTINCT ut.genre ORDER BY ut.genre SEPARATOR ', ') AS user_tags
    FROM posters p
    JOIN poster_collections pc ON pc.poster_id = p.id
    LEFT JOIN user_tags ut ON ut.poster_id = p.id
    LEFT JOIN poster_languages pl ON pl.poster_id = p.id
    WHERE pc.collection_id = ?
    GROUP BY p.id
    ORDER BY p.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res) {
    while ($row = $res->fetch_assoc()) {
        
        // --- לוגיקה זהה ל-export.php לחישוב שפות ודגלים ---
        $all_codes = [];

        // הוספת הקוד הראשי
        if (!empty($row['main_code'])) {
            $all_codes[] = trim($row['main_code']);
        }

        // הוספת הקודים המשניים
        if (!empty($row['secondary_codes'])) {
            $seconds = explode(',', $row['secondary_codes']);
            foreach ($seconds as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $all_codes[] = $s;
                }
            }
        }

        $all_codes = array_unique($all_codes);

        // תרגום הקודים
        $translated_labels = [];
        foreach ($all_codes as $code) {
            $clean_code = strtolower($code);
            if (isset($code_to_label[$clean_code])) {
                $translated_labels[] = $code_to_label[$clean_code];
            } else {
                $translated_labels[] = $clean_code; 
            }
        }

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

$stmt->close();
fclose($out);
$conn->close();
exit;
?>