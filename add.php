<?php
include 'header.php';
require_once 'imdb.class.php';
require_once 'functions.php';

$host = 'localhost';
$db = 'media';
$user = 'root';
$pass = '123456';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🎬 הוספת פוסטר
if ($_SERVER["REQUEST_METHOD"] == "POST" 
    && !isset($_POST['add_category']) 
    && !isset($_POST['delete_category'])) {

    $title_en         = $_POST['title_en'] ?? '';
    $title_he         = $_POST['title_he'] ?? '';
    $year             = $_POST['year'] ?? '';
    $imdb_rating      = $_POST['imdb_rating'] ?? '';
    $imdb_link        = $_POST['imdb_link'] ?? '';
    $image_url        = $_POST['image_url'] ?? '';
    $plot             = $_POST['plot'] ?? '';
    $type             = $_POST['type'] ?? 'movie';
    $tvdb_id          = $_POST['tvdb_id'] ?? '';
    $genre            = $_POST['genre'] ?? '';
    $actors           = $_POST['actors'] ?? '';
    $youtube_trailer  = $_POST['youtube_trailer'] ?? '';

    // בדוק אם IMDb כבר קיים במסד
$check = $conn->prepare("SELECT id FROM posters WHERE imdb_link = ?");
$check->bind_param("s", $imdb_link);
/*

$check = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
$check->bind_param("s", $imdb_id);
*/


$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo "<p style='color:orange; text-align:center;'>⚠️ פוסטר עם IMDb הזה כבר קיים</p>";
return;

  } else {
    // שמירה כרגיל
    $stmt = $conn->prepare("INSERT INTO posters 
    (title_en, title_he, year, imdb_rating, imdb_link, image_url, plot, type, tvdb_id, genre, actors, youtube_trailer, has_subtitles, is_dubbed, lang_code) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
$has_subtitles = isset($_POST['has_subtitles']) ? 1 : 0;
$is_dubbed     = isset($_POST['is_dubbed'])     ? 1 : 0;
$lang_code     = $_POST['lang_code'] ?? '';

    $stmt->bind_param("sssssssssssiiis",
      $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $plot,
      $type, $tvdb_id, $genre, $actors, $youtube_trailer, $has_subtitles, $is_dubbed, $lang_code);

      
    $stmt->execute();
    $poster_id = $conn->insert_id;
    $stmt->close();

    echo "<p style='color:green; text-align:center;'>✅ הפוסטר נשמר בהצלחה!</p>";
}

$check->close();


    // 🏷️ שמירת תגיות
    if (!empty($_POST['categories'])) {
        foreach ($_POST['categories'] as $cat_id) {
            $stmt = $conn->prepare("INSERT INTO poster_categories (poster_id, category_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $poster_id, intval($cat_id));
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<p style='color:green; text-align:center;'>✅ הפוסטר נשמר בהצלחה!</p>";

    if ($type === 'series' && !empty($tvdb_id)) {
        echo "<div style='text-align:center;'>
          🌐 <a href='https://thetvdb.com/series/" . htmlspecialchars($tvdb_id) . "' target='_blank'>TVDB</a>
        </div>";
    }
}


?>
<?php
// ➕ הוספת תגית
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category']) && !empty($_POST['new_category'])) {
    $name = trim($_POST['new_category']);
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
}

// 🗑️ מחיקת תגית
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_category']) && !empty($_POST['delete_category_id'])) {
    $id = intval($_POST['delete_category_id']);
    $conn->query("DELETE FROM poster_categories WHERE category_id = $id");
    $conn->query("DELETE FROM categories WHERE id = $id");
}

$genre           = $_POST['genre'] ?? '';
$actors          = $_POST['actors'] ?? '';
$youtube_trailer = $_POST['youtube_trailer'] ?? '';

$is_dubbed = isset($_POST['is_dubbed']) ? 1 : 0;

$lang_code = $_POST['lang_code'] ?? '';

?>

<script>
  function checkIfExists(imdbId) {
  return fetch('check_imdb.php?imdb=' + imdbId)
    .then(res => res.text())
    .then(result => result === 'true');
}

document.querySelector('form').addEventListener('submit', async function(e) {
  const imdbUrl = document.getElementById('imdbUrl').value.trim();
  const match = imdbUrl.match(/tt\d+/);
  if (!match) return;

  const imdbId = match[0];
  const exists = await checkIfExists(imdbId);

  if (exists) {
    e.preventDefault();
    alert("⚠️ פוסטר עם IMDb הזה כבר קיים!");
  }
});
</script>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>הוספת פוסטר</title>
  <link rel="stylesheet" href="style.css">
  
 <script>
function fetchFromIMDb() {
  const url = document.getElementById('imdbUrl').value.trim();
  const match = url.match(/tt\d+/);
  if (!match) return;

  const imdbId = match[0];
  const apiKey = '1ae9a12e'; // החלף למפתח תקף אם צריך

  fetch(`https://www.omdbapi.com/?i=${imdbId}&apikey=${apiKey}`)
    .then(res => res.json())
    .then(data => {
      if (data.Response === "True") {
        document.getElementById('title_en').value = data.Title || '';
        document.getElementById('year').value = data.Year || '';
        document.getElementById('imdb_rating').value = data.imdbRating || '';
        document.getElementById('image_url').value = data.Poster || '';
        document.getElementById('plot').value = data.Plot || '';
        document.getElementById('imdb_link').value = `https://www.imdb.com/title/${imdbId}/`;
document.getElementById('genre').value = data.Genre || '';
document.getElementById('actors').value = data.Actors || '';



        // תצוגה חיה
        const image = document.getElementById('previewImage');
        const plot = document.getElementById('previewPlot');

        if (data.Poster) {
          image.src = data.Poster;
          image.style.display = 'block';
        } else {
          image.style.display = 'none';
        }

        plot.textContent = data.Plot || '';
      } else {
        alert("לא נמצאו נתונים עבור IMDb ID זה");
      }
    })
    .catch(err => {
      console.error("שגיאה ב־OMDb API:", err);
      alert("שגיאה בשליפת נתונים");
    });
}

// הפעלה אוטומטית
document.addEventListener("DOMContentLoaded", () => {
  const imdbField = document.getElementById('imdbUrl');
  imdbField.addEventListener('change', fetchFromIMDb);
  imdbField.addEventListener('blur', fetchFromIMDb);
});
</script>


</head>
<body>
  <h2 style="text-align:center;">📥 הוספת פוסטר חדש</h2>


  <form method="post" action="add.php" style="max-width:500px; margin:auto;">



    <label>🔗 קישור ל־IMDb:</label><br>
    <input type="text" id="imdbUrl" style="width:100%;"><br>
    <button type="button" onclick="fetchFromIMDb()">🕵️‍♂️ שלוף פרטים</button><br><br>

    <label>סוג הפוסטר:</label><br>
<select name="type" required>
  <option value="movie">🎬 סרט</option>
  <option value="series">📺 סדרה</option>
</select><br>

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

    <label>📌 כותרת באנגלית:</label><br>
    <input type="text" id="title_en" name="title_en" required><br>
    <label>כותרת בעברית:</label><br>
    <input type="text" id="title_he" name="title_he"><br>
    <label>🗓️ שנה:</label><br>
    <input type="text" id="year" name="year"><br>
    <label>📊 דירוג IMDb:</label><br>
    <input type="text" id="imdb_rating" name="imdb_rating"><br>
    <label>קישור ל-IMDb:</label><br>
    <input type="text" id="imdb_link" name="imdb_link"><br>
    <label>🎞️ קישור לטריילר YouTube:</label><br>
    <input type="text" name="youtube_trailer" placeholder=""><br>
   
    <label>🖼️ כתובת תמונה:</label><br>
    <input type="text" id="image_url" name="image_url" required><br>

    <label>📝 יש כתוביות?</label><br>
<input type="checkbox" name="has_subtitles" value="1"><br>

<label>      <span>
        <img src="hebdub.svg" class="bookmark">
    </span> מדובב?</label><br>
<input type="checkbox" name="is_dubbed" value="1"><br>


   
    <label>📘 תקציר:</label><br>
    <textarea id="plot" name="plot" rows="4" cols="50"></textarea><br>
    <label>🎭 ז'אנר:</label><br>
<input type="text" id="genre" name="genre"><br>

<label>👥 שחקנים:</label><br>
<input type="text" id="actors" name="actors"><br>


    <div id="preview" style="margin-top:20px; text-align:center;">
  <img id="previewImage" src="" alt="" style="max-width:250px; display:none; border-radius:6px;"><br>
  <div id="previewPlot" style="margin-top:10px; font-size:14px; line-height:1.6;"></div>
</div>


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
<?php include 'footer.php'; ?>
<?php
/*
echo "<pre>tvdb_id: $tvdb_id</pre>";
*/
?>