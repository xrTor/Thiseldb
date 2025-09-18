<?php
include 'header.php';
require_once 'server.php';

function safe($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$log_report = [];

// --- לוגיקת הוספה מרובה ---
if (isset($_POST['bulk_add'])) {
    // ניקוי ראשוני + סינון כפילויות
    $genres = array_filter(array_map('trim', preg_split('/[,|\n]/', $_POST['bulk_genre'])));
    $genres = array_unique(array_map('strtolower', $genres));
    $lines = explode("\n", $_POST['bulk_ids']);

    foreach ($lines as $raw) {
        $id = trim($raw);
        if ($id === '') continue;
        if (preg_match('/tt\d+/', $id, $match)) {
            $id = $match[0];
        }

        // שליפת הפוסטר כולל שני השדות
        if (is_numeric($id)) {
            $stmt = $conn->prepare("SELECT id, genre, genres FROM posters WHERE id = ?");
            $stmt->bind_param("i", $id);
        } elseif (preg_match('/^tt\d+$/', $id)) {
            $stmt = $conn->prepare("SELECT id, genre, genres FROM posters WHERE imdb_id = ?");
            $stmt->bind_param("s", $id);
        } else {
            $log_report[] = ['status' => 'error', 'id' => $id, 'genre' => implode(', ', $genres)];
            continue;
        }

        $stmt->execute();
        $poster = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($poster) {
            $pid = intval($poster['id']);

            // ✅ מאחדים את שני השדות לרשימה אחת
            $poster_genres_raw = ($poster['genre'] ?? '') . ',' . ($poster['genres'] ?? '');
            $poster_genres_list = explode(',', $poster_genres_raw);
            $poster_genres_trimmed = array_map(function($g){
                return strtolower(trim($g));
            }, $poster_genres_list);
            $existing_genres = array_filter($poster_genres_trimmed);

            foreach ($genres as $genre_lc) {
                if ($genre_lc === '') continue;
                $genre = ucfirst($genre_lc);

                // ✅ בדיקה מול רשימת הז'אנרים המאוחדת
                if (in_array($genre_lc, $existing_genres, true)) {
                    $log_report[] = ['status' => 'exists_genre', 'id' => $id, 'genre' => $genre];
                    continue;
                }

                // בדיקה מול user_tags
                $stmt = $conn->prepare("SELECT COUNT(*) FROM user_tags WHERE poster_id = ? AND LOWER(genre) = LOWER(?)");
                $stmt->bind_param("is", $pid, $genre);
                $stmt->execute();
                $stmt->bind_result($exists);
                $stmt->fetch();
                $stmt->close();

                if ($exists == 0) {
                    $stmt = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
                    $stmt->bind_param("is", $pid, $genre);
                    $stmt->execute();
                    $stmt->close();
                    $log_report[] = ['status' => 'added', 'id' => $id, 'genre' => $genre];
                } else {
                    $log_report[] = ['status' => 'exists', 'id' => $id, 'genre' => $genre];
                }
            }
        } else {
            $log_report[] = ['status' => 'error', 'id' => $id, 'genre' => implode(', ', $genres)];
        }
    }
}

// --- לוגיקת מחיקה ---
if (isset($_GET['delete']) && isset($_GET['pid']) && isset($_GET['genre'])) {
    $pid = intval($_GET['pid']);
    $genre = trim($_GET['genre']);
    $stmt = $conn->prepare("DELETE FROM user_tags WHERE poster_id = ? AND genre = ?");
    $stmt->bind_param("is", $pid, $genre);
    $stmt->execute();
    $stmt->close();
}

// --- עימוד ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (preg_match('/tt\d+/', $search, $match)) {
    $search = $match[0];
}

$results_per_page = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $results_per_page;

