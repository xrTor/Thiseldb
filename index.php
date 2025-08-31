<?php
require_once 'server.php';
include 'header.php';
?>

<style>
   body {background-color:#EDEDEE !important}
</style>
<?php
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;

// --- הלוגיקה הקיימת לשליפת הנתונים נשארת כמעט ללא שינוי ---
$count_query_part = "FROM posters p LEFT JOIN poster_types pt ON p.type_id = pt.id";
$where = [];
$params = [];
$types = '';

if (!empty($_GET['tag'])) {
    $where[] = "p.id IN (SELECT poster_id FROM poster_categories WHERE category_id = ?)";
    $params[] = intval($_GET['tag']);
    $types .= 'i';
}
if (!empty($_GET['search'])) {
    $where[] = "(title_en LIKE ? OR title_he LIKE ?)";
    $search = "%" . $_GET['search'] . "%";
    $params[] = $search; $params[] = $search;
    $types .= 'ss';
}
if (!empty($_GET['year'])) {
    $where[] = "year LIKE ?";
    $params[] = "%" . $_GET['year'] . "%";
    $types .= 's';
}
if (!empty($_GET['min_rating'])) {
    $where[] = "CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) >= ?";
    $params[] = floatval($_GET['min_rating']);
    $types .= 'd';
}
$type_filter = $_GET['type'] ?? '';
if (!empty($type_filter)) {
    $where[] = "pt.code = ?";
    $params[] = $type_filter;
    $types .= 's';
}

$where_clause = "";
if (!empty($where)) {
    $where_clause = " WHERE " . implode(" AND ", $where);
}

// חישוב מספר עמודים כולל
$count_sql = "SELECT COUNT(p.id) AS total " . $count_query_part . $where_clause;
$stmt_count = $conn->prepare($count_sql);
if ($types) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_rows / $limit));

if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// שליפת הנתונים לעמוד הראשון
$orderBy = "";
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'year_asc': $orderBy = "ORDER BY year ASC, p.id DESC"; break;
        case 'year_desc': $orderBy = "ORDER BY year DESC, p.id DESC"; break;
        case 'rating_desc': $orderBy = "ORDER BY CAST(SUBSTRING_INDEX(imdb_rating, '/', 1) AS DECIMAL(3,1)) DESC, p.id DESC"; break;
    }
}
if (empty($orderBy)) $orderBy = "ORDER BY p.id DESC";

$sql = "SELECT p.*, pt.code, pt.label_he, pt.icon " . $count_query_part . $where_clause . " $orderBy LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
?>

<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>Thiseldb</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="rtl">

<div class="poster-wall" id="poster-wall">
<?php if (empty($rows) && $page === 1): ?>
  <p class="no-results">לא נמצאו תוצאות 😢</p>
<?php else: ?>
  <?php foreach ($rows as $row): ?>
    <div class="poster ltr">
      <?php $img = (!empty($row['image_url'])) ? $row['image_url'] : 'images/no-poster.png'; ?>
      <a href="poster.php?id=<?= $row['id'] ?>">
        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($row['title_en']) ?>" loading="lazy">
      </a> 

      <div class="poster-title ltr">
        <b><?= htmlspecialchars($row['title_en']) ?>
          <?php if (!empty($row['title_he'])): ?><br><span style="color:#777;"><?= htmlspecialchars($row['title_he']) ?><?php endif; ?>
        </b><br>[<?= $row['year'] ?>]
      </div>

      <span class="imdb-first">
        <?php if ($row['imdb_rating']): ?>
          <a href="https://www.imdb.com/title/<?= $row['imdb_id'] ?>" target="_blank" style="display:inline-flex; align-items:center; gap:1px;">
            <img src="images/imdb.png" class="imdb ltr" alt="IMDb" style="height:18px;"> <span>⭐<?= htmlspecialchars($row['imdb_rating']) ?> / 10</span>
          </a>
        <?php endif; ?>
      </span>

      <?php
        $label = $row['label_he'] ?? '';
        $icon = $row['icon'] ?? '';
        if ($label || $icon) {
          echo "<div style='font-size:12px; color:#555;'>$icon $label</div>";
        } else {
          echo "<div style='font-size:12px; color:#555;'>❓ לא ידוע</div>";
        }
      ?>

      <div class="poster-actions rtl" style="margin-top:10px; font-size:13px; text-align:center;">
        <a href="edit.php?id=<?= $row['id'] ?>">✏️ עריכה</a> |
        <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('למחוק את הפוסטר?')">🗑️ מחיקה</a>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div id="loading" style="display: none; text-align: center; padding: 20px; font-size: 1.2em;">
  טוען פריטים נוספים...
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const posterWall = document.getElementById('poster-wall');
    const loadingIndicator = document.getElementById('loading');
    
    let currentPage = 1;
    let totalPages = <?= $total_pages ?>;
    let isLoading = false;

    // פונקציה לטעינת פוסטרים נוספים
    const loadMorePosters = async () => {
        if (isLoading || currentPage >= totalPages) {
            return;
        }

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

    // האזנה לאירוע גלילה
    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.documentElement.offsetHeight - 500) {
            loadMorePosters();
        }
    });
});
</script>

</body>
</html>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>
