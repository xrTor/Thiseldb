<?php
include 'bar.php';
require_once 'server.php';
require_once 'alias.php';

// ----- קביעות בסיס -----
$allowed_limits = [5, 10, 20, 50, 100, 250];
$limit = in_array((int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50), $allowed_limits)
    ? (int)($_GET['limit'] ?? $_SESSION['limit'] ?? 50) : 50;
$_SESSION['limit'] = $limit;

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$view = $_GET['view'] ?? $_SESSION['view_mode'] ?? 'grid';
$_SESSION['view_mode'] = $view;

$search_mode = $_GET['search_mode'] ?? 'and';

// ----- נורמליזציה קלה לקלט -----
if (!empty($_GET['imdb_id'])) {
  if (preg_match('~tt\d{6,10}~i', (string)$_GET['imdb_id'], $m)) {
    $_GET['imdb_id'] = strtolower($m[0]);
  } else {
    $_GET['imdb_id'] = '';
  }
}
if (!empty($_GET['metacritic']) && preg_match('/^\d+$/', (string)$_GET['metacritic'])) {
  $_GET['metacritic'] = $_GET['metacritic'] . '-';
}
if (!empty($_GET['min_rating']) && preg_match('/^\d+(\.\d+)?$/', (string)$_GET['min_rating'])) {
  $_GET['min_rating'] = $_GET['min_rating'] . '-';
}

// ----- שדות אפשריים מהטופס -----
$fields = [
  'search', 'min_rating', 'metacritic', 'rt_score', 'year', 'imdb_id', 'tvdb_id',
  'genre', 'actor', 'user_tag', 'directors', 'producers', 'writers',
  'composers', 'cinematographers', 'lang_code', 'country', 'runtime',
  'network'
];
$types_selected = $_GET['type'] ?? [];

// ----- בניית WHERE דינמי -----
$where = [];
$params = [];
$bind_types = '';
$join_akas = ''; // יתווסף רק כשצריך חיפוש ב-AKAS (בשדה search)

if ($types_selected && is_array($types_selected)) {
  $placeholders = implode(',', array_fill(0, count($types_selected), '?'));
  $where[] = 'posters.type_id IN (' . $placeholders . ')';
  foreach ($types_selected as $tid) { $params[] = (int)$tid; $bind_types .= 'i'; }
}

// פונקציית עזר להוספת תנאי LIKE/טווחים/NOT
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

