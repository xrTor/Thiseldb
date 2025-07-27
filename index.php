<?php require_once 'server.php';?>
<?php include 'header.php'; ?>

<?php
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;

$count_result = $conn->query("SELECT COUNT(*) AS total FROM posters");
$total_rows   = $count_result->fetch_assoc()['total'];
$total_pages  = max(1, ceil($total_rows / $limit));

if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = '';

if (!empty($_GET['tag'])) {
    $where[] = "p.id IN (SELECT poster_id FROM poster_categories WHERE category_id = ?)";
    $params[] = intval($_GET['tag']);
    $types .= 'i';
}

if (!empty($_GET['search'])) {
    $where[] = "(title_en LIKE ? OR title_he LIKE ?)";
    $search = "%" . $_GET['search'] . "%";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (!empty($_GET['year'])) {
    $where[] = "year LIKE ?";
    $params[] = "%" . $_GET['year'] . "%";
    $types .= 's';
}

if (!empty($_GET['min_rating'])) {
    $where[] = "CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) >= ?";
    $params[] = floatval($_GET['min_rating']);
    $types .= 'd';
}

$type_filter = $_GET['type'] ?? '';
if (!empty($type_filter)) {
    $where[] = "pt.code = ?";
    $params[] = $type_filter;
    $types .= 's';
}

$orderBy = "";
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'year_asc': $orderBy = "ORDER BY year ASC"; break;
        case 'year_desc': $orderBy = "ORDER BY year DESC"; break;
        case 'rating_desc':
            $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC";
            break;
    }
}
if (empty($orderBy)) $orderBy = "ORDER BY p.id DESC";

$sql = "SELECT p.*, pt.code, pt.label_he, pt.icon FROM posters p LEFT JOIN poster_types pt ON p.type_id = pt.id";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " $orderBy LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
?>

<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>Thiseldb</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="rtl">

<?php $tags = $conn->query("SELECT * FROM categories"); ?>

<div class="poster-wall">
<?php if (empty($rows)): ?>
  <p class="no-results">לא נמצאו תוצאות 😢</p>
<?php else: ?>
  <?php foreach ($rows as $row): ?>
    <div class="poster ltr">
      <?php $img = (!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png'; ?>
      <a href="poster.php?id=<?= $row['id'] ?>">
  <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($row['title_en']) ?>">
</a> 

      <div class="poster-title ltr">
        <b><?= htmlspecialchars($row['title_en']) ?>
          <?php if (!empty($row['title_he'])): ?><br><?= htmlspecialchars($row['title_he']) ?><?php endif; ?>
        </b><br>[<?= $row['year'] ?>]
      </div>

      <span class="imdb-first">
        <?php if ($row['imdb_link']): ?>
          <a href="<?= $row['imdb_link'] ?>" target="_blank" style="display:inline-flex; align-items:center; gap:1px;">
            <img src="IMDb.png" class="imdb ltr" alt="IMDb" style="height:18px;"> <span>⭐<?= htmlspecialchars($row['imdb_rating']) ?> / 10</span>
          </a>
        <?php endif; ?>
      </span>

      <?php
        $lang_icons = [ 'en'=>'', 'he'=>'🇮🇱', 'fr'=>'🇫🇷', 'es'=>'🇪🇸', 'ja'=>'🇯🇵', 'de'=>'🇩🇪' ];
        $lang_icon = $lang_icons[$row['lang_code'] ?? ''] ?? '';
        $features = '';
        if (!empty($row['is_dubbed'])) $features .= ' <span title="מדובב"><img src="hebdub.svg" class="bookmark1"></span>';
        if (!empty($row['has_subtitles'])) $features .= ' <span title="כתוביות">📝</span>';
        echo "<div style='margin-top:6px; font-size:16px;'>$lang_icon $features</div>";
      ?>

      <?php
$label = $row['label_he'] ?? '';
$icon = $row['icon'] ?? '';
if ($label || $icon) {
  echo "<div style='font-size:12px; color:#555;'>$icon $label</div>";
} else {
  echo "<div style='font-size:12px; color:#555;'>❓ לא ידוע</div>";
}
?>

      <div class="poster-actions rtl" style="margin-top:10px; font-size:13px; text-align:center;">
        <a href="edit.php?id=<?= $row['id'] ?>">✏️ עריכה</a> |
        <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('למחוק את הפוסטר?')">🗑️ מחיקה</a>
      </div>

      <div class="view-link rtl" style="margin-top:10px; text-align:center;">
        <a href="poster.php?id=<?= $row['id'] ?>">📄 צפה בפוסטר</a>
        <?php if (($row['code'] ?? '') === 'series' && !empty($row['tvdb_id'])): ?>
          <div style="text-align:center; margin-top:6px;">
            <a href="<?= htmlspecialchars($row['tvdb_id']) ?>" target="_blank">צפה בסדרה ב־TVDB</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div style="text-align:center;">
  <?php if ($page > 1): ?>
    <a href="index.php?page=<?= $page - 1 ?>">⬅ הקודם</a>
  <?php endif; ?>
  <a href="index.php?page=<?= $page + 1 ?>">הבא ➡</a>
</div>
<p style="text-align:center;">עמוד <?= $page ?> מתוך <?= $total_pages ?></p>

</body>
</html>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>