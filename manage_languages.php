<?php 
include 'header.php'; 
require_once 'server.php';

// --- actions (no change) ---
if (isset($_POST['poster_id'], $_POST['remove_lang'])) {
  $pid = (int)$_POST['poster_id'];
  $lang = $_POST['remove_lang'];
  $stmt = $conn->prepare("DELETE FROM poster_languages WHERE poster_id = ? AND lang_code = ?");
  $stmt->bind_param("is", $pid, $lang);
  $stmt->execute();
  $stmt->close();
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
    $line = trim($raw);
    if ($line === '') {
      continue;
    }

    // ✅ לוקחים אך ורק IMDb ID (tt...) מתוך השורה
    if (!preg_match('/tt\d+/', $line, $m)) {
      // אין בכלל tt בשורה – מדווחים כשגיאה ולא מחפשים לפי ID פנימי
      $log_report[] = [
        'status' => 'error',
        'id'     => $line,
        'lang'   => implode(', ', $langs)
      ];
      continue;
    }

    $imdb_id = $m[0]; // תמיד ttXXXXXXX

    // חיפוש אך ורק לפי imdb_id
    $stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
    $stmt->bind_param("s", $imdb_id);
    $stmt->execute();
    $poster = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($poster) {
      $pid = (int)$poster['id'];

      foreach ($langs as $lang) {
        // בדיקה אם השפה כבר קיימת לפוסטר
        $stmt = $conn->prepare("SELECT COUNT(*) FROM poster_languages WHERE poster_id = ? AND lang_code = ?");
        $stmt->bind_param("is", $pid, $lang);
        $stmt->execute();
        $stmt->bind_result($exists);
        $stmt->fetch();
        $stmt->close();

        if ((int)$exists === 0) {
          // הוספת שפה
          $stmt = $conn->prepare("INSERT INTO poster_languages (poster_id, lang_code) VALUES (?, ?)");
          $stmt->bind_param("is", $pid, $lang);
          $stmt->execute();
          $stmt->close();

          $log_report[] = [
            'status' => 'added',
            'id'     => $imdb_id,
            'lang'   => $lang
          ];
        } else {
          $log_report[] = [
            'status' => 'exists',
            'id'     => $imdb_id,
            'lang'   => $lang
          ];
        }
      }
    } else {
      // לא נמצא פוסטר עם imdb_id הזה
      $log_report[] = [
        'status' => 'error',
        'id'     => $imdb_id,
        'lang'   => implode(', ', $langs)
      ];
    }
  }
}

// --- pagination setup ---
$results_per_page = 250;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $results_per_page;

$total_results = $conn->query("SELECT COUNT(*) FROM posters")->fetch_row()[0];
$total_pages = ceil($total_results / $results_per_page);

// --- fetch posters for current page ---
$posters_raw = $conn->query("SELECT id, title_en, title_he FROM posters ORDER BY id DESC LIMIT $offset, $results_per_page")->fetch_all(MYSQLI_ASSOC);

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

