<?php
require_once 'server.php';
include 'languages.php'; // Include for flags array

// --- Language to Flag Mapping (זהה ל-index.php) ---
$lang_map = [];
foreach ($languages as $lang) {
    // Create a map that can find the correct data using either the code or the label
    $lang_data = ['code' => $lang['code'], 'label' => $lang['label'], 'flag' => $lang['flag']];
    $lang_map[strtolower($lang['code'])] = $lang_data;
    $lang_map[strtolower($lang['label'])] = $lang_data;
}

// --- לוגיקת שליפת נתונים (זהה ל-index.php) ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

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

$orderBy = "ORDER BY p.id DESC";
$sql = "SELECT p.*, pt.label_he, pt.image AS type_image " . $count_query_part . $where_clause . " $orderBy LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;




// ===============================
// Watch status for grid buttons (collections: watched=231, watchlist=196)
// ===============================
$WATCHED_COLLECTION_ID = 231;
$WATCHLIST_COLLECTION_ID = 196;
$watched_set = [];
$watchlist_set = [];

if (!empty($rows)) {
    $poster_ids_for_watch = array_column($rows, 'id');
    $poster_ids_for_watch = array_values(array_filter(array_map('intval', $poster_ids_for_watch)));

    if (!empty($poster_ids_for_watch)) {
        $ids_placeholder2 = implode(',', array_fill(0, count($poster_ids_for_watch), '?'));
        $ids_types2 = str_repeat('i', count($poster_ids_for_watch));

        $sql_wc = "SELECT collection_id, poster_id
                   FROM poster_collections
                   WHERE collection_id IN (?, ?) AND poster_id IN ($ids_placeholder2)";

        $stmt_wc = $conn->prepare($sql_wc);
        if ($stmt_wc) {
            $bind_types  = "ii" . $ids_types2;
            $bind_params = array_merge([(int)$WATCHED_COLLECTION_ID, (int)$WATCHLIST_COLLECTION_ID], $poster_ids_for_watch);

            $refs = [];
            $refs[] = &$bind_types;
            foreach ($bind_params as $k => $v) { $refs[] = &$bind_params[$k]; }
            call_user_func_array([$stmt_wc, 'bind_param'], $refs);

            $stmt_wc->execute();
            $r_wc = $stmt_wc->get_result();
            while ($rr = $r_wc->fetch_assoc()) {
                $pid = (int)$rr['poster_id'];
                if ((int)$rr['collection_id'] === (int)$WATCHED_COLLECTION_ID)  $watched_set[$pid] = true;
                if ((int)$rr['collection_id'] === (int)$WATCHLIST_COLLECTION_ID) $watchlist_set[$pid] = true;
            }
            $stmt_wc->close();
        }
    }
}


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

    // --- שליפת סטיקרים של אוספים עבור כל הפוסטרים בעמוד ---
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
// --- סוף לוגיקת שליפת נתונים ---


// --- הדפסת HTML של הפוסטרים (זהה ל-index.php) ---
if (!empty($rows)):
  foreach ($rows as $row):
?>
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

    <div class="imdb-container" style="display:flex; flex-direction:column; justify-content:center; align-items:center; gap: 4px; padding-bottom: 4px; direction: rtl;">
            <?php if ($row['imdb_rating']): ?>
                <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none; color:inherit; white-space:nowrap;">
                    <span> 10 / <?= htmlspecialchars($row['imdb_rating']) ?></span> ⭐<img src="images/imdb.png" alt="IMDb">
                </a>
            <?php endif; ?>
            <?php if (!empty($row['runtime'])): ?>
              <span style="font-size: 0.9em; color: #555; white-space:nowrap; text-align:center;">⏱
                <?= htmlspecialchars($row['runtime']) ?> דקות 
              </span>
            <?php endif; ?>
          </div>


      <?php $pid = (int)$row['id']; $inWatched = !empty($watched_set[$pid]); $inWatch = !empty($watchlist_set[$pid]); ?>
      <div class="watch-actions" data-poster-id="<?= $pid ?>">
        <button type="button" class="wa-btn watched <?= $inWatched ? 'on' : '' ?>" data-cid="231"><?= $inWatched ? '✅ צפיתי' : '👁️ צפיתי' ?></button>
        <button type="button" class="wa-btn watch <?= $inWatch ? 'on' : '' ?>" data-cid="196"><?= $inWatch ? '✅ הסר מצפייה' : '⏳ הוסף לצפייה' ?></button>
      </div>

      <?php if (!empty($row['label_he'])): ?>
        <a href="home.php?type[]=<?= htmlspecialchars($row['type_id']) ?>" class="poster-type-link">
            <div class="poster-type-display" style="direction: ltr;">
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
<?php if (isset($stickers_by_poster_id[$row['id']])): ?>
        <div class="collection-sticker-container">
            <?php foreach ($stickers_by_poster_id[$row['id']] as $sticker): ?>
                <a href="collection.php?id=<?= (int)$sticker['collection_id'] ?>" title="שייך לאוסף: <?= htmlspecialchars($sticker['collection_name']) ?>">
                    <img src="<?= htmlspecialchars($sticker['poster_image_url']) ?>" class="collection-sticker-image" alt="<?= htmlspecialchars($sticker['collection_name']) ?>" style="width: 50px; height: 50px; object-fit: contain;">
                </a>
            <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
                        <a href="home.php?flag_code=<?= urlencode($flag_data['code']) ?>" title="שפה: <?= htmlspecialchars($flag_data['label']) ?>" style="display:inline-flex; align-items:center; gap:3px; text-decoration:none;">
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

      <div class="poster-actions rtl" style="margin-top:10px; font-size:13px; text-align:center;">
        <?php if (!empty($row['trailer_url'])): ?>
            <button class="trailer-btn" data-trailer-url="<?= htmlspecialchars($row['trailer_url']) ?>">🎬 טריילר</button>
        <?php endif; ?>
        <span class="admin-actions">
            <?php if (!empty($row['trailer_url'])): ?><span style="margin: 0 4px;">|</span><?php endif; ?>
            <a href="edit.php?id=<?= $row['id'] ?>" title="עריכה">✏️</a>
            <span style="margin: 0 4px;">|</span>
            <a href="delete.php?id=<?= $row['id'] ?>" title="מחיקה" onclick="return confirm('למחוק את הפוסטר?')">🗑️</a>
        </span>
      </div>
    </div>
<?php
  endforeach;
endif;




?>