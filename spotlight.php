<?php
include 'header.php'; 
require_once 'server.php';

// --- 1. שליפת פוסטרים שנוספו לאחרונה ---
$recently_added = [];
$res_recent = $conn->query("SELECT * FROM posters ORDER BY created_at DESC LIMIT 10");
if ($res_recent) {
    while($row = $res_recent->fetch_assoc()) {
        $recently_added[] = $row;
    }
}

// --- 2. שליפת פוסטרים משנים עגולות ---
$current_year = date('Y');
$target_years = [$current_year - 10, $current_year - 20, $current_year - 30, $current_year - 40];
$placeholders = implode(',', array_fill(0, count($target_years), '?'));
$types = str_repeat('i', count($target_years));
$on_this_year = [];
$sql_years = "SELECT * FROM posters WHERE year IN ($placeholders) ORDER BY RAND() LIMIT 10";
$stmt_years = $conn->prepare($sql_years);
if ($stmt_years) {
    $stmt_years->bind_param($types, ...$target_years);
    $stmt_years->execute();
    $res_years = $stmt_years->get_result();
    while($row = $res_years->fetch_assoc()) {
        $on_this_year[] = $row;
    }
    $stmt_years->close();
}

// --- 3. שליפת אוסף אקראי ---
$featured_collection = null;
$featured_collection_posters = [];
$res_coll = $conn->query("SELECT * FROM collections ORDER BY RAND() LIMIT 1");
if ($res_coll && $res_coll->num_rows > 0) {
    $featured_collection = $res_coll->fetch_assoc();
    $coll_id = $featured_collection['id'];
    $res_coll_posters = $conn->query("SELECT p.* FROM posters p JOIN poster_collections pc ON p.id = pc.poster_id WHERE pc.collection_id = $coll_id ORDER BY RAND() LIMIT 10");
    if ($res_coll_posters) {
        while($row = $res_coll_posters->fetch_assoc()) {
            $featured_collection_posters[] = $row;
        }
    }
}

