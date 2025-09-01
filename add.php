<?php
require_once 'server.php';
include 'header.php';

/**
 * Add Poster — One file, full page
 * - original_title removed
 * - TVDb URL only for series/miniseries (series-only block)
 * - Chips for crew + lists: directors, writers, producers, composers, cinematographers, cast,
 *   genres, user_tags, languages, countries, networks
 * - Save/Reset buttons also at top
 * - Media (image+trailer) at bottom with Show/Hide, without reloading when hidden
 * - OMDb optional autofill by IMDb link/id (AJAX)
 * - poster_languages via lang_flags[] (separate from text 'languages')
 */

// Load poster types (need 'code' to infer is_tv)
$types = [];
$type_result = $conn->query("SELECT id, icon, label_he, code, sort_order, image FROM poster_types ORDER BY sort_order, id");
while ($row = $type_result->fetch_assoc()) { $types[] = $row; }
$typeCodeById = [];
foreach ($types as $t) $typeCodeById[(int)$t['id']] = trim((string)$t['code']);

// Defaults
$data = [
  // classify
  'type_id' => 3,
  'is_tv'   => 0,

  // titles
  'title_en'       => '',
  'title_he'       => '',
  'year'           => '',

  // external links & ids
  'imdb_link' => '', // for OMDb fill only
  'imdb_id'   => '',
  'tmdb_url'  => '',
  'tvdb_url'  => '',

  // ratings
  'imdb_rating' => '',
  'imdb_votes'  => '',
  'mc_score'    => '',
  'mc_url'      => '',
  'rt_score'    => '',
  'rt_url'      => '',

  // media
  'image_url'   => '',
  'trailer_url' => '',

  // texts (legacy kept, hidden in UI)
  'plot'        => '',
  'plot_he'     => '',
  'overview_en' => '',
  'overview_he' => '',

  // crew & people
  'directors'        => '',
  'writers'          => '',
  'producers'        => '',
  'composers'        => '',
  'cinematographers' => '',
  'cast'             => '', // שחקנים

  // lists stored as text columns in posters
  'genres'       => '',
  'languages'    => '',
  'countries'    => '',
  'networks'     => '',

  // time
  'runtime'         => '',
  'seasons_count'   => '',
  'episodes_count'  => '',

  // flags
  'has_subtitles' => 0,
  'is_dubbed'     => 0,

  // user tags table
  'user_tags' => ''
];

$message = '';
$selected_flags = []; // poster_languages (from lang_flags[])

// helper: extract tt*
function extractImdbId($input) {
  if (preg_match('/tt\d{7,10}/', (string)$input, $m)) return $m[0];
  return trim((string)$input);
}

// ---- OMDb autofill (optional) ----
if (isset($_POST['fetch_omdb'])) {
  $imdb_link_input = trim($_POST['imdb_link'] ?? '');
  $imdb_id = extractImdbId($imdb_link_input);

  $omdb_key = isset($omdb_key) ? $omdb_key : (getenv('OMDB_KEY') ?: '');

  if (!$omdb_key) {
    $message = "<span style='color:#a00'>OMDb API key חסר (omdb_key).</span>";
  } else {
    $api = "https://www.omdbapi.com/?apikey=" . urlencode($omdb_key) . "&i=" . urlencode($imdb_id) . "&plot=full&r=json";
    $json = @file_get_contents($api);
    if (!$json) {
      $message = "<span style='color:#a00'>שגיאה בשליפה מ-OMDb! נסה שוב.</span>";
    } else {
      $j = json_decode($json, true);
      if (!is_array($j) || (!empty($j['Error']))) {
        $err = !empty($j['Error']) ? $j['Error'] : 'Unknown';
        $message = "<span style='color:#a00'>OMDb: " . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</span>";
      } else {
        // map (only if present)
        $data['imdb_id']     = $imdb_id ?: trim((string)($j['imdbID'] ?? ''));
        $data['imdb_link']   = $imdb_link_input;
        $data['title_en']    = trim((string)($j['Title'] ?? ''));
        $data['year']        = trim((string)($j['Year'] ?? ''));
        $data['imdb_rating'] = trim((string)($j['imdbRating'] ?? ''));
        $data['imdb_votes']  = preg_replace('~[^\d]~', '', (string)($j['imdbVotes'] ?? ''));

        $genres  = trim((string)($j['Genre'] ?? ''));
        $actors  = trim((string)($j['Actors'] ?? ''));
        $plot    = trim((string)($j['Plot'] ?? ''));
        $runtime = trim((string)($j['Runtime'] ?? '')); // "123 min"

        if ($genres !== '') $data['genres'] = $genres;
        if ($actors !== '') $data['cast'] = $actors;   // שחקנים
        if ($plot   !== '') {
          $data['plot']        = $plot;   // legacy hidden
          $data['overview_en'] = $plot;   // visible EN
        }

        $dr = trim((string)($j['Director'] ?? ''));
        $wr = trim((string)($j['Writer'] ?? ''));
        if ($dr !== '') $data['directors'] = $dr;
        if ($wr !== '') $data['writers']   = $wr;

        $img = trim((string)($j['Poster'] ?? ''));
        if ($img !== '' && stripos($img,'N/A') === false) $data['image_url'] = $img;

        $langs = trim((string)($j['Language'] ?? ''));
        $ctrs  = trim((string)($j['Country']  ?? ''));
        if ($langs !== '') $data['languages'] = $langs;
        if ($ctrs  !== '') $data['countries'] = $ctrs;

        $mins = (int)preg_replace('~\D~', '', $runtime);
        if ($mins > 0) $data['runtime'] = $mins;
      }
    }
  }

  // keep manual entries that were already posted (not overridden by the above)
  foreach ($data as $k => $v) {
    if (isset($_POST[$k]) && $v === '') $data[$k] = is_array($_POST[$k]) ? $_POST[$k] : trim((string)$_POST[$k]);
  }
  // read flags from lang_flags[] (avoid conflict with text "languages")
  $selected_flags = (isset($_POST['lang_flags']) && is_array($_POST['lang_flags'])) ? $_POST['lang_flags'] : [];
}

