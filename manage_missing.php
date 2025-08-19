<?php
require_once 'server.php';
include 'header.php';

$types = [];
$type_result = $conn->query("SELECT id, label_he, icon FROM poster_types ORDER BY id ASC");
while ($row = $type_result->fetch_assoc()) {
    $types[$row['id']] = ['label' => $row['label_he'], 'icon' => $row['icon']];
}

$selected_type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;

// עיצוב CSS מודרני עם יישור מתוקן
echo <<<STYLE
<style>
  body { font-family: 'Segoe UI', sans-serif; }
  .styled-table {
    width: 95%; margin: 20px auto; border-collapse: collapse; direction: rtl;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .styled-table th {
    background-color: #F2F2F3; color: black; padding: 10px;
    text-align: center; /* כותרות ממורכזות */
  }
  .styled-table td {
    padding: 10px; text-align: right; /* תוכן מיושר לימין */ border-bottom: 1px solid #ccc;
  }
  .styled-table td:nth-child(1), .styled-table td:nth-child(3), .styled-table td:nth-child(4), .styled-table td:nth-child(5) {
    text-align: center; /* שמירה על יישור מרכזי לעמודות ספציפיות */
  }
  .styled-table tr:nth-child(even) { background-color: #f9f9f9; }
  .btn {
    text-decoration: none; padding: 4px 8px; border-radius: 4px;
    font-size: 13px;
  }
  .btn-edit { background: /* #FF9800 */; color: white; }
  .filters { text-align: center; margin: 20px; font-size: 16px; }
  .filters select { padding: 6px; font-size: 15px; }
</style>
STYLE;


echo '<h2 style="text-align:center; margin:10px;">🎯 פוסטרים עם מידע חסר</h2>';


// טופס סינון
echo '<form method="get" class="filters">
  <label>סוג פוסטר:</label>
  <select name="type_id" onchange="this.form.submit()">
    <option value="0">— כל הסוגים —</option>';
foreach ($types as $id => $data) {
    $sel = ($selected_type_id === $id) ? 'selected' : '';
    $icon = $data['icon'] ? $data['icon'] . ' ' : '';
    echo "<option value=\"$id\" $sel>{$icon}{$data['label']}</option>";
}
echo '</select></form>';

$where = "
  (plot IS NULL OR plot = '')
  OR (plot_he IS NULL OR plot_he = '')
  OR (imdb_rating IS NULL OR imdb_rating = 0)
  OR (image_url IS NULL OR image_url = '' OR image_url = 'N/A')
  OR (year IS NULL OR year = '')
  OR (genre IS NULL OR genre = '')
  OR (title_he IS NULL OR title_he = '')
  OR (title_en IS NULL OR title_en = '')
";
if ($selected_type_id > 0) {
    $where = "(type_id = $selected_type_id) AND ($where)";
}

$count_result = $conn->query("SELECT COUNT(*) AS total FROM posters WHERE $where");
$total_missing = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

echo "<p style='text-align:center; font-weight:bold;'>נמצאו <span style='color:darkred;'>$total_missing</span> פוסטרים חסרים</p>";

$query = "SELECT * FROM posters WHERE $where ORDER BY id DESC LIMIT 1000000";
$result = $conn->query($query);

if ($total_missing === 0) {
  echo "<p style='text-align:center;'>✅ כל הפוסטרים תקינים.</p>";
} else {
  echo '<table class="styled-table">
    <tr>
      <th>#</th>
      <th>כותרת</th>
      <th>שנה</th>
      <th>דירוג</th>
      <th>IMDb</th>
      <th>חסרים</th>
      <th>עריכה</th>
    </tr>';

  while ($row = $result->fetch_assoc()) {
    $missing = [];
    if (empty($row['title_he']))       $missing[] = 'שם בעברית';
    if (empty($row['title_en']))       $missing[] = 'שם באנגלית';
    if (empty($row['plot']))           $missing[] = 'תקציר';
    if (empty($row['plot_he']))        $missing[] = 'תקציר בעברית';
    if (empty($row['imdb_rating']) || $row['imdb_rating'] == 0) $missing[] = 'דירוג';
    if (empty($row['image_url']) || $row['image_url'] == 'N/A') $missing[] = 'תמונה';
    if (empty($row['year']))           $missing[] = 'שנה';
    if (empty($row['genre']))          $missing[] = 'ז\'אנרים';

    $id_link = '<a href="poster.php?id=' . $row['id'] . '" target="_blank">' . $row['id'] . '</a>';
    $title_raw = $row['title_he'] ?: $row['title_en'];
    $title = mb_strlen($title_raw) > 60 ? mb_substr($title_raw, 0, 60) . '…' : $title_raw;
    $title_link = '<a href="poster.php?id=' . $row['id'] . '" target="_blank">' . htmlspecialchars($title) . '</a>';
    $imdb_id = htmlspecialchars($row['imdb_id']);
    $imdb_link = $imdb_id ? '<a href="https://www.imdb.com/title/' . $imdb_id . '" target="_blank">' . $imdb_id . '</a>' : '—';

    echo '<tr>
      <td>' . $id_link . '</td>
      <td>' . $title_link . '</td>
      <td>' . htmlspecialchars($row['year']) . '</td>
      <td>' . htmlspecialchars($row['imdb_rating']) . '</td>
      <td>' . $imdb_link . '</td>
      <td style="color:red;">' . implode(', ', $missing) . '</td>
      <td><a class="btn btn-edit" href="edit.php?id=' . $row['id'] . '" target="_blank">✏ ערוך</a></td>
    </tr>';
  }

  echo '</table>';
}

include 'footer.php';
?>