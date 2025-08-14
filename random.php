<?php
include 'header.php'; 
require_once 'server.php';

function extractYoutubeId($url) {
  if (preg_match('/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) return $matches[1];
  return '';
}

// --- שליפת נתונים עבור הפילטרים ---
$types_list = [];
$types_result = $conn->query("SELECT id, label_he FROM poster_types ORDER BY sort_order ASC");
if ($types_result->num_rows > 0) {
    while($row = $types_result->fetch_assoc()) {
        $types_list[] = $row;
    }
}

// --- לוגיקה לבחירת פוסטר אקראי (עם או בלי סינון) ---
$random_movie = null;
$result_title = '';

// בניית שאילתה דינמית על בסיס הפילטרים
$sql_conditions = [];
$params = [];
$types = "";

if (isset($_GET['spin'])) {
    $result_title = 'תוצאת הרולטה:';
    if (!empty($_GET['min_year'])) { $sql_conditions[] = "p.year >= ?"; $params[] = (int)$_GET['min_year']; $types .= "i"; }
    if (!empty($_GET['max_year'])) { $sql_conditions[] = "p.year <= ?"; $params[] = (int)$_GET['max_year']; $types .= "i"; }
    if (!empty($_GET['type_id'])) { $sql_conditions[] = "p.type_id = ?"; $params[] = (int)$_GET['type_id']; $types .= "i"; }
    if (!empty($_GET['min_rating'])) { $sql_conditions[] = "p.imdb_rating >= ?"; $params[] = (float)$_GET['min_rating']; $types .= "d"; }
} else {
    $result_title = 'ההצעה האקראית שלנו:';
}

$where_clause = !empty($sql_conditions) ? 'WHERE ' . implode(' AND ', $sql_conditions) : '';

// שאילתה משודרגת שכוללת תמיד תגיות וז'אנר
$sql = "SELECT p.*, GROUP_CONCAT(ut.genre SEPARATOR ', ') AS user_tags
        FROM posters p
        LEFT JOIN user_tags ut ON p.id = ut.poster_id
        $where_clause 
        GROUP BY p.id
        ORDER BY RAND() 
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) { $random_movie = $result->fetch_assoc(); }
$stmt->close();


// --- שליפת טריילר אקראי נוסף ---
$random_trailer = null;
// שאילתה משודרגת שכוללת גם את מזהה הפוסטר
$trailer_sql = "SELECT id, title_he, title_en, youtube_trailer 
                FROM posters 
                WHERE youtube_trailer IS NOT NULL AND youtube_trailer != '' AND youtube_trailer != '0' 
                ORDER BY RAND() 
                LIMIT 1";
$trailer_res = $conn->query($trailer_sql);
if ($trailer_res && $trailer_res->num_rows > 0) {
    $random_trailer = $trailer_res->fetch_assoc();
    $random_trailer['video_id'] = extractYoutubeId($random_trailer['youtube_trailer']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🎲 רולטת סרטים</title>
    <style>
        body { text-align: center; }
        .roulette-container { max-width: 900px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; flex-direction: column; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 30px; }
        .filter-row { display: flex; gap: 10px; justify-content: center; width: 100%; }
        .filter-form input { padding: 10px; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; }
        .filter-form input[type="number"] { width: 220px; }
        .type-buttons { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .type-btn { padding: 8px 16px; font-size: 1em; border: 1px solid #ccc; border-radius: 20px; background-color: #f0f0f0; color: #333; text-decoration: none; transition: background-color 0.2s, color 0.2s; }
        .type-btn.active, .type-btn:hover { background-color: #333; color: white; border-color: #333; }
        .spin-button { padding: 12px 30px; font-size: 1.2em; font-weight: bold; background-color: #e62429; color: white; border: none; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
        .spin-button:hover { transform: scale(1.05); }
        .result-card, .random-trailer-section { margin-top: 30px; border-top: 1px solid #eee; padding-top: 30px; }
        .result-content { display: flex; gap: 20px; text-align: right; }
        .result-content img { width: 150px; height: auto; border-radius: 4px; }
        .result-details h2, .random-trailer-section h3 { margin-top: 0; font-size: 1.8em; color: #333; }
        .result-details .year { font-weight: bold; color: #e62429; }
        
        /* עיצובים חדשים לתגיות ולכותרת הטריילר */
        .result-details .tags-container { margin-top: 10px; display:flex; align-items:center; gap: 8px; flex-wrap:wrap; }
        .result-details .tag { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .result-details .genre { background-color: #333; color: #ccc; }
        .result-details .user-tag { background-color: #4a2d3c; color: white; }
        
        .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000; border-radius: 8px; margin: 10px auto; }
        .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .trailer-caption { font-size: 1.1em; color: #333; margin-top: 10px; }
        .trailer-caption a { color: #007bff; text-decoration: none; font-weight: bold; }
        .trailer-caption .title-en { color: #666; font-size: 0.9em; display: block; }

    </style>
</head>
<body>

<div class="roulette-container">
    <h1>🎲 רולטת סרטים</h1>
    <p>לא יודעים מה לראות? הגדירו את העדפותיכם או קבלו הצעה אקראית!</p>
    
    <form action="random.php" method="GET" class="filter-form">
        <div class="type-buttons">
            <a href="<?= strtok($_SERVER["REQUEST_URI"],'?') ?>" class="type-btn <?= (!isset($_GET['type_id']) || empty($_GET['type_id'])) ? 'active' : '' ?>">הכל</a>
            <?php foreach ($types_list as $type): ?>
                <a href="?type_id=<?= $type['id'] ?>" class="type-btn <?= (isset($_GET['type_id']) && $_GET['type_id'] == $type['id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($type['label_he']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="filter-row">
            <input type="number" name="min_rating" step="0.1" min="1" max="10" placeholder="דירוג IMDb מינימלי" value="<?= htmlspecialchars($_GET['min_rating'] ?? '') ?>">
            <input type="number" name="min_year" placeholder="משנה (לדוגמה: 1980)" value="<?= htmlspecialchars($_GET['min_year'] ?? '') ?>">
            <input type="number" name="max_year" placeholder="עד שנה (לדוגמה: 2024)" value="<?= htmlspecialchars($_GET['max_year'] ?? '') ?>">
        </div>
        <input type="hidden" name="type_id" value="<?= htmlspecialchars($_GET['type_id'] ?? '') ?>">
        <button type="submit" name="spin" class="spin-button">סובב את הרולטה!</button>
    </form>

    <div class="result-card">
        <?php if ($random_movie): ?>
            <h2><?= $result_title ?></h2>
            <div class="result-content">
                <a href="poster.php?id=<?= $random_movie['id'] ?>">
                    <img src="<?= htmlspecialchars($random_movie['image_url'] ?: 'images/no-poster.png') ?>" alt="Poster">
                </a>
                <div class="result-details">
                    <p class="year"><?= htmlspecialchars($random_movie['year']) ?></p>
                    <h3><?= htmlspecialchars($random_movie['title_he'] ?: $random_movie['title_en']) ?></h3>
                    <p><?= htmlspecialchars($random_movie['plot_he'] ?: $random_movie['plot']) ?></p>
                    
                    <div class="tags-container">
                        <?php if (!empty($random_movie['genre'])): ?>
                            <span class="tag genre"><?= htmlspecialchars($random_movie['genre']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($random_movie['user_tags'])): ?>
                            <span class="tag user-tag"><?= htmlspecialchars($random_movie['user_tags']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif(isset($_GET['spin'])): ?>
            <h2>תוצאת הרולטה:</h2>
            <p>😢 לא נמצא סרט שעונה על הדרישות. נסה סינון אחר.</p>
        <?php endif; ?>
    </div>
    
    <?php if ($random_trailer && !empty($random_trailer['video_id'])): ?>
        <div class="random-trailer-section">
            <h3>🎞️ טריילר אקראי לצפייה</h3>
            <div class="video-container">
                <iframe 
                    src="https://www.youtube.com/embed/<?= htmlspecialchars($random_trailer['video_id']) ?>" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
            <p class="trailer-caption">
                <a href="poster.php?id=<?= htmlspecialchars($random_trailer['id']) ?>">
                    <span><?= htmlspecialchars($random_trailer['title_he']) ?></span><br>
                    <span class="title-en"><?= htmlspecialchars($random_trailer['title_en']) ?></span>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
</div>

<?php include 'footer.php'; ?>
</body>
</html>