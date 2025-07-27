<?php
require_once 'server.php';

$type      = $_GET['type']      ?? '';
$year      = $_GET['year']      ?? '';
$genre     = $_GET['genre']     ?? '';
$subtitles = $_GET['subtitles'] ?? '';
$dubbed    = $_GET['dubbed']    ?? '';

$where = ["imdb_rating IS NOT NULL", "imdb_rating != ''"];
$params = []; $types = '';

if ($type) {
  $where[] = "type = ?";
  $params[] = $type;
  $types .= 's';
}
if ($year) {
  $where[] = "year = ?";
  $params[] = $year;
  $types .= 's';
}
if ($genre) {
  $where[] = "genre LIKE ?";
  $params[] = "%$genre%";
  $types .= 's';
}
if ($subtitles) $where[] = "has_subtitles = 1";
if ($dubbed)    $where[] = "is_dubbed = 1";

$sql = "SELECT * FROM posters";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC LIMIT 10";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
?>

<?php include 'header.php'; ?>

<style>
.top10-wrapper {
  max-width: 1000px;
  margin: 50px auto;
  padding: 20px;
  font-family: sans-serif;
}
.top10-wrapper h2 {
  text-align: center;
  font-size: 24px;
  margin-bottom: 30px;
}
.top10-wrapper form {
  text-align: center;
  margin-bottom: 30px;
}
.top10-wrapper form input,
.top10-wrapper form select {
  padding: 6px;
  margin: 6px;
  font-size: 14px;
}
.top-poster {
  display: flex;
  align-items: center;
  gap: 20px;
  background: #fff;
  padding: 12px;
  margin-bottom: 16px;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.top-rank {
  font-size: 26px;
  font-weight: bold;
  color: #888;
  width: 50px;
  text-align: center;
}
.top-img {
  height: 100px;
  border-radius: 1px;
  object-fit: cover;
}
.top-details {
  text-align: right;
  font-size: 16px;
  flex: 1;
}
.top-title {
  color: #467AFC !important; /* #5CABED */
  font-weight: bold;
  text-decoration: none;
  font-size: 16px;
}
.top-title:hover {
  text-decoration: underline;
}
.top-link {
  color: #007bff;
  font-size: 14px;
  text-decoration: none;
}
.top-link:hover {
  text-decoration: underline;
}
.imdb-link {
  color: #E6B91E;
  font-weight: bold;
  text-decoration: none;
}
.imdb-link:hover {
  text-decoration: underline;
}
</style>

<div class="top10-wrapper">
  <h2>🏆 10 הפוסטרים עם הדירוג הגבוה ביותר לפי IMDb</h2>

  <form method="get" action="top.php">
    <select name="type">
      <option value="">כל הסוגים</option>
      <option value="movie" <?= $type == 'movie' ? 'selected' : '' ?>>🎬 סרטים</option>
      <option value="series" <?= $type == 'series' ? 'selected' : '' ?>>📺 סדרות</option>
      <option value="short" <?= $type == 'short' ? 'selected' : '' ?>>🎞 קצר</option>
      <option value="miniseries" <?= $type == 'miniseries' ? 'selected' : '' ?>>📺 מיני־סדרה</option>
    </select>

    <input type="text" name="year" value="<?= htmlspecialchars($year) ?>" placeholder="🗓 שנה">
    <input type="text" name="genre" value="<?= htmlspecialchars($genre) ?>" placeholder="🎭 ז׳אנר">
    <label><input type="checkbox" name="subtitles" value="1" <?= $subtitles ? 'checked' : '' ?>> כתוביות</label>
    <label><input type="checkbox" name="dubbed" value="1" <?= $dubbed ? 'checked' : '' ?>> מדובבים</label>
    <button type="submit">📊 הצג</button>
    <a href="top.php" style="margin-right: 10px;">🔄 איפוס</a>
  </form>

  <?php $index = 1; ?>
  <?php while ($row = $res->fetch_assoc()): ?>
    <div class="top-poster">
      <div class="top-rank">#<?= $index ?></div>
      <a href="poster.php?id=<?= $row['id'] ?>">
        <img src="<?= htmlspecialchars($row['image_url']) ?>" class="top-img" alt="<?= htmlspecialchars($row['title_en']) ?>">
      </a>
      <div class="top-details">
        <a href="poster.php?id=<?= $row['id'] ?>" class="top-title">
          <?= htmlspecialchars($row['title_en']) ?>
        </a>
        <?php if (!empty($row['title_he'])): ?>
          <br><span style="font-size:15px; color:#666;">
            <?= htmlspecialchars($row['title_he']) ?>
          </span>
        <?php endif; ?>
        <br>
        <span>🗓 <?= $row['year'] ?>
          <?php if (!empty($row['imdb_link'])): ?>
            <a href="<?= htmlspecialchars($row['imdb_link']) ?>" target="_blank" class="imdb-link"> ⭐
              <?= htmlspecialchars($row['imdb_rating']) ?> / 10
              <img src="IMDb.png" alt="IMDb" style="height:18px; vertical-align:middle; margin-left:3px;">
           </a>
          <?php else: ?>
            <?= htmlspecialchars($row['imdb_rating']) ?> / 10
          <?php endif; ?>
        </span><br>
        <a href="poster.php?id=<?= $row['id'] ?>" class="top-link">📄 לפרטים</a>
      </div>
    </div>
    <?php $index++; ?>
  <?php endwhile; ?>
</div>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>
