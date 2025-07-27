<?php include 'header.php'; 
require_once 'server.php';
require_once 'imdb.class.php';

set_time_limit(3000); // מאפשר עד 50 דקות

function safe($str) {
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$tmdb_key = 'KEY'; //כאן מפתח
$report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imdb_ids'])) {
  $ids = preg_split('/[\n\r]+/', $_POST['imdb_ids']);

  foreach ($ids as $raw) {
    $raw = trim($raw);
    if ($raw === '') continue;

    if (preg_match('/tt\d+/', $raw, $match)) {
      $imdb_id = $match[0];
    } else {
      $report[] = ['id' => $raw, 'status' => 'invalid'];
      continue;
    }

    // בדיקה במסד
    $stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
    $stmt->bind_param("s", $imdb_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
      $report[] = ['id' => $imdb_id, 'status' => 'exists'];
      $stmt->close();
      continue;
    }
    $stmt->close();

    // IMDb Grabber
    $IMDB = new IMDB($imdb_id);
    if (!$IMDB->isReady) {
      $report[] = ['id' => $imdb_id, 'status' => 'error'];
      continue;
    }

    $title_en = $IMDB->getTitle();
    $year     = $IMDB->getYear();
    $plot     = $IMDB->getPlot();

    // TMDb API
    $tmdb_url = "https://api.themoviedb.org/3/find/$imdb_id?api_key=$tmdb_key&external_source=imdb_id";
    $response = @file_get_contents($tmdb_url);
    $tmdb = json_decode($response, true);

    $poster = '';
    $poster_type_code = 'movie'; // ברירת מחדל

    if (!empty($tmdb['movie_results'])) {
      $movie = $tmdb['movie_results'][0];
      $poster = 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'];
      $plot = $movie['overview'] ?: $plot;
      $year = substr($movie['release_date'], 0, 4) ?: $year;
    } elseif (!empty($tmdb['tv_results'])) {
      $tv = $tmdb['tv_results'][0];
      $poster = 'https://image.tmdb.org/t/p/w500' . $tv['poster_path'];
      $plot = $tv['overview'] ?: $plot;
      $year = substr($tv['first_air_date'], 0, 4) ?: $year;
      $poster_type_code = 'series';
    }

    // שליפה של type_id לפי קוד
    $type_id = null;
    $type_stmt = $conn->prepare("SELECT id FROM poster_types WHERE code = ? LIMIT 1");
    $type_stmt->bind_param("s", $poster_type_code);
    $type_stmt->execute();
    $type_stmt->bind_result($type_id);
    $type_stmt->fetch();
    $type_stmt->close();

    // שמירה במסד
    $stmt = $conn->prepare("INSERT INTO posters (imdb_id, title_en, year, plot, image_url, type_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissi", $imdb_id, $title_en, $year, $plot, $poster, $type_id);
    $stmt->execute();
    $stmt->close();

    $report[] = ['id' => $imdb_id, 'status' => 'added', 'title' => $title_en];
  }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📥 הוספת פוסטרים עם TMDb</title>
  <style>
    body { font-family: Arial; padding:40px; background:#f7f7f7; direction:rtl; }
    textarea { width:100%; height:160px; padding:10px; font-size:14px; margin-bottom:10px; }
    button { padding:10px 20px; font-size:14px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#0056b3; }
    .report { margin-top:30px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 6px rgba(0,0,0,0.05); }
    .report li { margin-bottom:6px; font-size:14px; }
  </style>
</head>
<body>

<h1>📥 הוספת פוסטרים לפי IMDb ID</h1>

<form method="post">
  <textarea name="imdb_ids" placeholder="tt0110912&#10;tt0133093" style="width: 800px"></textarea>
  <br>
  <button type="submit">💾 הוסף</button>
</form>

<?php if (!empty($report)): ?>
  <div class="report right" >
    <h2>📋 דוח פעולות</h2>
    <ul>
      <?php foreach ($report as $r): ?>
        <li>
          <?php if ($r['status'] === 'added'): ?>
            ✅ נוסף <?= safe($r['title']) ?> (<?= safe($r['id']) ?>)
          <?php elseif ($r['status'] === 'exists'): ?>
            ℹ️ כבר קיים <?= safe($r['id']) ?>
          <?php elseif ($r['status'] === 'error'): ?>
            ❌ שגיאה בשליפה <?= safe($r['id']) ?>
          <?php elseif ($r['status'] === 'invalid'): ?>
            ⚠️ מזהה לא תקין: <?= safe($r['id']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

</body>
</html>

<?php include 'footer.php'; ?>