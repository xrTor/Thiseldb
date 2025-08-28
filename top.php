<?php
require_once 'server.php';

// שלב 1: איסוף כל הנתונים מהבקשה (GET)
$type_id   = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;
$year      = $_GET['year']      ?? '';
$genre     = $_GET['genre']     ?? '';
$subtitles = $_GET['subtitles'] ?? '';
$dubbed    = $_GET['dubbed']    ?? '';
$limits = [10, 20, 50, 100, 250];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limits) ? (int)$_GET['limit'] : 10;

// שלב 2: הרצת כל השאילתות ושמירת התוצאות במערכים

// שאילתה א': שליפת סוגים עבור כפתורי הסינון
$types_for_filter = [];
if ($res_types = $conn->query("SELECT * FROM poster_types ORDER BY sort_order ASC, id ASC")) {
    while ($t = $res_types->fetch_assoc()) {
        $types_for_filter[] = $t;
    }
}

// שאילתה ב': שליפת הפוסטרים המדורגים
$posters_data = [];
$where = ["imdb_rating IS NOT NULL", "imdb_rating > 0"];
$params = [];
$bind_types = '';

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
  $where[] = "genres LIKE ?"; // התאמה ל-SQL החדש
  $params[] = "%$genre%";
  $bind_types .= 's';
}
if ($subtitles) $where[] = "has_subtitles = 1";
if ($dubbed)    $where[] = "is_dubbed = 1";

// שימוש ב-CAST כדי למיין נכון מספרים עשרוניים
$sql = "SELECT * FROM posters WHERE " . implode(" AND ", $where) . " ORDER BY CAST(imdb_rating AS DECIMAL(3,1)) DESC LIMIT ?";
$bind_types .= 'i';
$params[] = $limit;

if ($stmt = $conn->prepare($sql)) {
    if ($bind_types) {
        $stmt->bind_param($bind_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $posters_data[] = $row;
    }
    $stmt->close();
}

// שלב 3: טעינת ה-header והצגת ה-HTML
include 'header.php';
?>

<style>
/* העיצוב שלך נשאר כאן */
.top10-wrapper { max-width: 1000px; margin: 50px auto; padding: 20px; font-family: sans-serif; }
h2 { text-align: center; font-size: 24px; margin-bottom: 30px; }
.top-poster { display: flex; align-items: center; gap: 20px; background: #fff; padding: 12px; margin-bottom: 16px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.top-rank { font-size: 26px; font-weight: bold; color: #888; width: 50px; text-align: center; }
.top-img { height: 100px; border-radius: 4px; object-fit: cover; }
.top-details { text-align: right; flex: 1; }
.top-title { color: #0056b3; font-weight: bold; text-decoration: none; font-size: 18px; }
.imdb-link { color: #E6B91E; font-weight: bold; text-decoration: none; }
.type-tags-bar { text-align: center; margin: 18px 0 6px 0; }
.type-tag-btn { display: inline-block; background: #f0f0f0; border-radius: 16px; border: 1px solid #ddd; color: #333; font-size: 14px; padding: 7px 14px; margin: 0 4px 6px 4px; text-decoration: none; }
.type-tag-btn.selected { background: #468bf5; color: #fff; font-weight: bold; border-color: #357ad5; }
.limit-bar { text-align: center; margin-bottom: 16px; }
.limit-btn { display: inline-block; background: #eee; border-radius: 8px; border: 1px solid #ccc; color: #333; font-size: 14px; padding: 5px 13px; margin: 0 2px 8px 2px; text-decoration: none; }
.limit-btn.selected { background: #007bff; color: #fff; border-color: #0056b3; font-weight: bold; }
</style>

<div class="top10-wrapper">
  <h2>🏆 הפוסטרים עם הדירוג הגבוה ביותר לפי IMDb</h2>

  <form method="get" action="top.php">
    <div class="type-tags-bar">
      <a href="top.php?limit=<?= $limit ?>" class="type-tag-btn<?= !$type_id ? ' selected' : '' ?>">כל הסוגים</a>
      <?php foreach($types_for_filter as $t): ?>
        <a href="top.php?type_id=<?= $t['id'] ?>&limit=<?= $limit ?>" class="type-tag-btn<?= $type_id == $t['id'] ? ' selected' : '' ?>">
          <?= htmlspecialchars($t['icon'].' '.$t['label_he']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="limit-bar">
      הצג:
      <?php foreach($limits as $l): ?>
        <a href="top.php?type_id=<?= $type_id ?>&limit=<?= $l ?>" class="limit-btn<?= $limit == $l ? ' selected' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </form>

  <?php if (empty($posters_data)): ?>
    <p style="text-align:center;">לא נמצאו פוסטרים התואמים לסינון.</p>
  <?php else: ?>
    <?php foreach ($posters_data as $index => $row): ?>
      <div class="top-poster">
        <div class="top-rank">#<?= $index + 1 ?></div>
        <a href="poster.php?id=<?= $row['id'] ?>">
          <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" class="top-img" alt="<?= htmlspecialchars($row['title_en']) ?>">
        </a>
        <div class="top-details">
          <a href="poster.php?id=<?= $row['id'] ?>" class="top-title"><?= htmlspecialchars($row['title_en']) ?></a>
          <?php if (!empty($row['title_he'])): ?>
            <div style="font-size:15px; color:#666;"><?= htmlspecialchars($row['title_he']) ?></div>
          <?php endif; ?>
          <div style="margin-top: 5px;">
            <span>🗓 <?= $row['year'] ?></span> | 
            <a href="<?= htmlspecialchars($row['imdb_link']) ?>" target="_blank" class="imdb-link">⭐ <?= htmlspecialchars($row['imdb_rating']) ?></a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>