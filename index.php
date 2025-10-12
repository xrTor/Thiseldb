<?php
// FILE: index.php (Universal Version)
// This file is identical for both admin and public servers.
// Its behavior is controlled by the version of style.css on the server.

require_once 'server.php';
include 'header.php';
include 'languages.php'; // Include for flags array

// --- Language to Flag Mapping ---
$lang_map = [];
foreach ($languages as $lang) {
    // Create a map that can find the correct data using either the code or the label
    $lang_data = ['code' => $lang['code'], 'label' => $lang['label'], 'flag' => $lang['flag']];
    $lang_map[strtolower($lang['code'])] = $lang_data;
    $lang_map[strtolower($lang['label'])] = $lang_data;
}

// --- Data Fetching Logic ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;

$count_query_part = "FROM posters p LEFT JOIN poster_types pt ON p.type_id = pt.id";
$where = [];
$params = [];
$types = '';

if (!empty($_GET['tag'])) {
    $where[] = "p.id IN (SELECT poster_id FROM poster_categories WHERE category_id = ?)";
    $params[] = intval($_GET['tag']);
    $types .= 'i';
}
if (!empty($_GET['year'])) {
    $where[] = "year LIKE ?";
    $params[] = "%" . $_GET['year'] . "%";
    $types .= 's';
}
if (!empty($_GET['type_id'])) {
    $where[] = "p.type_id = ?";
    $params[] = intval($_GET['type_id']);
    $types .= 'i';
}

$where_clause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

$count_sql = "SELECT COUNT(p.id) AS total " . $count_query_part . $where_clause;
$stmt_count = $conn->prepare($count_sql);
if ($types) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_rows / $limit));

if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$orderBy = "ORDER BY p.id DESC";
$sql = "SELECT p.*, pt.label_he, pt.image AS type_image " . $count_query_part . $where_clause . " $orderBy LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;

$user_tags_by_poster_id = [];
$manual_languages_by_poster_id = [];
$stickers_by_poster_id = [];

