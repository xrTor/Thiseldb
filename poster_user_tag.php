<?php include 'header.php'; 
require_once 'server.php';
?>

<?php
require_once 'server.php';

function safe($str) {
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$log_report = [];

if (isset($_POST['bulk_add'])) {
  $genres = array_map('trim', preg_split('/[,|
]/', $_POST['bulk_genre']));
  $lines = explode("\n", $_POST['bulk_ids']);

  foreach ($lines as $raw) {
    $id = trim($raw);
    if ($id === '') continue;
    if (preg_match('/tt\d+/', $id, $match)) {
      $id = $match[0];
    }

    if (is_numeric($id)) {
      $poster = $conn->query("SELECT id, genre FROM posters WHERE id = $id")->fetch_assoc();
    } elseif (preg_match('/^tt\d+$/', $id)) {
      $stmt = $conn->prepare("SELECT id, genre FROM posters WHERE imdb_id = ?");
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $poster = $stmt->get_result()->fetch_assoc();
      $stmt->close();
    } else {
      $poster = null;
    }

    if ($poster) {
      $pid = intval($poster['id']);
      $poster_genres = explode(',', strtolower($poster['genre'] ?? ''));
      $poster_genres = array_map('trim', $poster_genres);

      foreach ($genres as $genre) {
        if ($genre === '') continue;
        $genre_lc = strtolower($genre);

        if (in_array($genre_lc, $poster_genres)) {
          $log_report[] = ['status' => 'exists_genre', 'id' => $id, 'genre' => $genre];
          continue;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_tags WHERE poster_id = ? AND LOWER(genre) = LOWER(?)");
        $stmt->bind_param("is", $pid, $genre);
        $stmt->execute();
        $stmt->bind_result($exists);
        $stmt->fetch();
        $stmt->close();

        if ($exists == 0) {
          $stmt = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
          $stmt->bind_param("is", $pid, $genre);
          $stmt->execute();
          $stmt->close();
          $log_report[] = ['status' => 'added', 'id' => $id, 'genre' => $genre];
        } else {
          $log_report[] = ['status' => 'exists', 'id' => $id, 'genre' => $genre];
        }
      }
    } else {
      $log_report[] = ['status' => 'error', 'id' => $id, 'genre' => implode(', ', $genres)];
    }
  }
}

if (isset($_GET['delete']) && isset($_GET['pid']) && isset($_GET['genre'])) {
  $pid = intval($_GET['pid']);
  $genre = trim($_GET['genre']);
  $stmt = $conn->prepare("DELETE FROM user_tags WHERE poster_id = ? AND genre = ?");
  $stmt->bind_param("is", $pid, $genre);
  $stmt->execute();
  $stmt->close();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (preg_match('/tt\d+/', $search, $match)) {
  $search = $match[0];
}
$like = $conn->real_escape_string("%$search%");
$query = "SELECT id, title_en, title_he FROM posters ORDER BY id DESC";

if ($search !== '') {
  $query .= " WHERE title_en LIKE '$like' OR imdb_id LIKE '$like'";
}
$posters_raw = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$posters = [];
foreach ($posters_raw as $p) {
  $pid = intval($p['id']);
  $stmt = $conn->prepare("SELECT genre FROM user_tags WHERE poster_id = ?");
  $stmt->bind_param("i", $pid);
  $stmt->execute();
  $result = $stmt->get_result();
  $genres = [];
  while ($row = $result->fetch_assoc()) {
    $genres[] = $row['genre'];
  }
  $stmt->close();

  $posters[] = [
    'id'       => $pid,
    'title_en' => $p['title_en'],
    'title_he' => $p['title_he'],
    'genres'   => $genres
  ];
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🎬 תגיות משתמש לפי פוסטר</title>
  <style>
    body { font-family:"Segoe UI"; padding:40px; background:#f8f8f8; }
    h1, h2 { text-align:center; color:#007bff; margin-bottom:20px; }
    form.search, form.bulk { max-width:500px; margin:0 auto 30px; }
    input[type="text"], textarea { width:100%; padding:10px; font-size:14px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; }
    button[type="submit"] { padding:10px 20px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
    button:hover { background:#0056b3; }
    table { width:100%; border-collapse:collapse; margin-top:30px; background-color: white;}
    th, td { padding:10px; border-bottom:1px solid #eee; text-align:right; vertical-align:top; }
    th { background:#f1f1f1; }
    td.title small { display:block; font-size:13px; color:#555; margin-top:4px; }
    a.delete { font-size:13px; margin-right:8px; text-decoration:none; color: #99999A !important; }
    a.delete:hover { text-decoration:underline; }
    ul.report { list-style:none; padding:0; margin-bottom:30px; max-width:600px; margin:0 auto; }
    ul.report li { margin-bottom:6px; font-size:14px; }
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
          תגית <?= safe($entry['genre']) ?> נוספה לפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'exists'): ?>
          <span style="color:blue;">🔵</span>
          תגית <?= safe($entry['genre']) ?> כבר קיימת בפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'exists_genre'): ?>
          <span style="color:orange;">🟠</span>
          תגית <?= safe($entry['genre']) ?> כבר קיימת בז'אנרים של הפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'error'): ?>
          <span style="color:red;">🔴</span>
          לא נמצא פוסטר עבור מזהה <?= safe($entry['id']) ?>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" class="bulk">
  <h2>➕ החלת תגית אחת או יותר על כמה פוסטרים</h2>
  <input type="text" name="bulk_genre" placeholder="למשל: Drama, Thriller">
  <textarea name="bulk_ids" rows="5" placeholder="tt0404940&#10;2&#10;https://www.imdb.com/title/tt0110912/"></textarea>
  <button type="submit" name="bulk_add">💾 הוסף תגית</button>
</form>

<h1>🎭 תגיות ממשתמשים לפי פוסטר</h1>

<form method="get" class="search">
  <input type="text" name="search" placeholder="🔍 חיפוש לפי שם פוסטר או IMDb" value="<?= safe($search) ?>">
  <button type="submit">חפש</button>
</form>
<table>
  <thead>
    <tr><th>מזהה</th><th>שם</th><th>תגיות</th></tr>
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
              <a href="user_tags.php?name=<?= urlencode($g) ?>" style="text-decoration:none; color:#007bff;"><?= safe($g) ?></a>
              <a href="?delete=1&pid=<?= $p['id'] ?>&genre=<?= urlencode($g) ?>" class="delete">מחק</a> | 
            <?php endforeach; ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>

<?php include 'footer.php'; ?>
