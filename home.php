<?php
include 'bar.php'; // מאתחל את $rows ו־$total_rows

$view = $_SESSION['view_mode'] ?? 'grid';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = $_SESSION['limit'] ?? 20;
$offset = ($page - 1) * $limit;
$total_pages = max(1, ceil(($total_rows ?? 0) / $limit));
$start_item = $offset + 1;
$end_item = $offset + count($rows);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🎬 ספריית פוסטרים</title>
  <style>
    body {
      font-family: Arial;
      background: #f0f0f0;
      direction: rtl;
      margin: 0;
      padding: 10px;
    }
    h1, p { text-align: center; }
    .poster-wall {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
      margin: 30px 0;
    }
    .poster {
      width: 200px;
      background: #fff;
      border: 1px solid #ccc;
      padding: 10px;
      text-align: center;
      box-shadow: 0 0 6px rgba(0,0,0,0.05);
      border-radius: 6px;
    }
    .poster img {
      width: 100%;
      border-radius: 4px;
      object-fit: cover;
    }
    .poster-list, .poster-regular {
      list-style: none;
      padding: 0;
      margin: 30px auto;
      width: 90%;
    }
    .poster-list li {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px;
      border-bottom: 1px solid #ccc;
      background: #fff;
      border-radius: 6px;
    }
    .poster-list img {
      height: 60px;
      border-radius: 4px;
    }
    .poster-regular li {
      display: inline-block;
      width: 180px;
      margin: 10px;
      vertical-align: top;
      text-align: center;
      background: #fff;
      padding: 10px;
      border-radius: 6px;
    }
    .poster-regular img {
      height: 150px;
      border-radius: 4px;
      margin-bottom: 6px;
    }
    .rating {
      font-size: 14px;
      color: #666;
      margin-top: 6px;
    }
    .pager {
      text-align: center;
      margin: 40px;
    }
    .pager a {
      margin: 0 10px;
      text-decoration: none;
      color: #007bff;
    }
    .pager a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>


<?php if (empty($rows)): ?>
  <p style="text-align:center;">😢 לא נמצאו תוצאות</p>
<?php elseif ($view === 'grid'): ?>
  <div class="poster-wall">
    <?php foreach ($rows as $row): ?>
      <?php $img = (!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png'; ?>
      <div class="poster">
        <a href="poster.php?id=<?= $row['id'] ?>">
          <img src="<?= htmlspecialchars($img) ?>" alt="פוסטר">
          <strong><?= htmlspecialchars($row['title_en']) ?></strong><br>
          <?= htmlspecialchars($row['title_he']) ?><br>
          🗓 <?= $row['year'] ?>
        </a>
        <div class="rating">⭐ <?= $row['imdb_rating'] ?>/10</div>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($view === 'list'): ?>
  <ul class="poster-list">
    <?php foreach ($rows as $row): ?>
      <?php $img = (!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png'; ?>
      <li>
        <img src="<?= htmlspecialchars($img) ?>" alt="פוסטר">
        <strong><?= htmlspecialchars($row['title_en']) ?></strong> —
        <?= htmlspecialchars($row['title_he']) ?> (<?= $row['year'] ?>)
        ⭐ <?= $row['imdb_rating'] ?>
        <a href="poster.php?id=<?= $row['id'] ?>">📄 צפייה</a>
      </li>
    <?php endforeach; ?>
  </ul>

<?php else: ?>
  <ul class="poster-regular">
    <?php foreach ($rows as $row): ?>
      <?php $img = (!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png'; ?>
      <li>
        <a href="poster.php?id=<?= $row['id'] ?>">
          <img src="<?= htmlspecialchars($img) ?>" alt="פוסטר">
          <strong><?= htmlspecialchars($row['title_en']) ?></strong><br>
          <?= htmlspecialchars($row['title_he']) ?><br>
          🗓 <?= $row['year'] ?>
        </a>
        <div class="rating">⭐ <?= $row['imdb_rating'] ?>/10</div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<!-- 🧭 ניווט עמודים -->
<nav aria-label="Page navigation">
  <ul class="pagination">
    <?php if ($page > 1): ?>
      <li><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">⬅ הקודם</a></li>
    <?php endif; ?>
    <?php
    $max_links = 5;
    $start_page = max(1, $page - floor($max_links / 2));
    $end_page = min($total_pages, $start_page + $max_links - 1);
    if ($end_page - $start_page < $max_links - 1) {
      $start_page = max(1, $end_page - $max_links + 1);
    }
    for ($i = $start_page; $i <= $end_page; $i++): ?>
      <li>
        <?php if ($i == $page): ?>
          <strong><?= $i ?></strong>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
      </li>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <li><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">הבא ➡</a></li>
    <?php endif; ?>
  </ul>
</nav>


<?php include 'footer.php'; ?>
</body>
</html>