// --- 4. שליפת הפוסטרים האהובים של השבוע ---
$most_liked_weekly = [];
$res_liked = $conn->query("
    SELECT p.*, COUNT(pv.id) as vote_count FROM posters p
    JOIN poster_votes pv ON p.id = pv.poster_id
    WHERE pv.vote_type = 'like' AND pv.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY p.id ORDER BY vote_count DESC, RAND() LIMIT 10
");
if ($res_liked) {
    while($row = $res_liked->fetch_assoc()) {
        $most_liked_weekly[] = $row;
    }
}

// --- 5. זרקור על כוכב/ת ---
$featured_actor_name = null;
$featured_actor_posters = [];
$all_actors_str_res = $conn->query("SELECT GROUP_CONCAT(actors SEPARATOR ',') as all_actors FROM posters WHERE actors IS NOT NULL AND actors != ''");
if ($all_actors_str_res) {
    $all_actors_str = $all_actors_str_res->fetch_assoc()['all_actors'];
    $all_actors_array = array_filter(array_unique(array_map('trim', explode(',', $all_actors_str))));
    if (!empty($all_actors_array)) {
        $featured_actor_name = $all_actors_array[array_rand($all_actors_array)];
        if ($featured_actor_name) {
            $actor_stmt = $conn->prepare("SELECT * FROM posters WHERE actors LIKE ? ORDER BY RAND() LIMIT 10");
            $like_actor = '%' . $conn->real_escape_string($featured_actor_name) . '%';
            $actor_stmt->bind_param("s", $like_actor);
            $actor_stmt->execute();
            $res_actor = $actor_stmt->get_result();
            while($row = $res_actor->fetch_assoc()) {
                $featured_actor_posters[] = $row;
            }
            $actor_stmt->close();
        }
    }
}

// --- 6. הצמרת של IMDb ---
$top_rated = [];
$res_top_rated = $conn->query("SELECT * FROM posters WHERE imdb_rating > 0 ORDER BY imdb_rating DESC, id DESC LIMIT 10");
if ($res_top_rated) {
    while($row = $res_top_rated->fetch_assoc()) {
        $top_rated[] = $row;
    }
}

// --- 7. קולנוע מרחבי העולם ---
$featured_country_name = null;
$featured_country_posters = [];
$all_countries_str_res = $conn->query("SELECT GROUP_CONCAT(countries SEPARATOR ',') as all_countries FROM posters WHERE countries IS NOT NULL AND countries != ''");
if ($all_countries_str_res) {
    $all_countries_str = $all_countries_str_res->fetch_assoc()['all_countries'];
    $all_countries_array = array_filter(array_unique(array_map('trim', explode(',', $all_countries_str))));
     if (!empty($all_countries_array)) {
        $featured_country_name = $all_countries_array[array_rand($all_countries_array)];
        if ($featured_country_name) {
            $country_stmt = $conn->prepare("SELECT * FROM posters WHERE countries LIKE ? ORDER BY RAND() LIMIT 10");
            $like_country = '%' . $conn->real_escape_string($featured_country_name) . '%';
            $country_stmt->bind_param("s", $like_country);
            $country_stmt->execute();
            $res_country = $country_stmt->get_result();
            while($row = $res_country->fetch_assoc()) {
                $featured_country_posters[] = $row;
            }
            $country_stmt->close();
        }
    }
}

// --- 8. תגיות פופולריות ---
$popular_tag_name = null;
$popular_tag_posters = [];
$res_pop_tag = $conn->query("SELECT genre, COUNT(*) as tag_count FROM user_tags GROUP BY genre ORDER BY tag_count DESC LIMIT 1");
if ($res_pop_tag && $res_pop_tag->num_rows > 0) {
    $popular_tag_name = $res_pop_tag->fetch_assoc()['genre'];
    if ($popular_tag_name) {
        $tag_stmt = $conn->prepare("SELECT p.* FROM posters p JOIN user_tags ut ON p.id = ut.poster_id WHERE ut.genre = ? ORDER BY RAND() LIMIT 10");
        $tag_stmt->bind_param("s", $popular_tag_name);
        $tag_stmt->execute();
        $res_tag = $tag_stmt->get_result();
        while($row = $res_tag->fetch_assoc()) {
            $popular_tag_posters[] = $row;
        }
        $tag_stmt->close();
    }
}

$conn->close();

function render_poster_carousel($posters, $carousel_id) {
    if (empty($posters)) {
        echo '<p class="empty-widget">לא נמצאו פריטים להצגה בקטגוריה זו.</p>';
        return;
    }
    echo '<div class="widget-carousel" id="'. $carousel_id .'">';
    foreach ($posters as $poster) {
        $img_url = htmlspecialchars($poster['image_url'] ?: 'images/no-poster.png');
        $title = htmlspecialchars($poster['title_he'] ?: $poster['title_en']);
        $year = htmlspecialchars($poster['year']);
        $link = "poster.php?id=" . $poster['id'];
        echo "<a href='{$link}' class='carousel-item'><img src='{$img_url}' alt='Poster for {$title}' loading='lazy'><div class='carousel-item-title'>{$title} ({$year})</div></a>";
    }
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🎯 זרקור יומי</title>
    <style>
        .page-title { text-align: center; }
        .spotlight-container { max-width: 1200px; margin: 20px auto; }
        .widget { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px; }
        
        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .widget-header h2 { margin: 0; padding: 0; border: none; font-size: 1.5em; }
        .carousel-nav { display: flex; gap: 8px; }
        .carousel-arrow {
            background-color: #f0f0f0; color: #333; border: 1px solid #ddd;
            border-radius: 50%; width: 30px; height: 30px;
            font-size: 20px; font-weight: bold; cursor: pointer; line-height: 28px;
            transition: background-color 0.2s, color 0.2s;
        }
        .carousel-arrow:hover { background-color: #333; color: white; }
        .carousel-arrow:disabled { opacity: 0.3; cursor: not-allowed; }
        
        .widget-carousel {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            padding-bottom: 15px;
            scroll-behavior: smooth;
        }
        .carousel-item { flex: 0 0 160px; text-decoration: none; color: #333; transition: transform 0.2s; }
        .carousel-item:hover { transform: scale(1.05); }
        .carousel-item img { width: 100%; height: 240px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .carousel-item-title { font-size: 0.9em; margin-top: 8px; text-align: center; }
        .empty-widget { color: #888; }
        .widget-header a.collection-link { font-size: 0.8em; }
    </style>
</head>
<body>

<h1 class="page-title">🎯 זרקור יומי</h1>

<div class="spotlight-container">
    <div class="widget">
        <div class="widget-header">
            <h2>🆕 נוספו לאחרונה</h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-recent">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-recent">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($recently_added, 'carousel-recent'); ?>
    </div>
    
    <div class="widget">
        <div class="widget-header">
            <h2>🏆 הצמרת של IMDb</h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-top-rated">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-top-rated">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($top_rated, 'carousel-top-rated'); ?>
    </div>

    <?php if ($featured_actor_name): ?>
    <div class="widget">
        <div class="widget-header">
            <h2>🌟 זרקור על: <?= htmlspecialchars($featured_actor_name) ?></h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-actor">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-actor">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($featured_actor_posters, 'carousel-actor'); ?>
    </div>
    <?php endif; ?>

    <div class="widget">
        <div class="widget-header">
            <h2>📅 השנה בהיסטוריה: מבט לאחור</h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-history">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-history">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($on_this_year, 'carousel-history'); ?>
    </div>

    <?php if ($featured_collection): ?>
    <div class="widget">
        <div class="widget-header">
            <h2>
                🌟 אוסף היום: <?= htmlspecialchars($featured_collection['name']) ?>
                <a href="collection.php?id=<?= $featured_collection['id'] ?>" class="collection-link">(לכל האוסף)</a>
            </h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-collection">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-collection">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($featured_collection_posters, 'carousel-collection'); ?>
    </div>
    <?php endif; ?>

    <div class="widget">
        <div class="widget-header">
            <h2>❤️ האהובים של השבוע</h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-liked">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-liked">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($most_liked_weekly, 'carousel-liked'); ?>
    </div>

    <?php if ($featured_country_name): ?>
    <div class="widget">
        <div class="widget-header">
            <h2>🌍 קולנוע מרחבי העולם: <?= htmlspecialchars($featured_country_name) ?></h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-country">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-country">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($featured_country_posters, 'carousel-country'); ?>
    </div>
    <?php endif; ?>

    <?php if ($popular_tag_name): ?>
    <div class="widget">
        <div class="widget-header">
            <h2>📝 תגיות פופולריות: #<?= htmlspecialchars($popular_tag_name) ?></h2>
            <div class="carousel-nav">
                <button class="carousel-arrow prev" data-target="#carousel-tags">&#8249;</button>
                <button class="carousel-arrow next" data-target="#carousel-tags">&#8250;</button>
            </div>
        </div>
        <?php render_poster_carousel($popular_tag_posters, 'carousel-tags'); ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.carousel-arrow').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const carousel = document.querySelector(targetId);
            if (carousel) {
                const scrollAmount = carousel.clientWidth * 0.8;
                carousel.scrollBy({ 
                    left: this.classList.contains('next') ? scrollAmount : -scrollAmount, 
                    behavior: 'smooth' 
                });
            }
        });
    });
});
</script>

</body>
</html>
<?php include 'footer.php'; ?>