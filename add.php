<?php
require_once 'header.php';
require_once 'functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// חיבור למסד
 require_once 'server.php';

 // שליפת סוגים כולל קוד
$types_result = $conn->query("SELECT id, label_he, icon, code FROM poster_types ORDER BY sort_order");
$default_code = 'movie'; // ברירת מחדל: סרט


$message = '';
$poster_id = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // קבלת נתונים
  $title_en         = trim($_POST['title_en'] ?? '');
  $title_he         = trim($_POST['title_he'] ?? '');
  $year             = trim($_POST['year'] ?? '');
  $lang_code        = $_POST['lang_code'] ?? (count($_POST['languages'] ?? []) === 1 ? $_POST['languages'][0] : 'en');
  $imdb_id          = trim($_POST['imdb_id'] ?? '');
  $imdb_rating      = trim($_POST['imdb_rating'] ?? '');
  $imdb_link        = trim($_POST['imdb_link'] ?? '');
  $image_url        = trim($_POST['image_url'] ?? '');
  $plot             = trim($_POST['plot'] ?? '');
  $type             = intval($_POST['type_id']) ?? 'movie';
  $tvdb_id          = trim($_POST['tvdb_id'] ?? '');
  $genre            = trim($_POST['genre'] ?? '');
  $actors           = trim($_POST['actors'] ?? '');
  $youtube_trailer  = trim($_POST['youtube_trailer'] ?? '');
  $has_subtitles    = isset($_POST['has_subtitles']) ? 1 : 0;
  $is_dubbed        = isset($_POST['is_dubbed']) ? 1 : 0;
  $metacritic_score = trim($_POST['metacritic_score'] ?? '');
  $rt_score         = trim($_POST['rt_score'] ?? '');
  $metacritic_link  = trim($_POST['metacritic_link'] ?? '');
  $rt_link          = trim($_POST['rt_link'] ?? '');
  $languages_posted = $_POST['languages'] ?? [];
  $categories       = $_POST['categories'] ?? [];

  if (empty($imdb_id) && preg_match('/tt\d+/', $imdb_link, $m)) {
    $imdb_id = $m[0];
  }

  if ($youtube_trailer === '0' || strlen($youtube_trailer) < 10 ||
      !preg_match('/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_trailer)) {
    $youtube_trailer = '';
  }

  if (strpos($youtube_trailer, 'youtu') !== false && strpos($youtube_trailer, 'http') === false) {
    $youtube_trailer = 'https://' . $youtube_trailer;
  }

  if (!empty($imdb_id)) {
    $check = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
    $check->bind_param("s", $imdb_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $message = "⚠️ הפוסטר כבר קיים במסד";
    }
    $check->close();
  }

  if (empty($message)) {
    $stmt = $conn->prepare("INSERT INTO posters 
      (title_en, title_he, year, imdb_rating, imdb_link, image_url, plot, type_id,
       tvdb_id, genre, actors, youtube_trailer, has_subtitles, is_dubbed, lang_code,
       imdb_id, metacritic_score, rt_score, metacritic_link, rt_link)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssssssisssssss",
      $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $plot, $type,
      $tvdb_id, $genre, $actors, $youtube_trailer, $has_subtitles, $is_dubbed,
      $lang_code, $imdb_id, $metacritic_score, $rt_score, $metacritic_link, $rt_link
    );

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      $poster_id = $conn->insert_id;
      foreach ($languages_posted as $code) {
        $lang_stmt = $conn->prepare("INSERT INTO poster_languages (poster_id, lang_code) VALUES (?, ?)");
        $lang_stmt->bind_param("is", $poster_id, $code);
        $lang_stmt->execute();
        $lang_stmt->close();
      }
      foreach ($categories as $cat_id) {
        $cat_stmt = $conn->prepare("INSERT INTO poster_categories (poster_id, category_id) VALUES (?, ?)");
        $cat_stmt->bind_param("ii", $poster_id, intval($cat_id));
        $cat_stmt->execute();
        $cat_stmt->close();
      }
      $message = "✅ הפוסטר נשמר בהצלחה (ID: $poster_id) — <a href='poster.php?id=$poster_id'>לצפייה בפוסטר</a>";
    } else {
      $message = "❌ שמירת הפוסטר נכשלה: " . $stmt->error;
    }
    $stmt->close();
  }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>📥 הוספת פוסטר</title>
  <link rel="stylesheet" href="style-add.css">
  
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const imdbInput = document.querySelector("[name='imdb_link']");
    const imageUrlInput = document.querySelector("[name='image_url']");
    const trailerInput = document.querySelector("[name='youtube_trailer']");
    const posterPreview = document.getElementById("posterPreview");
    const trailerPreview = document.getElementById("trailerPreview");

    function updatePosterPreview(url) {
      if (!url) return posterPreview.style.display = 'none';
      posterPreview.innerHTML = `<img src="${url}" style="max-width:100%; border-radius:6px;">`;
      posterPreview.style.display = 'block';
    }

    function updateTrailerPreview(url) {
      const match = url.match(/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
      const videoId = match ? match[1] : null;
      if (!videoId) return trailerPreview.style.display = 'none';
      trailerPreview.innerHTML = `<iframe width="100%" height="300" src="https://www.youtube.com/embed/${videoId}" frameborder="0" allowfullscreen></iframe>`;
      trailerPreview.style.display = 'block';
    }

    function fetchDetails(imdbId) {
      fetch(`https://www.omdbapi.com/?i=${imdbId}&apikey=1ae9a12e`)
        .then(r => r.json())
        .then(data => {
          if (data.Response !== "True") return alert("❌ IMDb לא מצא פרטים.");
          document.querySelector("[name='title_en']").value = data.Title || '';
          document.querySelector("[name='year']").value = data.Year || '';
          document.querySelector("[name='imdb_rating']").value = data.imdbRating || '';
          document.querySelector("[name='image_url']").value = data.Poster || '';
          document.querySelector("[name='plot']").value = data.Plot || '';
          document.querySelector("[name='genre']").value = data.Genre || '';
          document.querySelector("[name='actors']").value = data.Actors || '';
          document.querySelector("[name='imdb_id']").value = imdbId;
          updatePosterPreview(data.Poster || '');
        });
    }

    window.fetchFromIMDb = function () {
      const match = imdbInput.value.trim().match(/tt\d+/);
      if (!match) return alert("❌ מזהה IMDb לא תקין.");
      fetchDetails(match[0]);
    };


    imageUrlInput.addEventListener("input", () => updatePosterPreview(imageUrlInput.value.trim()));
    trailerInput.addEventListener("input", () => updateTrailerPreview(trailerInput.value.trim()));
  });
