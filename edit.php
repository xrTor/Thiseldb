<?php
require_once 'server.php';
include 'header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<p>❌ מזהה פוסטר לא תקין</p>";
    include 'footer.php';
    exit;
}

// שליפת כל הסוגים (type_id)
$types = [];
$type_result = $conn->query("SELECT id, icon, label_he FROM poster_types ORDER BY sort_order, id");
while ($t = $type_result->fetch_assoc()) $types[] = $t;

// שליפת פוסטר לעריכה
$stmt = $conn->prepare("SELECT * FROM posters WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo "<p>❌ פוסטר לא נמצא</p>";
    include 'footer.php'; exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'title_en', 'title_he', 'year', 'plot', 'plot_he', 'imdb_rating',
        'metacritic_score', 'metacritic_link', 'rt_score', 'rt_link',
        'imdb_id', 'tvdb_id', 'genre', 'actors', 'directors', 'writers',
        'producers', 'composers', 'cinematographers', 'languages', 'lang_code',
        'countries', 'runtime', 'has_subtitles', 'is_dubbed',
        'type_id', 'youtube_trailer', 'image_url'
    ];
    $update = [];
    $params = [];
    $typestr = '';

    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        if (in_array($f, ['year', 'runtime', 'type_id', 'has_subtitles', 'is_dubbed', 'tvdb_id'])) {
            $val = is_numeric($val) ? intval($val) : 0;
            $typestr .= 'i';
        } else {
            $typestr .= 's';
        }
        $update[] = "$f = ?";
        $params[] = $val;
    }

    $sql = "UPDATE posters SET " . implode(', ', $update) . " WHERE id = ?";
    $typestr .= 'i';
    $params[] = $id;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typestr, ...$params);
    $stmt->execute();
    $stmt->close();
    $message = '✅ הפוסטר עודכן בהצלחה!';

    // עדכון תגיות משתמש
    if (isset($_POST['user_tags'])) {
        $tags = preg_split('/[\n,]+/', $_POST['user_tags']);
        $tags = array_map('trim', $tags);
        $conn->query("DELETE FROM user_tags WHERE poster_id=$id");
        foreach ($tags as $tag) {
            if ($tag !== '') {
                $tagStmt = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
                $tagStmt->bind_param("is", $id, $tag); $tagStmt->execute(); $tagStmt->close();
            }
        }
    }
    $stmt = $conn->prepare("SELECT * FROM posters WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$tagsRes = $conn->query("SELECT genre FROM user_tags WHERE poster_id=$id");
$user_tags = [];
while ($t = $tagsRes->fetch_assoc()) $user_tags[] = $t['genre'];
$user_tags_str = implode("\n", $user_tags);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>עריכת פוסטר</title>
    <style>
        body { font-family: Varela Round, Arial, sans-serif; background: #f0f3f7; }
        .edit-form {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 32px 18px 28px 18px;
            border-radius: 15px;
            box-shadow: 0 2px 12px #0002;
            direction: rtl;
        }
        .edit-form h2 { text-align:center; margin-bottom:22px; }
        .ok { background:#e9ffe3; border:1px solid #b7e5b7; padding:9px 10px; margin:7px 0 14px 0; border-radius:7px; }
        table.edit-table { width: 100%; border-spacing:0 12px; }
        table.edit-table td { vertical-align:top; padding:0 5px 0 5px; }
        table.edit-table td:first-child { width:180px; font-weight:bold; text-align:right; color:#235; padding-top:11px; }
        table.edit-table input[type="text"], table.edit-table input[type="number"] {
            width: 98%; padding:8px; border:1px solid #bbb; border-radius:7px; font-size:15px; background:#f9fafd;
            font-family: inherit;
        }
        table.edit-table textarea {
            width: 98%; padding:8px; border:1px solid #bbb; border-radius:7px; font-size:15px; background:#f9fafd;
            font-family: inherit;
            min-height:44px; max-height:200px; resize: vertical;
        }
        .type-choices { display:flex; flex-wrap:wrap; gap:12px; margin-top:2px; }
        .type-choices label { display:inline-block; background: #e8f1fc; border:1px solid #b4d6fa; border-radius:12px; padding:7px 16px; cursor:pointer; font-size:16px; margin-bottom: 4px;}
        .type-choices input[type="radio"] { margin-left:5px; }
        .chip { display: inline-block; padding: 2px 10px 2px 10px; background: #e9eef6; color: #295687; border-radius: 14px; font-size: 14px; margin: 2px 2px 2px 0; font-family: inherit; }
        .note { color:#889; font-size:12px; margin-top:2px; }
        .row-buttons { gap: 10px; margin-top:26px; justify-content: flex-end; }
        .row-buttons button {
            padding: 13px 36px;
            border-radius: 9px;
            background: linear-gradient(92deg, #5eafff 50%, #1576cc 100%);
            color: #fff; border: none; font-size: 16px; font-weight: bold; cursor: pointer; margin-right: 10px;
            box-shadow: 0 1px 5px #1673cc22; transition: background 0.19s;
        }
        .row-buttons button:hover { background: linear-gradient(89deg, #499be6 40%, #186ebd 100%);}
        .row-buttons a { font-size: 15px; padding: 12px 14px; background: #eee; border-radius:7px; text-decoration:none;}
        @media (max-width:700px) {
            .edit-form { padding:6px; }
            table.edit-table td:first-child { width:100px;}
            table.edit-table input, table.edit-table textarea { font-size: 15px;}
        }
    </style>
    <script>
        function renderChips(fieldId) {
            const input = document.getElementById(fieldId);
            const chipBox = document.getElementById(fieldId + '_chips');
            if (!input || !chipBox) return;
            chipBox.innerHTML = '';
            const values = input.value.split(/,|\n/).map(s=>s.trim()).filter(Boolean);
            for (let v of values) {
                if (v) chipBox.innerHTML += `<span class="chip">${v}</span>`;
            }
        }
        window.addEventListener('DOMContentLoaded',()=>{
            ['genre','actors','directors','writers','producers','composers','cinematographers','languages','countries','user_tags'].forEach(f=>{
                renderChips(f);
                const el = document.getElementById(f);
                if(el) el.addEventListener('input', ()=>renderChips(f));
            });
            // ריחוף ובחירה אוטומטית לטריילר
            const ytInput = document.getElementById('youtube_trailer');
            if(ytInput){
                ytInput.addEventListener('mouseenter', function(){ this.select(); });
                ytInput.addEventListener('focus', function(){ this.select(); });
            }
        });
    </script>
</head>
<body>
<form class="edit-form" method="post">
    <h2>✏️ עריכת פוסטר</h2>
    <?php if ($message): ?><div class="ok"><?= $message ?></div><?php endif; ?>

         <div class="row-buttons">
        <button type="submit">💾 שמור</button>
        <a href="poster.php?id=<?= $id ?>">🔙 חזור</a>
    </div>
    
    <table class="edit-table">
        <tr>
            <td>סוג הפוסטר</td>
            <td>
                <div class="type-choices">
                    <?php foreach ($types as $t): ?>
                        <label>
                            <input type="radio" name="type_id" value="<?= $t['id'] ?>" <?= ($row['type_id']==$t['id'])?'checked':'' ?>>
                            <?= htmlspecialchars($t['icon']) ?> <?= htmlspecialchars($t['label_he']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </td>
        </tr>
        <tr><td>שם באנגלית</td><td><input type="text" name="title_en" value="<?= htmlspecialchars($row['title_en']) ?>"></td></tr>
        <tr><td>שם בעברית</td><td><input type="text" name="title_he" value="<?= htmlspecialchars($row['title_he']) ?>"></td></tr>
        <tr><td>שנה</td><td><input type="number" name="year" value="<?= htmlspecialchars($row['year']) ?>"></td></tr>
        <tr><td>אורך (דקות)</td><td><input type="number" name="runtime" value="<?= htmlspecialchars($row['runtime']) ?>"></td></tr>
        <tr><td>דירוג IMDb</td><td><input type="text" name="imdb_rating" value="<?= htmlspecialchars($row['imdb_rating']) ?>"></td></tr>
        <tr><td>Metacritic Score</td><td><input type="text" name="metacritic_score" value="<?= htmlspecialchars($row['metacritic_score']) ?>"></td></tr>
        <tr><td>Metacritic Link</td><td><input type="text" name="metacritic_link" value="<?= htmlspecialchars($row['metacritic_link'] ?? '') ?>"></td></tr>
        <tr><td>Rotten Tomatoes Score</td><td><input type="text" name="rt_score" value="<?= htmlspecialchars($row['rt_score']) ?>"></td></tr>
        <tr><td>Rotten Tomatoes Link</td><td><input type="text" name="rt_link" value="<?= htmlspecialchars($row['rt_link'] ?? '') ?>"></td></tr>
        <tr><td>IMDb ID</td><td><input type="text" name="imdb_id" value="<?= htmlspecialchars($row['imdb_id']) ?>"></td></tr>
        <tr><td>TVDB ID</td><td><input type="text" name="tvdb_id" value="<?= htmlspecialchars($row['tvdb_id']) ?>"></td></tr>
        <tr>
            <td style="vertical-align:top;">ז'אנרים<br><span class="note">שורה לכל ערך, או פסיקים</span></td>
            <td>
                <textarea name="genre" id="genre"><?= htmlspecialchars($row['genre']) ?></textarea>
                <div id="genre_chips"></div>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top;">שחקנים<br><span class="note">שורה לכל אחד, או פסיקים</span></td>
            <td>
                <textarea name="actors" id="actors"><?= htmlspecialchars($row['actors']) ?></textarea>
                <div id="actors_chips"></div>
            </td>
        </tr>
        <tr><td>במאים</td><td><textarea name="directors" id="directors"><?= htmlspecialchars($row['directors']) ?></textarea><div id="directors_chips"></div></td></tr>
        <tr><td>תסריטאים</td><td><textarea name="writers" id="writers"><?= htmlspecialchars($row['writers']) ?></textarea><div id="writers_chips"></div></td></tr>
        <tr><td>מפיקים</td><td><textarea name="producers" id="producers"><?= htmlspecialchars($row['producers']) ?></textarea><div id="producers_chips"></div></td></tr>
        <tr><td>מלחינים</td><td><textarea name="composers" id="composers"><?= htmlspecialchars($row['composers']) ?></textarea><div id="composers_chips"></div></td></tr>
        <tr><td>צלמים</td><td><textarea name="cinematographers" id="cinematographers"><?= htmlspecialchars($row['cinematographers']) ?></textarea><div id="cinematographers_chips"></div></td></tr>
        <tr><td>תקציר (עברית)</td><td><textarea name="plot_he"><?= htmlspecialchars($row['plot_he']) ?></textarea></td></tr>
        <tr><td>תקציר (אנגלית)</td><td><textarea name="plot"><?= htmlspecialchars($row['plot']) ?></textarea></td></tr>
        <tr>
            <td style="vertical-align:top;">שפות<br><span class="note">שורה לכל שפה או פסיקים</span></td>
            <td><textarea name="languages" id="languages"><?= htmlspecialchars($row['languages']) ?></textarea><div id="languages_chips"></div></td>
        </tr>
        <tr><td>קוד שפה (2 אותיות, פסיקים)</td><td><input type="text" name="lang_code" value="<?= htmlspecialchars($row['lang_code']) ?>"></td></tr>
        <tr>
            <td style="vertical-align:top;">מדינות</td>
            <td><textarea name="countries" id="countries"><?= htmlspecialchars($row['countries']) ?></textarea><div id="countries_chips"></div></td>
        </tr>
        <tr>
            <td style="vertical-align:top;">תגיות משתמש<br><span class="note">שורה לכל תגית</span></td>
            <td>
                <textarea name="user_tags" id="user_tags"><?= htmlspecialchars($user_tags_str) ?></textarea>
                <div id="user_tags_chips"></div>
                <span class="note">הפרד בשורה חדשה או בפסיק</span>
            </td>
        </tr>
        <tr><td>תמונה (קישור)</td><td><input type="text" name="image_url" value="<?= htmlspecialchars($row['image_url']) ?>"></td></tr>
        <tr>
            <td>Youtube Trailer</td>
            <td>
                <input type="text" name="youtube_trailer" id="youtube_trailer" value="<?= htmlspecialchars($row['youtube_trailer']) ?>">
                <span class="note">בריחוף בעכבר נבחר אוטומטית להעתקה/הדבקה נוחה</span>
            </td>
        </tr>
    </table>
    <div class="row-buttons">
        <button type="submit">💾 שמור</button>
        <a href="poster.php?id=<?= $id ?>">🔙 חזור</a>
    </div>
</form>
</body>
</html>
<?php include 'footer.php'; ?>
