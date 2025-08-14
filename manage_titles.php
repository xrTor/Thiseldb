<?php
require_once 'server.php';
session_start();
include 'header.php';

// שמירה מרוכזת ב-AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'])) {
    header('Content-Type: application/json');
    $updated = 0;
    foreach ($_POST['data'] as $id => $row) {
        $title_he = trim($row['title_he'] ?? '');
        $title_en = trim($row['title_en'] ?? '');
        if ($title_he !== '' || $title_en !== '') {
            $stmt = $conn->prepare("UPDATE posters SET title_he = ?, title_en = ? WHERE id = ?");
            $stmt->bind_param("ssi", $title_he, $title_en, $id);
            $stmt->execute();
            $updated++;
        }
    }
    echo json_encode(['success' => true, 'updated' => $updated]);
    exit;
}

// חיפוש
$filter_aka = isset($_GET['aka']) && $_GET['aka'] == '1';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (preg_match('~tt\d{7,8}~', $search, $m)) {
    $search = $m[0];
}

$where = [];
$params = [];

if ($filter_aka) {
    $where[] = "(title_en LIKE '%AKA%' OR title_he LIKE '%AKA%')";
}
if ($search !== '') {
    $safe = "%{$search}%";
    $where[] = "(title_en LIKE ? OR title_he LIKE ? OR imdb_id = ? OR id = ?)";
    $params = [$safe, $safe, $search, intval($search)];
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT id, title_he, title_en, image_url, imdb_id FROM posters $where_sql ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if (count($params) === 4) {
    $stmt->bind_param("sssi", $params[0], $params[1], $params[2], $params[3]);
}
$stmt->execute();
$res = $stmt->get_result();

// כותרת וחיפוש
echo "<h2 class='text-center my-4'>📝 ניהול שמות פוסטרים</h2>";
echo "<div class='container mb-3 text-center'>";
echo "<form method='get' class='d-inline-flex gap-2'>";
echo "<input type='text' name='q' value='" . htmlspecialchars($_GET['q'] ?? '') . "' placeholder='שם, IMDb או מזהה' class='form-control form-control-sm' style='min-width: 250px;'>";
if ($filter_aka) echo "<input type='hidden' name='aka' value='1'>";
echo "<button type='submit' class='btn btn-sm btn-primary'>🔍 חפש</button>";
echo "<a href='manage_titles.php' class='btn btn-sm btn-outline-secondary'>🔄 איפוס</a>";
echo "</form> ";
if ($filter_aka) {
    $url = isset($_GET['q']) ? "manage_titles.php?q=" . urlencode($_GET['q']) : "manage_titles.php";
    echo "<a href='$url' class='btn btn-sm btn-outline-secondary ms-2'>הסר סינון AKA</a>";
} else {
    $url = "manage_titles.php?" . (isset($_GET['q']) ? "q=" . urlencode($_GET['q']) . "&" : "") . "aka=1";
    echo "<a href='$url' class='btn btn-sm btn-outline-primary ms-2'>הצג רק פוסטרים עם AKA</a>";
}
echo "</div>";

// טופס טבלה
echo "<div class='container'>";
echo "<form id='bulkForm'>";
echo "<table class='table table-bordered table-striped text-center align-middle' style='direction: rtl'>";
echo "<thead class='table-light'>
<tr>
    <th>ID</th>
    <th>תמונה</th>
    <th>IMDb</th>
    <th>שמות</th>
    <th>עדכון</th>
</tr>
</thead><tbody>";

while ($row = $res->fetch_assoc()) {
    $id = $row['id'];
    $title_he = htmlspecialchars($row['title_he']);
    $title_en = htmlspecialchars($row['title_en']);
    $imdb_id = htmlspecialchars($row['imdb_id']);
    $image = $row['image_url'] ?: 'images/default.jpg';

    $imdb_link = $imdb_id ? "<a href='https://www.imdb.com/title/$imdb_id/' target='_blank'>$imdb_id 🎬</a>" : "—";

    echo "<tr>";
    echo "<td><a href='poster.php?id=$id' target='_blank'>#$id</a></td>";
    echo "<td><a href='poster.php?id=$id' target='_blank'><img src='" . htmlspecialchars($image) . "' alt='poster' style='height:80px; max-width:60px; border-radius:1px;'></a></td>";
    echo "<td>$imdb_link</td>";
    echo "<td>
            <div class='mb-2'>
                <input type='text' name='data[$id][title_he]'
                       class='form-control form-control-sm text-center'
                       style='width:400px; max-width:700px;'
                       value=\"" . $title_he . "\" placeholder='שם בעברית'>
            </div>
            <div>
                <input type='text' name='data[$id][title_en]'
                       class='form-control form-control-sm'
                       style='width:100%; max-width:700px; text-align:left; direction:ltr;'
                       value=\"" . $title_en . "\" placeholder='English Title'>
            </div>
          </td>";
    echo "<td class='align-middle'>
            <button type='button' class='btn btn-sm btn-success' onclick='saveSingle($id)'>💾 שמור</button>
          </td>";
    echo "</tr>";
}

echo "</tbody></table>";
echo "<div class='text-center my-3'>";
echo "<button type='button' onclick='saveAllTitles()' class='btn btn-success px-4'>💾 שמור את כל הפוסטרים</button>";
echo "</div>";
echo "</form></div>";
?>

<style>
.updated-cell {
  animation: highlightFlash 2s ease-out;
  background-color: #d4edda !important;
}
@keyframes highlightFlash {
  0% { background-color: #d4edda; }
  100% { background-color: transparent; }
}
</style>

<script>
function saveAllTitles() {
  const form = document.getElementById('bulkForm');
  const formData = new FormData(form);
  fetch('manage_titles.php', {
    method: 'POST',
    body: formData
  }).then(r => r.json()).then(data => {
    if (data.success) {
      alert(`✅ עודכנו ${data.updated} פוסטרים`);
    }
  });
}

function saveSingle(id) {
  const titleHe = document.querySelector(`[name="data[${id}][title_he]"]`);
  const titleEn = document.querySelector(`[name="data[${id}][title_en]"]`);
  const formData = new FormData();
  formData.append(`data[${id}][title_he]`, titleHe.value);
  formData.append(`data[${id}][title_en]`, titleEn.value);
  fetch('manage_titles.php', {
    method: 'POST',
    body: formData
  }).then(r => r.json()).then(data => {
    if (data.success) {
      [titleHe, titleEn].forEach(el => {
        el.classList.add('updated-cell');
        setTimeout(() => el.classList.remove('updated-cell'), 2000);
      });
    }
  });
}
</script>

<?php include 'footer.php'; ?>
