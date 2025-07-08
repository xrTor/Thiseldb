<?php
$host = 'localhost';
$db = 'media';
$user = 'root';
$pass = '123456';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// טיפול בשליחה
if ($_SERVER["REQUEST_METHOD"] == "POST") {

// הוספת תגית חדשה
if (isset($_POST['add_category']) && !empty($_POST['new_category'])) {
    $name = trim($_POST['new_category']);
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
}

// מחיקת תגית קיימת
if (isset($_POST['delete_category']) && !empty($_POST['delete_category_id'])) {
    $id = intval($_POST['delete_category_id']);
    $conn->query("DELETE FROM poster_categories WHERE category_id = $id"); // מחיקת קשרים
    $conn->query("DELETE FROM categories WHERE id = $id"); // מחיקת התגית
}

    $title_en = $_POST['title_en'] ?? '';
    $title_he = $_POST['title_he'] ?? '';
    $year = $_POST['year'] ?? '';
    $imdb_rating = $_POST['imdb_rating'] ?? '';
    $imdb_link = $_POST['imdb_link'] ?? '';
    $image_url = $_POST['image_url'] ?? '';
    $plot = $_POST['plot'] ?? '';

    $stmt = $conn->prepare("INSERT INTO posters (title_en, title_he, year, imdb_rating, imdb_link, image_url, plot) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $plot);
    $stmt->execute();
    $poster_id = $conn->insert_id;


    $stmt->close();

    // שמירת קטגוריות
    if (!empty($_POST['categories'])) {
        foreach ($_POST['categories'] as $cat_id) {
            $stmt = $conn->prepare("INSERT INTO poster_categories (poster_id, category_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $poster_id, $cat_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<p style='color:green; text-align:center;'>✅ פוסטר נוסף בהצלחה!</p>";
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>הוספת פוסטר</title>
  <link rel="stylesheet" href="style.css">
  <script>
    function fetchFromIMDb() {
      const url = document.getElementById('imdbUrl').value;
      const match = url.match(/tt\d+/);
      if (!match) {
        alert("לא נמצא מזהה IMDb תקף");
        return;
      }
      const imdbId = match[0];
      const apiKey = '1ae9a12e'; // החלף לפי המפתח שלך

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

          } else {
            alert("לא נמצאו נתונים עבור IMDb ID זה");
          }
        })
        .catch(err => {
          console.error(err);
          alert("שגיאה בשליפת נתונים");
        });
    }
  </script>
</head>
<body>
  <h2 style="text-align:center;">📥 הוספת פוסטר חדש</h2>

  <form method="post" action="add.php" style="max-width:500px; margin:auto;">
    <label>🔗 קישור ל־IMDb:</label><br>
    <input type="text" id="imdbUrl" style="width:100%;"><br>
    <button type="button" onclick="fetchFromIMDb()">🕵️‍♂️ שלוף פרטים</button><br><br>

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
    <label>🖼️ כתובת תמונה:</label><br>
    <input type="text" id="image_url" name="image_url" required><br>
    <label>📘 תקציר:</label><br>
    <textarea id="plot" name="plot" rows="4" cols="50"></textarea><br>

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
<h4>🔧 ניהול תגיות</h4>

<!-- הוספת תגית חדשה -->
<form method="post" style="margin-bottom:10px;">
  <input type="text" name="new_category" placeholder="שם תגית חדשה" required>
  <button type="submit" name="add_category">➕ הוסף</button>
</form>

<!-- מחיקת תגיות קיימות -->
<ul style="list-style:none; padding:0;">
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
