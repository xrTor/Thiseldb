
<?php include 'header.php'; 
require_once 'server.php';

// פונקציית עזר לחילוץ מזהה הטריילר
function extractYoutubeId($url) {
  if (preg_match('/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) return $matches[1];
  return '';
}

// --- שלב 2: שליפת רשימת כל האוספים עבור תפריט הבחירה ---
$collections_list = [];
$sql_collections = "SELECT id, name FROM collections ORDER BY name ASC";
$result_collections = $conn->query($sql_collections);
if ($result_collections->num_rows > 0) {
    while($row = $result_collections->fetch_assoc()) {
        $collections_list[] = $row;
    }
}

// --- שלב 3: קביעת האוסף הנבחר וסדר המיון ---
$selected_collection_id = null;
$selected_collection_name = "בחר אוסף";
$sort_order = 'ASC'; 
$sort_order_sql = 'p.year ASC';

if (isset($_GET['sort']) && $_GET['sort'] === 'DESC') {
    $sort_order = 'DESC';
    $sort_order_sql = 'p.year DESC';
}

// פיצ'ר חדש: קביעת האוסף הנבחר - נותן עדיפות להזנה ידנית
if (isset($_GET['collection_id_manual']) && !empty($_GET['collection_id_manual'])) {
    $selected_collection_id = (int)$_GET['collection_id_manual'];
} elseif (isset($_GET['collection_id']) && !empty($_GET['collection_id'])) {
    $selected_collection_id = (int)$_GET['collection_id'];
}

// --- שלב 4: שליפת הסרטים השייכים לאוסף הנבחר ---
$movies = [];
if ($selected_collection_id) {
    $foundName = false;
    foreach ($collections_list as $collection) {
        if ($collection['id'] == $selected_collection_id) {
            $selected_collection_name = $collection['name'];
            $foundName = true;
            break;
        }
    }
    if (!$foundName) {
        $selected_collection_name = "אוסף #" . $selected_collection_id;
    }
    
    $sql_movies = "SELECT 
                        p.id, p.title_he, p.title_en, p.year, p.plot_he, p.image_url, p.youtube_trailer, p.genre,
                        GROUP_CONCAT(ut.genre SEPARATOR ', ') AS user_tags
                   FROM posters AS p
                   JOIN poster_collections AS pc ON p.id = pc.poster_id
                   LEFT JOIN user_tags AS ut ON p.id = ut.poster_id
                   WHERE pc.collection_id = ?
                   GROUP BY p.id
                   ORDER BY $sort_order_sql";
    
    $stmt = $conn->prepare($sql_movies);
    $stmt->bind_param("i", $selected_collection_id);
    $stmt->execute();
    $result_movies = $stmt->get_result();

    if ($result_movies->num_rows > 0) {
        while($row = $result_movies->fetch_assoc()) {
            $movies[] = $row;
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ציר זמן: <?= htmlspecialchars($selected_collection_name) ?></title>
    <style>
        :root {
            --background-color: #101010;
            --text-color: #f0f0f0;
            --primary-color: #e62429;
            --card-bg: #1c1c1c;
            --timeline-line-color: #333;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 40px 20px;
        }
        .universe-header { text-align: center; margin-bottom: 20px; }
        .universe-header h1 { font-size: 3.5em; margin: 0; }
        
        .selection-form { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 15px; max-width: 900px; margin: 0 auto 50px auto; padding: 20px; background-color: var(--card-bg); border-radius: 8px; }
        .form-group { display: flex; align-items: center; gap: 10px; }
        .selection-form label { font-size: 1.1em; }
        .selection-form select, .selection-form input[type="text"] {
            background-color: #333; color: var(--text-color); padding: 10px;
            border: 1px solid #555; border-radius: 4px; font-size: 1em;
        }
        .selection-form input[type="text"] { width: 80px; }
        .selection-form select {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23c5c5c5%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E');
            background-repeat: no-repeat; background-position: left 1rem center;
            background-size: .8em auto; padding-right: 15px;
        }
        .selection-form .sort-buttons a {
            padding: 11px 15px; border: 1px solid #555; background-color: #333;
            color: white!important; font-size: 1em; border-radius: 4px;
            cursor: pointer; text-decoration: none; display: inline-block;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .selection-form .sort-buttons a.active {
            background-color: var(--primary-color); border-color: var(--primary-color);
            color: white!important; font-weight: bold;
        }

        .timeline-container { position: relative; max-width: 1200px; margin: 0 auto; }
        .timeline-container::before { content: ''; position: absolute; width: 6px; background-color: var(--timeline-line-color); top: 0; bottom: 0; left: 50%; margin-left: -3px; }
        .timeline-item { position: relative; margin-bottom: 50px; width: 100%; display: flex; }
        .timeline-item.right { justify-content: flex-end; }
        .timeline-item.left { justify-content: flex-start; }
        .timeline-content { padding: 20px; background: var(--card-bg); border-radius: 8px; display: flex; gap: 20px; align-items: flex-start; width: calc(50% - 42px); box-sizing: border-box; position: relative; }
        .timeline-content::after { content: ''; position: absolute; width: 25px; height: 25px; border-radius: 50%; background-color: var(--background-color); border: 4px solid var(--primary-color); top: 15px; z-index: 1; }
        .timeline-item.right .timeline-content::after { right: -37px; }
        .timeline-item.left .timeline-content::after { right: -39px; }
        
        /* תיקון: הסרת הכלל שהזיז את התמונה לשמאל */
        .timeline-item.right .timeline-content,
        .timeline-item.left .timeline-content {
            /* flex-direction: row-reverse; <-- This problematic line is now removed */
        }
        
        .timeline-content a.poster-link { display: block; }
        .timeline-content img.poster { width: 110px; height: auto; border-radius: 0px; display: block; }
        .timeline-content .text-content { flex: 1; text-align: right;}
        .timeline-content h2 { margin-top: 0; font-size: 1.7em; margin-bottom: 5px; }
        .timeline-content .title-en { font-size: 0.9em; color: #aaa; margin-bottom: 15px; }
        .timeline-content .year { font-size: 1.2em; font-weight: bold; color: var(--primary-color); margin-bottom: 10px; }
        .timeline-content .tags-container { margin-top: 15px; display:flex; align-items:center; gap: 8px; flex-wrap:wrap; }
        .timeline-content .genre, .timeline-content .user-tag { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
        .timeline-content .genre { background-color: #333; color: #ccc; }
        .timeline-content .user-tag { background-color: #4a2d3c; color: white /*#f2a6d8*/; }
        
        /* תיקון: הוחזר העיצוב האדום לכפתור הטריילר */
        .timeline-content .trailer-button {
            cursor: pointer; display: inline-block; margin-top: 15px;
            padding: 8px 15px; background-color: var(--primary-color);
            color: white; text-decoration: none; border: none;
            border-radius: 5px; font-weight: bold; font-family: inherit;
            font-size: 1em; transition: background-color 0.2s;
        }
        .timeline-content .trailer-button:hover { background-color: #b81c21; }
        
        .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.85); justify-content: center; align-items: center; }
        .modal-overlay.visible { display: flex; }
        .modal-content { position: relative; width: 90%; max-width: 800px; }
        .modal-close-button { position: absolute; top: -40px; right: 0; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer; }
        .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000; }
        .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
     body,  {
color:white;
      text-align: right!important;
    } 
      /* ============================================== */
    /* ==   סגנונות שנלקחו מהקובץ w3.css   == */
    /* ============================================== */

    /* הגדרות בסיס ואיפוס */
    html {
        box-sizing: border-box;
    }
    *, *:before, *:after {
        box-sizing: inherit;
    }
    
    /* .w3-bar */
    .w3-bar {
        width: 100%;
        overflow: hidden;
    }
    .w3-bar .w3-bar-item {
        padding: 8px 16px;
        float: left; /* הדפדפן הופך אוטומטית לימין ב-RTL */
        width: auto;
        border: none;
        display: block;
        outline: 0;
    }
    .w3-bar .w3-button {
        color: white !important;;
        white-space: normal;
    }
    .w3-bar:before, .w3-bar:after {
        content: "";
        display: table;
        clear: both;
    }

    /* .w3-padding */
    .w3-padding {
        padding: 8px 16px !important;
    }

    /* .w3-button */
    .w3-button {
        border: none;
        display: inline-block;
        padding: 8px 16px;
        vertical-align: middle;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        text-align: center;
        cursor: pointer;
        white-space: nowrap;
    }
    
    /* צבעים */
    .w3-black, .w3-hover-black:hover {
        color: #fff !important;
        background-color: white;
    }
    .w3-white, .w3-hover-white:hover {
        color: #000 !important;
        background-color: #fff !important;
    }
    .white {color: #f1f1f1 !important;}
    .w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover{color:#000!important;background-color:#f1f1f1!important}
    /* .logo {  filter: saturate(500%) contrast(800%) brightness(500%) 
      invert(100%) sepia(50%) hue-rotate(120deg); }
        filter: saturate(500%) contrast(800%) brightness(500%) 
      invert(80%) sepia(50%) hue-rotate(120deg); } */
   </style>
</head>
<body style="text-align:center">
<br>
<form action="" method="GET" class="selection-form">
    <div class="form-group">
        <label for="collection_select">בחר אוסף:</label> 
        <select name="collection_id" id="collection_select" onchange="this.form.submit()">
            <option value="">-- בחר מהרשימה --</option>
            <?php foreach ($collections_list as $collection): ?>
                <option value="<?= $collection['id'] ?>" <?= ($collection['id'] == $selected_collection_id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($collection['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="collection_id_manual">או ID:</label> 
        <input type="text" name="collection_id_manual" id="collection_id_manual" placeholder="הזן מזהה" onchange="this.form.submit()">
    </div>
    
    <?php if ($selected_collection_id): ?>
    <div class="form-group sort-buttons">
        <label>סדר לפי:</label> 
        <a href="?collection_id=<?= $selected_collection_id ?>&sort=ASC" class="<?= ($sort_order === 'ASC') ? 'active' : '' ?>">ישן ← חדש</a>
        <a href="?collection_id=<?= $selected_collection_id ?>&sort=DESC" class="<?= ($sort_order === 'DESC') ? 'active' : '' ?>">חדש ← ישן</a>
    </div>
    <?php endif; ?>
</form>

<header class="universe-header"><h1><?= htmlspecialchars($selected_collection_name) ?></h1></header>

<div class="timeline-container">
    <?php if ($selected_collection_id && count($movies) > 0): ?>
        <?php foreach ($movies as $index => $movie): ?>
            <?php $position_class = ($index % 2 == 0) ? 'left' : 'right'; // הראשון מימין, השני משמאל ?>
            <div class="timeline-item <?= $position_class ?>">
                <div class="timeline-content">
                    <a href="poster.php?id=<?= $movie['id'] ?>" class="poster-link">
                        <img src="<?= htmlspecialchars($movie['image_url'] ?: 'images/no-poster.png') ?>" alt="Poster" class="poster">
                    </a>
                    <div class="text-content">
                        <div class="year"><?= htmlspecialchars($movie['year']) ?></div>
                        <h2><?= htmlspecialchars($movie['title_he']) ?></h2>
                        <div class="title-en"><?= htmlspecialchars($movie['title_en']) ?></div>

                        <p><?= htmlspecialchars($movie['plot_he']) ?></p>
                        
                        <div class="tags-container">
    <?php if (!empty($movie['genre'])): ?>
        <span class="genre"><?= htmlspecialchars($movie['genre']) ?></span>
    <?php endif; ?>
    
    <?php if (!empty($movie['user_tags'])): ?>
        <span class="user-tag"><?= htmlspecialchars($movie['user_tags']) ?></span>
    <?php endif; ?>
</div>

                        <?php
                        // תיקון: חילוץ המזהה לפני הדפסת הכפתור
                        $trailer_id = extractYoutubeId($movie['youtube_trailer']);
                        if (!empty($trailer_id)): 
                        ?>
                            <button class="trailer-button" data-videoid="<?= htmlspecialchars($trailer_id) ?>">צפה בטריילר</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="trailerModal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close-button">&times;</span>
        <div class="video-container" id="videoContainer"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('trailerModal');
        if (!modal) return;

        const videoContainer = document.getElementById('videoContainer');
        const closeButton = modal.querySelector('.modal-close-button');
        const trailerButtons = document.querySelectorAll('.trailer-button');

        function openModal(videoId) {
            videoContainer.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
            modal.classList.add('visible');
        }

        function closeModal() {
            modal.classList.remove('visible');
            videoContainer.innerHTML = '';
        }

        trailerButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const videoId = this.dataset.videoid;
                openModal(videoId);
            });
        });

        closeButton.addEventListener('click', closeModal);
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('visible')) {
                closeModal();
            }
        });
    });
</script>
<div style="color:white!important">
<?php include 'footer.php'; ?>
</div>
</body>
</html>