// מעבר על כל שדות החיפוש
foreach ($fields as $f) {
  $val = trim($_GET[$f] ?? '');
  if ($val === '') continue;

  // מיפוי שם־שדה לטור במסד (עם prefix posters.)
  $col = match($f) {
    'search'      => '(posters.title_en LIKE ? OR posters.title_he LIKE ? OR posters.imdb_id LIKE ?)', // לא בשימוש ישיר (מטפלים בנפרד)
    'actor'       => 'posters.`cast`',
    'user_tag'    => '(SELECT GROUP_CONCAT(genre) FROM user_tags WHERE poster_id = posters.id)',
    'runtime'     => 'posters.runtime',
    'genre'       => 'posters.genres',
    'year'        => 'posters.year',
    'min_rating'  => 'posters.imdb_rating',
    'metacritic'  => 'posters.mc_score',
    'rt_score'    => 'posters.rt_score',
    'imdb_id'     => 'posters.imdb_id',
    'tvdb_id'     => 'posters.tvdb_id',
    'lang_code'   => 'posters.languages',
    'country'     => 'posters.countries',
    'directors'   => 'posters.directors',
    'producers'   => 'posters.producers',
    'writers'     => 'posters.writers',
    'composers'   => 'posters.composers',
    'cinematographers' => 'posters.cinematographers',
    'network'     => 'posters.networks',
    default       => 'posters.' . $f
  };

  if ($f === 'search') {
    $v = $_GET['search'];

    // אם יש ערך ב־search – נאפשר גם חיפוש ב־AKAS
    if ($v !== '') {
      $join_akas = " LEFT JOIN poster_akas pa ON pa.poster_id = posters.id ";
    }

    if ($v !== '' && $v[0] === '!') {
      $value = substr($v, 1);
      $where[] =
        '('
        . 'posters.title_en NOT LIKE ? AND '
        . 'posters.title_he NOT LIKE ? AND '
        . 'posters.imdb_id  NOT LIKE ? AND '
        . 'COALESCE(pa.aka_title, \'\') NOT LIKE ? AND '
        . 'COALESCE(pa.aka, \'\')       NOT LIKE ?'
        . ')';
      for ($i = 0; $i < 5; $i++) { $params[] = "%$value%"; $bind_types .= 's'; }
    } else {
      $where[] =
        '('
        . 'posters.title_en LIKE ? OR '
        . 'posters.title_he LIKE ? OR '
        . 'posters.imdb_id  LIKE ? OR '
        . 'pa.aka_title LIKE ? OR '
        . 'pa.aka LIKE ?'
        . ')';
      for ($i = 0; $i < 5; $i++) { $params[] = "%$v%"; $bind_types .= 's'; }
    }
  } elseif ($f === 'user_tag') {
    $v = $_GET['user_tag'];
    if ($v !== '' && $v[0] === '!') {
      $where[] = 'posters.id NOT IN (SELECT poster_id FROM user_tags WHERE genre LIKE ?)';
      $params[] = '%' . substr($v, 1) . '%'; $bind_types .= 's';
    } else {
      $where[] = 'posters.id IN (SELECT poster_id FROM user_tags WHERE genre LIKE ?)';
      $params[] = "%$v%"; $bind_types .= 's';
    }
  } elseif (in_array($f, ['runtime', 'year', 'min_rating', 'metacritic', 'rt_score'])) {
    addCondition($col, $val, $where, $params, $bind_types, $search_mode);
  } else {
    addCondition($col, $val, $where, $params, $bind_types, $search_mode);
  }
}

// סינון "לא עברית"
if (isset($_GET['is_foreign_language'])) {
  $where[] = "NOT (posters.languages LIKE '%Hebrew%' OR posters.languages LIKE '%עברית%')";
}

$logic     = strtoupper($search_mode) === 'or' ? 'OR' : 'AND';
$sql_where = $where ? "WHERE " . implode(" $logic ", $where) : "";

// ----- מיון -----
$orderBy = "ORDER BY posters.id DESC";
if (!empty($_GET['sort'])) {
  switch ($_GET['sort']) {
    case 'year_asc':    $orderBy = "ORDER BY posters.year ASC"; break;
    case 'year_desc':   $orderBy = "ORDER BY posters.year DESC"; break;
    case 'rating_desc': $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(posters.imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC"; break;
  }
}

// ----- שליפות -----
$sql = "SELECT DISTINCT posters.*
        FROM posters
        $join_akas
        $sql_where
        $orderBy
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($bind_types) $stmt->bind_param($bind_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
$stmt->close();

$count_sql = "SELECT COUNT(DISTINCT posters.id) as c
              FROM posters
              $join_akas
              $sql_where";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types) $count_stmt->bind_param($bind_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['c'] ?? 0;
$count_stmt->close();

$total_pages = max(1, ceil($total_rows / $limit));
$start = $offset + 1;
$end   = $offset + count($rows);
$uri_base = preg_replace('/([&?])page=\d+/', '', $_SERVER['REQUEST_URI']);
$uri_sep  = (str_contains($uri_base, '?') ? '&' : '?');

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
          <div class="rating">⭐ <?= htmlspecialchars($row['imdb_rating']) ?>/10</div>
          <div class="tags"><?= htmlspecialchars($row['genres'] ?? '') ?></div>
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
          ⭐ <?= htmlspecialchars($row['imdb_rating']) ?>
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
            🗓 <?= htmlspecialchars($row['year']) ?>
          </a>
          <div class="rating">⭐ <?= htmlspecialchars($row['imdb_rating']) ?>/10</div>
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