// --- generate pagination HTML ---
$pagination_html = '';
if ($total_pages > 1) {
    ob_start();
    include 'pagination.php';
    $pagination_html = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>🌐 שפות לפי פוסטר</title>
  <style>
    body { font-family: Arial; background:#f7f7f7; padding:40px; direction:rtl; }
    h1, h2 { text-align:center; color:#007bff; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.1); overflow:hidden; margin-top: 15px; }
    th, td { padding:12px; border-bottom:1px solid #eee; text-align:right; vertical-align:middle; }
    th { background:#f0f0f0; font-weight:bold; }
    td.title small { display:block; font-size:13px; color:#777; }
    .lang-button { display:inline-flex; align-items:center; gap:6px; background:#f5f5f5; padding:6px 10px; border-radius:6px; margin:3px; font-size:13px; text-decoration:none; color:#333; }
    .lang-button:hover { background:#e0e0e0; }
    .lang-button img { height:16px; }
    form.inline { display:inline; margin:0; padding:0; }
    button.remove-btn { background:none; border:none; color:#a00; font-size:14px; cursor:pointer; margin-right:1x; }
    button.remove-btn:hover { color:#d00; }
    textarea { width:100%; padding:10px; margin-top:10px; box-sizing: border-box; }
    button { padding:10px 20px; margin-top:10px; background:#007bff; color:#fff; border:none; border-radius:6px; cursor:pointer; }
    .form1 { max-width:700px; margin:20px auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 4px rgba(0,0,0,0.1); }
    button:hover { background:#0056b3; }
    ul.report { list-style:none; padding:0; margin:20px auto; max-width:600px; }
    .pagination { text-align: center; margin: 15px 0; }
    .pagination a, .pagination span { display: inline-block; color: #007bff; text-decoration: none; padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; border-radius: 4px; }
    .pagination a.active { background-color: #007bff; color: white; border-color: #007bff; cursor: default; }
    .pagination a:hover:not(.active) { background-color: #f1f1f1; }
    .pagination span { color: #999; border-color: transparent; }

    /* ✅ כפתורי עזר מתחת לטקסטאריה */
    .ta-tools { display:flex; gap:10px; justify-content:flex-start; flex-wrap:wrap; margin-top:10px; }
    .btn-secondary { background:#6c757d; }
    .btn-secondary:hover { background:#565e64; }
    .btn-outline { background:#f5f5f5; color:#333; border:1px solid #ddd; }
    .btn-outline:hover { background:#e9e9e9; }
    .mini-note { font-size:12px; color:#777; margin-top:6px; }
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

  <textarea id="poster_ids" name="poster_ids" rows="12" placeholder="tt1234567&#10;15&#10;https://www.imdb.com/title/tt0110912/"></textarea>

  <!-- ✅ כפתור הדבק -->
  <div class="ta-tools">
    <button type="button" id="btnPaste" class="btn-secondary">📋 הדבק</button>
    <button type="button" id="btnClear" class="btn-outline">🧹 נקה</button>
      <button type="submit" name="bulk_lang_apply">💾 החלת שפה</button>

  </div>
  <div class="mini-note">הדבקה תחלץ רק tt... ותמנע כפילויות (לא ניתן להדביק אותו IMDb ID פעמיים ומעלה).</div>

</form>

<h1>🌐 רשימת פוסטרים והשפות שלהם</h1>

<?= $pagination_html ?>

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

<?= $pagination_html ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const ta = document.getElementById('poster_ids');
  const btnPaste = document.getElementById('btnPaste');
  const btnClear = document.getElementById('btnClear');
  if (!ta) return;

  function applySmartPaste(pastedText) {
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;

    let before = ta.value.slice(0, start);
    let after  = ta.value.slice(end);

    // אם אין ירידת שורה לפני – נוסיף
    if (before && !before.endsWith('\n')) {
      before += '\n';
    }

    // נשלוף רק מזהי IMDb (tt...) מכל מה שהודבק
    const ttMatches = String(pastedText || '').match(/tt\d{7,10}/gi) || [];

    // סט של IDs שכבר קיימים בטקסט (בלי החלק שנבחר והולך להתחלף)
    const existingText = (before + after);
    const existingIds = new Set((existingText.match(/tt\d{7,10}/gi) || []).map(s => s.toLowerCase()));

    // מסננים כפילויות:
    // 1) כפילות בתוך ההדבקה עצמה
    // 2) כפילות מול מה שכבר יש בטקסט
    const uniqueToPaste = [];
    const seenInPaste = new Set();
    for (const raw of ttMatches) {
      const id = String(raw).toLowerCase();
      if (seenInPaste.has(id)) continue;
      seenInPaste.add(id);
      if (existingIds.has(id)) continue;
      uniqueToPaste.push(id);
    }

    if (uniqueToPaste.length === 0) {
      const caretPos = before.length;
      ta.selectionStart = ta.selectionEnd = caretPos;
      return;
    }

    const normalized = uniqueToPaste.join('\n');
    ta.value = before + normalized + after;

    const caretPos = (before + normalized).length;
    ta.selectionStart = ta.selectionEnd = caretPos;
  }

  // הדבקה רגילה (Ctrl+V) – עם המנגנון
  ta.addEventListener('paste', function (e) {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text');
    applySmartPaste(pasted);
  });

  // ✅ כפתור "הדבק" – קורא מהקליפבורד ומכניס לטקסטאריה באותה לוגיקה
  if (btnPaste) {
    btnPaste.addEventListener('click', async function () {
      try {
        if (!navigator.clipboard || !navigator.clipboard.readText) {
          alert('הדבקה בלחיצה דורשת הרשאה/תמיכה בדפדפן. נסה Ctrl+V בתוך התיבה.');
          return;
        }
        const text = await navigator.clipboard.readText();
        applySmartPaste(text);
        ta.focus();
      } catch (err) {
        // אם נחסם ע"י הרשאות / HTTP לא מאובטח וכו'
        alert('לא הצלחתי לקרוא מהקליפבורד (הרשאות/אבטחה). נסה Ctrl+V בתוך התיבה.');
      }
    });
  }

  if (btnClear) {
    btnClear.addEventListener('click', function () {
      ta.value = '';
      ta.focus();
    });
  }
});
</script>

</body>
</html>
<?php include 'footer.php'; ?>
