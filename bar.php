<?php
require_once 'server.php';
session_start();
include 'header.php';

// שליפת הסוגים החדשים
$type_result = $conn->query("SELECT id, label_he, icon FROM poster_types ORDER BY sort_order ASC");
$type_options = [];
while ($type = $type_result->fetch_assoc()) {
  $type_options[$type['id']] = [
    'label' => $type['label_he'],
    'icon'  => $type['icon']
  ];
}

// הגדרות תצוגה
$allowed_limits = [5, 10, 20, 50, 100, 250];
$limit = in_array((int)($_GET['limit'] ?? $_SESSION['limit'] ?? 20), $allowed_limits) ? (int)($_GET['limit'] ?? $_SESSION['limit'] ?? 20) : 20;
$_SESSION['limit'] = $limit;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$view = $_SESSION['view_mode'] = $_GET['view'] ?? $_SESSION['view_mode'] ?? 'grid';

// קלטים
$search       = $_GET['search'] ?? '';
$year         = $_GET['year'] ?? '';
$min_rating   = $_GET['min_rating'] ?? '';
$imdb_id      = $_GET['imdb_id'] ?? '';
$type         = $_GET['type'] ?? '';
$genre        = $_GET['genre'] ?? '';
$actor        = $_GET['actor'] ?? '';
$search_mode  = $_GET['search_mode'] ?? 'or';
$lang_filter  = $_GET['lang_code'] ?? '';

if (preg_match('/tt\d{7,8}/', $imdb_id, $matches)) {
  $imdb_id = $matches[0];
}

// תנאים
$where = []; $params = []; $types = '';
function buildCondition($field, $input, $mode, &$where, &$params, &$types) {
  $values = array_filter(array_map('trim', explode(',', $input)));
  if ($values) {
    $glue = ($mode === 'and') ? ' AND ' : ' OR ';
    $parts = [];
    foreach ($values as $val) {
      $parts[] = "$field LIKE ?";
      $params[] = "%$val%";
      $types .= 's';
    }
    $where[] = '(' . implode($glue, $parts) . ')';
  }
}

buildCondition('title_en', $search, $search_mode, $where, $params, $types);
buildCondition('year',     $year,   $search_mode, $where, $params, $types);
buildCondition('imdb_id',  $imdb_id,$search_mode, $where, $params, $types);
buildCondition('genre',    $genre,  $search_mode, $where, $params, $types);
buildCondition('actors',   $actor,  $search_mode, $where, $params, $types);

if (!empty($min_rating)) {
  $where[] = "CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) >= ?";
  $params[] = (float)$min_rating;
  $types .= 'd';
}
if (!empty($type)) {
  $where[] = "type_id = ?";
  $params[] = (int)$type;
  $types .= 'i';
}
if (!empty($_GET['is_dubbed']))            $where[] = "is_dubbed = 1";
if (!empty($_GET['is_netflix_exclusive'])) $where[] = "is_netflix_exclusive = 1";
if (!empty($lang_filter)) {
  $where[] = "id IN (SELECT poster_id FROM poster_languages WHERE lang_code = ?)";
  $params[] = $lang_filter;
  $types .= 's';
}
if (!empty($_GET['missing_translation']))  $where[] = "has_translation = 0";

// מיון
$orderBy = "ORDER BY id DESC";
if (!empty($_GET['sort'])) {
  switch ($_GET['sort']) {
    case 'year_asc':    $orderBy = "ORDER BY year ASC"; break;
    case 'year_desc':   $orderBy = "ORDER BY year DESC"; break;
    case 'rating_desc': $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC"; break;
  }
}

// שליפה
$sql = "SELECT * FROM posters";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " $orderBy LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
if (!$stmt) die("❌ SELECT Error: " . $conn->error);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
$stmt->close();

// תיקון: הגדרת total_rows
$count_sql = "SELECT COUNT(*) AS c FROM posters";
if ($where) $count_sql .= " WHERE " . implode(" AND ", $where);
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['c'] ?? 0;
$count_stmt->close();

