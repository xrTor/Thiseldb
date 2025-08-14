<?php
require_once 'header.php';
require_once 'server.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// מחיקה
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->query("DELETE FROM poster_types WHERE id = $id");
  header("Location: type_admin.php");
  exit;
}

// שמירה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
  foreach ($_POST['ids'] as $id) {
    $code        = $_POST['code'][$id];
    $label_he    = $_POST['label_he'][$id];
    $label_en    = $_POST['label_en'][$id];
    $icon        = $_POST['icon'][$id];
    $description = $_POST['description'][$id];
    $sort_order  = intval($_POST['sort_order'][$id]);
    $id_int      = intval($id);

    $stmt = $conn->prepare("UPDATE poster_types SET code=?, label_he=?, label_en=?, icon=?, description=?, sort_order=? WHERE id=?");
    $stmt->bind_param("ssssiii", $code, $label_he, $label_en, $icon, $description, $sort_order, $id_int);
    $stmt->execute();
    $stmt->close();
  }
  echo "<p style='color:green;'>✅ כל הסוגים נשמרו בהצלחה!</p>";
}

// הוספה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
  $stmt = $conn->prepare("INSERT INTO poster_types (code, label_he, label_en, icon, description, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("sssssi",
    $_POST['code'], $_POST['label_he'], $_POST['label_en'],
    $_POST['icon'], $_POST['description'], intval($_POST['sort_order'] ?? 0)
  );
  $stmt->execute();
  header("Location: type_admin.php");
  exit;
}

// שליפה
$types = $conn->query("SELECT * FROM poster_types ORDER BY sort_order ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>ניהול סוגי פוסטרים</title>
  <style>
    body { font-family: Arial; background: #f4f4f4; padding: 40px; direction: rtl; }
    h2, h3 { margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border:1px solid #ccc; margin-bottom: 30px; }
    th, td { padding: 12px; border: 1px solid #ccc; text-align: right; vertical-align: top; }
    th { background: #eee; font-size:15px; }
    input[type="text"], input[type="number"], textarea {
      width: 100%; padding: 6px; margin-top: 4px;
      font-size: 14px; box-sizing: border-box;
      border:1px solid #ccc; border-radius:4px; background:#fcfcfc;
    }
    button {
      padding: 6px 14px; margin-top: 10px;
      background:#007bff; color:#fff;
      border:none; border-radius:4px; cursor:pointer;
    }
    button:hover { background:#0056b3; }
    a { text-decoration:none; color:#c00; font-size:15px; }
    tbody tr:hover { background:#f0f8ff; cursor:grab; }
  </style>
</head>
<body>

<h2>📦 ניהול סוגי פוסטרים</h2>

<form method="post">
  <table id="types-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>קוד</th>
        <th>עברית</th>
        <th>אנגלית</th>
        <th>אייקון</th>
        <th>תיאור</th>
        <th>סדר</th>
        <th>מחיקה</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($type = $types->fetch_assoc()): ?>
        <tr>
          <td>
            <?= $type['id'] ?>
            <input type="hidden" name="ids[]" value="<?= $type['id'] ?>">
          </td>
          <td><input type="text" name="code[<?= $type['id'] ?>]" value="<?= htmlspecialchars($type['code']) ?>"></td>
          <td><input type="text" name="label_he[<?= $type['id'] ?>]" value="<?= htmlspecialchars($type['label_he']) ?>"></td>
          <td><input type="text" name="label_en[<?= $type['id'] ?>]" value="<?= htmlspecialchars($type['label_en']) ?>"></td>
          <td><input type="text" name="icon[<?= $type['id'] ?>]" value="<?= htmlspecialchars($type['icon']) ?>"></td>
          <td><textarea name="description[<?= $type['id'] ?>]" rows="2"><?= htmlspecialchars($type['description']) ?></textarea></td>
          <td><input type="number" name="sort_order[<?= $type['id'] ?>]" value="<?= intval($type['sort_order']) ?>"></td>
          <td><a href="?delete=<?= $type['id'] ?>" onclick="return confirm('למחוק סוג זה?')">🗑️</a></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <button type="submit" name="save_all">💾 שמור את כל הסוגים</button>
</form>

<h3>➕ הוספת סוג חדש</h3>
<form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:600px;">
  <label>קוד פנימי (movie, series)</label>
  <input type="text" name="code" required>

  <label>שם בעברית</label>
  <input type="text" name="label_he" required>

  <label>שם באנגלית</label>
  <input type="text" name="label_en" required>

  <label>אייקון (🎬)</label>
  <input type="text" name="icon">

  <label>תיאור הסוג</label>
  <textarea name="description" rows="2"></textarea>

  <label>סדר הופעה בתפריט</label>
  <input type="number" name="sort_order" value="0">

  <button type="submit" name="add_type">✅ הוסף סוג</button>
</form>

<?php include 'footer.php'; ?>

<!-- SortableJS for drag and update sort_order -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
  Sortable.create(document.querySelector("#types-table tbody"), {
    animation: 150,
    handle: 'td',
    onEnd: function () {
      document.querySelectorAll("#types-table tbody tr").forEach((row, index) => {
        const input = row.querySelector('input[name^="sort_order"]');
        if (input) input.value = index + 1;
      });
    }
  });
</script>
</body>
</html>