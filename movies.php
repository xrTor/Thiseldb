<?php
include 'header.php';
$conn = new mysqli('localhost','root','123456','media');
if ($conn->connect_error) die("Connection failed");

// תנאים לסינון
$where = ["type = 'movie'"];
$params = [];
$types = '';

if (!empty($_GET['year'])) {
  $where[] = "year LIKE ?";
  $params[] = '%' . $_GET['year'] . '%';
  $types .= 's';
}

if (!empty($_GET['min_rating'])) {
  $where[] = "CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) >= ?";
  $params[] = floatval($_GET['min_rating']);
  $types .= 'd';
}

if (!empty($_GET['lang'])) {
  $where[] = "lang_code = ?";
  $params[] = $_GET['lang'];
  $types .= 's';
}

$sql = "SELECT * FROM posters WHERE " . implode(" AND ", $where) . " ORDER BY year DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🎬 סרטים | Thiseldb</title>
  <style>
    body { font-family: sans-serif; background:#f9f9f9; }
    h1 { text-align:center; }
    .filter { margin-bottom:20px; text-align:center; }
    input, select { padding:6px; margin:0 4px; }
    .poster-wall { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
    .poster { width: 200px; background: #fff; border: 1px solid #ddd; padding: 10px;  box-shadow: 0 0 5px rgba(0,0,0,0.05); text-align: center; }
    .poster img { width: 100%; }
    .lang-icon { margin-top: 4px; font-size: 18px; }
    .rating { font-size: 14px; margin-top:6px; color:#666; }
  </style>
</head>
<body>

<h1>🎬 רשימת סרטים</h1>

<div class="filter">
  <form method="get">
    🗓️ שנה: <input type="text" name="year" value="<?= htmlspecialchars($_GET['year'] ?? '') ?>" style="width:80px;">
    ⭐ דירוג מ־: <input type="text" name="min_rating" value="<?= htmlspecialchars($_GET['min_rating'] ?? '') ?>" style="width:60px;">
    🌍 שפה:
    <select name="lang">
      <option value="">-- כל השפות --</option>
      <option value="en" <?= ($_GET['lang'] ?? '') == 'en' ? 'selected' : '' ?>>🇬🇧 אנגלית</option>
      <option value="he" <?= ($_GET['lang'] ?? '') == 'he' ? 'selected' : '' ?>>🇮🇱 עברית</option>
      <option value="fr" <?= ($_GET['lang'] ?? '') == 'fr' ? 'selected' : '' ?>>🇫🇷 צרפתית</option>
      <option value="es" <?= ($_GET['lang'] ?? '') == 'es' ? 'selected' : '' ?>>🇪🇸 ספרדית</option>
      <!-- אפשר להרחיב -->
    </select>
    <button type="submit">🔍 סנן</button>
    <a href="movies.php">🔄 איפוס</a>
  </form>
</div>

<div class="poster-wall">
  <?php if (empty($rows)): ?>
    <p>😢 לא נמצאו סרטים לפי הסינון</p>
  <?php else: ?>
    <?php foreach ($rows as $row): ?>
      <div class="poster">
        <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="">
        <div><b><?= htmlspecialchars($row['title_en']) ?></b></div>
        <div><?= htmlspecialchars($row['year']) ?></div>

        <div class="lang-icon">
          <?php
          $lang_icons = ['en'=>'🇬🇧','he'=>'🇮🇱','fr'=>'🇫🇷','es'=>'🇪🇸','ja'=>'🇯🇵','de'=>'🇩🇪'];
          $lang = $row['lang_code'] ?? '';
          echo $lang_icons[$lang] ?? '🌐';
          ?>
        </div>

        <div class="rating">
          ⭐ <?= $row['imdb_rating'] ?? '—' ?> / 10
        </div>

        <div style="margin-top:8px;">
          <a href="poster.php?id=<?= $row['id'] ?>">📄 לפרטים</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<p style="text-align:center; margin-top:30px;">
  <a href="index.php">⬅ חזרה לכל הפוסטרים</a>
</p>

</body>
</html>

<?php $stmt->close(); $conn->close(); ?>
<?php include 'footer.php'; ?>