$start = ($page - 1) * $limit + 1;
$end = $start + count($rows) - 1;
$total_pages = max(1, ceil($total_rows / $limit));
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🔍 טופס סינון פוסטרים</title>
  <style>
    body { font-family: Arial; background:#f0f0f0; text-align: center; direction: rtl; padding: 40px; }
    form input, form select, form button, form textarea { padding: 6px; margin: 4px; }
    textarea { resize: vertical; width: 60%; }
    .pagination { list-style: none; display: flex; justify-content: center; gap: 6px; flex-wrap: wrap; padding: 0; margin-top: 20px; }
    .pagination li a { text-decoration: none; padding: 6px 10px; border: 1px solid #999; border-radius: 4px; background:#fff; color: #000; }
    .pagination li strong { padding: 6px 10px; border: 1px solid #333; border-radius: 4px; background:#ddd; }
  </style>
</head>
<body>

<h2>🔍 טופס סינון פוסטרים</h2>

<form method="get" action="home.php">
  <input type="text" name="search" placeholder="🎬 שם" value="<?= htmlspecialchars($search) ?>">
  <input type="text" name="year" placeholder="🗓 שנה" value="<?= htmlspecialchars($year) ?>">
  <input type="text" name="min_rating" placeholder="⭐ דירוג מינימלי" value="<?= htmlspecialchars($min_rating) ?>">
  <input type="text" name="imdb_id" placeholder="🔗 IMDb ID" value="<?= htmlspecialchars($imdb_id) ?>">
  <input type="text" name="genre" placeholder="🎭 ז'אנר" value="<?= htmlspecialchars($genre) ?>">
  <input type="text" name="actor" placeholder="👥 שחקנים" value="<?= htmlspecialchars($actor) ?>"><br>

  <select name="type">
    <option value="">🔍 כל הסוגים</option>
    <?php foreach ($type_options as $tid => $data): ?>
      <option value="<?= $tid ?>" <?= $type == $tid ? 'selected' : '' ?>>
        <?= htmlspecialchars($data['icon'] . ' ' . $data['label']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="sort">
    <option value="">מיון</option>
    <option value="year_asc" <?= ($_GET['sort'] ?? '') == 'year_asc' ? 'selected' : '' ?>>שנה ↑</option>
    <option value="year_desc" <?= ($_GET['sort'] ?? '') == 'year_desc' ? 'selected' : '' ?>>שנה ↓</option>
    <option value="rating_desc" <?= ($_GET['sort'] ?? '') == 'rating_desc' ? 'selected' : '' ?>>דירוג ↓</option>
  </select>

  <select name="view">
    <option value="">תצוגה</option>
    <option value="default" <?= $view === 'default' ? 'selected' : '' ?>>🔤 רגילה</option>
    <option value="grid" <?= $view === 'grid' ? 'selected' : '' ?>>🧱 Grid</option>
    <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>📋 List</option>
  </select>

  <select name="limit">
    <?php foreach ($allowed_limits as $opt): ?>
      <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
    <?php endforeach; ?>
  </select>

  <input type="hidden" name="lang_code" id="lang_code" value="<?= htmlspecialchars($_GET['lang_code'] ?? '') ?>">

<p style="margin:10px 0;">
    🔧 מצב חיפוש בין תנאים:  
    <strong>OR</strong> — לפחות תנאי אחד חייב להתקיים |
    <strong>AND</strong> — כל התנאים חייבים להתקיים
  </p>

  <label><input type="radio" name="search_mode" value="or" <?= $search_mode == 'or' ? 'checked' : '' ?>> OR</label>
  <label><input type="radio" name="search_mode" value="and" <?= $search_mode == 'and' ? 'checked' : '' ?>> AND</label><br>

  <label><input type="checkbox" name="is_dubbed" value="1" <?= isset($_GET['is_dubbed']) ? 'checked' : '' ?>> מדובב</label>
  <label><input type="checkbox" name="is_netflix_exclusive" value="1" <?= isset($_GET['is_netflix_exclusive']) ? 'checked' : '' ?>> בלעדי לנטפליקס</label>
  <label><input type="checkbox" id="is_foreign_language" name="is_foreign_language" value="1" <?= isset($_GET['is_foreign_language']) ? 'checked' : '' ?>> 🌍 שפה זרה</label>
  <label><input type="checkbox" name="missing_translation" value="1" <?= isset($_GET['missing_translation']) ? 'checked' : '' ?>> חסר תרגום</label><br><br>

  <button type="submit">📥 סנן</button>
  <a href="home.php">🔄 איפוס</a>

  <div id="languageMenu" style="display:none;">
    <?php include 'flags.php'; ?>
  </div>
</form>

<!-- ✨ תצוגת טווח תוצאה -->
<p>הצגת <?= $start ?>–<?= $end ?> מתוך <?= $total_rows ?> פוסטרים — עמוד <?= $page ?> מתוך <?= $total_pages ?></p>

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

<script>
document.addEventListener("DOMContentLoaded", () => {
  const checkbox = document.getElementById('is_foreign_language');
  const menu = document.getElementById('languageMenu');
  const langInput = document.getElementById('lang_code');
  const form = document.querySelector('form');

  if (checkbox && menu) {
    function toggleFlags() {
      menu.style.display = checkbox.checked ? 'block' : 'none';
    }
    toggleFlags();
    checkbox.addEventListener('change', toggleFlags);
  }

  if (menu && langInput && form) {
    menu.querySelectorAll('.language-cell').forEach(cell => {
      cell.addEventListener('click', () => {
        const langCode = cell.getAttribute('data-lang') || cell.title || '';
        if (langCode) {
          langInput.value = langCode;
          form.submit();
        }
      });
    });
  }
});
</script>

</body>
</html>