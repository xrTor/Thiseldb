<?php
require_once 'server.php';
include 'header.php';

// שליפת סוגי פוסטרים
$type_result = $conn->query("SELECT id, icon, label_he FROM poster_types ORDER BY sort_order, id");
$types = [];
while ($row = $type_result->fetch_assoc()) $types[] = $row;

// ברירת מחדל: סרט (id=3)
$data = [
    'type_id' => 3, 'title_en' => '', 'title_he' => '', 'year' => '', 'plot' => '', 'plot_he' => '',
    'imdb_rating' => '', 'metacritic_score' => '', 'metacritic_link' => '',
    'rt_score' => '', 'rt_link' => '', 'imdb_id' => '', 'imdb_link' => '',
    'tvdb_id' => '', 'genre' => '', 'actors' => '', 'directors' => '', 'writers' => '',
    'producers' => '', 'composers' => '', 'cinematographers' => '', 'languages' => '',
    'countries' => '', 'runtime' => '', 'has_subtitles' => 0, 'is_dubbed' => 0,
    'youtube_trailer' => '', 'image_url' => '', 'seasons_count' => '', 'episodes_count' => '',
    'user_tags' => ''
];
$message = '';
$selected_flags = [];

function extractImdbId($input) {
    if (preg_match('/tt\d{7,8}/', $input, $m)) return $m[0];
    return trim($input);
}