$total_results = 0;
if ($search !== '') {
    $searchLike = "%$search%";
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM posters WHERE title_en LIKE ? OR title_he LIKE ? OR imdb_id LIKE ?");
    $stmt_count->bind_param("sss", $searchLike, $searchLike, $searchLike);
} else {
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM posters");
}
$stmt_count->execute();
$stmt_count->bind_result($total_results);
$stmt_count->fetch();
$stmt_count->close();
$total_pages = ceil($total_results / $results_per_page);

$posters = [];
if ($search !== '') {
    $searchLike = "%$search%";
    $stmt = $conn->prepare("SELECT id, title_en, title_he FROM posters WHERE title_en LIKE ? OR title_he LIKE ? OR imdb_id LIKE ? ORDER BY id DESC LIMIT ?, ?");
    $stmt->bind_param("sssii", $searchLike, $searchLike, $searchLike, $offset, $results_per_page);
} else {
    $stmt = $conn->prepare("SELECT id, title_en, title_he FROM posters ORDER BY id DESC LIMIT ?, ?");
    $stmt->bind_param("ii", $offset, $results_per_page);
}
$stmt->execute();
$result = $stmt->get_result();
$posters_raw = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($posters_raw as $p) {
    $pid = intval($p['id']);
    $stmt_tags = $conn->prepare("SELECT genre FROM user_tags WHERE poster_id = ?");
    $stmt_tags->bind_param("i", $pid);
    $stmt_tags->execute();
    $result_tags = $stmt_tags->get_result();
    $genres = [];
    while ($row_tag = $result_tags->fetch_assoc()) {
        $genres[] = $row_tag['genre'];
    }
    $stmt_tags->close();

    $posters[] = [
        'id'       => $pid,
        'title_en' => $p['title_en'],
        'title_he' => $p['title_he'],
        'genres'   => $genres
    ];
}

