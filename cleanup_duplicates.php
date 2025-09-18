<?php
include 'header.php';
require_once 'server.php';

// קריטי: הגדרת קידוד החיבור למסד הנתונים
if (function_exists('mysqli_set_charset')) {
    mysqli_set_charset($conn, "utf8mb4");
}

// --- פונקציות עזר ---
function safe($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$message = '';

// --- לוגיקת טיפול בטפסים ---

if (isset($_POST['update_genre'])) {
    $poster_id_to_update = (int)$_POST['poster_id'];
    $new_genres = trim(strip_tags($_POST['new_genres']));
    if ($poster_id_to_update > 0 && !empty($new_genres)) {
        $stmt = $conn->prepare("UPDATE posters SET genres = ? WHERE id = ?");
        $stmt->bind_param("si", $new_genres, $poster_id_to_update);
        if ($stmt->execute()) {
            $message = '<div class="message success">✅ הז\'אנרים לפוסטר #' . $poster_id_to_update . ' עודכנו בהצלחה.</div>';
        } else {
            $message = '<div class="message error">❌ שגיאה בעדכון פוסטר #' . $poster_id_to_update . '.</div>';
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_tag_id']) && ctype_digit((string)$_GET['delete_tag_id'])) {
    $tag_id_to_delete = (int)$_GET['delete_tag_id'];
    $stmt = $conn->prepare("DELETE FROM user_tags WHERE id = ?");
    $stmt->bind_param("i", $tag_id_to_delete);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = '<div class="message success">תגית בודדת נמחקה בהצלחה.</div>';
    }
    $stmt->close();
}

if (isset($_POST['delete_all']) && !empty($_POST['tag_ids'])) {
    $ids_array = array_filter(explode(',', $_POST['tag_ids']), 'is_numeric');
    if (!empty($ids_array)) {
        $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
        $types = str_repeat('i', count($ids_array));
        $stmt = $conn->prepare("DELETE FROM user_tags WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids_array);
        $stmt->execute();
        $count = $stmt->affected_rows;
        $stmt->close();
        $message = "<div class='message success'>✅ נמחקו {$count} תגיות כפולות בהצלחה!</div>";
    }
}


// --- לוגיקת איתור בעיות ---

// 1. איתור פוסטרים לתיקון
$posters_to_fix = [];
$fix_stmt = $conn->query("
    SELECT p.id, p.title_en, p.title_he, GROUP_CONCAT(ut.genre SEPARATOR ', ') AS user_tags_for_poster
    FROM posters p
    JOIN user_tags ut ON p.id = ut.poster_id
    WHERE p.genres IS NULL OR p.genres = ''
    GROUP BY p.id, p.title_en, p.title_he
");
if ($fix_stmt) {
    $posters_to_fix = $fix_stmt->fetch_all(MYSQLI_ASSOC);
}

// 2. איתור כפילויות
$duplicates = [];
$duplicate_tag_ids = [];
$duplicates_query = "
    SELECT
        p.id AS poster_id,
        p.title_en,
        p.title_he,
        ut.id AS tag_id,
        ut.genre AS tag_name
    FROM
        posters AS p
    JOIN
        user_tags AS ut ON p.id = ut.poster_id
    WHERE
        p.genres IS NOT NULL AND p.genres != ''
    AND
        FIND_IN_SET(LOWER(ut.genre), REPLACE(LOWER(p.genres), ' ', ''))
";
$duplicates_result = $conn->query($duplicates_query);
if ($duplicates_result) {
    $duplicates = $duplicates_result->fetch_all(MYSQLI_ASSOC);
    if (!empty($duplicates)) {
        $duplicate_tag_ids = array_column($duplicates, 'tag_id');
    }
}

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🛠️ כלי תיקון וניקוי תגיות</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
    body { 
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Hebrew", Arial; 
        direction: rtl; 
        background: #f5f6fa; 
        color: #333; 
        margin: 0; 
        padding: 24px; 
    }
    h1 { text-align: center; margin-bottom: 20px; color: #222; }
    .wrap { max-width: 1000px; margin: 0 auto; display: grid; gap: 24px; }

    .card { background: #fff; border: 1px solid #ddd; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .card-header { padding: 15px 20px; background: #fafafa; border-bottom: 1px solid #eee; }
    .card-header h2 { margin: 0; font-size: 1.2em; color: #444; }
    .card-content { padding: 20px; }

    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #e6e6e6; text-align: right; vertical-align: middle; color: #333; }
    tr:last-child td { border-bottom: none; }
    th { font-weight: 600; color: #555; background: #f9f9f9; }
    tr:hover td { background: #f5f7fb; }

    a { color: #0073e6; text-decoration: none; }
    a:hover { text-decoration: underline; }

    .btn { cursor: pointer; border: 1px solid #ccc; border-radius: 8px; padding: 6px 12px; background: #fafafa; color: #333; font-size: 14px; }
    .btn:hover { background: #e6e6e6; }

    .btn-danger { border-color: #e74c3c; color: #e74c3c; background: #fff; }
    .btn-danger:hover { background: #e74c3c; color: #fff; }

    .btn-success { border-color: #27ae60; color: #27ae60; background: #fff; }
    .btn-success:hover { background: #27ae60; color: #fff; }

    .message { padding: 15px; margin-bottom: 20px; border-radius: 10px; text-align: center; font-weight: bold; border: 1px solid; background: #fff; }
    .success { color: #27ae60; border-color: #27ae60; }
    .error { color: #e74c3c; border-color: #e74c3c; }

    .empty-state { padding: 40px 20px; text-align: center; color: #888; background: #fff; border-radius: 8px; border: 1px dashed #ddd; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 0 0 15px 0; border-bottom: 1px solid #eee; margin-bottom: 15px; }
    .toolbar p { margin: 0; font-size: 1.05em; color: #333; }

    .form-inline { display: flex; gap: 10px; align-items: center; }
    .form-inline input[type="text"] { flex-grow: 1; background: #fff; border: 1px solid #ccc; color: #333; border-radius: 6px; padding: 8px 10px; }
</style>

</head>
<body>

<div class="wrap">
    <div>
        <h1>🛠️ כלי תיקון וניקוי תגיות</h1>
        <?= $message ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>שלב 1: תיקון פוסטרים עם ז'אנרים חסרים</h2>
        </div>
        <div class="card-content">
            <?php if (empty($posters_to_fix)): ?>
                <div class="empty-state">✅ כל הפוסטרים תקינים.</div>
            <?php else: ?>
                <p style="margin-top:0; color:var(--muted);">נמצאו <?= count($posters_to_fix) ?> פוסטרים שהז'אנר הרשמי שלהם חסר. אנא עדכן אותם:</p>
                <table>
                    <thead><tr><th>שם הפוסטר</th><th>תגיות קיימות (רמז)</th><th style="width: 40%;">הזן ז'אנרים נכונים</th></tr></thead>
                    <tbody>
                        <?php foreach ($posters_to_fix as $poster): ?>
                            <tr>
                                <td>
                                    <a href="poster.php?id=<?= $poster['id'] ?>" target="_blank"><?= safe($poster['title_en']) ?></a>
                                </td>
                                <td><small style="color:var(--muted);"><?= safe($poster['user_tags_for_poster']) ?></small></td>
                                <td>
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="poster_id" value="<?= $poster['id'] ?>">
                                        <input type="text" name="new_genres" placeholder="למשל: Action, Drama, Comedy" required>
                                        <button type="submit" name="update_genre" class="btn">💾 שמור</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>שלב 2: ניקוי תגיות כפולות</h2>
        </div>
        <div class="card-content">
            <?php if (empty($duplicates)): ?>
                <div class="empty-state">✨ לא נמצאו תגיות כפולות לניקוי.</div>
            <?php else: ?>
                <div class="toolbar">
                    <p>נמצאו <strong><?= count($duplicates) ?></strong> תגיות כפולות</p>
                    <form method="post" onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את כל <?= count($duplicates) ?> הכפילויות?')">
                        <input type="hidden" name="tag_ids" value="<?= implode(',', $duplicate_tag_ids) ?>">
                        <button type="submit" name="delete_all" class="btn btn-danger">🗑️ מחק את הכל</button>
                    </form>
                </div>
                <table>
                    <thead><tr><th>שם הפוסטר</th><th>התגית הכפולה</th><th style="text-align: center;">פעולה</th></tr></thead>
                    <tbody>
                        <?php foreach ($duplicates as $dup): ?>
                            <tr>
                                <td>
                                    <a href="poster.php?id=<?= $dup['poster_id'] ?>" target="_blank">
                                        <?= safe($dup['title_en']) ?>
                                        <?php if (!empty($dup['title_he'])): ?>
                                            <small style="display: block; color: var(--muted);"><?= safe($dup['title_he']) ?></small>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td><strong><?= safe($dup['tag_name']) ?></strong></td>
                                <td style="text-align: center;">
                                    <a href="?delete_tag_id=<?= $dup['tag_id'] ?>" class="btn" onclick="return confirm('למחוק את התגית הזו?')">מחק</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>