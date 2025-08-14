<?php
include 'bar.php';
require_once 'server.php';

$allowed_limits = [5, 10, 20, 50, 100, 250];
$limit = in_array((int)($_GET['limit'] ?? $_SESSION['limit'] ?? 20), $allowed_limits)
    ? (int)($_GET['limit'] ?? $_SESSION['limit'] ?? 20) : 20;
$_SESSION['limit'] = $limit;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$view = $_GET['view'] ?? $_SESSION['view_mode'] ?? 'grid';
$_SESSION['view_mode'] = $view;

$search_mode = $_GET['search_mode'] ?? 'and';

$fields = [
  'search', 'min_rating', 'metacritic', 'rt_score', 'year', 'imdb_id', 'tvdb_id',
  'genre', 'actor', 'user_tag', 'directors', 'producers', 'writers',
  'composers', 'cinematographers', 'lang_code', 'country', 'runtime'
];
$types_selected = $_GET['type'] ?? [];

$where = []; $params = []; $bind_types = '';

if ($types_selected && is_array($types_selected)) {
  $placeholders = implode(',', array_fill(0, count($types_selected), '?'));
  $where[] = 'type_id IN (' . $placeholders . ')';
  foreach ($types_selected as $tid) { $params[] = $tid; $bind_types .= 'i'; }
}

function addCondition($col, $val, &$where, &$params, &$bind_types, $mode = 'and') {
  if ($val === '') return;
  $parts = array_map('trim', explode(',', $val));
  $cond_group = [];
  foreach ($parts as $item) {
    if ($item === '') continue;
    if ($item[0] === '!') {
      $value = substr($item, 1);
      $cond_group[] = "$col NOT LIKE ?";
      $params[] = "%$value%";
      $bind_types .= 's';
    } elseif (preg_match('/^(\d+)?-(\d+)?$/', $item, $m)) {
      $from = (isset($m[1]) && $m[1] !== '') ? (int)$m[1] : null;
      $to   = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : null;
      if ($from && $to)      { $cond_group[] = "($col BETWEEN ? AND ?)"; $params[] = $from; $params[] = $to; $bind_types .= 'ii'; }
      elseif ($from)         { $cond_group[] = "($col >= ?)"; $params[] = $from; $bind_types .= 'i'; }
      elseif ($to)           { $cond_group[] = "($col <= ?)"; $params[] = $to; $bind_types .= 'i'; }
    } else {
      $cond_group[] = "$col LIKE ?";
      $params[] = "%$item%";
      $bind_types .= 's';
    }
  }
  if ($cond_group) {
    $logic = strtoupper($mode) === 'or' ? 'OR' : 'AND';
    $where[] = '(' . implode(" $logic ", $cond_group) . ')';
  }
}

