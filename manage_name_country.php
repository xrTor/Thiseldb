<?php
require_once 'server.php';
session_start();
include 'header.php';

/* ===== עדכון שם מדינה ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_name'], $_POST['new_name'])) {
    $old_name = trim($_POST['old_name']);
    $new_name = trim($_POST['new_name']);
    
    if ($old_name !== '' && $new_name !== '') {
        // שליפת פוסטרים שמכילים את המדינה הישנה
        $stmt = $conn->prepare("SELECT id, countries FROM posters WHERE countries LIKE ?");
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
            $countries_str = $row['countries'] ?? '';
            $countries = array_filter(array_map('trim', explode(',', $countries_str)));

            // הסרה של המדינה הישנה
            $new_list = [];
            foreach ($countries as $c) {
                if (strcasecmp($c, $old_name) !== 0) {
                    $new_list[] = $c;
                }
            }

            // הוספת המדינה החדשה (יכול להיות כמה עם פסיקים)
            $parts = array_map('trim', explode(',', $new_name));
            foreach ($parts as $p) {
                if ($p === '') continue;
                $exists = false;
                foreach ($new_list as $nc) {
                    if (strcasecmp($nc, $p) === 0) { $exists = true; break; }
                }
                if (!$exists) $new_list[] = ucwords(strtolower($p));
            }

            // ניקוי כפילויות ושמירה
            $new_list = array_unique($new_list);
            $final_str = implode(', ', $new_list);

            $stmt_up = $conn->prepare("UPDATE posters SET countries = ? WHERE id = ?");
            $stmt_up->bind_param("si", $final_str, $poster_id);
            $stmt_up->execute();
            $stmt_up->close();
        }

        echo "<div class='alert alert-success text-center'>המדינה עודכנה מ־<b>" . htmlspecialchars($old_name) . "</b> ל־<b>" . htmlspecialchars($new_name) . "</b> ב־<b>$updated_posters_count</b> פוסטרים.</div>";
    }
}

/* ===== מחיקה ===== */
if (isset($_GET['delete'])) {
    $del = trim($_GET['delete']);
    if ($del !== '') {
        $stmt = $conn->prepare("SELECT id, countries FROM posters WHERE countries LIKE ?");
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
            $countries = array_filter(array_map('trim', explode(',', $row['countries'])));
            $new_list = [];
            foreach ($countries as $c) {
                if (strcasecmp($c, $del) !== 0) {
                    $new_list[] = $c;
                }
            }
            $new_list = array_unique($new_list);
            $final_str = implode(', ', $new_list);

            $stmt_up = $conn->prepare("UPDATE posters SET countries = ? WHERE id = ?");
            $stmt_up->bind_param("si", $final_str, $poster_id);
            $stmt_up->execute();
            $stmt_up->close();
        }

        echo "<div class='alert alert-danger text-center'>המדינה <b>" . htmlspecialchars($del) . "</b> נמחקה מכל הפוסטרים</div>";
    }
}

/* ===== שליפה למסך ===== */
// אוספים את כל המדינות מתוך posters
$res = $conn->query("SELECT countries FROM posters WHERE countries IS NOT NULL AND TRIM(countries) != ''");
$counts = [];
while ($row = $res->fetch_assoc()) {
    $parts = array_filter(array_map('trim', explode(',', $row['countries'])));
    foreach ($parts as $p) {
        if ($p === '') continue;
        $p_clean = ucwords(strtolower($p));
        if (!isset($counts[$p_clean])) $counts[$p_clean] = 0;
        $counts[$p_clean]++;
    }
}
arsort($counts);

echo "<h2 class='text-center my-4'>🌍 ניהול מדינות</h2>";
echo "<div class='container'>";
echo "<table class='table table-bordered table-striped text-center' style='direction: rtl'>";
echo "<thead class='table-light'><tr>
        <th>מדינה</th>
        <th>כמות</th>
        <th>פוסטרים</th>
        <th>שינוי שם</th>
        <th>מחיקה</th>
      </tr></thead><tbody>";

foreach ($counts as $country => $count) {
    $url = "home.php?country=" . urlencode($country);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($country) . "</td>";
    echo "<td>$count</td>";
    echo "<td><a href='$url' class='btn btn-sm btn-outline-primary' target='_blank'>📂 הצג פוסטרים</a></td>";
    echo "<td>
        <form method='post' class='d-flex justify-content-center align-items-center'>
            <input type='hidden' name='old_name' value=\"" . htmlspecialchars($country) . "\">
            <input type='text' name='new_name' class='form-control form-control-sm w-auto mx-2' required>
            <button type='submit' class='btn btn-sm btn-warning'>שנה</button>
        </form>
    </td>";
    echo "<td><a href='manage_name_country.php?delete=" . urlencode($country) . "' class='btn btn-sm btn-danger' onclick=\"return confirm('למחוק את \\\"$country\\\" מכל הפוסטרים?')\">🗑</a></td>";
    echo "</tr>";
}

echo "</tbody></table></div>";

include 'footer.php';
?>
