<?php
include 'header.php';
require_once 'server.php';

function safe($str) {
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$log_report = [];

// --- bulk add genres to posters (no change) ---
if (isset($_POST['bulk_add'])) {
  $genres = array_map('trim', preg_split('/[,|]/', $_POST['bulk_genre']));
  $lines = explode("\n", $_POST['bulk_ids']);

  foreach ($lines as $raw) {
    $id = trim($raw);
    if ($id === '') continue;
    if (preg_match('/tt\d+/', $id, $match)) {
      $id = $match[0];
    }

    if (is_numeric($id)) {
      $poster = $conn->query("SELECT id, genres FROM posters WHERE id = $id")->fetch_assoc();
    } elseif (preg_match('/^tt\d+$/', $id)) {
      $stmt = $conn->prepare("SELECT id, genres FROM posters WHERE imdb_id = ?");
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $poster = $stmt->get_result()->fetch_assoc();
      $stmt->close();
    } else {
      $poster = null;
    }

    if ($poster) {
      $pid = intval($poster['id']);
      $poster_genres = explode(',', strtolower($poster['genres'] ?? ''));
      $poster_genres = array_map('trim', $poster_genres);

      $update = false;
      foreach ($genres as $genre) {
        if ($genre === '') continue;
        $genre_lc = strtolower($genre);

        if (in_array($genre_lc, $poster_genres)) {
          $log_report[] = ['status' => 'exists_genre', 'id' => $id, 'genre' => $genre];
          continue;
        }

        $poster_genres[] = $genre_lc;
        $log_report[] = ['status' => 'added', 'id' => $id, 'genre' => $genre];
        $update = true;
      }

      if ($update) {
        $final_genres = array_unique(array_map('trim', $poster_genres));
        $final_genres = array_filter($final_genres, fn($g) => $g !== '');
        $final_str = implode(', ', $final_genres);
        $stmt = $conn->prepare("UPDATE posters SET genres = ? WHERE id = ?");
        $stmt->bind_param("si", $final_str, $pid);
        $stmt->execute();
        $stmt->close();
      }
    } else {
      $log_report[] = ['status' => 'error', 'id' => $id, 'genre' => implode(', ', $genres)];
    }
  }
}

// --- remove genre from a poster (no change) ---
if (isset($_GET['delete']) && isset($_GET['pid']) && isset($_GET['genre'])) {
  $pid = intval($_GET['pid']);
  $genre = trim($_GET['genre']);
  $poster = $conn->query("SELECT genres FROM posters WHERE id = $pid")->fetch_assoc();
  if ($poster) {
    $genres = explode(',', $poster['genres']);
    $genres = array_map('trim', $genres);
    $genres = array_filter($genres, fn($g) => strtolower($g) !== strtolower($genre));
    $final_str = implode(', ', $genres);
    $stmt = $conn->prepare("UPDATE posters SET genres = ? WHERE id = ?");
    $stmt->bind_param("si", $final_str, $pid);
    $stmt->execute();
    $stmt->close();
  }
}

// --- search & pagination setup ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (preg_match('/tt\d+/', $search, $match)) {
  $search = $match[0];
}

$results_per_page = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $results_per_page;

// --- get total count for pagination ---
$count_sql = "SELECT COUNT(*) FROM posters";
$count_params = [];
$count_types = "";
if ($search !== '') {
    $searchLike = "%$search%";
    $count_sql .= " WHERE title_en LIKE ? OR imdb_id LIKE ?";
    $count_params = [$searchLike, $searchLike];
    $count_types = "ss";
}
$stmt_count = $conn->prepare($count_sql);
if ($count_types) $stmt_count->bind_param($count_types, ...$count_params);
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$stmt_count->close();
$total_pages = ceil($total_results / $results_per_page);

// --- fetch posters for current page ---
$posters = [];
$sql = "SELECT id, title_en, title_he, genres FROM posters";
$params = [];
$types = "";
if ($search !== '') {
    $searchLike = "%$search%";
    $sql .= " WHERE title_en LIKE ? OR imdb_id LIKE ?";
    $params = [$searchLike, $searchLike];
    $types = "ss";
}
$sql .= " ORDER BY id DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $results_per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$posters_raw = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($posters_raw as $p) {
  $genres = array_map('trim', explode(',', $p['genres'] ?? ''));
  $genres = array_filter($genres, fn($g) => $g !== '');
  $posters[] = [
    'id'       => intval($p['id']),
    'title_en' => $p['title_en'],
    'title_he' => $p['title_he'],
    'genres'   => $genres
  ];
}

