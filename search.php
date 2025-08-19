<?php
// שלב 1: טעינת החיבור למסד הנתונים והפונקציות
require_once 'server.php';

// פונקציה לזיהוי מזהה IMDb מתוך מזהה או קישור
function extractImdbId($input) {
  if (preg_match('/tt\d{7,8}/', $input, $matches)) {
    return $matches[0];
  }
  return $input;
}

// שלב 2: כל הלוגיקה של החיפוש וההפניה רצה כאן, לפני ה-HTML
$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);
$keyword = extractImdbId($keyword);

$results = [];
$num_results = 0;

if (!empty($keyword)) {
  $searchFields = [
    "title_en", "title_he", "plot", "plot_he", "actors", "genre",
    "directors", "writers", "producers", "composers", "cinematographers",
    "languages", "countries", "imdb_id", "year", "tvdb_id"
  ];
  $like = "%$keyword%";
  $params = array_fill(0, count($searchFields), $like);
  $types  = str_repeat('s', count($searchFields));
  $where  = [];
  foreach ($searchFields as $f) $where[] = "p.$f LIKE ?";

  $where[] = "ut.genre LIKE ?";
  $params[] = $like;
  $types .= 's';

  $sql = "
    SELECT DISTINCT p.*
    FROM posters p
    LEFT JOIN user_tags ut ON ut.poster_id = p.id
    WHERE (" . implode(' OR ', $where) . ")
    ORDER BY p.year DESC, p.title_en ASC
    LIMIT 30000
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();

  // בדיקת הפנייה אוטומטית אם יש תוצאה אחת בלבד
  if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    header("Location: poster.php?id=" . $row['id']);
    exit; // חובה לצאת אחרי הפנייה
  }

  // אם לא בוצעה הפנייה, ממשיכים לשלוף את שאר התוצאות
  $num_results = $result->num_rows;
  while ($row = $result->fetch_assoc()) {
    $results[] = $row;
  }
  $stmt->close();
}

$conn->close();

// שלב 3: רק אחרי שסיימנו עם הלוגיקה, טוענים את ה-header ומתחילים להציג HTML
include 'header.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= empty($keyword) ? 'חיפוש פוסטרים' : 'תוצאות עבור ' . htmlspecialchars($keyword) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .card {
      width: 200px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      text-align: center;
      padding: 10px;
      margin: 10px;
      transition: transform 0.2s ease;
      color: #333; /* צבע טקסט לכרטיסיות */
    }
    .card:hover {
      transform: scale(1.05);
    }
    .card img {
      width: 100%;
      border-radius: 6px;
      object-fit: cover;
      min-height: 290px;
      background: #eee;
    }
    .results {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
    }
    .aka {
      font-size: 13px;
      color: #444;
    }
    .title-he {
      color: #666;
      font-size: 14px;
      margin-top: 3px;
    }
    .results-count {
      text-align: center;
      font-size: 18px;
      margin: 12px 0 4px 0;
      color: #444;
    }
  </style>
</head>
<body>

  <h2 style="text-align:center; color: #333;">
    <?= empty($keyword) ? '🔍 חיפוש פוסטרים' : '🔍 תוצאות עבור: ' . htmlspecialchars($keyword) ?>
  </h2>

<?php
if (!empty($keyword)) {
  if ($num_results > 0) {
    $txt = ($num_results == 1) ? "נמצאה תוצאה אחת" : "נמצאו $num_results תוצאות";
    echo "<div class='results-count'>$txt עבור <b>\"" . htmlspecialchars($keyword) . "\"</b></div>";
  } else {
    echo "<div class='results-count'>לא נמצאו תוצאות עבור <b>\"" . htmlspecialchars($keyword) . "\"</b></div>";
  }
  if ($num_results > 0): ?>
    <div class="results">
      <?php foreach ($results as $row): ?>
        <div class="card">
          <a href="poster.php?id=<?= $row['id'] ?>">
            <img src="<?= htmlspecialchars($row['image_url']) ?: 'images/no-poster.png' ?>" alt="Poster">
            <div style="margin-top: 8px; color: #333;">
              <?= htmlspecialchars($row['title_en']) ?>
              <?php
                if (preg_match('/^(.*?)\s*AKA\s*(.*)$/i', $row['title_en'], $m) && trim($m[2])) {
                  echo '<div class="aka">(AKA '.htmlspecialchars($m[2]).')</div>';
                }
              ?>
              <?php if (!empty($row['title_he'])): ?>
                <div class="title-he"><?= htmlspecialchars($row['title_he']) ?></div>
              <?php endif; ?>
            </div>
          </a>
          <div style="font-size:12px; color:#888; margin-top:4px;">
            🗓 <?= htmlspecialchars($row['year']) ?> | 
            ⭐ <?= htmlspecialchars($row['imdb_rating']) ?>
            <?php if (!empty($row['imdb_id'])): ?>
              <a href="<?= htmlspecialchars($row['imdb_link']) ?>" target="_blank" style="color:#E6B91E;text-decoration:none;font-weight:bold;">IMDb</a>
            <?php endif; ?>
          </div>
          <div style="margin-top:5px;">
            <?php if (!empty($row['genre'])): ?>
              <span style="font-size:12px;">🎭 <?= htmlspecialchars($row['genre']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif;
}
?>

</body>
</html>
<?php include 'footer.php'; ?>