<?php
// שלב 1: טעינת החיבור למסד הנתונים והפונקציות
require_once 'server.php';

// פונקציה לזיהוי מזהה IMDb מתוך מזהה או קישור
function extractImdbId($input) {
  if (preg_match('/tt\d{7,10}/', $input, $matches)) {
    return $matches[0];
  }
  return $input;
}

// פונקציה חדשה לחילוץ שם סדרה מקובץ (The.Good.Wife.S07.720p...)
function extractSeriesName($input) {
    // 1. הגדרת תבניות שכיחות המסמנות את סוף השם ותחילת המטא-דאטה:
    $pattern = '/^(.+?)[._\-\s]+(S\d{1,3}|E\d{1,3}|\d{3,4}p|20\d{2}|19\d{2}).*$/i';
    
    if (preg_match($pattern, $input, $matches)) {
        return trim($matches[1]);
    }
    return $input; 
}

// >>> פונקציה מתוקנת לנרמול קלט (נוסף / לתווים הכלליים)
function normalizeSearchInput($q) {
    // 1. המרה לאותיות קטנות
    $q = mb_strtolower($q, 'UTF-8');
    
    // 2. החלפת נקודה ופסיק ברווח (למשל: "Supa.Team" הופך ל-"Supa Team")
    $q = str_replace(['.', ','], ' ', $q);
    
    // 3. החלפת תווים מפרידים חזקים (כולל הלוכסן /) בתו כללי %
    $q = str_replace(['_', '-', ':', ';', '!', '(', ')', '[', ']', '?', '&', '\\', '"', '/', '/'], '%', $q);
    
    // 4. כיווץ כל התווים הכלליים המרובים (%%%) לתו כללי יחיד
    $q = preg_replace('/%+/i', '%', $q);

    // 5. כיווץ רווחים מרובים לרווח יחיד (שומר על הרווחים המקוריים)
    $q = preg_replace('/\s+/', ' ', $q);
    
    // 6. הסרת שנת יציאה (שמופרדת ע"י % או רווח)
    $q = preg_replace('/(%|\s)\d{4}$/', '', $q); 
    
    // 7. ניקוי קצוות מרווחים או %
    $q = trim($q, '% '); 
    
    return $q;
}

// שלב 2: כל הלוגיקה של החיפוש וההפניה רצה כאן, לפני ה-HTML
$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);

// >>> שמירת המילה המקורית לתצוגה, לפני הנרמול
$displayKeyword = $keyword;

// בדיקה ראשונית לזיהוי IMDb לפני הנרמול המסיבי
$tempKeyword = extractImdbId($keyword);

// >>> סדר הפעולות החדש: חילוץ <- נרמול
if ($tempKeyword === $keyword) {
    // 1. מנסים לחלץ את שם הסדרה 
    $keyword = extractSeriesName($keyword);
    // 2. מנרמלים את המחרוזת (הופך לתווים כלליים)
    $keyword = normalizeSearchInput($keyword);
} else {
    // אם זוהה IMDb, אנחנו משתמשים בו גם לחיפוש וגם לתצוגה (כי הוא כבר נקי)
    $keyword = $tempKeyword;
    $displayKeyword = $tempKeyword;
}

// >>> הכנת המחרוזת המעובדת להצגה (החלפת % ברווח)
$sqlSearchDisplay = str_replace('%', ' ', $keyword);
$sqlSearchDisplay = trim($sqlSearchDisplay);

$results = [];
$num_results = 0;

if ($keyword !== '') {
  // רשימת שדות מעודכנת ומורחבת לחיפוש מקיף
  $searchFields = [
    "title_en",
    "title_he",
    "he_title",       
    "original_title",
    "plot",
    "plot_he",
    "overview_he",
    "overview_en",    
    "cast",                 
    "genres",               
    "directors",
    "writers",
    "producers",
    "composers",
    "cinematographers",
    "languages",
    "countries",
    "networks",             
    "collection_name",
    "imdb_id",
    "tmdb_id",        
    "tvdb_id",        
    "year"
  ];

  $like   = "%$keyword%";
  $numSearchFields = count($searchFields);
  
  // בניית תנאי ה-WHERE
  $where = [];
  for ($i = 0; $i < $numSearchFields; $i++) {
      $where[] = "p." . $searchFields[$i] . " LIKE ?";
  }
  
  // הוספת תנאים נוספים ל-ut.genre ול-pa.aka
  $types = str_repeat('s', $numSearchFields); 
  $params = array_fill(0, $numSearchFields, $like);
  
  $where[] = "ut.genre LIKE ?";
  $params[] = $like;
  $types   .= 's';

  $where[] = "pa.aka_title LIKE ?";
  $params[] = $like;
  $types   .= 's';

  $where[] = "pa.aka LIKE ?";
  $params[] = $like;
  $types   .= 's';
  
  $sql = "
    SELECT DISTINCT p.*
    FROM posters p
    LEFT JOIN user_tags ut ON ut.poster_id = p.id
    LEFT JOIN poster_akas pa ON pa.poster_id = p.id
    WHERE (" . implode(' OR ', $where) . ")
    ORDER BY p.year DESC, p.title_en ASC
    LIMIT 30000
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();

  // הפניה אוטומטית אם יש תוצאה אחת
  if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $stmt->close();
    header("Location: poster.php?id=" . (int)$row['id']);
    exit;
  }

  // שליפת תוצאות להצגה
  $num_results = $result->num_rows;
  while ($row = $result->fetch_assoc()) {
    $results[] = $row;
  }
  $stmt->close();
}

