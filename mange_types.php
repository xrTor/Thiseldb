<?php include 'header.php'; 
require_once 'server.php';
?>

<?php
require_once 'server.php';

// שינוי קבוצתי לפי checkboxים + מזהים מעורבים
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
  $new_type_id = intval($_POST['bulk_type']);
  $selected = $_POST['selected_ids'] ?? [];
  $input_text = trim($_POST['bulk_mixed_list'] ?? '');

  $updated = 0;
  $not_found = [];

  // 1. עדכון לפי checkboxים
  foreach ($selected as $poster_id) {
    $poster_id_int = intval($poster_id);
    $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_type_id, $poster_id_int);
    $stmt->execute();
    $updated++;
    $stmt->close();
  }

  // 2. עדכון לפי מזהים מהטקסט
  if ($input_text !== '') {
    $lines = preg_split('/[\s,]+/', $input_text);
    foreach ($lines as $item) {
      $item = trim($item);
      if ($item === '') continue;

      // IMDb מזהה
      if (preg_match('/tt\d{7,}/', $item, $matches)) {
        $imdb_id = $matches[0];
        $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE imdb_id = ?");
        $stmt->bind_param("is", $new_type_id, $imdb_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
          $updated++;
        } else {
          $not_found[] = $imdb_id;
        }
        $stmt->close();

      // מזהה פנימי ID
      } elseif (is_numeric($item)) {
        $id_val = intval($item);
        $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_type_id, $id_val);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
          $updated++;
        } else {
          $not_found[] = $id_val;
        }
        $stmt->close();

      // לינק למערכת המקומית
      } elseif (preg_match('/poster\.php\?id=(\d+)/', $item, $matches)) {
        $id_val = intval($matches[1]);
        $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_type_id, $id_val);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
          $updated++;
        } else {
          $not_found[] = $item;
        }
        $stmt->close();

      } else {
        $not_found[] = $item;
      }
    }
  }

  echo "<p style='color:green;'>✅ עודכנו $updated פוסטרים!</p>";
  if (!empty($not_found)) {
    echo "<p style='color:orange;'>⚠️ לא נמצאו התאמות עבור: " . implode(', ', array_unique($not_found)) . "</p>";
  }
}

// שינוי פרטני
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  foreach ($_POST['poster_type'] as $poster_id => $new_type_id) {
    $poster_id_int = intval($poster_id);
    $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_type_id, $poster_id_int);
    $stmt->execute();
    $stmt->close();
  }
  echo "<p style='color:green;'>✅ הסוגים הפרטניים עודכנו!</p>";
}

// שליפה
$result = $conn->query("SELECT id, title_en, title_he, image_url, imdb_id, type_id FROM posters ORDER BY id DESC");
$type_result = $conn->query("SELECT id, label_he, icon FROM poster_types ORDER BY sort_order ASC");

$type_options = [];
while ($type = $type_result->fetch_assoc()) {
  $type_options[$type['id']] = [
    'label' => $type['label_he'],
    'icon'  => $type['icon']
  ];
}
?>
<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>🎬 ניהול סוגי פוסטרים</title>
  <style>
    body { font-family: Arial; direction: rtl; background: #f5f5f5; padding: 40px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #ccc; }
    th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: middle; }
    img { width: 90px; border-radius: 4px; }
    select, textarea { padding: 6px; font-size:14px; border-radius: 4px; border: 1px solid #ccc; }
    button { padding: 8px 16px; margin-top: 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #0056b3; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    h2, h3 { margin-bottom: 25px; }
    textarea { resize: vertical; width: 100%; }
  </style>
</head>
<body>

<h2>📁 ניהול סוגי פוסטרים</h2>

<form method="post">

  <h3>🔁 החלת סוג נבחר</h3>
  <label>בחר סוג:</label>
  <select name="bulk_type">
    <?php foreach ($type_options as $type_id => $data): ?>
      <option value="<?= $type_id ?>"><?= htmlspecialchars($data['icon'] . ' ' . $data['label']) ?></option>
    <?php endforeach; ?>
  </select>

  <br><br>

  <label for="bulk_mixed_list">הכנס מזהים (ID, IMDb, או לינקים):</label><br>
  <textarea name="bulk_mixed_list" rows="3" placeholder="tt1375666, 45, https://www.imdb.com/title/tt0111161, poster.php?id=78"></textarea>
  <small style="color:#777;">ניתן להזין מזהים מופרדים בפסיק, שורות או רווחים</small>

  <br><br>
  <button type="submit" name="bulk_update" value="1">💾 החל על מזהים / נבחרים</button>
  <button type="submit" name="update" value="1">💾 שמור שינויים פרטניים</button>

  <br><br>

  <table>
    <tr>
      <th>✔️</th>
      <th>תמונה</th>
      <th>שם</th>
      <th>IMDb</th>
      <th>עמוד פוסטר</th>
      <th>סוג נוכחי</th>
      <th>שינוי סוג</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
      <td><img src="<?= htmlspecialchars($row['image_url']) ?>" alt="poster"></td>
      <td>
        <div style="line-height:1.5;">
          <strong><?= htmlspecialchars($row['title_en']) ?></strong><br>
          <span style="color:#555; font-size:14px;"><?= htmlspecialchars($row['title_he']) ?></span>
        </div>
      </td>
      <td>
        <?php if (!empty($row['imdb_id'])): ?>
          <a href="https://www.imdb.com/title/<?= htmlspecialchars($row['imdb_id']) ?>" target="_blank">
            <?= htmlspecialchars($row['imdb_id']) ?>
          </a>
        <?php else: ?> — <?php endif; ?>
      </td>
      <td><a href="poster.php?id=<?= $row['id'] ?>" target="_blank">🔗 פתיחה</a></td>
      <td>
        <?= isset($type_options[$row['type_id']])
            ? htmlspecialchars($type_options[$row['type_id']]['icon'] . ' ' . $type_options[$row['type_id']]['label'])
            : '<span style="color:red;">⛔ לא מוכר</span>' ?>
      </td>
      <td>
        <select name="poster_type[<?= $row['id'] ?>]">
          <?php foreach ($type_options as $type_id => $data): ?>
            <option value="<?= $type_id ?>" <?= $row['type_id'] == $type_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($data['icon'] . ' ' . $data['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>

    <button type="submit" name="bulk_update" value="1">💾 החל על מזהים / נבחרים</button>
  <button type="submit" name="update" value="1">💾 שמור שינויים פרטניים</button>

</form>

</body>
</html>

<?php include 'footer.php'; ?>