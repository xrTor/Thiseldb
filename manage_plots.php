<?php
include 'header.php';
require_once 'server.php';

// --- הגדרות סינון ופאגינציה ---
$filter = $_GET['filter'] ?? 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// בחירת כמות פוסטרים להצגה עם ברירת מחדל 100
$per_page_options = [20, 50, 100, 250, 'all'];
$per_page = 100; // ברירת מחדל
if (isset($_GET['per_page']) && in_array($_GET['per_page'], $per_page_options)) {
    $per_page = $_GET['per_page'];
}

$offset = ($page - 1) * ($per_page === 'all' ? 0 : (int)$per_page);

// --- לוגיקת סינון ---
$conditions = [
    "((overview_he IS NULL OR overview_he = '') OR (overview_en IS NULL OR overview_en = '') OR (overview_he = overview_en))"
];
switch ($filter) {
    case 'no_he':
        $conditions[] = "(overview_he IS NULL OR overview_he = '')";
        break;
    case 'no_en':
        $conditions[] = "(overview_en IS NULL OR overview_en = '')";
        break;
    case 'no_both':
        $conditions[] = "((overview_he IS NULL OR overview_he = '') AND (overview_en IS NULL OR overview_en = ''))";
        break;
    case 'identical':
        $conditions[] = "(overview_he IS NOT NULL AND overview_en IS NOT NULL AND overview_he <> '' AND overview_en <> '' AND overview_he = overview_en)";
        break;
}
$where_clause = "WHERE " . implode(' AND ', $conditions);

// ספירת כל התוצאות
$count_sql = "SELECT COUNT(*) FROM posters $where_clause";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ($per_page === 'all' || $total_rows == 0) ? 1 : ceil($total_rows / $per_page);

