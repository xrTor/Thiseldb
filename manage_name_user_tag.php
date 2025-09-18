<?php
require_once 'server.php';
session_start();
include 'header.php';

// עדכון שם תגית עם לוגיקה מתקדמת למניעת כפילויות
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_name'], $_POST['new_name'])) {
    $old_name = trim($_POST['old_name']);
    $new_name = trim($_POST['new_name']);
    
    if ($old_name !== '' && $new_name !== '') {
        // 1. מצא את כל מזהי הפוסטרים שמושפעים מהשינוי
        $stmt_find = $conn->prepare("SELECT DISTINCT poster_id FROM user_tags WHERE genre = ?");
        $stmt_find->bind_param("s", $old_name);
        $stmt_find->execute();
        $affected_poster_ids_res = $stmt_find->get_result();
        $affected_poster_ids = [];
        while($row = $affected_poster_ids_res->fetch_assoc()) {
            $affected_poster_ids[] = $row['poster_id'];
        }
        $stmt_find->close();

        $updated_posters_count = count($affected_poster_ids);

        // 2. עבור על כל פוסטר בנפרד ועדכן אותו
        foreach ($affected_poster_ids as $poster_id) {
            // א. קבל את כל התגיות הקיימות של הפוסטר
            $stmt_all_tags = $conn->prepare("SELECT genre FROM user_tags WHERE poster_id = ?");
            $stmt_all_tags->bind_param("i", $poster_id);
            $stmt_all_tags->execute();
            $tags_result = $stmt_all_tags->get_result();
            $current_tags = [];
            while ($tag_row = $tags_result->fetch_assoc()) {
                $current_tags[] = $tag_row['genre'];
            }
            $stmt_all_tags->close();

            // ב. קבל את כל הז'אנרים הרשמיים של הפוסטר לבדיקה
            $stmt_genres = $conn->prepare("SELECT genre FROM posters WHERE id = ?");
            $stmt_genres->bind_param("i", $poster_id);
            $stmt_genres->execute();
            $official_genres_str = $stmt_genres->get_result()->fetch_assoc()['genre'] ?? '';
            $official_genres_lc = array_map('strtolower', array_map('trim', explode(',', $official_genres_str)));
            $stmt_genres->close();

            // ג. הסר את התגית הישנה מרשימת התגיות הנוכחית
            $tags_after_removal = [];
            foreach ($current_tags as $tag) {
                if (strcasecmp($tag, $old_name) !== 0) {
                    $tags_after_removal[] = $tag;
                }
            }

            // ד. פרק את השם החדש לחלקים והוסף רק מה שלא קיים
            $new_parts_to_add = array_map('trim', explode(',', $new_name));
            $final_tags = $tags_after_removal;
            $final_tags_lc = array_map('strtolower', $final_tags);

            foreach ($new_parts_to_add as $part) {
                if (empty($part)) continue;
                $part_lc = strtolower($part);
                if (!in_array($part_lc, $final_tags_lc) && !in_array($part_lc, $official_genres_lc)) {
                    $final_tags[] = $part;
                    $final_tags_lc[] = $part_lc; // עדכן גם את רשימת הבדיקה
                }
            }
            
            // ה. בצע סינכרון במסד הנתונים: מחק את כל התגיות הישנות והכנס את החדשות
            $stmt_delete = $conn->prepare("DELETE FROM user_tags WHERE poster_id = ?");
            $stmt_delete->bind_param("i", $poster_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            if (!empty($final_tags)) {
                // --- התיקון כאן ---
                // בצע ניקוי אחרון של כפילויות וסטנדרטיזציה בשני שלבים נפרדים
                $standardized_tags = array_map(fn($t) => ucwords(strtolower($t)), $final_tags);
                $final_tags = array_unique($standardized_tags);
                
                $stmt_insert = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
                foreach ($final_tags as $final_tag) {
                    if (trim($final_tag) !== '') {
                        $stmt_insert->bind_param("is", $poster_id, $final_tag);
                        $stmt_insert->execute();
                    }
                }
                $stmt_insert->close();
            }
        }
        
        echo "<div class='alert alert-success text-center'>התגית עודכנה מ־<b>" . htmlspecialchars($old_name) . "</b> ל־<b>" . htmlspecialchars($new_name) . "</b> ב-<b>$updated_posters_count</b> פוסטרים.</div>";
    }
}


// מחיקה בטוחה של תגית
if (isset($_GET['delete'])) {
    $del = trim($_GET['delete']);
    if ($del !== '') {
        $stmt = $conn->prepare("DELETE FROM user_tags WHERE genre = ?");
        $stmt->bind_param("s", $del);
        $stmt->execute();
        echo "<div class='alert alert-danger text-center'>התגית <b>" . htmlspecialchars($del) . "</b> נמחקה</div>";
    }
}

// שליפה
$res = $conn->query("
    SELECT TRIM(genre) AS genre, COUNT(*) AS count
    FROM user_tags
    WHERE genre IS NOT NULL AND TRIM(genre) != ''
    GROUP BY TRIM(genre)
    ORDER BY count DESC
");

echo "<h2 class='text-center my-4'>🛠 ניהול תגיות</h2>";
echo "<div class='container'>";
echo "<table class='table table-bordered table-striped text-center' style='direction: rtl'>";
echo "<thead class='table-light'><tr>
        <th>תגית</th>
        <th>כמות</th>
        <th>פוסטרים</th>
        <th>שינוי שם</th>
        <th>מחיקה</th>
      </tr></thead><tbody>";

while ($row = $res->fetch_assoc()) {
    $genre = htmlspecialchars($row['genre']);
    $count = (int)$row['count'];
    $url = "home.php?user_tag=" . urlencode($row['genre']);

    echo "<tr>";
    echo "<td>$genre</td>";
    echo "<td>$count</td>";
    echo "<td><a href='$url' class='btn btn-sm btn-outline-primary' target='_blank'>📂 הצג פוסטרים</a></td>";
    echo "<td>
        <form method='post' class='d-flex justify-content-center align-items-center'>
            <input type='hidden' name='old_name' value=\"$genre\">
            <input type='text' name='new_name' class='form-control form-control-sm w-auto mx_2' required>
            <button type='submit' class='btn btn-sm btn-warning'>שנה</button>
        </form>
    </td>";
    echo "<td><a href='manage_name_user_tag.php?delete=" . urlencode($row['genre']) . "' class='btn btn-sm btn-danger' onclick=\"return confirm('למחוק את \\\"$genre\\\"?')\">🗑</a></td>";
    echo "</tr>";
}

echo "</tbody></table></div>";
include 'footer.php';
?>