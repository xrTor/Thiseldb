<?php include 'header.php'; 
require_once 'server.php';

if (isset($_POST['poster_id'], $_POST['remove_lang'])) {
  $pid = (int)$_POST['poster_id'];
  $lang = $_POST['remove_lang'];

  $stmt = $conn->prepare("DELETE FROM poster_languages WHERE poster_id = ? AND lang_code = ?");
  $stmt->bind_param("is", $pid, $lang);
  $stmt->execute();
  $stmt->close();

  // אפשרות: להוסיף הודעה על הצלחה
  $log_report[] = ['status' => 'removed', 'id' => $pid, 'lang' => $lang];
}

function safe($str) {
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$log_report = [];

if (isset($_POST['bulk_lang_apply'])) {
  $langs = $_POST['languages'] ?? [];
  $lines = explode("\n", $_POST['poster_ids']);

  foreach ($lines as $raw) {
    $id = trim($raw);
    if ($id === '') continue;
    if (preg_match('/tt\d+/', $id, $m)) $id = $m[0];

    if (is_numeric($id)) {
      $res = $conn->query("SELECT id FROM posters WHERE id = $id");
      $poster = $res->fetch_assoc();
    } else {
      $stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
      $stmt->bind_param("s", $id); $stmt->execute();
      $poster = $stmt->get_result()->fetch_assoc(); $stmt->close();
    }

    if ($poster) {
      $pid = (int)$poster['id'];
      foreach ($langs as $lang) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM poster_languages WHERE poster_id = ? AND lang_code = ?");
        $stmt->bind_param("is", $pid, $lang);
        $stmt->execute(); $stmt->bind_result($exists); $stmt->fetch(); $stmt->close();

        if ($exists == 0) {
          
          $stmt = $conn->prepare("INSERT INTO poster_languages (poster_id, lang_code) VALUES (?, ?)");
          $stmt->bind_param("is", $pid, $lang); $stmt->execute(); $stmt->close();
          $log_report[] = ['status' => 'added', 'id' => $id, 'lang' => $lang];
        } else {
          $log_report[] = ['status' => 'exists', 'id' => $id, 'lang' => $lang];
        }
      }
    } else {
      $log_report[] = ['status' => 'error', 'id' => $id, 'lang' => implode(', ', $langs)];
    }
  }
}

$posters_raw = $conn->query("SELECT id, title_en, title_he FROM posters")->fetch_all(MYSQLI_ASSOC);


$posters_raw = $conn->query("SELECT id, title_en, title_he FROM posters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);


$posters = [];
foreach ($posters_raw as $p) {
  $pid = (int)$p['id'];
  $res = $conn->prepare("SELECT lang_code FROM poster_languages WHERE poster_id = ?");
  $res->bind_param("i", $pid); $res->execute();
  $result = $res->get_result();
  $langs = [];
  while ($r = $result->fetch_assoc()) $langs[] = $r['lang_code'];
  $res->close();

  $posters[] = [
    'id'       => $pid,
    'title_en' => $p['title_en'],
    'title_he' => $p['title_he'],
    'langs'    => $langs
  ];
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🌐 שפות לפי פוסטר</title>
  <style>
    body { font-family: Arial; background:#f7f7f7; padding:40px; direction:rtl; }
    h1 { text-align:center; color:#007bff; margin-bottom:30px; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); overflow:hidden; }
    th, td { padding:12px; border-bottom:1px solid #eee; text-align:right; vertical-align:middle; }
    th { background:#f0f0f0; font-weight:bold; }
    td.title small { display:block; font-size:13px; color:#777; }
    .lang-button {
      display:inline-flex; align-items:center; gap:6px;
      background:#f5f5f5; padding:6px 10px; border-radius:6px;
      margin:3px; font-size:13px; text-decoration:none; color:#333;
    }
    .lang-button:hover { background:#e0e0e0; }
    .lang-button img { height:16px; }
    form.inline { display:inline; margin:0; padding:0; }
    button.remove-btn {
      background:none; border:none; color:#a00; font-size:14px;
      cursor:pointer; margin-right:1x;
    }
    button.remove-btn:hover { color:#d00; }
        textarea { width:100%; padding:10px; margin-top:10px; }
    button { padding:10px 20px; margin-top:10px; background:#007bff; color:#fff; border:none; border-radius:6px; cursor:pointer; }
h1, h2 { text-align:center; color:#007bff; }
    
.form1 { max-width:700px; margin:20px auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 4px rgba(0,0,0,0.1); }

button:hover { background:#0056b3; }
    ul.report { list-style:none; padding:0; margin:20px auto; max-width:600px; }
 </style>
</head>
<body>

<h1>🌐 שפות לפי פוסטר</h1>

<?php if (!empty($log_report)): ?>
  <h2>📋 דוח החלה</h2>
  <ul class="report">
    <?php foreach ($log_report as $entry): ?>
      <li>
        <?php if ($entry['status'] === 'added'): ?>
          <span style="color:green;">🟢</span>
          שפה <?= safe($entry['lang']) ?> נוספה לפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'exists'): ?>
          <span style="color:blue;">🔵</span>
          שפה <?= safe($entry['lang']) ?> כבר קיימת בפוסטר <?= safe($entry['id']) ?>
        <?php elseif ($entry['status'] === 'error'): ?>
          <span style="color:red;">🔴</span>
          לא נמצא פוסטר עבור <?= safe($entry['id']) ?>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" class="form1">
  <h2>🌐 החלת שפות מרוכזת על פוסטרים</h2>
  <?php include 'flags.php'; ?>
  <textarea name="poster_ids" rows="5" placeholder="tt1234567&#10;15&#10;https://www.imdb.com/title/tt0110912/"></textarea>
  <button type="submit" name="bulk_lang_apply">💾 החלת שפה</button>
</form>

<h1>🌐 רשימת פוסטרים והשפות שלהם</h1>

<table>
  <thead>
    <tr><th>מזהה</th><th>שם פוסטר</th><th>שפות</th></tr>
  </thead>
  <tbody>
    <?php foreach ($posters as $p): ?>
      <tr>
        <td><?= safe($p['id']) ?></td>
        <td class="title">
          <a href="poster.php?id=<?= $p['id'] ?>"><?= safe($p['title_en']) ?></a>
          <?php if (!empty($p['title_he'])): ?>
            <small><?= safe($p['title_he']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($p['langs'])): ?>
            <?php foreach ($p['langs'] as $code): ?>
              <?php
              foreach ($languages as $lang) {
                if ($lang['code'] === $code):
                  ?>
                  <form method="post" class="inline">
                    <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="remove_lang" value="<?= safe($code) ?>">
                    <a href="language.php?lang_code=<?= urlencode($code) ?>" class="lang-button" target="_blank">
                      <img src="<?= safe($lang['flag']) ?>" alt="<?= safe($lang['label']) ?>">
                      <span><?= safe($lang['label']) ?></span>
                    </a>
                    <button type="submit" class="remove-btn" title="מחק שפה זו">🗑️</button>
                  </form>
                  <?php
                  break;
                endif;
              }
              ?>
            <?php endforeach; ?>
          <?php else: ?>
            <span style="color:#999;">אין שפות</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>


</body>
</html>


<?php include 'footer.php'; ?>