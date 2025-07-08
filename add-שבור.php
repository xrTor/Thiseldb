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

// 🧠 שליחת טופס רגיל (לא תגית)
if ($_SERVER["REQUEST_METHOD"] == "POST"
    && !isset($_POST['add_category']) 
    && !isset($_POST['delete_category'])) {

    // קלט מהטופס
    $imdb_id         = $_POST['imdb_id'] ?? '';
    $title_he        = $_POST['title_he'] ?? '';
    $type            = $_POST['type'] ?? 'movie';
    $tvdb_id         = $_POST['tvdb_id'] ?? '';
    $lang_code       = $_POST['lang_code'] ?? '';
    $genre           = $_POST['genre'] ?? '';
    $actors          = $_POST['actors'] ?? '';
    $youtube_trailer = $_POST['youtube_trailer'] ?? '';
    $has_subtitles   = isset($_POST['has_subtitles']) ? 1 : 0;
    $is_dubbed       = isset($_POST['is_dubbed']) ? 1 : 0;
    $categories      = $_POST['categories'] ?? [];

    // שליפה מ־IMDb
    $title_en    = '';
    $year        = '';
    $imdb_rating = '';
    $image_url   = '';
    $plot        = '';
    $imdb_link   = '';

    if (!empty($imdb_id)) {
        $imdb = new IMDB($imdb_id);
        if ($imdb->isReady) {
            $title_en    = $imdb->getTitle();
            $year        = $imdb->getYear();
            $imdb_rating = $imdb->getRating();
            $image_url   = $imdb->getPoster();
            $plot        = $imdb->getPlot();
            $imdb_link   = "https://www.imdb.com/title/" . $imdb_id;
        } else {
            echo "<p style='color:red;'>❌ IMDb ID לא תקף או לא נשלף</p>";
        }
    }

    // שמירה למסד
    $stmt = $conn->prepare("INSERT INTO posters 
    (title_en, title_he, year, imdb_rating, imdb_link, image_url, plot, type, tvdb_id, genre, actors, youtube_trailer, has_subtitles, is_dubbed, lang_code) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssssssiiis",
        $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $plot,
        $type, $tvdb_id, $genre, $actors, $youtube_trailer, $has_subtitles, $is_dubbed, $lang_code);

    $stmt->execute();
    $poster_id = $conn->insert_id;
    $stmt->close();

    // שמירת תגיות
    foreach ($categories as $cat_id) {
        $stmt = $conn->prepare("INSERT INTO poster_categories (poster_id, category_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $poster_id, intval($cat_id));
        $stmt->execute();
        $stmt->close();
    }

    echo "<p style='color:green; text-align:center;'>✅ הפוסטר נשמר בהצלחה!</p>";

    if ($type === 'series' && !empty($tvdb_id)) {
        echo "<div style='text-align:center;'>
            🌐 <a href='https://thetvdb.com/series/" . htmlspecialchars($tvdb_id) . "' target='_blank'>TVDB</a>
        </div>";
    }
}

// ➕ הוספת תגית
if ($_SERVER["REQUEST_METHOD"] == "POST" 
    && isset($_POST['add_category']) 
    && !empty($_POST['new_category"])) {

    $name = trim($_POST['new_category']);

    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();

    echo "<p style='color:green; text-align:center;'>✅ תגית חדשה נוספה בהצלחה: " . htmlspecialchars($name) . "</p>";
}



// 🗑️ מחיקת תגית
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_category']) && !empty($_POST['delete_category_id"])) {
    $id = intval($_POST['delete_category_id']);
    $conn->query("DELETE FROM poster_categories WHERE category_id = $id");
    $conn->query("DELETE FROM categories WHERE id = $id");
}
?>


<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>הוספת פוסטר</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">📥 הוספת פוסטר חדש</h2>

  <form method="post" action="add.php" style="max-width:600px; margin:auto;" dir="rtl">
    <label>🔗 קישור ל־IMDb:</label><br>
    <input type="text" id="imdbUrl" style="width:100%;" placeholder="https://www.imdb.com/title/tt..." required><br>
    <input type="hidden" id="imdb_id" name="imdb_id"><br>
    <button type="button" onclick="fetchFromIMDb()">🕵️‍♂️ שלוף פרטים</button><br><br>

    <label>📌 כותרת באנגלית:</label><br>
    <input type="text" id="title_en" name="title_en" required><br>

    <label>כותרת בעברית:</label><br>
    <input type="text" id="title_he" name="title_he"><br>

    <label>🗓️ שנה:</label><br>
    <input type="text" id="year" name="year"><br>

    <label>📊 דירוג IMDb:</label><br>
    <input type="text" id="imdb_rating" name="imdb_rating"><br>

    <label>🖼️ כתובת תמונה:</label><br>
    <input type="text" id="image_url" name="image_url" required><br>

    <label>📘 תקציר:</label><br>
    <textarea id="plot" name="plot" rows="4" cols="50"></textarea><br>

    <label>🌐 שפת מקור:</label><br>
    <select name="lang_code">
      <option value="">לא ידוע</option>
      <option value="he">🇮🇱 עברית</option>
      <option value="en">🇬🇧 אנגלית</option>
      <option value="fr">🇫🇷 צרפתית</option>
      <option value="ja">🇯🇵 יפנית</option>
      <option value="de">🇩🇪 גרמנית</option>
      <option value="es">🇪🇸 ספרדית</option>
    </select><br>

    <label>🔗 TVDB ID (לסדרות בלבד):</label><br>
    <input type="text" name="tvdb_id"><br>

    <label>🎞️ קישור לטריילר YouTube:</label><br>
    <input type="text" name="youtube_trailer" placeholder="https://www.youtube.com/watch?v=..."><br>

    <label>🎭 ז'אנר:</label><br>
    <input type="text" id="genre" name="genre"><br>

    <label>👥 שחקנים:</label><br>
    <input type="text" id="actors" name="actors"><br>

    <label>סוג הפוסטר:</label><br>
    <select name="type" required>
      <option value="movie">🎬 סרט</option>
      <option value="series">📺 סדרה</option>
    </select><br>

    <label>📝 כתוביות זמינות:</label>
    <input type="checkbox" name="has_subtitles" value="1"><br>

    <label>🎙️ מדובב:</label>
    <input type="checkbox" name="is_dubbed" value="1"><br>

    <label>🏷️ קטגוריות:</label><br>
    <select name="categories[]" multiple style="width:100%;">
      <?php
      $cat_result = $conn->query("SELECT * FROM categories");
      while ($cat = $cat_result->fetch_assoc()):
      ?>
        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
      <?php endwhile; ?>
    </select><br><br>

    <button type="submit">💾 שמור</button>
  </form>

  <hr>
  <h4 align="center">🔧 ניהול תגיות</h4>

  <!-- הוספת תגית חדשה -->
  <form method="post" style="margin-bottom:10px;" align="center">
    <input type="text" name="new_category" placeholder="שם תגית חדשה" required>
    <button type="submit" name="add_category">➕ הוסף</button>
  </form>

  <!-- מחיקת תגיות קיימות -->
  <ul style="list-style:none; padding:0;" align="center">
    <?php
    $cat_list = $conn->query("SELECT * FROM categories");
    while ($cat = $cat_list->fetch_assoc()):
    ?>
      <li>
        <?= htmlspecialchars($cat['name']) ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="delete_category_id" value="<?= $cat['id'] ?>">
          <button type="submit" name="delete_category" onclick="return confirm('למחוק את התגית?')">🗑️</button>
        </form>
      </li>
    <?php endwhile; ?>
  </ul>

  <div style="text-align:center;margin-top:20px;">
    <a href="index.php">⬅ חזרה לרשימת הפוסטרים</a>
  </div>
</body>

</html>

<?php
/*
echo "<pre>tvdb_id: $tvdb_id</pre>";
*/
?>