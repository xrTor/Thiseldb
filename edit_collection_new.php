<?php
// edit_collection_new.php
require_once 'server.php';
include 'header.php';

// ---------- Helpers ----------
function trim_nonempty($s) { $s = trim((string)$s); return $s === '' ? null : $s; }
function parse_csv_ids($s) {
    if (!isset($s)) return [];
    $parts = preg_split('/[,\s]+/', (string)$s, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_map('trim', $parts)));
}

// ---------- Load collection ----------
$collection_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($collection_id <= 0) {
    echo "<div style='padding:20px;color:#b00'>❌ מזהה אוסף לא תקין.</div>";
    include 'footer.php';
    exit;
}

// ---------- Handle POST (save) ----------
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    // 1) עדכון פרטי האוסף
    $name            = trim_nonempty($_POST['name'] ?? '');
    $description     = trim((string)($_POST['description'] ?? ''));
    $poster_image_url= trim_nonempty($_POST['poster_image_url'] ?? '');
    $is_pinned       = isset($_POST['is_pinned']) ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE collections
        SET name = ?, description = ?, poster_image_url = ?, is_pinned = ?
        WHERE id = ?
    ");
    $stmt->bind_param('sssii', $name, $description, $poster_image_url, $is_pinned, $collection_id);
    $stmt->execute();
    $stmt->close();

    // 2) הסרת פוסטרים מהאוסף
    if (!empty($_POST['delete_posters']) && is_array($_POST['delete_posters'])) {
        $toDelete = array_map('intval', $_POST['delete_posters']);
        if ($toDelete) {
            // composite PK (poster_id, collection_id) => מחיקה פר-פוסטר
            $stmt = $conn->prepare("DELETE FROM poster_collections WHERE collection_id = ? AND poster_id = ?");
            foreach ($toDelete as $pid) {
                $stmt->bind_param('ii', $collection_id, $pid);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    // 3) הוספת פוסטרים לפי poster_id
    $add_by_ids = parse_csv_ids($_POST['add_by_ids'] ?? '');
    if ($add_by_ids) {
        $stmt = $conn->prepare("INSERT IGNORE INTO poster_collections (poster_id, collection_id) VALUES (?, ?)");
        foreach ($add_by_ids as $pidRaw) {
            $pid = (int)$pidRaw;
            if ($pid > 0) {
                $stmt->bind_param('ii', $pid, $collection_id);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // 4) הוספת פוסטרים לפי imdb_id
    $add_by_imdb = parse_csv_ids($_POST['add_by_imdb'] ?? '');
    if ($add_by_imdb) {
        // שליפת ids לפי imdb_id
        $in  = implode(',', array_fill(0, count($add_by_imdb), '?'));
        $typ = str_repeat('s', count($add_by_imdb));
        $sql = "SELECT id FROM posters WHERE imdb_id IN ($in)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($typ, ...$add_by_imdb);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
        $stmt->close();

        if ($ids) {
            $stmt = $conn->prepare("INSERT IGNORE INTO poster_collections (poster_id, collection_id) VALUES (?, ?)");
            foreach ($ids as $pid) {
                $stmt->bind_param('ii', $pid, $collection_id);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $flash = "✅ השינויים נשמרו בהצלחה.";
}

// ---------- Fetch updated data ----------
$stmt = $conn->prepare("SELECT id, name, description, poster_image_url, is_pinned FROM collections WHERE id = ?");
$stmt->bind_param('i', $collection_id);
$stmt->execute();
$collection = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$collection) {
    echo "<div style='padding:20px;color:#b00'>❌ האוסף לא נמצא.</div>";
    include 'footer.php';
    exit;
}

// פוסטרים באוסף
$sql = "
    SELECT p.id, p.title_en, p.title_he, p.year, p.imdb_rating, p.image_url
    FROM poster_collections pc
    JOIN posters p ON p.id = pc.poster_id
    WHERE pc.collection_id = ?
    ORDER BY p.title_en IS NULL, p.title_en ASC, p.id ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $collection_id);
$stmt->execute();
$posters_res = $stmt->get_result();
$posters = $posters_res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<title>עריכת אוסף — <?= htmlspecialchars($collection['name'] ?? "ID $collection_id") ?></title>
<style>
    body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { margin: 0 0 12px; }
    .breadcrumb { color:#666; margin-bottom:16px; }
    .flash { background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9; padding:10px 12px; border-radius:8px; margin-bottom:16px; }

    .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,.04); margin-bottom:18px; }
    .card-header { padding:14px 16px; border-bottom:1px solid #e5e7eb; font-weight:700; }
    .card-body { padding:16px; }
    .row { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .row.full { grid-template-columns: 1fr; }
    label { font-size:13px; color:#333; display:block; margin-bottom:6px; }
    input[type="text"], input[type="url"], textarea {
        width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:8px; font-size:14px;
    }
    textarea { min-height:100px; resize:vertical; }
    .switch { display:flex; align-items:center; gap:8px; }
    .savebar { position:sticky; bottom:0; background:#fff; border:1px solid #e5e7eb; padding:10px 12px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 -2px 8px rgba(0,0,0,.04); }
    .btn { border:1px solid #cbd5e1; background:#0ea5e9; color:#fff; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:700; }
    .btn.secondary { background:#fff; color:#0e7490; }
    .btn:disabled { opacity:.6; cursor:not-allowed; }

    /* posters table */
    .poster-list { list-style:none; margin:0; padding:0; }
    .poster-item { display:grid; grid-template-columns: 80px 1fr 180px; gap:12px; align-items:center; border-bottom:1px solid #f1f5f9; padding:10px 0; }
    .poster-thumb { width:80px; height:120px; border-radius:6px; object-fit:cover; background:#eee; }
    .poster-meta b { display:block; font-size:15px; margin-bottom:2px; }
    .poster-meta small { color:#64748b; }
    .poster-actions { display:flex; justify-content:flex-end; gap:10px; align-items:center; }
    .poster-actions label { color:#b00020; }
    .muted { color:#64748b; }

    .add-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .help { font-size:12px; color:#64748b; margin-top:4px; }
</style>
</head>
<body>
<div class="container">
    <div class="breadcrumb">
        <a href="collections-new.php" style="color:#0ea5e9; text-decoration:none;">← חזרה לאוספים</a>
    </div>

    <h1>עריכת אוסף: <?= htmlspecialchars($collection['name']) ?></h1>

    <?php if ($flash): ?>
        <div class="flash"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <form method="post">

        <!-- פרטי האוסף -->
        <div class="card">
            <div class="card-header">פרטי האוסף</div>
            <div class="card-body">
                <div class="row">
                    <div>
                        <label>שם האוסף</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($collection['name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label>תמונת שער (poster_image_url)</label>
                        <input type="url" name="poster_image_url" value="<?= htmlspecialchars($collection['poster_image_url'] ?? '') ?>" placeholder="https://...">
                        <div class="help">אם תשים URL, הוא יוצג בעמוד האוספים כ־Cover.</div>
                    </div>
                    <div class="row full">
                        <div>
                            <label>תיאור</label>
                            <textarea name="description" placeholder="טקסט חופשי"><?= htmlspecialchars($collection['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="row full">
                        <div class="switch">
                            <input type="checkbox" id="is_pinned" name="is_pinned" <?= !empty($collection['is_pinned']) ? 'checked' : '' ?>>
                            <label for="is_pinned" style="margin:0;">הצמד אוסף (Pinned)</label>
                            <span class="muted">— יוצג כאוסף מודגש.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ניהול פוסטרים באוסף -->
        <div class="card">
            <div class="card-header">פוסטרים באוסף</div>
            <div class="card-body">
                <?php if (!$posters): ?>
                    <div class="muted">אין עדיין פוסטרים באוסף.</div>
                <?php else: ?>
                    <ul class="poster-list">
                        <?php foreach ($posters as $p): ?>
                        <li class="poster-item">
                            <img class="poster-thumb" src="<?= htmlspecialchars($p['image_url'] ?: 'images/no-poster.png') ?>" alt="">
                            <div class="poster-meta">
                                <b><?= htmlspecialchars($p['title_he'] ?: $p['title_en'] ?: 'ללא כותרת') ?></b>
                                <small>שנה: <?= htmlspecialchars($p['year'] ?? '-') ?> · IMDb: <?= htmlspecialchars($p['imdb_rating'] ?? '-') ?></small>
                            </div>
                            <div class="poster-actions">
                                <label>
                                    <input type="checkbox" name="delete_posters[]" value="<?= (int)$p['id'] ?>">
                                    הסר מהאוסף
                                </label>
                                <a class="btn secondary" href="poster.php?id=<?= (int)$p['id'] ?>" target="_blank">צפה</a>
                                <a class="btn secondary" href="edit.php?id=<?= (int)$p['id'] ?>">ערוך פוסטר</a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- הוספת פוסטרים -->
        <div class="card">
            <div class="card-header">הוספת פוסטרים לאוסף</div>
            <div class="card-body">
                <div class="add-grid">
                    <div>
                        <label>הוספה לפי poster_id</label>
                        <input type="text" name="add_by_ids" placeholder="לדוגמה: 12, 45, 128">
                        <div class="help">רשימת מזהי פוסטרים מופרדת בפסיקים.</div>
                    </div>
                    <div>
                        <label>הוספה לפי imdb_id</label>
                        <input type="text" name="add_by_imdb" placeholder="לדוגמה: tt0111161, tt0068646">
                        <div class="help">רשימת מזהי IMDb מופרדת בפסיקים.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- שמירה -->
        <div class="savebar">
            <div class="muted">כל השינויים יישמרו לאחר לחיצה על הכפתור.</div>
            <div>
                <button type="submit" name="save_changes" class="btn">💾 שמור שינויים</button>
                <a href="collections-new.php" class="btn secondary">ביטול</a>
            </div>
        </div>

    </form>
</div>
</body>
</html>
<?php include 'footer.php'; ?>
