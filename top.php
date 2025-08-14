<?php
require_once 'server.php';

// שליפת סוגים מה-DB (icon+label_he)
$types = [];
$res_types = $conn->query("SELECT * FROM poster_types ORDER BY id");
while ($t = $res_types->fetch_assoc()) $types[] = $t;

$type_id   = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;
$year      = $_GET['year']      ?? '';
$genre     = $_GET['genre']     ?? '';
$subtitles = $_GET['subtitles'] ?? '';
$dubbed    = $_GET['dubbed']    ?? '';
$limits = [10, 20, 50, 100, 250];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limits) ? (int)$_GET['limit'] : 10;

// WHERE
$where = ["imdb_rating IS NOT NULL", "imdb_rating != ''"];
$params = []; $bind_types = '';

if ($type_id) {
  $where[] = "type_id = ?";
  $params[] = $type_id;
  $bind_types .= 'i';
}
if ($year) {
  $where[] = "year = ?";
  $params[] = $year;
  $bind_types .= 's';
}
if ($genre) {
  $where[] = "genre LIKE ?";
  $params[] = "%$genre%";
  $bind_types .= 's';
}
if ($subtitles) $where[] = "has_subtitles = 1";
if ($dubbed)    $where[] = "is_dubbed = 1";

$sql = "SELECT * FROM posters";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC LIMIT $limit";

$stmt = $conn->prepare($sql);
if ($bind_types) $stmt->bind_param($bind_types, ...$params);
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
.top10-wrapper form input {
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
  color: #467AFC !important;
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
.type-tags-bar {
  text-align: center;
  margin: 18px 0 6px 0;
}
.type-tag-btn {
  display: inline-block;
  background: #f4f7ff;
  border-radius: 16px;
  border: 1px solid #dde5f4;
  color: #333;
  font-size: 14px;
  padding: 7px 14px;
  margin: 0 4px 6px 4px;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.type-tag-btn.selected {
  background: #468bf5;
  color: #fff;
  font-weight: bold;
  border-color: #357ad5;
}
.limit-bar {
  text-align: center;
  margin-bottom: 16px;
  margin-top: 0;
}
.limit-btn {
  display: inline-block;
  background: #f4f4f4;
  border-radius: 12px;
  border: 1px solid #ddd;
  color: #333;
  font-size: 14px;
  padding: 5px 13px;
  margin: 0 2px 8px 2px;
  text-decoration: none;
  transition: background 0.14s, color 0.14s;
}
.limit-btn.selected {
  background: #47c5ff;
  color: #fff;
  border-color: #2593b8;
  font-weight: bold;
}
</style>

<div class="top10-wrapper">
  <h2>🏆 הפוסטרים עם הדירוג הגבוה ביותר לפי IMDb</h2>

  <form method="get" action="top.php">
    <div class="type-tags-bar">
      <a href="top.php?limit=<?= $limit ?>&year=<?= urlencode($year) ?>&genre=<?= urlencode($genre) ?><?= $subtitles?'&subtitles=1':'' ?><?= $dubbed?'&dubbed=1':'' ?>" class="type-tag-btn<?= !$type_id ? ' selected' : '' ?>">כל הסוגים</a>
      <?php foreach($types as $t): ?>
        <a href="top.php?type_id=<?= $t['id'] ?>&limit=<?= $limit ?>&year=<?= urlencode($year) ?>&genre=<?= urlencode($genre) ?><?= $subtitles?'&subtitles=1':'' ?><?= $dubbed?'&dubbed=1':'' ?>"
           class="type-tag-btn<?= $type_id==$t['id'] ? ' selected' : '' ?>">
          <?= htmlspecialchars($t['icon'].' '.$t['label_he']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="limit-bar">
      הצג:
      <?php foreach($limits as $l): ?>
        <a href="top.php?<?= http_build_query([
            'type_id'=>$type_id, 'limit'=>$l,
            'year'=>$year, 'genre'=>$genre,
            'subtitles'=>$subtitles, 'dubbed'=>$dubbed
        ]) ?>"
        class="limit-btn<?= $limit == $l ? ' selected' : '' ?>"><?= $l ?></a>
        <?php if ($l !== end($limits)) echo '|'; ?>
      <?php endforeach; ?>
      רשומות
    </div>
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
