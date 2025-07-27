<?php
require_once 'server.php';
include 'header.php';

// פונקציה לזיהוי מזהה IMDb מתוך מזהה או קישור
function extractImdbId($input) {
  if (preg_match('/tt\d{7,8}/', $input, $matches)) {
    return $matches[0];
  }
  return $input;
}

$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);
$keyword = extractImdbId($keyword);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= empty($keyword) ? 'חיפוש פוסטרים' : 'תוצאות עבור ' . htmlspecialchars($keyword) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <style>
    .card {
      width: 200px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      text-align: center;
      padding: 10px;
      margin: 10px;
      transition: transform 0.2s ease;
    }
    .card:hover {
      transform: scale(1.05);
    }
    .card img {
      width: 100%;
      border-radius: 6px;
    }
    .results {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
    }
    .search-container {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-top: 15px;
    }
    .search-container input[type="text"] {
      width: 220px;
      padding: 8px;
    }
  </style>
</head>
<body>


<!-- טופס חיפוש שתמיד מופיע 
 <div class="" style="text-align:center;">
  <form method="get" action="search.php" class="search-container">
    <input type="text" name="q" placeholder="🔎 הקלד מילה או לינק ל-IMDb">
    <button type="submit">🔍 חפש</button>
  </form>
</div>

<div class="" style="text-align:center;">
  <form method="get" action="search.php" class="search-container">
    <input type="text" name="q" placeholder="🔎 הקלד מילה או לינק ל-IMDb" class="">
    <button type="submit" class="w3-button w3-blue">🔍 חפש</button>
  </form>
</div>
-->
<h2 class="w3-center">
  <?= empty($keyword) ? '🔍 חיפוש פוסטרים' : '🔍 תוצאות עבור: ' . htmlspecialchars($keyword) ?>
</h2>

<?php
if (!empty($keyword)) {
  $like = "%$keyword%";
  $stmt = $conn->prepare("
    SELECT * FROM posters 
    WHERE title_en LIKE ? 
    OR title_he LIKE ? 
    OR plot LIKE ? 
    OR genre LIKE ? 
    OR actors LIKE ? 
    OR imdb_id LIKE ?
  ");
  $stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0): ?>
    <div class="results">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster">
            <p><?= htmlspecialchars($row['title_en']) ?></p>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p class="w3-center">😢 לא נמצאו תוצאות עבור "<?= htmlspecialchars($keyword) ?>"</p>
  <?php endif;

  $stmt->close();
}

$conn->close();
?>

</body>
</html>
<?php include 'footer.php'; ?>