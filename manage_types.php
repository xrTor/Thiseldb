<?php
require_once 'server.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('IMAGE_BASE_PATH')) {
    define('IMAGE_BASE_PATH', 'images/types/');
}

/** h(): עטיפה בטוחה ל-htmlspecialchars */
if (!function_exists('h')) {
    function h($value) {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_array($value)) $value = implode(', ', array_map('strval', $value));
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/** הפניה בטוחה: header אם אפשר, אחרת JS/Meta */
if (!function_exists('safe_redirect')) {
    function safe_redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
            exit;
        }
        echo '<script>location.href=' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
        exit;
    }
}

/* ───────── מחיקה ───────── */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM poster_types WHERE id = $id");
    safe_redirect("manage_types.php");
}

/* ───────── שמירה ───────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $id) {
            $id_int      = intval($id);
            $code        = $_POST['code'][$id]        ?? '';
            $label_he    = $_POST['label_he'][$id]    ?? '';
            $label_en    = $_POST['label_en'][$id]    ?? '';
            $icon        = $_POST['icon'][$id]        ?? '';
            $sort_order  = intval($_POST['sort_order'][$id] ?? 0);

            $image_raw   = $_POST['image'][$id] ?? '';
            $image_path  = is_string($image_raw) ? trim($image_raw) : '';

            $stmt = $conn->prepare("
                UPDATE poster_types
                   SET code        = ?,
                       label_he    = ?,
                       label_en    = ?,
                       icon        = ?,
                       sort_order  = ?,
                       image       = ?
                 WHERE id = ?
            ");
            $stmt->bind_param(
                "ssssisi",
                $code, $label_he, $label_en, $icon, $sort_order, $image_path, $id_int
            );
            $stmt->execute();
            $stmt->close();
        }
    }
    $saved_message = "✅ כל הסוגים נשמרו בהצלחה!";
}

/* ───────── הוספה ───────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $code        = $_POST['code']     ?? '';
    $label_he    = $_POST['label_he'] ?? '';
    $label_en    = $_POST['label_en'] ?? '';
    $icon        = $_POST['icon']     ?? '';

    // הבא בתור בסדר הופעה
    $next = 1;
    if ($res = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM poster_types")) {
        if ($row = $res->fetch_assoc()) {
            $next = (int)$row['next_order'];
        }
    }
    $sort_order = $next;

    $image_raw   = $_POST['image'] ?? '';
    $image_path  = is_string($image_raw) ? trim($image_raw) : '';

    $stmt = $conn->prepare("
        INSERT INTO poster_types (code, label_he, label_en, icon, sort_order, image)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssis",
        $code, $label_he, $label_en, $icon, $sort_order, $image_path
    );
    $stmt->execute();
    $stmt->close();

    safe_redirect("manage_types.php");
}

/* ───────── שליפה ───────── */
$types = $conn->query("SELECT * FROM poster_types ORDER BY sort_order ASC, id ASC");
$next_sort_order = 1;
if ($res2 = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM poster_types")) {
    if ($r2 = $res2->fetch_assoc()) {
        $next_sort_order = (int)$r2['next_order'];
    }
}

/* רק עכשיו לכלול header.php כדי שלא יהיה פלט לפני הפניות */
require_once 'header.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>ניהול סוגי פוסטרים</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 40px; direction: rtl; }
    h2, h3 { margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border:1px solid #ccc; margin-bottom: 30px; }
    th, td { padding: 12px; border: 1px solid #ccc; text-align: right; vertical-align: top; }
    th { background: #eee; font-size:15px; }
    input[type="text"], input[type="number"], textarea {
      width: 100%; padding: 6px; margin-top: 4px;
      font-size: 14px; box-sizing: border-box;
      border:1px solid #ccc; border-radius:4px; background:#fcfcfc;
    }
    img.preview { max-width: 60px; max-height: 40px; display: block; margin-bottom: 5px; border-radius: 3px; border: 1px solid #ddd; }
    button { padding: 6px 14px; margin-top: 10px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#0056b3; }
    a { text-decoration:none; color:#c00; font-size:15px; }
    tbody tr:hover { background:#f0f8ff; cursor:grab; }
  </style>
</head>
<body>

<h2>📦 ניהול סוגי פוסטרים</h2>

<?php if (!empty($saved_message)): ?>
  <p style="color:green;"><?= h($saved_message) ?></p>
<?php endif; ?>

<form method="post">
  <table id="types-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>קוד</th>
        <th>עברית</th>
        <th>אנגלית</th>
        <th>אייקון</th>
        <th>שם קובץ תמונה</th>
        <th>סדר</th>
        <th>מחיקה</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($type = $types->fetch_assoc()): ?>
      <?php
        $img_val = $type['image'] ?? '';
        $img_str = is_string($img_val) ? trim($img_val) : '';
        $id_int  = isset($type['id']) ? (int)$type['id'] : 0;
        $img_fs  = __DIR__ . '/' . IMAGE_BASE_PATH . $img_str;
      ?>
      <tr>
        <td>
          <?= h($type['id']) ?>
          <input type="hidden" name="ids[]" value="<?= h($type['id']) ?>">
        </td>

        <td><input type="text" name="code[<?= h($type['id']) ?>]"      value="<?= h($type['code']) ?>"></td>
        <td><input type="text" name="label_he[<?= h($type['id']) ?>]"  value="<?= h($type['label_he']) ?>"></td>
        <td><input type="text" name="label_en[<?= h($type['id']) ?>]"  value="<?= h($type['label_en']) ?>"></td>
        <td><input type="text" name="icon[<?= h($type['id']) ?>]"      value="<?= h($type['icon']) ?>"></td>

        <td>
          <?php if ($img_str !== '' && file_exists($img_fs)): ?>
            <img src="<?= h(IMAGE_BASE_PATH . $img_str) ?>" alt="תצוגה מקדימה" class="preview">
          <?php endif; ?>
          <input type="text" name="image[<?= $id_int ?>]" value="<?= htmlspecialchars($img_str, ENT_QUOTES, 'UTF-8') ?>">
        </td>

        <td><input type="number" name="sort_order[<?= h($type['id']) ?>]" value="<?= h($type['sort_order'] ?? 0) ?>"></td>
        <td><a href="?delete=<?= h($type['id']) ?>" onclick="return confirm('למחוק סוג זה?')">🗑️</a></td>
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

  <label>שם קובץ תמונה (מתוך images/types/)</label>
  <input type="text" name="image" placeholder="example.png">

  <label>סדר הופעה בתפריט</label>
  <input type="number" name="sort_order" value="<?= $next_sort_order ?>">

  <button type="submit" name="add_type">✅ הוסף סוג</button>
</form>

<?php include 'footer.php'; ?>

</body>
</html>