// --- HTML לעימוד ---
$pagination_html = '';
if ($total_pages > 1) {
    $query_params = $_GET;
    unset($query_params['page']);
    $base_url = '?' . http_build_query($query_params);
    $separator = empty($query_params) ? '' : '&';
    $range = 3;
    $links = [];

    if ($page > 1) {
        $links[] = '<a href="' . $base_url . $separator . 'page=1">« ראשון</a>';
        $links[] = '<a href="' . $base_url . $separator . 'page=' . ($page - 1) . '">‹ הקודם</a>';
    }

    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
            $active_class = ($i == $page) ? 'active' : '';
            $links[] = '<a href="' . $base_url . $separator . 'page=' . $i . '" class="' . $active_class . '">' . $i . '</a>';
        } else if (($i == $page - $range - 1) || ($i == $page + $range + 1)) {
            $links[] = '<span>...</span>';
        }
    }

    if ($page < $total_pages) {
        $links[] = '<a href="' . $base_url . $separator . 'page=' . ($page + 1) . '">הבא ›</a>';
        $links[] = '<a href="' . $base_url . $separator . 'page=' . $total_pages . '">אחרון »</a>';
    }

    $pagination_html = '<div class="pagination">' . implode(' ', $links) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🎬 תגיות משתמש לפי פוסטר</title>
    <style>
        body { font-family:"Segoe UI"; padding:40px; background:#f8f8f8; }
        h1, h2 { text-align:center; color:#007bff; margin-bottom:20px; }
        form.search, form.bulk { max-width:500px; margin:0 auto 30px; }
        input[type="text"], textarea { width:100%; padding:10px; font-size:14px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; box-sizing: border-box; }
        button[type="submit"] { padding:10px 20px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
        button:hover { background:#0056b3; }
        table { width:100%; border-collapse:collapse; margin-top:15px; background-color: white; table-layout: fixed; }
        th, td { padding:10px; border-bottom:1px solid #eee; text-align:right; vertical-align:top; word-wrap: break-word; }
        th { background:#f1f1f1; }
        td.title small { display:block; font-size:13px; color:#555; margin-top:4px; }
        a.delete { font-size:13px; margin-right:3px; text-decoration:none; color: #99999A !important; }
        a.delete:hover { text-decoration:underline; }
        ul.report { list-style:none; padding:0; margin-bottom:30px; max-width:600px; margin:0 auto; }
        ul.report li { margin-bottom:6px; font-size:14px; }
        .bold,td.title a {font-weight: bold; }
        .back {color:#007bff; display: inline-flex; align-items: center; background: #F0F1F2; color: #495057; padding: 8px 8px; border-radius: 12px; margin: 2px; font-size: 13px;}
        .pagination { text-align: center; margin: 15px 0; }
        .pagination a, .pagination span { display: inline-block; color: #007bff; text-decoration: none; padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.3s; }
        .pagination a.active { background-color: #007bff; color: white; border-color: #007bff; cursor: default; }
        .pagination a:hover:not(.active) { background-color: #f1f1f1; }
        .pagination span { color: #999; border-color: transparent; }
    </style>
</head>
<body>

<?php if (!empty($log_report)): ?>
    <h2>📋 דוח החלה</h2>
    <ul class="report">
        <?php foreach ($log_report as $entry): ?>
            <li>
                <?php if ($entry['status'] === 'added'): ?>
                    <span style="color:green;">🟢</span>
                    תגית <?= safe($entry['genre']) ?> נוספה לפוסטר <?= safe($entry['id']) ?>
                <?php elseif ($entry['status'] === 'exists'): ?>
                    <span style="color:blue;">🔵</span>
                    תגית <?= safe($entry['genre']) ?> כבר קיימת בפוסטר <?= safe($entry['id']) ?>
                <?php elseif ($entry['status'] === 'exists_genre'): ?>
                    <span style="color:orange;">🟠</span>
                    תגית <?= safe($entry['genre']) ?> כבר קיימת בז'אנרים של הפוסטר <?= safe($entry['id']) ?>
                <?php elseif ($entry['status'] === 'error'): ?>
                    <span style="color:red;">🔴</span>
                    לא נמצא פוסטר עבור מזהה <?= safe($entry['id']) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" class="bulk">
    <h2>➕ החלת תגית אחת או יותר על כמה פוסטרים</h2>
    <input type="text" name="bulk_genre" placeholder="למשל: Drama, Thriller">
    <textarea name="bulk_ids" rows="10" placeholder="tt0404940&#10;2&#10;https://www.imdb.com/title/tt0110912/"></textarea>
    <button type="submit" name="bulk_add">💾 הוסף תגית</button>
</form>

<h1>🎭 תגיות לפי פוסטר</h1>

<form method="get" class="search">
    <input type="text" name="search" placeholder="🔍 חיפוש לפי שם פוסטר או IMDb" value="<?= safe($search) ?>">
    <button type="submit">חפש</button>
</form>

<?php echo $pagination_html; ?>

<table>
    <thead>
        <tr>
            <th style="width: 5%;">מזהה</th>
            <th style="width: 35%;">שם</th>
            <th style="width: 60%;">תגיות</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($posters as $p): ?>
            <tr>
                <td><?= safe($p['id']) ?></td>
                <td class="title">
                    <a href="poster.php?id=<?= $p['id'] ?>"><?= safe($p['title_en']) ?></a>
                    <?php if (!empty($p['title_he'])): ?>
                        <small><?= safe($p['title_he']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($p['genres'])): ?>
                        <?php foreach ($p['genres'] as $g): ?>
                            <span class="back">
                                <a href="user_tags.php?name=<?= urlencode($g) ?>" style="text-decoration:none; color:inherit;" class="bold">
                                    <?= safe($g) ?>
                                </a>
                                <a href="?delete=1&pid=<?= $p['id'] ?>&genre=<?= urlencode($g) ?>" class="delete" onclick="return confirm('האם למחוק את התגית?')">[מחק]</a>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($posters)): ?>
            <tr><td colspan="3" style="text-align: center; padding: 20px;">לא נמצאו תוצאות.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php echo $pagination_html; ?>

</body>
</html>

<?php include 'footer.php'; ?>
