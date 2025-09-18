<?php
include 'header.php';
require_once 'server.php';

function extractYoutubeId($url) {
    if (preg_match('/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) return $matches[1];
    return '';
}

// --- שליפת נתונים עבור הפילטרים ---
$types_list = [];
$types_result = $conn->query("SELECT id, label_he, icon, image FROM poster_types ORDER BY sort_order ASC");
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

$sql = "SELECT p.*, GROUP_CONCAT(DISTINCT ut.genre SEPARATOR ', ') AS user_tags
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
$trailer_sql = "SELECT id, title_he, title_en, youtube_trailer, year
                 FROM posters
                 WHERE youtube_trailer IS NOT NULL AND youtube_trailer != '' AND youtube_trailer != '0'
                 ORDER BY RAND()
                 LIMIT 1";
$trailer_res = $conn->query($trailer_sql);
if ($trailer_res && $trailer_res->num_rows > 0) {
    $random_trailer = $trailer_res->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🎲 רולטת סרטים</title>
    <style>
        body { text-align: center; }
        .roulette-container { max-width: 1200px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; flex-direction: column; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 30px; }
        .filter-row { display: flex; gap: 10px; justify-content: center; width: 100%; }
        .filter-form input, .filter-form select { padding: 10px; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; }
        .filter-form input[type="number"] { width: 220px; }

        .type-buttons { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .type-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 12px;
            font-size: 1em;
            border: 2px solid transparent;
            border-radius: 8px;
            background-color: transparent;
            color: #333;
            cursor: pointer;
            transition: border-color 0.2s, transform 0.2s;
        }
        .type-btn:hover {
            transform: scale(1.05);
        }
        .type-btn.active {
            border-color: #007bff;
            color: #e62429;
        }
        .type-btn img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        .type-btn .icon {
            font-size: 48px;
            width: 48px;
            height: 48px;
            line-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .spin-button { padding: 12px 30px; font-size: 1.2em; font-weight: bold; background-color: #e62429; color: white; border: none; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
        .spin-button:hover { transform: scale(1.05); }

        .result-card, .random-trailer-section {
            margin: 30px auto;
            border-top: 1px solid #eee;
            padding-top: 30px;
        }
        .result-card h2, .random-trailer-section h3 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.8em;
            color: #333;
        }
        .result-content {
            display: flex;
            flex-direction: row-reverse; /* טריילר בצד ימין, פוסטר ופרטים בצד שמאל */
            align-items: flex-start;
            gap: 20px;
            justify-content: center;
        }
        .result-details-wrap {
            display: flex;
            gap: 20px;
            text-align: right;
        }
        .result-content .trailer-box {
            width: 600px;
            /* order: 1; - כבר מוגדר על ידי flex-direction: row-reverse; */
        }
        .result-content .poster-details-box {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            /* order: 2; - כבר מוגדר על ידי flex-direction: row-reverse; */
        }
        .result-content img { width: 150px; height: auto; border-radius: 4px; }
        .result-details { text-align: right; }
        .result-details .year { font-weight: bold; color: #e62429; }

        .result-details .tags-container { margin-top: 10px; display:flex; align-items:center; gap: 8px; flex-wrap:wrap; }
        .result-details .tag { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .result-details .genre { background-color: #eee; color: #333; border: 1px solid #ccc; }
        .result-details .user-tag { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .trailer-wrap {
            display: flex;
            justify-content: center;
            max-width: 100%;
        }
        .trailer-embed {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }
        .trailer-embed.has-yt {
            aspect-ratio: 16 / 9;
            width: 600px;
            background: #000;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .trailer-embed.has-yt iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>
</head>
<body>

<div class="roulette-container">
    <h1>🎲 רולטת סרטים</h1>
    <p>לא יודעים מה לראות? הגדירו את העדפותיכם או קבלו הצעה אקראית!</p>

    <form action="random.php" method="GET" class="filter-form">
        <div class="type-buttons">
            <button type="button" class="type-btn <?= (!isset($_GET['type_id']) || empty($_GET['type_id'])) ? 'active' : '' ?>" data-type-id="">
                <div class="icon"><img src="images/types/posters.png" alt="">
                </div>
                <span>הכל</span>
            </button>
            <?php foreach ($types_list as $type): ?>
                <button type="button"
                        class="type-btn <?= (isset($_GET['type_id']) && $_GET['type_id'] == $type['id']) ? 'active' : '' ?>"
                        data-type-id="<?= $type['id'] ?>">
                    <?php if (!empty($type['image'])): ?>
                        <img src="images/types/<?= htmlspecialchars($type['image']) ?>" alt="<?= htmlspecialchars($type['label_he']) ?>">
                    <?php else: ?>
                        <div class="icon"><?= htmlspecialchars($type['icon']) ?></div>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($type['label_he']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="filter-row">
            <input type="number" name="min_rating" step="0.1" min="1" max="10" placeholder="דירוג IMDb מינימלי" value="<?= htmlspecialchars($_GET['min_rating'] ?? '') ?>">
            <input type="number" name="min_year" placeholder="משנה (לדוגמה: 1980)" value="<?= htmlspecialchars($_GET['min_year'] ?? '') ?>">
            <input type="number" name="max_year" placeholder="עד שנה (לדוגמה: 2024)" value="<?= htmlspecialchars($_GET['max_year'] ?? '') ?>">
        </div>
        <input type="hidden" name="type_id" id="type_id_hidden" value="<?= htmlspecialchars($_GET['type_id'] ?? '') ?>">
        <button type="submit" name="spin" class="spin-button">סובב את הרולטה!</button>
    </form>

    <div class="result-card">
        <?php if ($random_movie): ?>
            <h2 style="text-align: center;"><?= $result_title ?></h2>
            <div class="result-content">
                <div class="trailer-box">
                    <?php if (!empty($random_movie['youtube_trailer'])): ?>
                        <div class="trailer-wrap">
                            <div class="trailer-embed has-yt">
                                <iframe
                                    src="https://www.youtube.com/embed/<?= htmlspecialchars(extractYoutubeId($random_movie['youtube_trailer'])) ?>"
                                    title="Trailer"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen>
                                </iframe>
                            </div>
                        </div>
                        <a href="poster.php?id=<?= $random_movie['id'] ?>" style="display:block; text-align:center; margin-top:8px;">
                            <span>צפה בעמוד הפוסטר: </span>
                            <strong><?= htmlspecialchars($random_movie['title_he'] ?: $random_movie['title_en']) ?></strong>
                            <?php if (!empty($random_movie['title_en'])): ?>
                                (<?= htmlspecialchars($random_movie['title_en']) ?>)
                            <?php endif; ?>
                            <span class="year">[<?= htmlspecialchars($random_movie['year'] ?? '') ?>]</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="poster-details-box">
                    <a href="poster.php?id=<?= $random_movie['id'] ?>">
                        <img src="<?= htmlspecialchars($random_movie['image_url'] ?: 'images/no-poster.png') ?>" alt="Poster">
                    </a>
                    <div class="result-details">
                        <p class="year"><?= htmlspecialchars($random_movie['year']) ?></p>
                        <h3><?= htmlspecialchars($random_movie['title_he'] ?: $random_movie['title_en']) ?></h3>
                        <p><?= htmlspecialchars($random_movie['plot_he'] ?: $random_movie['plot']) ?></p>

                        <div class="tags-container">
                            <?php if (!empty($random_movie['genres'])): ?>
                                <span class="tag genre"><?= htmlspecialchars($random_movie['genres']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($random_movie['user_tags'])): ?>
                                <span class="tag user-tag"><?= htmlspecialchars($random_movie['user_tags']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(isset($_GET['spin'])): ?>
            <h2 style="text-align: center;">תוצאת הרולטה:</h2>
            <p>😢 לא נמצא סרט שעונה על הדרישות. נסה סינון אחר.</p>
        <?php endif; ?>
    </div>

    <?php if ($random_trailer && !empty($random_trailer['youtube_trailer'])): ?>
        <div class="random-trailer-section">
            <h3 style="margin: 30px auto 8px; text-align: center;">טריילר אקראי נוסף</h3>
            <div class="trailer-wrap">
                <div class="trailer-embed has-yt">
                    <iframe
                        src="https://www.youtube.com/embed/<?= htmlspecialchars(extractYoutubeId($random_trailer['youtube_trailer'])) ?>"
                        title="Trailer"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
            <a href="poster.php?id=<?= $random_trailer['id'] ?>" style="display:block; text-align:center; margin-top:8px;">
                <span>צפה בעמוד הפוסטר: </span>
                <strong><?= htmlspecialchars($random_trailer['title_he'] ?: $random_trailer['title_en']) ?></strong>
                <?php if (!empty($random_trailer['title_en'])): ?>
                    (<?= htmlspecialchars($random_trailer['title_en']) ?>)
                <?php endif; ?>
                <span class="year">[<?= htmlspecialchars($random_trailer['year'] ?? '') ?>]</span>
            </a>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeButtonsContainer = document.querySelector('.type-buttons');
    const hiddenTypeInput = document.getElementById('type_id_hidden');

    typeButtonsContainer.addEventListener('click', function(event) {
        if (event.target.closest('.type-btn')) {
            const clickedButton = event.target.closest('.type-btn');

            typeButtonsContainer.querySelectorAll('.type-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            clickedButton.classList.add('active');

            hiddenTypeInput.value = clickedButton.dataset.typeId;
        }
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>