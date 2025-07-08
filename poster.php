<?php
include 'header.php';

$host = 'localhost';
$db = 'media';
$user = 'root';
$pass = '123456';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$result = $conn->query("SELECT * FROM posters WHERE id = $id");

if ($result->num_rows == 0) {
    echo "<p style='text-align:center;'>😢 פוסטר לא נמצא</p>";
    exit;
}

$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($row['title_en']) ?></title>
  <link rel="stylesheet" href="style.css">
  <style>
    .poster-page {
      max-width: 800px;
      margin: 30px auto;
          background-image: url("wbg.png") !important;
      /*       background: #fff; */
      padding: 20px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      border-radius: 10px;
    }
    .poster-image {
      width: 200px;
      float: right;
      margin-left: 20px;
      border-radius: 1px;
    }
    .poster-details {
      overflow: hidden;
    }
    .poster-tags {
      margin-top: 10px;
    }
    .tag {
      background: #eee;
      padding: 5px 10px;
      margin: 5px;
      display: inline-block;
      border-radius: 4px;
      font-size: 13px;
    }
    .actions {
      margin-top: 20px;
      text-align: center;
    }
    .actions a {
      color: #2d89ef;
      text-decoration: none;
      margin: 0 10px;
    }
  </style>
</head>
<body class="rtl">

<div class="poster-page rtl">
  <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster" class="poster-image rtl">
  <div class="poster-details rtl">
    <h2>
      <?= htmlspecialchars($row['title_en']) ?>
      <?php if (!empty($row['title_he'])): ?>
        <br><?= htmlspecialchars($row['title_he']) ?>

        
      <?php endif; ?>
    </h2>
<?php if (!empty($row['is_dubbed']) || !empty($row['has_subtitles'])): ?>
  <p style="margin-top:6px; font-size:14px;">
    <?php if (!empty($row['is_dubbed'])): ?>
      <span>
        <img src="hebdub.svg" class="bookmark">מדובב
    </span>
      <br>
    <?php endif; ?>
    <?php if (!empty($row['has_subtitles'])): ?>
      📝 כולל כתוביות
    <?php endif; ?>
  </p>
<?php endif; ?>


    <p><strong>🗓️ שנה:</strong> <?= htmlspecialchars($row['year']) ?></p>
    <p><strong>⭐ דירוג IMDb:</strong>
      <?= !empty($row['imdb_rating']) ? htmlspecialchars($row['imdb_rating']) . ' / 10' : 'לא זמין' ?>
    </p>

<?php if (!empty($row['plot'])): ?>
  <p><strong>📘 תקציר:</strong><br><?= htmlspecialchars($row['plot']) ?></p>
<?php endif; ?>



<?php if (!empty($row['genre'])):
  $genres = explode(',', $row['genre']); ?>
  <p><strong>🎭 ז'אנר:</strong><br>
    <?php foreach ($genres as $g): 
      $g_clean = trim($g); ?>
      <a href="genre.php?name=<?= urlencode($g_clean) ?>" class="tag"><?= htmlspecialchars($g_clean) ?></a>
    <?php endforeach; ?>
  </p>
<?php endif; ?>


<?php
/*
<pre>
<?= print_r($row, true); ?>
</pre>
*/
?>

<?php if (!empty($row['actors'])):
  $actors = explode(',', $row['actors']); ?>
  <p><strong>👥 שחקנים:</strong><br>
    <?php foreach ($actors as $a): 
      $a_clean = trim($a); ?>
      <a href="actor.php?name=<?= urlencode($a_clean) ?>" class="tag"><?= htmlspecialchars($a_clean) ?></a>
    <?php endforeach; ?>
  </p>
<?php endif; ?>


    
    <?php if (!empty($row['imdb_link'])): ?>
      <p><strong>🔗 IMDb:</strong>
        <a href="<?= htmlspecialchars($row['imdb_link']) ?>" target="_blank">מעבר לקישור</a>
      </p>

      
    <?php endif; ?>

    <!-- תגיות -->
    <div class="poster-tags">
      <strong>🏷️ תגיות:</strong><br>
      <?php
      $cat_result = $conn->query("SELECT c.name FROM categories c
        JOIN poster_categories pc ON c.id = pc.category_id
        WHERE pc.poster_id = $id");
      if ($cat_result->num_rows > 0) {
        while ($cat = $cat_result->fetch_assoc()) {
          echo "<span class='tag'>" . htmlspecialchars($cat['name']) . "</span> ";
        }
      } else {
        echo "<span style='color:#999;'>אין תגיות</span>";
      }
      ?>
    </div>

    <div class="actions">
      <a href="edit.php?id=<?= $row['id'] ?>">✏️ ערוך</a> |
      <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('למחוק את הפוסטר?')">🗑️ מחק</a> |
      <a href="index.php">⬅ חזרה</a>
    </div>
<?php if (!empty($row['youtube_trailer'])):
  // חילוץ מזהה הסרטון
  parse_str(parse_url($row['youtube_trailer'], PHP_URL_QUERY), $yt_params);
  $video_id = $yt_params['v'] ?? '';
  if ($video_id):
?>
  <div style="margin-top:20px; text-align:center;">
    <iframe width="100%" height="315" 
      src="https://www.youtube.com/embed/<?= htmlspecialchars($video_id) ?>" 
      frameborder="0" allowfullscreen></iframe>
  </div>
<?php endif; endif; ?>


  </div>
</div>

</body>
</html>

<?php include 'footer.php'; ?>

<?php
/*
<?php $conn->close(); ?>
<pre><?php print_r($row); ?></pre>
*/
?>