if (isset($_POST['fetch_omdb'])) {
    // AJAX מילוי OMDb
    $imdb_link_input = trim($_POST['imdb_link'] ?? '');
    $imdb_id = extractImdbId($imdb_link_input);

    $api = "https://www.omdbapi.com/?apikey=$omdb_key&i=" . urlencode($imdb_id) . "&plot=full&r=json";
    $json = @file_get_contents($api);
    if (!$json) {
        $message = "<span style='color:#a00'>שגיאה בשליפה מ-OMDb! נסה שוב.</span>";
    } else {
        $j = json_decode($json, true);
        if (!empty($j['Error'])) {
            $message = "<span style='color:#a00'>OMDb: ".$j['Error']."</span>";
        } elseif (!empty($j['Title'])) {
            $data['imdb_id'] = $imdb_id;
            $data['imdb_link'] = $imdb_link_input;
            $data['title_en'] = $j['Title'] ?? '';
            $data['year'] = $j['Year'] ?? '';
            $data['imdb_rating'] = $j['imdbRating'] ?? '';
            $data['genre'] = $j['Genre'] ?? '';
            $data['actors'] = $j['Actors'] ?? '';
            $data['directors'] = $j['Director'] ?? '';
            $data['writers'] = $j['Writer'] ?? '';
            $data['plot'] = $j['Plot'] ?? '';
            $data['runtime'] = preg_replace('/\D/', '', $j['Runtime'] ?? '');
            $data['image_url'] = $j['Poster'] ?? '';
            $data['languages'] = $j['Language'] ?? '';
            $data['countries'] = $j['Country'] ?? '';
        }
    }
    // שמור קלט קיים
    foreach ($data as $k => $v) {
        if (isset($_POST[$k]) && $v === '') $data[$k] = trim($_POST[$k]);
    }
    $selected_flags = $_POST['flags'] ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['fetch_omdb'])) {
    $fields = [
        'type_id', 'title_en', 'title_he', 'year', 'plot', 'plot_he', 'imdb_rating',
        'metacritic_score', 'metacritic_link', 'rt_score', 'rt_link',
        'imdb_id', 'imdb_link', 'tvdb_id', 'genre', 'actors', 'directors', 'writers', 'producers',
        'composers', 'cinematographers', 'languages', 'countries', 'runtime',
        'has_subtitles', 'is_dubbed', 'youtube_trailer', 'image_url',
        'seasons_count', 'episodes_count'
    ];
    foreach ($fields as $f) {
        if (in_array($f, ['has_subtitles', 'is_dubbed'])) {
            $data[$f] = !empty($_POST[$f]) ? 1 : 0;
        } elseif (in_array($f, ['year','runtime','type_id','seasons_count','episodes_count','tvdb_id'])) {
            $data[$f] = isset($_POST[$f]) && $_POST[$f] !== '' ? intval(is_array($_POST[$f]) ? $_POST[$f][0] : $_POST[$f]) : 0;
        } else {
           if ($f === 'languages') {
    // אם זה מערך (הגיע מהדגלים), אל תכניס כלום
    if (isset($_POST[$f]) && !is_array($_POST[$f])) {
        $data[$f] = trim($_POST[$f]);
    } else {
        $data[$f] = ''; // ריק כי דגלים לא אמורים להיכנס
    }
} else {
    $val = $_POST[$f] ?? '';
    if (is_array($val)) $val = implode(',', $val);
    $data[$f] = trim($val);
}

        }
    }
    $user_tags = $_POST['user_tags'] ?? '';
    if (is_array($user_tags)) $user_tags = implode(',', $user_tags);

    // בדיקת ייחודיות imdb_id
    if ($data['imdb_id']) {
        $exists = $conn->prepare("SELECT id FROM posters WHERE imdb_id=?");
        $exists->bind_param("s", $data['imdb_id']);
        $exists->execute();
        $exists->store_result();
        if ($exists->num_rows > 0) {
            $message = "❌ פוסטר עם מזהה IMDb זה כבר קיים!";
            $exists->close();
        } else {
            $exists->close();
        }
    }
    if (!$message) {
        $insert_fields = $fields;
        $insert_vals = [];
        $insert_types = '';
        foreach ($insert_fields as $f) {
            $insert_vals[] = $data[$f];
            $insert_types .= in_array($f, ['year','runtime','type_id','has_subtitles','is_dubbed','tvdb_id','seasons_count','episodes_count']) ? 'i' : 's';
        }
        $sql = "INSERT INTO posters (" . implode(",", $insert_fields) . ") VALUES (" . implode(",", array_fill(0, count($insert_fields), "?")) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($insert_types, ...$insert_vals);
        $stmt->execute();
        $new_poster_id = $stmt->insert_id;
        $stmt->close();

        // user_tags
        $tags = preg_split('/[,\n]+/', $_POST['user_tags'] ?? '');
        $tags = array_map('trim', $tags);
        foreach ($tags as $tag) {
            if ($tag) {
                $tagStmt = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
                $tagStmt->bind_param("is", $new_poster_id, $tag);
                $tagStmt->execute();
                $tagStmt->close();
            }
        }
        // flags -> poster_languages (רק למסד, לא לשדה שפה)
   // flags -> poster_languages (רק למסד, לא לשדה שפה)
if (!empty($_POST['languages']) && is_array($_POST['languages'])) {
    foreach ($_POST['languages'] as $lang) {
        $lang = trim($lang);
        if ($lang) {
            $langStmt = $conn->prepare("INSERT IGNORE INTO poster_languages (poster_id, lang_code) VALUES (?, ?)");
            $langStmt->bind_param("is", $new_poster_id, $lang);
            $langStmt->execute();
            $langStmt->close();
        }
    }
}

        $message = "✅ הפוסטר נוסף בהצלחה!";
        foreach ($data as $k => $v) $data[$k] = '';
        $selected_flags = [];
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>הוספת פוסטר חדש</title>
  <style>
    body { font-family: Arial; background: #f2f4f6; direction:rtl; }
    .form-wrap { max-width: 670px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 1px 8px #0002; padding: 20px 20px 15px 20px;}
    h2 { text-align:center; margin-bottom: 10px;}
    table { width: 100%; border-spacing: 0 7px; }
    td { vertical-align: top; padding: 0 4px 0 0; }
    label { font-weight: bold; font-size: 14px;}
    input[type="text"], input[type="number"], textarea { width: 96%; padding: 5px; font-size: 13px; border: 1px solid #bbb; border-radius: 7px; background: #f8fafb; resize: vertical;}
    textarea { min-height:32px; }
    .row-btns { text-align: left; padding-top: 10px;}
    .message { text-align: center; font-weight: bold; margin: 10px 0 5px 0; color: #13684b;}
    .type-choices { display:flex; flex-wrap:wrap; gap:8px; margin: 0 0 13px 0;}
    .type-choices label { background: #e8f1fc; border:1px solid #b4d6fa; border-radius:10px; padding:5px 11px; cursor:pointer; font-size:14px;}
    .type-choices input[type="radio"] { margin-left:5px; }
    .omdb-btn { background: linear-gradient(90deg,#53c1f8 60%,#2274bb 100%); color: #fff; border:none; border-radius:6px; font-size:13px; font-weight:bold; padding:5px 14px; cursor:pointer; margin-right:7px;}
    .omdb-btn:hover { background:linear-gradient(90deg,#34aad8 40%,#1970bb 100%);}
    .lang-table { margin-top:8px;}
    .note { font-size:12px; color:#888; margin-bottom:3px;}
    .ok { background: #e9ffe3; border: 1px solid #b7e5b7; padding: 7px 10px; margin: 7px 0 0 0; border-radius: 7px; color:#25674b;}
  </style>
  <script>
    function fetchOmdbAJAX() {
      var imdb_link = document.getElementById('imdb_link').value.trim();
      if (!imdb_link.match(/tt\d{7,8}/)) {
        alert("יש להזין לינק או מזהה IMDb חוקי (ttXXXXXXX)");
        return;
      }
      var form = document.getElementById('addForm');
      var omdbBtn = document.getElementById('omdb-btn');
      omdbBtn.disabled = true; omdbBtn.innerText = 'טוען...';
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      var formData = new FormData(form);
      formData.append('fetch_omdb', '1');
      xhr.onload = function() {
        var temp = document.createElement('div');
        temp.innerHTML = xhr.responseText;
        var innerForm = temp.querySelector('.form-wrap');
        if (innerForm) document.querySelector('.form-wrap').innerHTML = innerForm.innerHTML;
      };
      xhr.onerror = function() { alert('שגיאת רשת!'); }
      xhr.send(formData);
    }
    window.onload = function(){
      document.getElementById('imdb_link').addEventListener('blur',function(){
        if (this.value.match(/tt\d{7,8}/)) fetchOmdbAJAX();
      });
    }
  </script>
</head>
<body>
  <div class="form-wrap">
    <h2>➕ הוספת פוסטר חדש</h2>
    <?php if ($message): ?><div class="ok"><?= $message ?></div><?php endif; ?>
    <!-- סוג פוסטר בראש -->
    <div class="type-choices">
      <?php foreach ($types as $t): ?>
        <label>
          <input type="radio" name="type_id" value="<?= $t['id'] ?>" <?= ($data['type_id']==$t['id'] || (!isset($data['type_id']) && $t['id']==3)) ? 'checked' : '' ?>>
          <?= htmlspecialchars($t['icon']) ?> <?= htmlspecialchars($t['label_he']) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <form method="post" id="addForm" autocomplete="off">
      <table>
        <tr>
          <td><label>לינק IMDb (או מזהה)</label></td>
          <td>
            <input type="text" name="imdb_link" id="imdb_link" value="<?= htmlspecialchars($data['imdb_link']) ?>" style="direction:ltr; width:80%;" autocomplete="off">
            <button type="button" class="omdb-btn" id="omdb-btn" onclick="fetchOmdbAJAX()">🔄 השלמה אוטומטית OMDb</button>
          </td>
        </tr>
        <tr>
          <td><label>מזהה IMDb (נמשך אוטומטית)</label></td>
          <td><input type="text" name="imdb_id" id="imdb_id" value="<?= htmlspecialchars($data['imdb_id']) ?>" style="direction:ltr;" autocomplete="off"></td>
        </tr>
        <tr>
          <td><label>שם באנגלית</label></td>
          <td><input type="text" name="title_en" value="<?= htmlspecialchars($data['title_en']) ?>"></td>
        </tr>
        <tr>
          <td><label>שם בעברית</label></td>
          <td><input type="text" name="title_he" value="<?= htmlspecialchars($data['title_he']) ?>"></td>
        </tr>
        <tr>
          <td><label>שנה</label></td>
          <td><input type="number" name="year" value="<?= htmlspecialchars($data['year']) ?>"></td>
        </tr>
        <tr>
          <td><label>אורך (דקות)</label></td>
          <td><input type="number" name="runtime" value="<?= htmlspecialchars($data['runtime']) ?>"></td>
        </tr>
        <tr>
          <td><label>דירוג IMDb</label></td>
          <td><input type="text" name="imdb_rating" value="<?= htmlspecialchars($data['imdb_rating']) ?>"></td>
        </tr>
        <tr>
          <td><label>Metacritic Score</label></td>
          <td><input type="text" name="metacritic_score" value="<?= htmlspecialchars($data['metacritic_score']) ?>"></td>
        </tr>
        <tr>
          <td><label>Metacritic Link</label></td>
          <td><input type="text" name="metacritic_link" value="<?= htmlspecialchars($data['metacritic_link']) ?>"></td>
        </tr>
        <tr>
          <td><label>Rotten Tomatoes Score</label></td>
          <td><input type="text" name="rt_score" value="<?= htmlspecialchars($data['rt_score']) ?>"></td>
        </tr>
        <tr>
          <td><label>Rotten Tomatoes Link</label></td>
          <td><input type="text" name="rt_link" value="<?= htmlspecialchars($data['rt_link']) ?>"></td>
        </tr>
        <tr>
          <td><label>TVDB ID</label></td>
          <td><input type="text" name="tvdb_id" value="<?= htmlspecialchars($data['tvdb_id']) ?>"></td>
        </tr>
        <tr>
          <td><label>מס' עונות</label></td>
          <td><input type="number" name="seasons_count" value="<?= htmlspecialchars($data['seasons_count']) ?>"></td>
        </tr>
        <tr>
          <td><label>מס' פרקים</label></td>
          <td><input type="number" name="episodes_count" value="<?= htmlspecialchars($data['episodes_count']) ?>"></td>
        </tr>
        <tr>
          <td><label>ז'אנרים (פסיק בין ערכים)</label></td>
          <td><input type="text" name="genre" value="<?= htmlspecialchars($data['genre']) ?>"></td>
        </tr>
        <tr>
          <td><label>שחקנים (פסיק בין ערכים)</label></td>
          <td><input type="text" name="actors" value="<?= htmlspecialchars($data['actors']) ?>"></td>
        </tr>
        <tr>
          <td><label>במאים</label></td>
          <td><input type="text" name="directors" value="<?= htmlspecialchars($data['directors']) ?>"></td>
        </tr>
        <tr>
          <td><label>תסריטאים</label></td>
          <td><input type="text" name="writers" value="<?= htmlspecialchars($data['writers']) ?>"></td>
        </tr>
        <tr>
          <td><label>מפיקים</label></td>
          <td><input type="text" name="producers" value="<?= htmlspecialchars($data['producers']) ?>"></td>
        </tr>
        <tr>
          <td><label>מלחינים</label></td>
          <td><input type="text" name="composers" value="<?= htmlspecialchars($data['composers']) ?>"></td>
        </tr>
        <tr>
          <td><label>צלמים</label></td>
          <td><input type="text" name="cinematographers" value="<?= htmlspecialchars($data['cinematographers']) ?>"></td>
        </tr>
        <tr>
          <td><label>שפות (פסיק בין ערכים)</label></td>
          <td><input type="text" name="languages" value="<?= htmlspecialchars($data['languages']) ?>"></td>
        </tr>
        <tr>
          <td><label>מדינות</label></td>
          <td><input type="text" name="countries" value="<?= htmlspecialchars($data['countries']) ?>"></td>
        </tr>
        <tr>
          <td><label>תגיות משתמש (user tags, פסיק בין ערכים)</label></td>
          <td><input type="text" name="user_tags" value="<?= htmlspecialchars($data['user_tags']) ?>"></td>
        </tr>
        <tr>
          <td><label>תמונה (קישור)</label></td>
          <td><input type="text" name="image_url" value="<?= htmlspecialchars($data['image_url']) ?>"></td>
        </tr>
        <tr>
          <td><label>Youtube Trailer</label></td>
          <td><input type="text" name="youtube_trailer" value="<?= htmlspecialchars($data['youtube_trailer']) ?>"></td>
        </tr>
      </table>
      <div class="lang-table">
        <?php include 'flags.php'; ?>
      </div>
      <div class="row-btns">
        <button type="submit">💾 שמור</button>
      </div>
    </form>
  </div>
</body>
</html>
<?php include 'footer.php'; ?>