if (!empty($rows)) {
    $poster_ids = array_column($rows, 'id');
    $ids_placeholder = implode(',', array_fill(0, count($poster_ids), '?'));
    $ids_types = str_repeat('i', count($poster_ids));

    $ut_sql = "SELECT poster_id, genre FROM user_tags WHERE poster_id IN ($ids_placeholder)";
    $stmt_ut = $conn->prepare($ut_sql);
    $stmt_ut->bind_param($ids_types, ...$poster_ids);
    $stmt_ut->execute();
    $ut_result = $stmt_ut->get_result();
    while ($ut_row = $ut_result->fetch_assoc()) {
        $user_tags_by_poster_id[$ut_row['poster_id']][] = $ut_row['genre'];
    }

    $lang_sql = "SELECT poster_id, lang_code FROM poster_languages WHERE poster_id IN ($ids_placeholder)";
    $stmt_lang = $conn->prepare($lang_sql);
    $stmt_lang->bind_param($ids_types, ...$poster_ids);
    $stmt_lang->execute();
    $lang_result = $stmt_lang->get_result();
    while ($lang_row = $lang_result->fetch_assoc()) {
        $manual_languages_by_poster_id[$lang_row['poster_id']][] = $lang_row['lang_code'];
    }

    if (!empty($poster_ids)) {
        $sql_stickers = "SELECT pc.poster_id, c.poster_image_url, c.id as collection_id, c.name as collection_name
                         FROM poster_collections pc
                         JOIN collections c ON pc.collection_id = c.id
                         WHERE pc.poster_id IN ($ids_placeholder) AND c.poster_image_url IS NOT NULL AND c.poster_image_url <> ''";
        
        $stmt_stickers = $conn->prepare($sql_stickers);
        $stmt_stickers->bind_param($ids_types, ...$poster_ids);
        $stmt_stickers->execute();
        $stickers_result = $stmt_stickers->get_result();
        while ($sticker_row = $stickers_result->fetch_assoc()) {
            $stickers_by_poster_id[$sticker_row['poster_id']][] = $sticker_row;
        }
        $stmt_stickers->close();
    }
}
?>
<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>Thiseldb</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .poster { display: flex; flex-direction: column; }
    .poster-actions { margin-top: auto; padding-top: 8px; margin-bottom: 8px; display: flex; justify-content: center; align-items: center; direction: rtl; flex-wrap: wrap; }
    .poster-actions .separator { margin: 0 4px; color: #ccc; }
    .poster { width: 205px; }
    .poster:hover { position: relative; z-index: 10; transform: scale(1.25); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    body {background-color:#EDEDEE !important}
    .trailer-btn { background-color: #d92323; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-family: inherit; font-size: 13px; }
    .trailer-btn:hover { background-color: #b01c1c; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); padding-top: 60px; }
    .modal-content { position: relative; background-color: #181818; margin: auto; padding: 0; width: 90%; max-width: 800px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19); animation-name: animatetop; animation-duration: 0.4s; }
    @keyframes animatetop { from {top: -300px; opacity: 0} to {top: 0; opacity: 1} }
    .close-btn { color: white; float: left; font-size: 38px; font-weight: bold; line-height: 1; padding: 0 15px; }
    .close-btn:hover, .close-btn:focus { color: #999; text-decoration: none; cursor: pointer; }
    .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; }
    .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
    .poster-actions a { text-decoration: none; font-size: 18px; margin: 0 3px; }
    .poster-type-link { text-decoration: none; }
    .poster-type-display { font-size: 12px; color: #555; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 5px; padding: 5px 0; }
    .poster-tags { text-align: center; padding: 5px 0; }
    .tag-badge { display: inline-block; background: linear-gradient(to bottom, #f7f7f7, #e0e0e0); color: #333; padding: 4px 12px; border-radius: 16px; font-size: 12px; margin: 3px; text-decoration: none; font-weight: 500; border: 1px solid #ccc; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s ease-in-out; }
    .user-tag { background: linear-gradient(to bottom, #e3f2fd, #bbdefb); border-color: #90caf9; }
    .tag-badge:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .network-logo-container { display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; padding: 8px 0; }
    .flags-container { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 5px; }
    .network-logo-container:empty, .poster-tags:empty, .flags-container:empty { display: none; }
    .flag-row { width: 100%; display: flex; justify-content: center; align-items: center; gap: 4px; flex-wrap: wrap; }
    .flag-row + .flag-row { margin-top: 4px; }
    .collection-sticker-container { padding: 8px 0; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap; }
    .imdb-container img { height: 18px; width: auto; object-fit: contain; }
    .flags-container a { font-size: 16px; color: #333; font-weight: 500; }
  </style>
</head>
<body class="rtl">

<!-- <div style="width:100%; max-width:900px; margin:20px auto;">
    <iframe src="chat.php" style="width:100%; height:470px; border:1px solid #444; border-radius:10px;"></iframe>
</div> -->

<div style="text-align:center; margin:10px;">
  <button id="toggle-admin" style="padding:6px 12px; cursor:pointer;">🔑 מצב ניהול</button>
</div>

<div class="poster-wall" id="poster-wall">
<?php if (empty($rows) && $page === 1): ?>
  <p class="no-results">לא נמצאו תוצאות 😢</p>
<?php else: ?>
  <?php foreach ($rows as $row): ?>
    <div class="poster ltr">
      <a href="poster.php?id=<?= $row['id'] ?>">
        <img src="<?= htmlspecialchars((!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png') ?>" alt="<?= htmlspecialchars($row['title_en']) ?>" loading="lazy">
      </a> 

      <div class="poster-title ltr">
        <a href="poster.php?id=<?= $row['id'] ?>" style="text-decoration: none; color: inherit;">
            <b><?= htmlspecialchars($row['title_en']) ?>
              <?php if (!empty($row['title_he'])): ?><br><span style="color:#777;"><?= htmlspecialchars($row['title_he']) ?></span><?php endif; ?>
            </b>
        </a>
        <br>[<a href="home.php?year=<?= htmlspecialchars($row['year']) ?>"><?= $row['year'] ?></a>]
      </div>

      <div class="imdb-container">
        <?php if ($row['imdb_rating']): ?>
          <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank" style="display:inline-flex; align-items:center; gap:1px; text-decoration:none; color:inherit;">
            <img src="images/imdb.png" class="imdb ltr" alt="IMDb"> <span>⭐<?= htmlspecialchars($row['imdb_rating']) ?> / 10</span>
          </a>
        <?php endif; ?>
      </div>

      <?php if (!empty($row['label_he'])): ?>
        <a href="home.php?type[]=<?= htmlspecialchars($row['type_id']) ?>" class="poster-type-link">
            <div class="poster-type-display">
                <span><?= htmlspecialchars($row['label_he']) ?></span>
                <?php if (!empty($row['type_image'])): ?>
                    <img src="images/types/<?= htmlspecialchars($row['type_image']) ?>" alt="" style="max-height: 32px; width: auto; vertical-align: middle;">
                <?php endif; ?>
            </div>
        </a>
      <?php endif; ?>
      
      <div class="network-logo-container">
          <?php
            $networks = array_filter(array_map('trim', explode(',', $row['networks'] ?? '')));
            foreach ($networks as $net) {
                $slug = strtolower(preg_replace('/\s+/', '', $net));
                foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
                    $logoPath = "images/networks/{$slug}.{$ext}";
                    if (is_file($logoPath)) {
                        echo "<a href='home.php?network=" . urlencode($net) . "'>";
                        echo "<img src='" . htmlspecialchars($logoPath) . "' alt='" . htmlspecialchars($net) . "' style='max-width: 80px; max-height: 35px; width: auto; height: auto; object-fit: contain;'>";
                        echo "</a>";
                        break;
                    }
                }
            }
          ?>
      </div>
            
      <div class="poster-tags">
          <?php
            $official_genres = array_filter(array_map('trim', explode(',', $row['genres'] ?? '')));
            foreach ($official_genres as $genre):?>
                <a href="home.php?genre=<?= urlencode($genre) ?>" class="tag-badge"><?= htmlspecialchars($genre) ?></a>
          <?php endforeach; ?>
          <?php if (isset($user_tags_by_poster_id[$row['id']])): ?>
              <?php foreach ($user_tags_by_poster_id[$row['id']] as $utag): ?>
                <a href="home.php?user_tag=<?= urlencode($utag) ?>" class="tag-badge user-tag"><?= htmlspecialchars($utag) ?></a>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>

      <div class="flags-container">
            <?php
              $manual_langs = $manual_languages_by_poster_id[$row['id']] ?? [];
              $auto_langs = array_filter(array_map('trim', explode(',', $row['languages'] ?? '')));
              $auto_langs_unique = array_diff($auto_langs, $manual_langs); // הצג דגלי IMDb רק אם הם לא הוגדרו ידנית
            ?>
            <?php if (!empty($manual_langs)): ?>
                <div class="flag-row">
                <?php foreach ($manual_langs as $lang_name): ?>
                    <?php
                        $lang_key = strtolower($lang_name);
                        if (isset($lang_map[$lang_key])):
                            $flag_data = $lang_map[$lang_key];
                    ?>
                        <a href="language.php?lang_code=<?= urlencode($flag_data['code']) ?>" title="שפה: <?= htmlspecialchars($flag_data['label']) ?>" style="display:inline-flex; align-items:center; gap:3px; text-decoration:none;">
                            <span><?= htmlspecialchars($flag_data['label']) ?></span>
                            <img src="<?= htmlspecialchars($flag_data['flag']) ?>" style="height: 16px; width: auto; object-fit: contain; vertical-align: middle;">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($auto_langs_unique)): ?>
                <div class="flag-row">
                <?php foreach ($auto_langs_unique as $lang_name): ?>
                    <?php
                        $lang_key = strtolower($lang_name);
                        if (isset($lang_map[$lang_key])):
                            $flag_data = $lang_map[$lang_key];
                    ?>
                        <a href="home.php?lang_code=<?= urlencode($flag_data['label']) ?>" title="שפה: <?= htmlspecialchars($flag_data['label']) ?>" style="display:inline-flex; align-items:center; gap:3px; text-decoration:none;">
                             <img src="<?= htmlspecialchars($flag_data['flag']) ?>" style="height: 16px; width: auto; object-fit: contain; vertical-align: middle;">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
      </div>
        <?php if (isset($stickers_by_poster_id[$row['id']])): ?>
        <div class="collection-sticker-container">
            <?php foreach ($stickers_by_poster_id[$row['id']] as $sticker): ?>
                <a href="collection.php?id=<?= (int)$sticker['collection_id'] ?>" title="שייך לאוסף: <?= htmlspecialchars($sticker['collection_name']) ?>">
                    <img src="<?= htmlspecialchars($sticker['poster_image_url']) ?>" class="collection-sticker-image" alt="<?= htmlspecialchars($sticker['collection_name']) ?>" style="width: 50px; height: 50px; object-fit: contain;">
                </a>
            <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <div class="poster-actions">
          <?php if (!empty($row['trailer_url'])): ?>
              <button class="trailer-btn" data-trailer-url="<?= htmlspecialchars($row['trailer_url']) ?>">🎬 טריילר</button>
          <?php endif; ?>
          
          <span class="admin-actions">
              <?php if (!empty($row['trailer_url'])): ?><span class="separator">|</span><?php endif; ?>
              <a href="edit.php?id=<?= $row['id'] ?>" title="עריכה">✏️</a>
              <span class="separator">|</span>
              <a href="delete.php?id=<?= $row['id'] ?>" title="מחיקה" onclick="return confirm('למחוק את הפוסטר?')">🗑️</a>
          </span>
      </div>
      <br>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div id="loading" style="display: none; text-align: center; padding: 20px; font-size: 1.2em;">
  טוען פריטים נוספים...
</div>

<div id="trailer-modal" class="modal">
  <div class="modal-content ltr">
    <span class="close-btn">&times;</span>
    <div class="video-container" id="video-container"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Trailer and Infinite Scroll Logic ---
    const posterWall = document.getElementById('poster-wall');
    const loadingIndicator = document.getElementById('loading');
    const modal = document.getElementById('trailer-modal');
    const videoContainer = document.getElementById('video-container');
    const closeBtn = document.querySelector('.close-btn');

    const getYouTubeEmbedUrl = (url) => {
        try {
            const urlObj = new URL(url);
            let videoId = urlObj.searchParams.get('v');
            if (urlObj.hostname === 'youtu.be') {
                videoId = urlObj.pathname.slice(1);
            }
            if (videoId) {
                return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
            }
        } catch (e) { console.error("Invalid trailer URL", e); }
        return null;
    };

    const openModal = (trailerUrl) => {
        const embedUrl = getYouTubeEmbedUrl(trailerUrl);
        if (embedUrl) {
            videoContainer.innerHTML = `<iframe src="${embedUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            modal.style.display = 'block';
        } else {
            alert('כתובת הטריילר אינה תקינה או אינה נתמכת.');
        }
    };

    const closeModal = () => {
        modal.style.display = 'none';
        videoContainer.innerHTML = '';
    };

    posterWall.addEventListener('click', (event) => {
        const trailerButton = event.target.closest('.trailer-btn');
        if (trailerButton) {
            event.preventDefault();
            const url = trailerButton.dataset.trailerUrl;
            openModal(url);
        }
    });

    closeBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            closeModal();
        }
    });

    let currentPage = 1;
    let totalPages = <?= $total_pages ?>;
    let isLoading = false;

    const loadMorePosters = async () => {
        if (isLoading || currentPage >= totalPages) return;
        isLoading = true;
        currentPage++;
        loadingIndicator.style.display = 'block';
        const currentUrlParams = new URLSearchParams(window.location.search);
        currentUrlParams.set('page', currentPage);
        try {
            const response = await fetch(`load_more.php?${currentUrlParams.toString()}`);
            const newPostersHtml = await response.text();
            if (newPostersHtml.trim().length > 0) {
                posterWall.insertAdjacentHTML('beforeend', newPostersHtml);
            } else {
                currentPage = totalPages; 
            }
        } catch (error) {
            console.error('שגיאה בטעינת פריטים נוספים:', error);
        } finally {
            isLoading = false;
            loadingIndicator.style.display = 'none';
        }
    };

    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.documentElement.offsetHeight - 500) {
            loadMorePosters();
        }
    });

    // --- Admin Mode Toggle Logic ---
    var body = document.body;
    var btn = document.getElementById('toggle-admin');
    
    // The script checks if the button exists in the HTML.
    // If the public CSS hides it, it still exists, and this script will run.
    if (btn) {
        // Restore admin state from localStorage on page load
        if (localStorage.getItem('adminMode') === '1') {
            body.classList.add('admin-mode');
            btn.textContent = '🚪 יציאה ממצב ניהול';
        }
        
        // Add click listener to the button
        btn.addEventListener('click', function() {
            body.classList.toggle('admin-mode');
            var on = body.classList.contains('admin-mode');
            localStorage.setItem('adminMode', on ? '1' : '0');
            btn.textContent = on ? '🚪 יציאה ממצב ניהול' : '🔑 מצב ניהול';
        });
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>
<?php include 'footer.php'; ?>