</script>

</head>
<body>

<h2>📥 הוספת פוסטר חדש</h2>

<?php if (!empty($message)): ?>
  <div style="padding:10px; background:#e0f7ff; border:1px solid #00a; border-radius:6px; margin:10px auto; max-width:700px;">
    <?= $message ?>
  </div>
<?php endif; ?>

<form method="post" action="add.php">
  <label>🔗 קישור ל־IMDb:</label>
  <input type="text" name="imdb_link"><br><br>
  <button type="button" onclick="fetchFromIMDb()">🕵️‍♂️ שלוף פרטים</button>

 
  <label>🎞️ סוג הפוסטר:</label>
<select name="type_id" required>
<?php while($t = $types_result->fetch_assoc()):
  $selected = ($t['code'] === $default_code) ? 'selected' : '';
  $icon = htmlspecialchars($t['icon']);
  $label = htmlspecialchars($t['label_he']);
?>
  <option value="<?= $t['id'] ?>" <?= $selected ?>><?= "$icon $label" ?></option>
<?php endwhile; ?>
</select>

  <label>כותרת באנגלית:</label><input type="text" name="title_en">
  <label>כותרת בעברית:</label><input type="text" name="title_he">
  <label>🗓️ שנה:</label><input type="text" name="year">
  <label>🎯 דירוג IMDb:</label><input type="text" name="imdb_rating">
  <label>🖼️ כתובת תמונה:</label><input type="text" name="image_url">
  <div id="posterPreview"></div>
  <label>📘 תקציר:</label><textarea name="plot" rows="3"></textarea>
  <label>🎭 ז'אנר:</label><input type="text" name="genre">
  <label>👥 שחקנים:</label><input type="text" name="actors">
  <label>🔗 TVDB ID:</label><input type="text" name="tvdb_id">
  <label>🎞️ טריילר YouTube:</label><input type="text" name="youtube_trailer">
  <div id="trailerPreview"></div>

  <label>📝 כתוביות:</label><input type="checkbox" name="has_subtitles" value="1">
  <label>🎙️ דיבוב:</label><input type="checkbox" name="is_dubbed" value="1">
  <label>📊 Metacritic:</label><input type="text" name="metacritic_score">
  <label>🍅 Rotten Tomatoes:</label><input type="text" name="rt_score">
  <label>🔗 קישור Metacritic:</label><input type="text" name="metacritic_link">
  <label>🔗 קישור RT:</label><input type="text" name="rt_link">
  <label>🔤 IMDb ID:</label><input type="text" name="imdb_id">