// ---- Save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['fetch_omdb'])) {
  // checkboxes (not shown in UI; still respected)
  $checkboxes = ['has_subtitles','is_dubbed'];
  foreach ($checkboxes as $cb) $data[$cb] = !empty($_POST[$cb]) ? 1 : 0;

  $simpleStrings = [
    'title_en','title_he','year',
    'imdb_link','imdb_id','tmdb_url','tvdb_url',
    'mc_url','rt_url',
    'image_url','trailer_url',
    'plot','plot_he','overview_en','overview_he',
    'directors','writers','producers','composers','cinematographers','cast',
    'genres','languages','countries','networks',
    'user_tags'
  ];
  foreach ($simpleStrings as $f) $data[$f] = trim((string)($_POST[$f] ?? ''));

  $ints = ['type_id','imdb_votes','mc_score','runtime','seasons_count','episodes_count'];
  foreach ($ints as $f) {
    $val = $_POST[$f] ?? '';
    $data[$f] = ($val === '' ? '' : (int)$val);
  }
  // decimals/strings
  $data['imdb_rating'] = trim((string)($_POST['imdb_rating'] ?? ''));
  $data['rt_score']    = trim((string)($_POST['rt_score'] ?? ''));

  // poster_languages (read ONLY from lang_flags[])
  $selected_flags = (isset($_POST['lang_flags']) && is_array($_POST['lang_flags'])) ? $_POST['lang_flags'] : [];

  // uniqueness
  if ($data['imdb_id'] !== '') {
    $st = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ? LIMIT 1");
    $st->bind_param("s", $data['imdb_id']);
    $st->execute();
    $st->store_result();
    if ($st->num_rows > 0) {
      $message = "❌ פוסטר עם מזהה IMDb זה כבר קיים!";
    }
    $st->close();
  }

  if (!$message) {
    // infer is_tv by type code
    $typeId = (int)$data['type_id'];
    $code = strtolower(trim((string)($typeCodeById[$typeId] ?? '')));
    $is_tv = in_array($code, ['series','miniseries']) ? 1 : 0;
    $data['is_tv'] = $is_tv;

    // legacy back-compat (stored, not shown)
    $legacy_genre  = $data['genres'];
    $legacy_actors = $data['cast'];

    $runtime_minutes = ($data['runtime'] !== '' ? (int)$data['runtime'] : null);

    // column map (NO original_title)
    $cols = [
      // classify
      'type_id'        => ['i', $data['type_id']],
      'is_tv'          => ['i', $data['is_tv']],

      // names/year
      'title_en'       => ['s', $data['title_en']],
      'title_he'       => ['s', $data['title_he']],
      'year'           => ['s', $data['year']],

      // external
      'imdb_id'        => ['s', $data['imdb_id']],
      'tmdb_url'       => ['s', $data['tmdb_url']],
      'tvdb_url'       => ['s', $data['tvdb_url']],

      // ratings
      'imdb_rating'    => ['s', $data['imdb_rating']],
      'imdb_votes'     => ['i', ($data['imdb_votes'] === '' ? null : (int)$data['imdb_votes'])],
      'mc_score'       => ['i', ($data['mc_score'] === '' ? null : (int)$data['mc_score'])],
      'mc_url'         => ['s', $data['mc_url']],
      'rt_score'       => ['s', $data['rt_score']],
      'rt_url'         => ['s', $data['rt_url']],

      // media
      'image_url'      => ['s', $data['image_url']],
      'trailer_url'    => ['s', $data['trailer_url']],

      // texts
      'plot'           => ['s', $data['plot']],
      'plot_he'        => ['s', $data['plot_he']],
      'overview_en'    => ['s', $data['overview_en']],
      'overview_he'    => ['s', $data['overview_he']],

      // crew
      'directors'        => ['s', $data['directors']],
      'writers'          => ['s', $data['writers']],
      'producers'        => ['s', $data['producers']],
      'composers'        => ['s', $data['composers']],
      'cinematographers' => ['s', $data['cinematographers']],
      'cast'             => ['s', $data['cast']],

      // lists
      'genres'        => ['s', $data['genres']],
      'genre'         => ['s', $legacy_genre],   // legacy
      'actors'        => ['s', $legacy_actors],  // legacy
      'languages'     => ['s', $data['languages']],
      'countries'     => ['s', $data['countries']],
      'networks'      => ['s', $data['networks']],

      // time & tv
      'runtime'         => ['i', ($data['runtime'] === '' ? null : (int)$data['runtime'])],
      'runtime_minutes' => ['i', $runtime_minutes],
      'seasons_count'   => ['i', ($data['seasons_count'] === '' ? 0 : (int)$data['seasons_count'])],
      'episodes_count'  => ['i', ($data['episodes_count'] === '' ? 0 : (int)$data['episodes_count'])],

      // flags
      'has_subtitles' => ['i', (int)$data['has_subtitles']],
      'is_dubbed'     => ['i', (int)$data['is_dubbed']],
    ];

    $colNames = array_keys($cols);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO posters (" . implode(',', $colNames) . ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    $typesStr = '';
    $values = [];
    foreach ($cols as $meta) { $typesStr .= $meta[0]; $values[] = $meta[1]; }
    $stmt->bind_param($typesStr, ...$values);
    $ok = $stmt->execute();
    if (!$ok) {
      $message = "❌ שגיאה בשמירה: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
    }
    $new_poster_id = (int)$stmt->insert_id;
    $stmt->close();

    if ($ok && $new_poster_id > 0) {
      // user_tags
      $tags = preg_split('~[,\n]+~u', (string)$data['user_tags']);
      $tags = array_values(array_filter(array_map('trim', $tags), fn($x)=>$x!==''));
      foreach ($tags as $tag) {
        $tagStmt = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
        $tagStmt->bind_param("is", $new_poster_id, $tag);
        $tagStmt->execute();
        $tagStmt->close();
      }

      // poster_languages from lang_flags[]
      if (!empty($selected_flags)) {
        $ins = $conn->prepare("INSERT IGNORE INTO poster_languages (poster_id, lang_code) VALUES (?, ?)");
        foreach ($selected_flags as $lang) {
          $lc = trim((string)$lang);
          if ($lc !== '') { $ins->bind_param("is", $new_poster_id, $lc); $ins->execute(); }
        }
        $ins->close();
      }

      // Build view link (poster.php / view.php / fallback index.php)
      $view_url = 'index.php';
      if (file_exists(__DIR__ . '/poster.php'))      $view_url = 'poster.php?id=' . $new_poster_id;
      elseif (file_exists(__DIR__ . '/view.php'))    $view_url = 'view.php?id=' . $new_poster_id;

      $safe_url = htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8');
      $message = "✅ הפוסטר נוסף בהצלחה! (ID: {$new_poster_id}) — <a class=\"btn-link\" href=\"{$safe_url}\">לצפייה בפוסטר</a>";

      // reset (keep type_id)
      foreach ($data as $k => $v) {
        if ($k === 'type_id') continue;
        $data[$k] = in_array($k, ['has_subtitles','is_dubbed']) ? 0 : '';
      }
      $selected_flags = [];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>➕ הוספת פוסטר חדש</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; background:#f2f4f6; direction:rtl; color:#222; }
    .wrap  { max-width: 1200px; margin: 24px auto; }
    .card  { background:#fff; border:1px solid #e3e7ef; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.05); }
    .hdr   { padding:16px 18px; border-bottom:1px solid #e9edf5; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .hdr h2 { margin:0; font-size:20px; }
    .inner { padding: 16px 18px 6px; }

    .grid  { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:14px 22px; }
    .section { border-top:1px dashed #e4e8f2; padding-top:16px; margin-top:16px; }
    .section h3 { margin:0 0 8px; font-size:16px; color:#0e2a63; }
    label { display:block; font-weight:600; font-size:13px; margin-bottom:6px; color:#314056; }
    input[type="text"], input[type="number"], textarea {
      width:100%; padding:7px 9px; font-size:13px;
      border:1px solid #cfd7e6; border-radius:8px; background:#fbfdff; resize:vertical;
    }
    textarea { min-height:64px; }
    .row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .actions { 
  display:flex; 
  gap:8px; 
  align-items:center; 
  justify-content:center; /* יישור למרכז */
  flex-wrap:wrap; 
}/* בר כפתורים ממורכז למעלה/למטה */
.actions-bar {
  display:flex;
  justify-content:center;
  gap:10px;
  flex-wrap:wrap;
  margin:8px 0 16px;
}

/* כדי ש־OMDb יהיה באותה שורה עם השדה */
.row.inline { 
  display:flex; 
  align-items:center; 
  gap:8px; 
  flex-wrap:nowrap; 
}
.row.inline input.thin { 
  flex:1 1 auto; 
  width:auto;    /* עוקף width:100% הגלובלי */
  min-width:0; 
}
.row.inline .btn.omdb {
  flex:0 0 auto; 
  white-space:nowrap;
}

flex-wrap:wrap; }
    .btn { appearance:none; border:1px solid #cfd7e6; background:#fff; border-radius:8px; padding:8px 12px; cursor:pointer; font-size:13px; }
    .btn.primary { background:linear-gradient(90deg,#53c1f8,#2274bb); color:#fff; border:none; }
    .btn-link { text-decoration:underline; }
    .msg-ok { background:#e9ffe7; border:1px solid #bfe8b6; color:#25674b; padding:8px 10px; border-radius:8px; margin-bottom:12px; }
    .msg-err { background:#ffe9e9; border:1px solid #efb3b3; color:#7a1f1f; padding:8px 10px; border-radius:8px; margin-bottom:12px; }

    .types { display:flex; flex-wrap:wrap; gap:10px; }
    .types label {
      background:#eef5ff; border:1px solid #bfd7ff; border-radius:999px; padding:6px 10px; cursor:pointer; font-weight:500;
      display:flex; align-items:center; gap:8px;
    }
    .types input[type="radio"] { margin-left:5px; }
    .type-img { height:22px; width:auto; border-radius:6px; display:inline-block; }

    /* Chips */
    .chips { margin-top:6px; }
    .chip {
      display:inline-block; padding:2px 10px; background:#eef3fb; color:#295687;
      border:1px solid #d7e3f9; border-radius:999px; font-size:12px; margin:2px 2px 0 0;
    }
    .hint { font-size:12px; color:#777; margin-top:4px; }

    /* previews (toggle) */
    .preview-box { display:none; margin-top:8px; }
    .preview-box.show { display:block; }
    .img-prev{
      width:220px; height:auto; border:1px solid #e2e6ef; border-radius:8px; background:#f5f7fb; display:block;
    }
    .trailer-frame { width:100%; max-width:640px; aspect-ratio:16/9; border:0; border-radius:8px; }

    /* series-only block visibility */
    .series-only { display:none; }
    .series-only.show { display:block; }

    .flags-box { padding:10px; border:1px dashed #d6deee; border-radius:8px; background:#fafcff; }
  /* כפתורי פעולה כלליים */
.btn {
  appearance:none;
  border:none;
  border-radius:8px;
  padding:10px 18px;
  font-size:14px;
  font-weight:600;
  cursor:pointer;
  transition:all .2s ease;
  box-shadow:0 2px 4px rgba(0,0,0,0.1);
}

/* כפתור עיקרי (שמור) */
.btn.primary {
  background:linear-gradient(90deg,#53c1f8,#2274bb);
  color:#fff;
}
.btn.primary:hover {
  background:linear-gradient(90deg,#34aad8,#1860aa);
  transform:translateY(-1px);
}

/* כפתור משני (איפוס) */
.btn.reset {
  background:#f4f6fa;
  color:#333;
  border:1px solid #d0d7e5;
}
.btn.reset:hover {
  background:#e9eef6;
  transform:translateY(-1px);
}

/* כפתור OMDb */
.btn.omdb {
  background:linear-gradient(90deg,#53c1f8,#2274bb);
  color:#fff;
  padding:8px 14px;
  font-size:13px;
}
.btn.omdb:hover {
  background:linear-gradient(90deg,#34aad8,#1860aa);
  transform:translateY(-1px);
}

/* סידור במרכז */
.actions-bar {
  display:flex;
  justify-content:center;
  gap:12px;
  margin:16px 0;
}

  </style>
  <script>
    // ===== Utilities =====
    function ytId(url){
      var m = (url||'').match(/(?:youtu\.be\/|v=)([A-Za-z0-9_-]{6,})/i);
      return m ? m[1] : '';
    }
    function vimeoId(url){
      var m = (url||'').match(/vimeo\.com\/(\d+)/i);
      return m ? m[1] : '';
    }
    function makeTrailerEmbed(url){
      url = (url||'').trim();
      var id, html='';
      if (!url) return '<img src="images/no-trailer.png" alt="No trailer" class="trailer-frame">';
      if ((id = ytId(url))) {
        html = '<iframe class="trailer-frame" loading="lazy" allowfullscreen '+
               'src="https://www.youtube.com/embed/'+id+'?rel=0"></iframe>';
      } else if ((id = vimeoId(url))) {
        html = '<iframe class="trailer-frame" loading="lazy" allow="fullscreen; picture-in-picture" '+
               'src="https://player.vimeo.com/video/'+id+'"></iframe>';
      } else if (/\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) {
        html = '<video class="trailer-frame" controls src="'+url.replace(/"/g,'&quot;')+'"></video>';
      } else {
        html = '<a target="_blank" rel="noopener" href="'+url.replace(/"/g,'&quot;')+'">פתח טריילר בקישור חיצוני</a>';
      }
      return html;
    }

    function splitToParts(value){
      return (value||'').split(/,|\n/).map(function(s){return s.trim();}).filter(Boolean);
    }
    function renderChips(fieldId){
      var input = document.getElementById(fieldId);
      var box = document.getElementById(fieldId + '_chips');
      if (!input || !box) return;
      var parts = splitToParts(input.value);
      box.innerHTML = parts.map(function(p){ return '<span class="chip">'+p.replace(/[&<>"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]);})+'</span>'; }).join(' ');
    }

    // ===== Wiring after (re)render) =====
    function wireOmdb() {
      var el = document.getElementById('imdb_link');
      if (!el) return;
      if (!el.dataset.bound) {
        el.addEventListener('blur', function(){
          if (this.value.match(/tt\d{7,10}/)) fetchOmdbAJAX();
        });
        el.dataset.bound = "1";
      }
    }
    function wireTypeToggle() {
      var radios = document.querySelectorAll('input[name="type_id"]');
      var seriesBlock = document.getElementById('seriesBlock');
      if (!radios.length || !seriesBlock) return;

      function isSeries(code) {
        if (!code) return false;
        code = (code+'').toLowerCase();
        return (code === 'series' || code === 'miniseries');
      }
      function currentCode() {
        var r = document.querySelector('input[name="type_id"]:checked');
        return r ? (r.dataset.code || '') : '';
      }
      function apply() {
        var code = currentCode();
        if (isSeries(code)) seriesBlock.classList.add('show');
        else seriesBlock.classList.remove('show');
      }
      radios.forEach(function(r){
        if (!r.dataset.bound) {
          r.addEventListener('change', apply);
          r.dataset.bound = "1";
        }
      });
      apply();
    }
    function wireImagePreview() {
      var input = document.querySelector('input[name="image_url"]');
      var box   = document.getElementById('imgPrevBox');
      var img   = document.getElementById('imgPreview');
      var btn   = document.getElementById('btnImgToggle');
      if (!input || !box || !img || !btn) return;

      function openBox(){
        var v = (input.value || '').trim();
        img.removeAttribute('src');
        img.setAttribute('data-src', v);
        img.src = v || 'images/no-poster.png';
        box.classList.add('show');
        btn.textContent = 'סגור תצוגה';
      }
      function closeBox(){
        img.removeAttribute('src'); // stop loading remote
        img.src = 'images/no-poster.png';
        box.classList.remove('show');
        btn.textContent = 'הצג תצוגה';
      }
      btn.onclick = function(){
        if (box.classList.contains('show')) closeBox(); else openBox();
      };
      img.addEventListener('error', function(){ img.src = 'images/no-poster.png'; });

      // live update while open
      input.addEventListener('input', function(){
        if (box.classList.contains('show')) img.src = (this.value||'').trim() || 'images/no-poster.png';
      });

      // Auto-open on first paint
      openBox();
    }
    function wireTrailerPreview() {
      var input = document.querySelector('input[name="trailer_url"]');
      var box   = document.getElementById('trailerPrevBox');
      var slot  = document.getElementById('trailerFrameBox');
      var btn   = document.getElementById('btnTrailerToggle');
      if (!input || !box || !slot || !btn) return;

      function openBox(){
        var v = (input.value || '').trim();
        slot.innerHTML = makeTrailerEmbed(v);
        box.classList.add('show');
        btn.textContent = 'סגור טריילר';
      }
      function closeBox(){
        slot.innerHTML = ''; // remove iframe/video to stop network
        box.classList.remove('show');
        btn.textContent = 'הצג טריילר';
      }
      btn.onclick = function(){
        if (box.classList.contains('show')) closeBox(); else openBox();
      };

      // live update while open
      input.addEventListener('input', function(){
        if (box.classList.contains('show')) slot.innerHTML = makeTrailerEmbed((this.value||'').trim());
      });

      // Auto-open on first paint
      openBox();
    }
    function wireHoverSelect(){
      ['image_url','trailer_url'].forEach(function(name){
        var el = document.querySelector('input[name="'+name+'"]');
        if (!el || el.dataset.boundHover) return;
        el.addEventListener('mouseover', function(){ try{ this.select(); }catch(e){} });
        el.addEventListener('focus', function(){ try{ this.select(); }catch(e){} });
        el.dataset.boundHover = '1';
      });
    }
    function wireChips(){
      var chipFields = [
        'genres','user_tags','languages','countries','networks',
        'directors','writers','producers','composers','cinematographers','cast'
      ];
      chipFields.forEach(function(fid){
        var el = document.getElementById(fid);
        renderChips(fid);
        if (el && !el.dataset.bound) {
          el.addEventListener('input', function(){ renderChips(fid); });
          el.dataset.bound = '1';
        }
      });
    }
    function rehydrate() {
      wireOmdb();
      wireTypeToggle();
      wireImagePreview();
      wireTrailerPreview();
      wireHoverSelect();
      wireChips();
    }

    // AJAX fetch OMDb then replace inner card
    function fetchOmdbAJAX() {
      var imdb_link = document.getElementById('imdb_link').value.trim();
      if (!imdb_link.match(/tt\d{7,10}/)) {
        alert("יש להזין לינק או מזהה IMDb חוקי (ttXXXXXXX)");
        return;
      }
      var form = document.getElementById('addForm');
      var omdbBtn = document.getElementById('omdb-btn');
      if (omdbBtn) { omdbBtn.disabled = true; omdbBtn.textContent = 'טוען...'; }
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      var formData = new FormData(form);
      formData.append('fetch_omdb', '1');
      xhr.onload = function() {
        var temp = document.createElement('div');
        temp.innerHTML = xhr.responseText;
        var newInner = temp.querySelector('.card .inner');
        if (newInner) {
          document.querySelector('.card .inner').innerHTML = newInner.innerHTML;
          rehydrate();
        } else {
          alert('שגיאה בעדכון התצוגה.');
        }
        if (omdbBtn) { omdbBtn.disabled = false; omdbBtn.textContent = '🔄 OMDb'; }
      };
      xhr.onerror = function(){ alert('שגיאת רשת!'); if (omdbBtn) { omdbBtn.disabled=false; omdbBtn.textContent='🔄 OMDb'; } };
      xhr.send(formData);
    }

    window.addEventListener('DOMContentLoaded', rehydrate);
  </script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="hdr">
  <h2>➕ הוספת פוסטר חדש</h2>
  <div class="actions">
    <button type="submit" class="btn primary" form="addForm">💾 שמור</button>
    <button type="reset" class="btn" form="addForm">איפוס</button>
    <button type="button" class="btn" onclick="location.href='index.php'">🏠 לדף הבית</button>
  </div>
</div>


    <div class="inner">
      <?php if ($message): ?>
        <div class="<?= (function_exists('str_starts_with') && str_starts_with($message,'✅')?'msg-ok':'msg-err') ?>"><?= $message ?></div>
      <?php endif; ?>

      <!-- סוג פוסטר -->
      <div class="section">
        <h3>סוג פוסטר</h3>
        <div class="types">
          <?php foreach ($types as $t):
            $img = trim((string)($t['image'] ?? ''));
            $imgUrl = $img !== '' ? 'images/types/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') : '';
          ?>
            <label class="type-option">
              <input type="radio" name="type_id" value="<?= (int)$t['id'] ?>" form="addForm"
                     data-code="<?= htmlspecialchars($t['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     <?= ((int)$data['type_id']===(int)$t['id']) ? 'checked' : '' ?>>
              <?php if ($imgUrl): ?>
                <img src="<?= $imgUrl ?>" alt="" width="28" height="28" class="type-img"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
              <?php endif; ?>
              <span class="type-icon" style="display:<?= $imgUrl ? 'none' : 'inline-block' ?>;font-size:18px;">
                <?= htmlspecialchars($t['icon'] ?? '', ENT_QUOTES, 'UTF-8') ?>
              </span>
              <span><?= htmlspecialchars(($t['label_he'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <form method="post" id="addForm" autocomplete="off">
        <div class="actions-bar">
  <button type="submit" class="btn primary">💾 שמור</button>
  <button type="reset" class="btn">איפוס</button>
</div>

        <!-- קישורים חיצוניים וזיהוי (מעל כותרות) -->
        <div class="section">
          <h3>קישורים חיצוניים וזיהוי</h3>
          <div class="grid">
            <div>
  <label>קישור IMDB או מזהה tt</label>
  <div class="row inline">
    <input class="thin" type="text" id="imdb_link" name="imdb_link"
           value="<?= htmlspecialchars($data['imdb_link']) ?>" style="direction:ltr">
    <button type="button" class="btn omdb" id="omdb-btn" onclick="fetchOmdbAJAX()">🔄 OMDb</button>
  </div>
  <div class="hint">משמש לשליפה אוטומטית בלבד (לא נשמר בעמודה).</div>
</div>


            <div>
              <label>IMDb ID (נמשך אוטומטית)</label>
              <input type="text" name="imdb_id" id="imdb_id" value="<?= htmlspecialchars($data['imdb_id']) ?>" style="direction:ltr">
            </div>
            <div>
              <label>TMDb URL</label>
              <input type="text" name="tmdb_url" value="<?= htmlspecialchars($data['tmdb_url']) ?>" style="direction:ltr">
            </div>
            <!-- TVDb URL moved to series-only block -->
          </div>
        </div>

        <!-- כותרות/זיהוי בסיסי (כולל runtime ליד שנה) -->
        <div class="section">
          <h3>כותרות וזיהוי</h3>
          <div class="grid">
            <div>
              <label>שם באנגלית</label>
              <input type="text" name="title_en" value="<?= htmlspecialchars($data['title_en']) ?>">
              <div id="title_en_chips" class="chips"></div>
            </div>
            <div>
              <label>שם בעברית</label>
              <input type="text" name="title_he" value="<?= htmlspecialchars($data['title_he']) ?>">
              <div id="title_he_chips" class="chips"></div>
            </div>
            <div>
              <label>שנה</label>
              <input type="text" name="year" value="<?= htmlspecialchars($data['year']) ?>">
            </div>
            <div>
              <label>אורך (דקות)</label>
              <input type="number" name="runtime" value="<?= htmlspecialchars($data['runtime']) ?>">
            </div>
          </div>
        </div>

        <!-- דירוגים -->
        <div class="section">
          <h3>דירוגים</h3>
          <div class="grid">
            <div>
              <label>IMDb Rating</label>
              <input type="text" name="imdb_rating" value="<?= htmlspecialchars($data['imdb_rating']) ?>">
            </div>
            <div>
              <label>IMDb Votes</label>
              <input type="number" name="imdb_votes" value="<?= htmlspecialchars($data['imdb_votes']) ?>">
            </div>
            <div>
              <label>Metacritic URL</label>
              <input type="text" name="mc_url" value="<?= htmlspecialchars($data['mc_url']) ?>" style="direction:ltr">
            </div>
            <div>
              <label>Metacritic (מספר)</label>
              <input type="number" name="mc_score" value="<?= htmlspecialchars($data['mc_score']) ?>">
            </div>
            <div>
              <label>Rotten Tomatoes URL</label>
              <input type="text" name="rt_url" value="<?= htmlspecialchars($data['rt_url']) ?>" style="direction:ltr">
            </div>
            <div>
              <label>Rotten Tomatoes (אחוזים)</label>
              <input type="text" name="rt_score" value="<?= htmlspecialchars($data['rt_score']) ?>">
            </div>
          </div>
        </div>

        <!-- תקצירים -->
        <div class="section">
          <h3>תקציר/עלילה</h3>
          <div class="grid">
            <div class="full">
              <label>תקציר אנגלית</label>
              <textarea name="overview_en"><?= htmlspecialchars($data['overview_en']) ?></textarea>
            </div>
            <div class="full">
              <label>תקציר עברית</label>
              <textarea name="overview_he"><?= htmlspecialchars($data['overview_he']) ?></textarea>
            </div>
          </div>
        </div>

        <!-- אנשי צוות + Chips -->
        <div class="section">
          <h3>אנשי צוות</h3>
          <div class="grid">
            <div>
              <label>במאים:</label>
              <input type="text" name="directors" id="directors" value="<?= htmlspecialchars($data['directors']) ?>">
              <div id="directors_chips" class="chips"></div>
            </div>
            <div>
              <label>תסריטאים:</label>
              <input type="text" name="writers" id="writers" value="<?= htmlspecialchars($data['writers']) ?>">
              <div id="writers_chips" class="chips"></div>
            </div>
            <div>
              <label>מפיקים:</label>
              <input type="text" name="producers" id="producers" value="<?= htmlspecialchars($data['producers']) ?>">
              <div id="producers_chips" class="chips"></div>
            </div>
            <div>
              <label>מלחינים:</label>
              <input type="text" name="composers" id="composers" value="<?= htmlspecialchars($data['composers']) ?>">
              <div id="composers_chips" class="chips"></div>
            </div>
            <div>
              <label>צלמים:</label>
              <input type="text" name="cinematographers" id="cinematographers" value="<?= htmlspecialchars($data['cinematographers']) ?>">
              <div id="cinematographers_chips" class="chips"></div>
            </div>
            <div>
              <label>שחקנים:</label>
              <input type="text" name="cast" id="cast" value="<?= htmlspecialchars($data['cast']) ?>">
              <div id="cast_chips" class="chips"></div>
            </div>
          </div>
          <div class="hint">אפשר להפריד ערכים בפסיקים או בשורה חדשה.</div>
        </div>

        <!-- רשימות טקסטואליות + Chips -->
        <div class="section">
          <h3>רשימות (פסיקים בין ערכים)</h3>
          <div class="grid">
            <div>
              <label>ז׳אנרים</label>
              <input type="text" name="genres" id="genres" value="<?= htmlspecialchars($data['genres']) ?>">
              <div id="genres_chips" class="chips"></div>
            </div>
            <div>
              <label>תגיות משתמש</label>
              <input type="text" name="user_tags" id="user_tags" value="<?= htmlspecialchars($data['user_tags']) ?>">
              <div id="user_tags_chips" class="chips"></div>
            </div>
            <div>
              <label>שפות</label>
              <input type="text" name="languages" id="languages" value="<?= htmlspecialchars($data['languages']) ?>">
              <div id="languages_chips" class="chips"></div>
            </div>
            <div>
              <label>מדינות</label>
              <input type="text" name="countries" id="countries" value="<?= htmlspecialchars($data['countries']) ?>">
              <div id="countries_chips" class="chips"></div>
            </div>
          </div>
        </div>

        <!-- בלוק סדרות בלבד (כולל TVDb URL) -->
        <?php
          $isSeriesNow = false;
          $tcode = $typeCodeById[(int)$data['type_id']] ?? '';
          $lc = strtolower(trim((string)$tcode));
          if ($lc === 'series' || $lc === 'miniseries') $isSeriesNow = true;
        ?>
        <div class="section series-only <?= $isSeriesNow ? 'show' : '' ?>" id="seriesBlock">

          <h3>פרטי סדרה</h3>
          <div class="grid">
            <div>
  <label>TVDb URL</label>
  <input type="text" name="tvdb_url" value="<?= htmlspecialchars($data['tvdb_url']) ?>" style="direction:ltr">
</div>

            <div>
              <label>רשתות</label>
              <input type="text" name="networks" id="networks" value="<?= htmlspecialchars($data['networks']) ?>">
              <div id="networks_chips" class="chips"></div>
            </div>
          
            <div>
              <label>מס' עונות</label>
              <input type="number" name="seasons_count" value="<?= htmlspecialchars($data['seasons_count']) ?>">
            </div>
            <div>
              <label>מס' פרקים</label>
              <input type="number" name="episodes_count" value="<?= htmlspecialchars($data['episodes_count']) ?>">
            </div>
          </div>
        </div>

        <!-- מדיה (למטה, הצג/סגור) -->
        <div class="section">
          <h3>מדיה</h3>
          <div class="grid">
            <div>
              <label>תמונה (Image URL)</label>
              <input type="text" name="image_url" value="<?= htmlspecialchars($data['image_url']) ?>" style="direction:ltr">
              <div class="row" style="margin-top:6px">
                <button type="button" class="btn" id="btnImgToggle">סגור תצוגה</button>
              </div>
              <div id="imgPrevBox" class="preview-box">
                <img id="imgPreview" class="img-prev" alt="Preview">
              </div>
            </div>
            <div>
              <label>טריילר (YouTube/Vimeo/MP4)</label>
              <input type="text" name="trailer_url" value="<?= htmlspecialchars($data['trailer_url']) ?>" style="direction:ltr">
              <div class="row" style="margin-top:6px">
                <button type="button" class="btn" id="btnTrailerToggle">סגור טריילר</button>
              </div>
              <div id="trailerPrevBox" class="preview-box">
                <div id="trailerFrameBox"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- דגלים (poster_languages) -->
        <div class="section">
          <h3>דגלים/שפות</h3>
          <div class="flags-box">
            <?php
              // Include flags.php but rename its checkbox name from languages[] to lang_flags[] to avoid collision with text input "languages"
              ob_start();
              include 'flags.php'; // must output checkboxes name="languages[]" value="code"
              $flags_html = ob_get_clean();
              // Replace both double and single-quoted name attributes
              $flags_html = str_replace(['name="languages[]"','name=\'languages[]\''], ['name="lang_flags[]"','name=\'lang_flags[]\''], $flags_html);
              echo $flags_html;
            ?>
          </div>
        </div>

        <!-- hidden legacy fields to preserve logic -->
        <input type="hidden" name="plot" value="<?= htmlspecialchars($data['plot']) ?>">
        <input type="hidden" name="plot_he" value="<?= htmlspecialchars($data['plot_he']) ?>">

        <!-- BOTTOM ACTIONS -->
       <div class="section">
  <div class="actions-bar">
    <button type="submit" class="btn primary">💾 שמור</button>
    <button type="reset" class="btn">איפוס</button>
  </div>
</div>


      </form>
    </div>
  </div>
</div>

</body>
</html>
<?php include 'footer.php'; ?>