// --- generate pagination HTML ---
$pagination_html = '';
if ($total_pages > 1) {
    ob_start();
    include 'pagination.php';
    $pagination_html = ob_get_clean();
}

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🎬 ניהול ז'אנרים לפי פוסטר</title>
  <style>
    body { font-family:"Segoe UI"; padding:40px; background:#f8f8f8; }
    h1, h2 { text-align:center; color:#007bff; margin-bottom:20px; }
    form.search, form.bulk { max-width:500px; margin:0 auto 30px; }
    input[type="text"], textarea { width:100%; padding:10px; font-size:14px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; box-sizing: border-box; }
    button[type="submit"] { padding:10px 20px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
    button:hover { background:#0056b3; }
    table { width:100%; border-collapse:collapse; margin-top:15px; background-color: white;}
    th, td { padding:10px; border-bottom:1px solid #eee; text-align:right; vertical-align:top; }
    th { background:#f1f1f1; }
    td.title small { display:block; font-size:13px; color:#555; margin-top:4px; }
    a.delete { font-size:13px; margin-right:3px; text-decoration:none; color: #99999A !important; }
    a.delete:hover { text-decoration:underline; }
    ul.report { list-style:none; padding:0; margin-bottom:30px; max-width:600px; margin:0 auto; }
    ul.report li { margin-bottom:6px; font-size:14px; }
    .bold,td.title a {font-weight: bold; }
    .back {color:#007bff; display: inline-flex; align-items: center; background: #F0F1F2; color: #495057; padding: 8px 8px; border-radius: 12px; margin: 2px; font-size: 13px;}
    .pagination { text-align: center; margin: 15px 0; }
    .pagination a, .pagination span { display: inline-block; color: #007bff; text-decoration: none; padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; border-radius: 4px; }
    .pagination a.active { background-color: #007bff; color: white; border-color: #007bff; cursor: default; }
    .pagination a:hover:not(.active) { background-color: #f1f1f1; }
    .pagination span { color: #999; border-color: transparent; }
  </style>
</head>
<body>

<?php if (!empty($log_report)): ?>
  <h2>📋 דוח החלה</h2>
  <ul class="report">
    <?php foreach ($log_report as $entry): ?>
      <li>
        <?php if ($entry['status'] === 'added'): ?>
          <span style="color:green;">🟢</span>
          ז'אנר <?= safe($entry['genre']) ?> נוסף לפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'exists_genre'): ?>
          <span style="color:orange;">🟠</span>
          ז'אנר <?= safe($entry['genre']) ?> כבר קיים בפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'error'): ?>
          <span style="color:red;">🔴</span>
          לא נמצא פוסטר עבור מזהה <?= safe($entry['id']) ?>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" class="bulk">
  <h2>➕ החלת ז'אנר אחד או יותר על כמה פוסטרים</h2>
  <input type="text" name="bulk_genre" placeholder="למשל: Drama, Thriller">
  <textarea name="bulk_ids" rows="5" placeholder="tt0404940&#10;2&#10;https://www.imdb.com/title/tt0110912/"></textarea>
  <button type="submit" name="bulk_add">💾 הוסף ז'אנר</button>
</form>

<h1>🎭 ז'אנרים לפי פוסטר</h1>

<form method="get" class="search">
  <input type="text" name="search" placeholder="🔍 חיפוש לפי שם פוסטר או IMDb" value="<?= safe($search) ?>">
  <button type="submit">חפש</button>
</form>

<?= $pagination_html ?>

<table>
  <thead>
    <tr><th>מזהה</th><th>שם</th><th>ז'אנרים</th></tr>
  </thead>
  <tbody>
    <?php foreach ($posters as $p): ?>
      <tr>
        <td><?= safe($p['id']) ?></td>
        <td class="title">
          <a href="poster.php?id=<?= $p['id'] ?>"><?= safe($p['title_en']) ?></a>
          <?php if (!empty($p['title_he'])): ?>
            <small><?= safe($p['title_he']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($p['genres'])): ?>
            <?php foreach ($p['genres'] as $g): ?>
              <span class="back"><span class="bold"><?= safe($g) ?>
              <a href="?delete=1&pid=<?= $p['id'] ?>&genre=<?= urlencode($g) ?>"class="delete"></span>[מחק]</span></a>
            <?php endforeach; ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
     <?php if (empty($posters)): ?>
      <tr><td colspan="3" style="text-align: center; padding: 20px;">לא נמצאו תוצאות.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?= $pagination_html ?>

</body>
</html>
<?php include 'footer.php'; ?>