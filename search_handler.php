<?php
require_once 'server.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$q = trim($q);

if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode([]);
    exit;
}

// משתנה שיחזיק את ה-ID אם נמצא אותו
$detectedImdbId = null;

// בדיקה חכמה (Regex): מחפש תבנית של tt ואחריה 7 עד 10 ספרות
// זה תופס גם טקסט נקי "tt1104001" וגם לינקים מלוכלכים
if (preg_match('/tt\d{7,10}/', $q, $matches)) {
    $detectedImdbId = $matches[0];
}

if ($detectedImdbId) {
    // --- אופציה א': נמצא מזהה IMDb ---
    // מחפשים בדיוק את הסרט הזה (LIMIT 1 מספיק)
    // שים לב: אני מניח שיש לך עמודה בשם imdb_id בטבלה
    $sql = "SELECT id, title_he, title_en, year, poster_url, image_url, imdb_rating, runtime FROM posters 
            WHERE imdb_id = ? 
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $detectedImdbId);

} else {
    // --- אופציה ב': חיפוש רגיל (טקסט) ---
    // מחפשים לפי כותרות בעברית או באנגלית
    $sql = "SELECT id, title_he, title_en, year, poster_url, image_url, imdb_rating, runtime FROM posters 
            WHERE title_he LIKE ? OR title_en LIKE ? 
            ORDER BY year DESC 
            LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    $term = "%$q%";
    $stmt->bind_param("ss", $term, $term);
}

// הרצת השאילתה (זהה לשני המקרים)
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    // בחירת תמונה
    $img = !empty($row['image_url']) ? $row['image_url'] : 
           (!empty($row['poster_url']) ? $row['poster_url'] : 'images/no-poster.png');
    
    $data[] = [
        'id' => $row['id'],
        'title' => $row['title_he'] ?: $row['title_en'],
        'sub' => $row['title_en'],
        'year' => $row['year'],
        'poster' => $img,
        'rating' => $row['imdb_rating'],
        'runtime' => $row['runtime'] // הוספת Runtime
    ];
}

echo json_encode($data);
?>