// שליפת הפוסטרים לעמוד הנוכחי
$limit_clause = ($per_page === 'all') ? '' : "LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$sql = "SELECT id, imdb_id, title_en, title_he, year, image_url, overview_en, overview_he 
        FROM posters 
        $where_clause 
        ORDER BY id DESC 
        $limit_clause";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📝 ניהול תקצירים</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 10px; text-align: right; }
    .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
    .filter-form { margin-bottom: 20px; padding: 15px; background: #f1f1f1; border-radius: 6px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
    .filter-form label { font-weight: bold; }
    .filter-form select, .filter-form button { padding: 8px 12px; font-size: 1em; border-radius: 4px; border: 1px solid #ccc; }
    .poster-table { width: 100%; border-collapse: collapse; table-layout: fixed; } 
    .poster-table th, .poster-table td { padding: 12px 8px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: middle; }
    .poster-table th { background: #f2f2f2; }
    .poster-table img { width: 60px; height: 90px; object-fit: cover; border-radius: 4px; vertical-align: middle; }
    
    /* --- הגדרות רוחב לעמודות --- */
    .id-col { width: 5%; }
    .imdb-col { width: 9%; }
    .poster-col { width: 8%; }
    .title-col { width: 30%; }
    .status-actions-col { width: 48%; } /* עמודה רחבה מאוחדת */
    
    /* --- עיצוב חדש לקונטיינר המאוחד --- */
    .status-actions-container {
        display: flex;
        justify-content: space-between; /* דוחף את התוכן לקצוות */
        align-items: center;
        gap: 8px;
    }
    .status-indicators-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .overview-status, .identical-overview-indicator {
        padding: 6px 10px;
        border-radius: 4px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .missing-overview { background-color: #ffe5e5; color: #a00; }
    .existing-overview { background-color: #e5f7ea; color: #006b21; }
    .identical-overview-indicator { 
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background-color: #fffbe5; 
        border: 1px solid #e6dca5; 
        color: #7d6c0f; 
        font-size: 0.9em; 
    }
    
    .edit-btn { background: #007bff; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; white-space: nowrap; }
    .pagination { text-align:center; margin-top:20px; }
    .pagination a { margin:0 6px; padding:6px 10px; background:#eee; border-radius:44px; text-decoration:none; color:#333; }
    .pagination a.active { font-weight:bold; background:#ccc; }
    .title-link { text-decoration: none; color: inherit; }
    .title-link:hover { color: #0056b3; }
    .poster-title-cell {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
  </style>
</head>
<body>

<div class="container">
    <h2>📝 ניהול תקצירים (<?= $total_rows ?> תוצאות)</h2>

    <form method="get" class="filter-form">
        <label for="filter">הצג:</label>
        <select name="filter" id="filter" onchange="this.form.submit()">
            <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>הכל</option>
            <option value="no_he" <?= $filter == 'no_he' ? 'selected' : '' ?>>חסר תקציר בעברית</option>
            <option value="no_en" <?= $filter == 'no_en' ? 'selected' : '' ?>>חסר תקציר באנגלית</option>
            <option value="no_both" <?= $filter == 'no_both' ? 'selected' : '' ?>>חסר את שניהם</option>
            <option value="identical" <?= $filter == 'identical' ? 'selected' : '' ?>>תקצירים זהים</option>
        </select>
        
        <label for="per_page">פריטים לעמוד:</label>
        <select name="per_page" id="per_page" onchange="this.form.submit()">
            <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
            <option value="250" <?= $per_page == 250 ? 'selected' : '' ?>>250</option>
            <option value="all" <?= $per_page == 'all' ? 'selected' : '' ?>>הכל</option>
        </select>
        <noscript><button type="submit">סנן</button></noscript>
    </form>

    <table class="poster-table">
        <thead>
            <tr>
                <th class="id-col">ID</th>
                <th class="imdb-col">IMDb ID</th>
                <th class="poster-col">פוסטר</th>
                <th class="title-col">כותרת</th>
                <th class="status-actions-col">סטטוס ופעולות</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $overview_he = trim($row['overview_he'] ?? '');
                    $overview_en = trim($row['overview_en'] ?? '');
                    $is_identical = !empty($overview_he) && !empty($overview_en) && ($overview_he === $overview_en);
                    ?>
                    <tr>
                        <td><a href="poster.php?id=<?= $row['id'] ?>"><?= $row['id'] ?></a></td>
                        <td>
                            <?php if (!empty($row['imdb_id'])): ?>
                                <a href="https://www.imdb.com/title/<?= htmlspecialchars($row['imdb_id']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= htmlspecialchars($row['imdb_id']) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="poster.php?id=<?= $row['id'] ?>">
                                <img src="<?= htmlspecialchars($row['image_url'] ?: 'images/no-poster.png') ?>" alt="Poster">
                            </a>
                        </td>
                        <td class="poster-title-cell"> 
                            <a href="poster.php?id=<?= $row['id'] ?>" class="title-link">
                                <strong><?= htmlspecialchars($row['title_he'] ?: $row['title_en']) ?></strong><br>
                                <small><?= htmlspecialchars($row['title_en']) ?> (<?= $row['year'] ?>)</small>
                            </a>
                        </td>
                        <td>
                            <div class="status-actions-container">
                                <div class="status-indicators-group">
                                    <?php if (!empty($overview_he)): ?>
                                        <div class="overview-status existing-overview">תקציר עברית: קיים</div>
                                    <?php else: ?>
                                        <div class="overview-status missing-overview">תקציר עברית: חסר</div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($overview_en)): ?>
                                        <div class="overview-status existing-overview">תקציר אנגלית: קיים</div>
                                    <?php else: ?>
                                        <div class="overview-status missing-overview">תקציר אנגלית: חסר</div>
                                    <?php endif; ?>

                                    <?php if ($is_identical): ?>
                                        <div class="identical-overview-indicator">⚠️ התקצירים זהים</div>
                                    <?php endif; ?>
                                </div>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="edit-btn">✏️ ערוך</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;">לא נמצאו פוסטרים התואמים לסינון.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?filter=<?= $filter ?>&per_page=<?= $per_page ?>&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
</body>
</html>