foreach ($fields as $f) {
  $val = trim($_GET[$f] ?? '');
  if ($val === '') continue;
  $col = match($f) {
    'search'      => '(title_en LIKE ? OR title_he LIKE ? OR imdb_id LIKE ?)',
    'actor'       => 'actors',
    'user_tag'    => '(SELECT GROUP_CONCAT(genre) FROM user_tags WHERE poster_id=posters.id)',
    'runtime'     => 'runtime',
    'genre'       => 'genre',
    'year'        => 'year',
    'min_rating'  => 'imdb_rating',
    'metacritic'  => 'metacritic_score',
    'rt_score'    => 'rt_score',
    'imdb_id'     => 'imdb_id',
    'tvdb_id'     => 'tvdb_id',
    'lang_code'   => 'languages',
    'country'     => 'countries',
    'directors'   => 'directors',
    'producers'   => 'producers',
    'writers'     => 'writers',
    'composers'   => 'composers',
    'cinematographers' => 'cinematographers',
    default       => $f
  };

  if ($f === 'search') {
    $v = $_GET['search'];
    if ($v[0] === '!') {
      $value = substr($v, 1);
      $where[] = '(title_en NOT LIKE ? AND title_he NOT LIKE ? AND imdb_id NOT LIKE ?)';
      for ($i = 0; $i < 3; $i++) { $params[] = "%$value%"; $bind_types .= 's'; }
    } else {
      $where[] = $col;
      for ($i = 0; $i < 3; $i++) { $params[] = "%$v%"; $bind_types .= 's'; }
    }
  } elseif ($f === 'user_tag') {
    $v = $_GET['user_tag'];
    if ($v[0] === '!') {
      $where[] = 'id NOT IN (SELECT poster_id FROM user_tags WHERE genre LIKE ?)';
      $params[] = '%' . substr($v, 1) . '%'; $bind_types .= 's';
    } else {
      $where[] = 'id IN (SELECT poster_id FROM user_tags WHERE genre LIKE ?)';
      $params[] = "%$v%"; $bind_types .= 's';
    }
  } elseif (in_array($f, ['runtime', 'year', 'min_rating', 'metacritic', 'rt_score'])) {
    addCondition($col, $val, $where, $params, $bind_types, $search_mode);
  } else {
    addCondition($col, $val, $where, $params, $bind_types, $search_mode);
  }
}

if (isset($_GET['is_foreign_language'])) {
  $where[] = "NOT (languages LIKE '%Hebrew%' OR languages LIKE '%עברית%')";
}

$logic = strtoupper($search_mode) === 'or' ? 'OR' : 'AND';
$sql_where = $where ? "WHERE " . implode(" $logic ", $where) : "";

$orderBy = "ORDER BY id DESC";
if (!empty($_GET['sort'])) {
  switch ($_GET['sort']) {
    case 'year_asc': $orderBy = "ORDER BY year ASC"; break;
    case 'year_desc': $orderBy = "ORDER BY year DESC"; break;
    case 'rating_desc': $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC"; break;
  }
}

$view_modes = ['grid', 'list', 'default'];
if (!in_array($view, $view_modes)) $view = 'grid';

$sql = "SELECT * FROM posters $sql_where $orderBy LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($bind_types) $stmt->bind_param($bind_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
$stmt->close();