<div style="
  all: unset;
  display: block;
  direction: ltr;
  text-align: left;
  font-family: Calibri, sans-serif;
  font-size: 13px;
  font-weight: normal;
">
  <?php include 'flags.php'; ?>

  
</div>

  <br>
  <button type="submit">💾 שמור פוסטר</button>
</form>
   
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const imdbInput = document.querySelector("[name='imdb_link']");
    let debounceTimer;

    function fetchDetails(imdbId) {
      fetch(`https://www.omdbapi.com/?i=${imdbId}&apikey=1ae9a12e`)
        .then(res => res.json())
        .then(data => {
          if (data.Response !== "True") return alert("❌ IMDb לא החזיר תוצאה תקפה");

          document.querySelector("[name='title_en']").value    = data.Title || '';
          document.querySelector("[name='year']").value        = data.Year || '';
          document.querySelector("[name='imdb_rating']").value = data.imdbRating || '';
          document.querySelector("[name='image_url']").value   = data.Poster || '';
          document.querySelector("[name='plot']").value        = data.Plot || '';
          document.querySelector("[name='genre']").value       = data.Genre || '';
          document.querySelector("[name='actors']").value      = data.Actors || '';
          document.querySelector("[name='imdb_id']").value     = imdbId;

          const posterPreview = document.getElementById("posterPreview");
          posterPreview.innerHTML = data.Poster
            ? `<img src="${data.Poster}" style="max-width:100%; border-radius:6px;">`
            : '';
          posterPreview.style.display = data.Poster ? 'block' : 'none';
        })
        .catch(() => alert("❌ שגיאת רשת מול OMDb"));
    }

    // ⏱️ שליפה אוטומטית אם שדה כבר מכיל מזהה
    const existingMatch = imdbInput.value.trim().match(/tt\d+/);
    if (existingMatch) fetchDetails(existingMatch[0]);

    // 🖊️ השלמה בזמן הקלדה
    imdbInput.addEventListener("input", () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        const match = imdbInput.value.trim().match(/tt\d+/);
        if (match) fetchDetails(match[0]);
      }, 500);
    });

    // 🕵️ כפתור שליפה ידני
    window.fetchFromIMDb = function () {
      const match = imdbInput.value.trim().match(/tt\d+/);
      if (!match) return alert("❌ מזהה IMDb לא תקין.");
      fetchDetails(match[0]);
    };
  });
</script>


</body>
</html>

<?php include 'footer.php'; ?>