<?php
require_once 'server.php';
session_start();
include 'header.php';

// עדכון שם ז'אנר עם בדיקת כפילויות מול ז'אנרים ותגיות
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_name'], $_POST['new_name'])) {
    $old_name = trim($_POST['old_name']);
    $new_name = trim($_POST['new_name']);
    
    if ($old_name !== '' && $new_name !== '') {
        $stmt_find = $conn->prepare("SELECT id, genre FROM posters WHERE genre LIKE ?");
        $like_old = "%$old_name%";
        $stmt_find->bind_param("s", $like_old);
        $stmt_find->execute();
        $affected_posters = $stmt_find->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_find->close();

        $updated_count = 0;

        foreach ($affected_posters as $poster) {
            $poster_id = $poster['id'];
            
            // 1. קבל את רשימת התגיות הקיימת של הפוסטר
            $stmt_tags = $conn->prepare("SELECT genre FROM user_tags WHERE poster_id = ?");
            $stmt_tags->bind_param("i", $poster_id);
            $stmt_tags->execute();
            $tags_result = $stmt_tags->get_result();
            $user_tags_lc = [];
            while ($tag_row = $tags_result->fetch_assoc()) {
                $user_tags_lc[] = strtolower(trim($tag_row['genre']));
            }
            $stmt_tags->close();
            
            // 2. הסר את כל המופעים של הז'אנר הישן
            $genres_array = array_map('trim', explode(',', $poster['genre']));
            $genres_after_removal = [];
            foreach ($genres_array as $genre) {
                if (strcasecmp($genre, $old_name) !== 0) {
                    $genres_after_removal[] = $genre;
                }
            }

            // 3. פרק את השם החדש לחלקים ובדוק כל חלק לפני הוספה
            $new_parts_to_add = array_map('trim', explode(',', $new_name));
            $final_genres = $genres_after_removal;

            foreach ($new_parts_to_add as $part) {
                if (empty($part)) continue;
                
                $part_lc = strtolower($part);
                
                // צור רשימה של כל המונחים הקיימים (ז'אנרים ותגיות) לבדיקה
                $current_final_genres_lc = array_map('strtolower', $final_genres);
                
                // הוסף את החלק החדש רק אם הוא לא קיים כבר בז'אנרים או בתגיות
                if (!in_array($part_lc, $current_final_genres_lc) && !in_array($part_lc, $user_tags_lc)) {
                    $final_genres[] = $part;
                }
            }

            // 4. בצע ניקוי סופי
            $final_genres = array_map(fn($g) => ucwords(strtolower(trim($g))), $final_genres);
            $final_genres = array_unique($final_genres);
            $final_genres = array_filter($final_genres, fn($g) => $g !== '');
            $final_genre_str = implode(', ', $final_genres);

            // 5. עדכן את הפוסטר
            $stmt_update = $conn->prepare("UPDATE posters SET genre = ? WHERE id = ?");
            $stmt_update->bind_param("si", $final_genre_str, $poster_id);
            $stmt_update->execute();
            $stmt_update->close();
            $updated_count++;
        }

        echo "<div class='alert alert-success text-center'>הז'אנר עודכן מ־<b>" . htmlspecialchars($old_name) . "</b> ל־<b>" . htmlspecialchars($new_name) . "</b> ב-<b>$updated_count</b> פוסטרים.</div>";
    }
}


// מחיקה (עם לוגיקה משופרת)
if (isset($_GET['delete'])) {
    $del = trim($_GET['delete']);
    if ($del !== '') {
        $stmt_find = $conn->prepare("SELECT id, genre FROM posters WHERE genre LIKE ?");
        $like_del = "%$del%";
        $stmt_find->bind_param("s", $like_del);
        $stmt_find->execute();
        $affected_posters = $stmt_find->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_find->close();

        foreach($affected_posters as $poster) {
            $genres_array = array_map('trim', explode(',', $poster['genre']));
            $final_genres = [];
            foreach($genres_array as $genre) {
                if(strcasecmp($genre, $del) !== 0) {
                    $final_genres[] = $genre;
                }
            }
            $final_genre_str = implode(', ', $final_genres);
            $stmt_update = $conn->prepare("UPDATE posters SET genre = ? WHERE id = ?");
            $stmt_update->bind_param("si", $final_genre_str, $poster['id']);
            $stmt_update->execute();
            $stmt_update->close();
        }

        echo "<div class='alert alert-danger text-center'>הז'אנר <b>" . htmlspecialchars($del) . "</b> נמחק מכל הפוסטרים</div>";
    }
}

// שליפת ז'אנרים מתוך posters (ללא שינוי)
$res = $conn->query("SELECT genre FROM posters WHERE genre IS NOT NULL AND genre != ''");

$genres = [];
while ($row = $res->fetch_assoc()) {
    $list = explode(',', $row['genre']);
    foreach ($list as $g) {
        $g = trim($g);
        if ($g !== '') {
            $key = mb_strtolower($g);
            if (!isset($genres[$key])) {
                $genres[$key] = ['label' => $g, 'count' => 1];
            } else {
                $genres[$key]['count']++;
            }
        }
    }
}

usort($genres, fn($a, $b) => $b['count'] - $a['count']);

echo "<h2 class='text-center my-4'>🛠 ניהול ז'אנרים</h2>";
echo "<div class='container'>";
echo "<table class='table table-bordered table-striped text-center' style='direction: rtl'>";
echo "<thead class='table-light'><tr>
        <th>ז'אנר</th>
        <th>כמות</th>
        <th>פוסטרים</th>
        <th>שינוי שם</th>
        <th>מחיקה</th>
      </tr></thead><tbody>";

foreach ($genres as $g) {
    $genre = htmlspecialchars($g['label']);
    $count = $g['count'];
    $url = "home.php?genre=" . urlencode('' . $g['label'] . '');

    echo "<tr>";
    echo "<td>$genre</td>";
    echo "<td>$count</td>";
    echo "<td><a href='$url' class='btn btn-sm btn-outline-primary' target='_blank'>📂 הצג פוסטרים</a></td>";
    echo "<td>
        <form method='post' class='d-flex justify-content-center align-items-center'>
            <input type='hidden' name='old_name' value=\"$genre\">
            <input type='text' name='new_name' class='form-control form-control-sm w-auto mx-2' required>
            <button type='submit' class='btn btn-sm btn-warning'>שנה</button>
        </form>
    </td>";
    echo "<td><a href='manage_name_genres.php?delete=" . urlencode($g['label']) . "' class='btn btn-sm btn-danger' onclick=\"return confirm('למחוק את \\\"$genre\\\" מכל הפוסטרים?')\">🗑</a></td>";
    echo "</tr>";
}

echo "</tbody></table></div>";
include 'footer.php';
?>