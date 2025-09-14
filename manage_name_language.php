<?php
require_once 'server.php';
session_start();
include 'header.php';

/* ===== עדכון שם שפה ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_name'], $_POST['new_name'])) {
    $old_name = trim($_POST['old_name']);
    $new_name = trim($_POST['new_name']);
    
    if ($old_name !== '' && $new_name !== '') {
        // שליפת פוסטרים שמכילים את השפה הישנה
        $stmt = $conn->prepare("SELECT id, languages FROM posters WHERE languages LIKE ?");
        $like = '%' . $old_name . '%';
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $affected = [];
        while ($row = $res->fetch_assoc()) {
            $affected[] = $row;
        }
        $stmt->close();

        $updated_posters_count = count($affected);

        foreach ($affected as $row) {
            $poster_id = (int)$row['id'];
            $langs_str = $row['languages'] ?? '';
            $langs = array_filter(array_map('trim', explode(',', $langs_str)));

            // הסרת השפה הישנה
            $new_list = [];
            foreach ($langs as $l) {
                if (strcasecmp($l, $old_name) !== 0) {
                    $new_list[] = $l;
                }
            }

            // הוספת השפה החדשה (יכול להיות כמה עם פסיקים)
            $parts = array_map('trim', explode(',', $new_name));
            foreach ($parts as $p) {
                if ($p === '') continue;
                $exists = false;
                foreach ($new_list as $nl) {
                    if (strcasecmp($nl, $p) === 0) { $exists = true; break; }
                }
                if (!$exists) $new_list[] = ucwords(strtolower($p));
            }

            // ניקוי כפילויות ושמירה
            $new_list = array_unique($new_list);
            $final_str = implode(', ', $new_list);

            $stmt_up = $conn->prepare("UPDATE posters SET languages = ? WHERE id = ?");
            $stmt_up->bind_param("si", $final_str, $poster_id);
            $stmt_up->execute();
            $stmt_up->close();
        }

        echo "<div class='alert alert-success text-center'>השפה עודכנה מ־<b>" . htmlspecialchars($old_name) . "</b> ל־<b>" . htmlspecialchars($new_name) . "</b> ב־<b>$updated_posters_count</b> פוסטרים.</div>";
    }
}

/* ===== מחיקה ===== */
if (isset($_GET['delete'])) {
    $del = trim($_GET['delete']);
    if ($del !== '') {
        $stmt = $conn->prepare("SELECT id, languages FROM posters WHERE languages LIKE ?");
        $like = '%' . $del . '%';
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $affected = [];
        while ($row = $res->fetch_assoc()) {
            $affected[] = $row;
        }
        $stmt->close();

        foreach ($affected as $row) {
            $poster_id = (int)$row['id'];
            $langs = array_filter(array_map('trim', explode(',', $row['languages'])));
            $new_list = [];
            foreach ($langs as $l) {
                if (strcasecmp($l, $del) !== 0) {
                    $new_list[] = $l;
                }
            }
            $new_list = array_unique($new_list);
            $final_str = implode(', ', $new_list);

            $stmt_up = $conn->prepare("UPDATE posters SET languages = ? WHERE id = ?");
            $stmt_up->bind_param("si", $final_str, $poster_id);
            $stmt_up->execute();
            $stmt_up->close();
        }

        echo "<div class='alert alert-danger text-center'>השפה <b>" . htmlspecialchars($del) . "</b> נמחקה מכל הפוסטרים</div>";
    }
}

/* ===== שליפה למסך ===== */
// אוספים את כל השפות מתוך posters
$res = $conn->query("SELECT languages FROM posters WHERE languages IS NOT NULL AND TRIM(languages) != ''");
$counts = [];
while ($row = $res->fetch_assoc()) {
    $parts = array_filter(array_map('trim', explode(',', $row['languages'])));
    foreach ($parts as $p) {
        if ($p === '') continue;
        $p_clean = ucwords(strtolower($p));
        if (!isset($counts[$p_clean])) $counts[$p_clean] = 0;
        $counts[$p_clean]++;
    }
}
arsort($counts);

echo "<h2 class='text-center my-4'>🌐 ניהול שפות</h2>";
echo "<div class='container'>";
echo "<table class='table table-bordered table-striped text-center' style='direction: rtl'>";
echo "<thead class='table-light'><tr>
        <th>שפה</th>
        <th>כמות</th>
        <th>פוסטרים</th>
        <th>שינוי שם</th>
        <th>מחיקה</th>
      </tr></thead><tbody>";

foreach ($counts as $lang => $count) {
    $url = "home.php?lang_code=" . urlencode($lang);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($lang) . "</td>";
    echo "<td>$count</td>";
    echo "<td><a href='$url' class='btn btn-sm btn-outline-primary' target='_blank'>📂 הצג פוסטרים</a></td>";
    echo "<td>
        <form method='post' class='d-flex justify-content-center align-items-center'>
            <input type='hidden' name='old_name' value=\"" . htmlspecialchars($lang) . "\">
            <input type='text' name='new_name' class='form-control form-control-sm w-auto mx-2' required>
            <button type='submit' class='btn btn-sm btn-warning'>שנה</button>
        </form>
    </td>";
    echo "<td><a href='manage_name_language.php?delete=" . urlencode($lang) . "' class='btn btn-sm btn-danger' onclick=\"return confirm('למחוק את \\\"$lang\\\" מכל הפוסטרים?')\">🗑</a></td>";
    echo "</tr>";
}

echo "</tbody></table></div>";

include 'footer.php';
?>
