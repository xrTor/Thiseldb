<?php
require_once 'imdb.class.php';
require_once 'functions.php';

$host = 'localhost';
$db   = 'media';
$user = 'root';
$pass = '123456';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("❌ שגיאת חיבור למסד: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $imdb_id  = trim($_POST['imdb_id'] ?? '');
    $title_he = trim($_POST['title_he'] ?? '');

    if ($imdb_id === '' || !preg_match('/^tt\d+$/', $imdb_id)) {
        echo "<p style='color:red;'>🔴 IMDb ID חסר או לא תקין</p>";
        exit;
    }

    // בדיקה אם מזהה כבר קיים במסד
    
      $imdb_link = "https://www.imdb.com/title/" . $imdb_id;
    $check = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
    $check->bind_param("s", $imdb_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<p style='color:orange;'>⚠️ פוסטר עם IMDb הזה כבר קיים</p>";
        $check->close();
        exit;
    }
    $check->close();

    $imdb = new IMDB($imdb_id);
    if (!$imdb->isReady) {
        echo "<p style='color:red;'>❌ IMDb לא הצליח לשלוף פרטים. ודא שה־ID תקין</p>";
        exit;
    }

    // שליפה מ־IMDb
    $title_en    = $imdb->getTitle();
    $year        = $imdb->getYear();
    $imdb_rating = $imdb->getRating();
    $image_url   = $imdb->getPoster();
    $plot        = $imdb->getPlot();
    $imdb_link   = "https://www.imdb.com/title/" . $imdb_id . "/";

    // שמירה למסד (כעת עם imdb_id נפרד)
$stmt = $conn->prepare("INSERT INTO posters 
(imdb_id, title_en, title_he, year, imdb_rating, imdb_link, image_url, plot) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("ssssssss", $imdb_id, $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $plot);

    if (!$stmt) {
        die("❌ שגיאה ב־prepare: " . $conn->error);
    }
    $stmt->execute();
    $stmt->close();

    echo "<p style='color:green;'>✅ פוסטר נוסף בהצלחה!</p>";
}
?>
<h2 style="text-align:center;">📥 הוספת פוסטר חדש לפי IMDb</h2>
<form method="post" action="add.php" style="max-width:500px; margin:auto;">
  <label>🔗 IMDb ID (למשל tt0111161):</label><br>
  <input type="text" name="imdb_id" required><br><br>

  <label>שם בעברית (לא חובה):</label><br>
  <input type="text" name="title_he"><br><br>

  <button type="submit">🕵️‍♂️ שלוף ושמור פוסטר</button>
</form>