// שלב 3: רק אחרי שסיימנו עם הלוגיקה, טוענים את ה-header ומתחילים להציג HTML
include 'header.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= $displayKeyword === '' ? 'חיפוש פוסטרים' : 'תוצאות עבור ' . htmlspecialchars($displayKeyword) ?></title>
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
      color: #333;
    }
    .card:hover { transform: scale(1.05); }
    .card img {
      width: 100%;
      border-radius: 6px;
      object-fit: cover;
      min-height: 290px;
      background: #eee;
    }
    .results { display: flex; flex-wrap: wrap; justify-content: center; }
    .aka { font-size: 13px; color: #444; }
    .title-he { color: #666; font-size: 14px; margin-top: 3px; }
    .results-count {
      text-align: center; font-size: 18px; margin: 12px 0 4px 0; color: #444;
    }
  </style>
</head>
<body>

  <h2 style="text-align:center; color: #333;">
    <?= $displayKeyword === '' ? '🔍 חיפוש פוסטרים' : '🔍 תוצאות עבור: ' . htmlspecialchars($sqlSearchDisplay) ?>
  </h2>

<?php
if ($keyword !== '') {
  // השורה של חיפוש בסיס נתונים (מנורמל) הוסרה כאן כפי שביקשת.
        
  if ($num_results > 0) {
    // תוצאה של ספירת הרשומות
    $txt = ($num_results == 1) ? "נמצאה תוצאה אחת" : "נמצאו $num_results תוצאות";
    echo "<div class='results-count'>$txt עבור <b>\"" . htmlspecialchars($displayKeyword) . "\"</b></div>";

  } else {
    echo "<div class='results-count'>לא נמצאו תוצאות עבור <b>\"" . htmlspecialchars($displayKeyword) . "\"</b></div>";
  }
  if ($num_results > 0): ?>
    <div class="results">
      <?php foreach ($results as $row): ?>
        <div class="card">
          <a href="poster.php?id=<?= (int)$row['id'] ?>">
            <?php
              $img = trim((string)($row['image_url'] ?? ''));
              if ($img === '' || $img === 'N/A') {
                $img = trim((string)($row['poster_url'] ?? ''));
              }
              if ($img === '' || $img === 'N/A') {
                $img = 'images/no-poster.png';
              }
            ?>
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="Poster">
            <div style="margin-top: 8px; color: #333;">
              <?= htmlspecialchars($row['title_en'] ?? '', ENT_QUOTES, 'UTF-8') ?>
              <?php
                if (!empty($row['title_en']) &&
                    preg_match('/^(.*?)\s*AKA\s*(.*)$/i', $row['title_en'], $m) &&
                    trim($m[2])) {
                  echo '<div class="aka">(AKA '.htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8').')</div>';
                }
              ?>
              <?php if (!empty($row['title_he'])): ?>
                <div class="title-he"><?= htmlspecialchars($row['title_he'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>
          </a>
          <div style="font-size:12px; color:#888; margin-top:4px;">
            🗓 <?= htmlspecialchars((string)($row['year'] ?? ''), ENT_QUOTES, 'UTF-8') ?> |
            ⭐ <?= htmlspecialchars((string)($row['imdb_rating'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            <?php
              // הוספת Runtime אם קיים
              if (!empty($row['runtime'])): ?>
                | ⏱ <?= htmlspecialchars((string)($row['runtime'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <?php if (!empty($row['imdb_id'])):
              $imdbUrl = 'https://www.imdb.com/title/' . rawurlencode($row['imdb_id']) . '/'; ?>
              | <a href="<?= htmlspecialchars($imdbUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="color:#E6B91E;text-decoration:none;font-weight:bold;">IMDb</a>
            <?php endif; ?>
          </div>
          <div style="margin-top:5px;">
            <?php
              $genres = trim((string)($row['genres'] ?? ''));
              if ($genres !== ''): ?>
              <span style="font-size:12px;">🎭 <?= htmlspecialchars($genres, ENT_QUOTES, 'UTF-8') ?></span>
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