$count_sql = "SELECT COUNT(*) as c FROM posters $sql_where";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types) $count_stmt->bind_param($bind_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['c'] ?? 0;
$count_stmt->close();

$total_pages = max(1, ceil($total_rows / $limit));
$start = $offset + 1;
$end = $offset + count($rows);
$uri_base = preg_replace('/([&?])page=\d+/', '', $_SERVER['REQUEST_URI']);
$uri_sep = (str_contains($uri_base, '?') ? '&' : '?');

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>ספריית פוסטרים</title>
  <style>
    body { background-color:white; margin:0; }
    h1, .results-summary { text-align:center; margin:10px 0 15px 0; }
    .pager {
      text-align:center; margin: 18px 0 18px 0;
      display: flex; justify-content: center; gap: 7px; flex-wrap: wrap;
    }
    .pager a, .pager strong {
      padding: 6px 12px;
      border: 1px solid #bbb;
      border-radius: 6px;
      text-decoration: none;
      background: #fff;
      font-size: 15px;
      color: #147;
      margin: 0 1px;
    }
    .poster-wall {
      display: flex;
      flex-wrap: wrap;
      gap: 18px 12px;
      justify-content: center;
      margin: 10px 0 40px 0;
    }
    .poster {
      width: 190px;
      background: #fff;
      border: 1px solid #eee;
      border-radius: 7px;
      padding: 7px;
      text-align: center;
      box-shadow: 0 0 6px rgba(0,0,0,0.07);
    }
    .poster img {
      width: 100%; border-radius: 4px; object-fit: cover;
    }
    .poster .rating { font-size:14px; color:#666; margin-top: 3px;}
    .poster .tags { font-size:12px; color:#888; margin:2px 0;}
    .poster-title { color:#1567c0; font-weight:bold; }
    .poster-regular li, .poster-list li {
      background: #fff; border-radius: 7px; margin-bottom: 8px; padding: 8px 6px;
    }
    .poster-list { list-style:none; padding:0; width:93%; margin:24px auto;}
    .poster-list li { display:flex; align-items:center; gap:10px;}
    .poster-list img { height:60px; border-radius:5px;}
    .poster-regular { list-style:none; padding:0; margin:25px auto; width:95%;}
    .poster-regular li { display:inline-block; width:178px; margin:10px; vertical-align:top; text-align:center;}
    .poster-regular img { height:150px; border-radius:4px; margin-bottom:6px;}
  </style>
</head>
<body>
  <h1>🎬 ספריית פוסטרים</h1>
  <div class="results-summary">
    <b>הצגת <?= $start ?>–<?= $end ?> מתוך <?= $total_rows ?> — עמוד <?= $page ?> מתוך <?= $total_pages ?></b>
  </div>
  <div class="pager">
    <?php if ($page > 1): ?>
      <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page - 1)) ?>">⬅ הקודם</a>
    <?php endif; ?>
    <?php
      $max_links = 5;
      $start_page = max(1, $page - floor($max_links / 2));
      $end_page = min($total_pages, $start_page + $max_links - 1);
      if ($end_page - $start_page < $max_links - 1) $start_page = max(1, $end_page - $max_links + 1);
      for ($i = $start_page; $i <= $end_page; $i++): ?>
      <?php if ($i == $page): ?>
        <strong><?= $i ?></strong>
      <?php else: ?>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . $i) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page + 1)) ?>">הבא ➡</a>
    <?php endif; ?>
  </div>

  <?php if (empty($rows)): ?>
    <p style="text-align:center; color:#888;">😢 לא נמצאו תוצאות</p>
  <?php elseif ($view === 'grid'): ?>
    <div class="poster-wall">
      <?php foreach ($rows as $row): ?>
        <div class="poster">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר">
            <div class="poster-title"><?= htmlspecialchars($row['title_en']) ?></div>
            <?php if (!empty($row['title_he'])): ?><div><?= htmlspecialchars($row['title_he']) ?></div><?php endif; ?>
            <div><?= htmlspecialchars($row['year']) ?></div>
          </a>
          <div class="rating">⭐ <?= $row['imdb_rating'] ?>/10</div>
          <div class="tags"><?= htmlspecialchars($row['genre']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif ($view === 'list'): ?>
    <ul class="poster-list">
      <?php foreach ($rows as $row): ?>
        <li>
          <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר">
          <b><?= htmlspecialchars($row['title_en']) ?></b>
          <?php if (!empty($row['title_he'])): ?> — <?= htmlspecialchars($row['title_he']) ?><?php endif; ?>
          (<?= htmlspecialchars($row['year']) ?>)
          ⭐ <?= $row['imdb_rating'] ?>
          <a href="poster.php?id=<?= $row['id'] ?>">📄</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <ul class="poster-regular">
      <?php foreach ($rows as $row): ?>
        <li>
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="פוסטר">
            <strong><?= htmlspecialchars($row['title_en']) ?></strong><br>
            <?= htmlspecialchars($row['title_he']) ?><br>
            🗓 <?= $row['year'] ?>
          </a>
          <div class="rating">⭐ <?= $row['imdb_rating'] ?>/10</div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="pager">
    <?php if ($page > 1): ?>
      <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page - 1)) ?>">⬅ הקודם</a>
    <?php endif; ?>
    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
      <?php if ($i == $page): ?>
        <strong><?= $i ?></strong>
      <?php else: ?>
        <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . $i) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <a href="<?= htmlspecialchars($uri_base . $uri_sep . 'page=' . ($page + 1)) ?>">הבא ➡</a>
    <?php endif; ?>
  </div>
</body>
</html>
<?php include 'footer.php'; ?>
