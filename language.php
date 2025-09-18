<?php
// include 'header.php';
require_once 'server.php';
require_once 'languages.php';
// חיבור bar.php ישירות (עמוד מלא)
include 'bar.php';


/* ===== Helpers ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function current_url_base(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path   = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
  return $scheme.'://'.$host.$path;
}
function keep_params(array $extra = [], array $drop = []): string {
  $q = $_GET;
  foreach ($drop as $d) unset($q[$d]);
  foreach ($extra as $k=>$v) $q[$k] = $v;
  return current_url_base() . (empty($q) ? '' : '?'.http_build_query($q));
}
function render_pager(int $page, int $total_pages): string {
  if ($total_pages <= 1) return '';
  $max_links = 7;
  $start = max(1, $page - intdiv($max_links-1, 2));
  $end   = min($total_pages, $start + $max_links - 1);
  if ($end - $start + 1 < $max_links) $start = max(1, $end - $max_links + 1);

  $html = '<div class="pager">';
  if ($page > 1) $html .= '<a href="'.h(keep_params(['page'=>$page-1])).'">⬅ הקודם</a>';
  for ($i=$start; $i<=$end; $i++){
    $html .= ($i==$page) ? '<strong>'.$i.'</strong>' : '<a href="'.h(keep_params(['page'=>$i])).'">'.$i.'</a>';
  }
  if ($page < $total_pages) $html .= '<a href="'.h(keep_params(['page'=>$page+1])).'">הבא ➡</a>';
  $html .= '</div>';
  return $html;
}

/* ===== Inputs ===== */
$lang_code = trim((string)($_GET['lang_code'] ?? ''));
if ($lang_code === '') { echo "<p style='text-align:center;'>❌ לא נבחרה שפה</p>"; include 'footer.php'; exit; }

/* כמות להצגה */
$allowed_limits = [20,50,100,250,'all'];
$limit_param = $_GET['limit'] ?? 50;
if ($limit_param === 'all') { $limit = null; }
else {
  $limit_int = (int)$limit_param;
  $limit = in_array($limit_int, [20,50,100,250], true) ? $limit_int : 50; // ברירת מחדל 50
  $limit_param = $limit;
}

/* מיון */
$sort = (string)($_GET['sort'] ?? 'new'); // ברירת מחדל: אחרון שנוסף
$SORT_SQL = [
  'new'         => 'p.id DESC',
  'old'         => 'p.id ASC',
  'year_desc'   => 'p.year DESC',
  'year_asc'    => 'p.year ASC',
  'rating_desc' => 'CAST(SUBSTRING_INDEX(p.imdb_rating, "/", 1) AS DECIMAL(3,1)) DESC',
  'title_az'    => 'p.title_en ASC',
  'title_za'    => 'p.title_en DESC',
];
$order_sql = $SORT_SQL[$sort] ?? $SORT_SQL['new'];

/* גודל פוסטרים (ברירת־מחדל 200) */
$size_param = (int)($_GET['size'] ?? 200);
$allowed_sizes = [200,240,260,280,320];
$card_w = in_array($size_param, $allowed_sizes, true) ? $size_param : 260;

/* עימוד */
$page = max(1, (int)($_GET['page'] ?? 1));

/* ===== Language label/flag ===== */
$lang_label = $lang_code; $lang_flag = '';
foreach ($languages as $lang) {
  if ($lang['code'] === $lang_code) { $lang_label = $lang['label']; $lang_flag = $lang['flag']; break; }
}

/* ===== Count total ===== */
$sql_count = "SELECT COUNT(DISTINCT p.id) AS c
              FROM posters p
              JOIN poster_languages pl ON p.id = pl.poster_id
              WHERE pl.lang_code = ?";
$stmtc = $conn->prepare($sql_count);
$stmtc->bind_param("s", $lang_code);
$stmtc->execute();
$total_rows = (int)($stmtc->get_result()->fetch_assoc()['c'] ?? 0);
$stmtc->close();

$total_pages = ($limit === null) ? 1 : max(1, (int)ceil($total_rows / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($limit === null) ? 0 : ($page - 1) * $limit;

/* ===== Fetch page ===== */
$sql_base = "SELECT
               p.id, p.title_en, p.title_he, p.image_url, p.year, p.genres, p.trailer_url,
               p.imdb_id, p.imdb_rating,
               p.type_id,
               pt.label_he AS type_label,
               COALESCE(pt.image,'') AS type_image
             FROM posters p
             JOIN poster_languages pl ON p.id = pl.poster_id
             LEFT JOIN poster_types pt ON p.type_id = pt.id
             WHERE pl.lang_code = ?
             ORDER BY $order_sql";
if ($limit === null) {
  $stmt = $conn->prepare($sql_base);
  $stmt->bind_param("s", $lang_code);
} else {
  $sql_base .= " LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql_base);
  $stmt->bind_param("sii", $lang_code, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
$stmt->close();

/* user_tags לדף */
$user_tags_by_poster_id = [];
if (!empty($rows)) {
  $poster_ids = array_column($rows, 'id');
  $ph = implode(',', array_fill(0, count($poster_ids), '?'));
  $types = str_repeat('i', count($poster_ids));
  $sql_ut = "SELECT poster_id, genre FROM user_tags WHERE poster_id IN ($ph)";
  $stmt_ut = $conn->prepare($sql_ut);
  $stmt_ut->bind_param($types, ...$poster_ids);
  $stmt_ut->execute();
  $res_ut = $stmt_ut->get_result();
  while ($t = $res_ut->fetch_assoc()) $user_tags_by_poster_id[$t['poster_id']][] = $t['genre'];
  $stmt_ut->close();
}

/* ===== Derived for UI ===== */
$start_i = ($total_rows === 0) ? 0 : $offset + 1;
$end_i   = $offset + count($rows);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים בשפה <?= h($lang_label) ?></title>
  <link rel="stylesheet" href="style.css">
  <style>
    :root { --cardw: <?= (int)$card_w ?>px; }
    body { background-color:#fff; margin:0; }
    h1, .results-summary { text-align:center; margin:10px 0 12px; }
    .topbar { display:flex; justify-content:center; align-items:center; gap:16px; flex-wrap:wrap; margin:6px 0 10px; }
    .topbar form { display:flex; gap:10px; align-items:center; }
    .topbar select { padding:6px 8px; border:1px solid #bbb; border-radius:6px; font-size:14px; }

    /* קיר פוסטרים */
    .poster-wall { display:flex; flex-wrap:wrap; gap:28px 22px; justify-content:center; margin:18px 0 32px; }
    .poster-plain { width:var(--cardw); text-align:center; }
    .poster-plain a { text-decoration:none; color:inherit; }     /* לא display:block לכל הקישורים */
    .poster-img{ width:100%; height:auto; border-radius:1px; box-shadow:0 2px 10px rgba(0,0,0,.15); display:block; }

    .poster-plain .title { margin:10px 0 0; font-size:20px; line-height:1.25; font-weight:600; }
    .poster-plain .subtitle { margin:4px 0 0; color:#777; font-size:14px; }

    .meta { margin:6px 0 2px; font-size:13px; color:#444; display:flex; gap:10px; justify-content:center; align-items:center; flex-wrap:wrap; }
    .meta a { color:#1567c0; text-decoration:none; }
    .meta a:hover { text-decoration:underline; }

    /* שורת מטא שניה: סוג + טריילר (RTL => הסוג מימין) */
    .meta2{ margin-top:4px; font-size:13px; color:#444; display:flex; gap:10px; justify-content:center; align-items:center; flex-wrap:wrap; direction: rtl; }

    /* סוג עם תמונה – בלי רקע/צל */
    .type-pill{ display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#555; text-decoration:none; }
    .type-icon{ height:36px; width:auto; vertical-align:middle; box-shadow:none!important; border-radius:0!important; background:transparent!important; }

    /* תגיות */
    .tags{ margin:6px auto 0; display:inline-flex; flex-wrap:wrap; gap:6px; width:fit-content; max-width:100%; justify-content:center; }
    .tag-badge{ display:inline-block; padding:4px 10px; border-radius:14px; font-size:12px; margin:3px; text-decoration:none; color:#223; background:linear-gradient(to bottom,#f7f7f7,#e6e6e6); border:1px solid #d0d0d0; }
    .tag-badge:hover{ filter:brightness(0.95); }
    .tag-user{ background:linear-gradient(to bottom,#e3f2fd,#bbdefb); border-color:#90caf9; }

    .trailer-btn{ background:#d92323; color:#fff; border:none; padding:5px 10px; border-radius:6px; cursor:pointer; font-size:12px; }

    .pager{ text-align:center; margin:8px 0; display:flex; justify-content:center; gap:7px; flex-wrap:wrap; }
    .pager a, .pager strong{ padding:6px 12px; border:1px solid #bbb; border-radius:6px; text-decoration:none; background:#fff; font-size:15px; color:#147; margin:0 1px; }

    /* Modal */
    .modal{ display:none; position:fixed; z-index:1000; inset:0; background:rgba(0,0,0,.7); }
    .modal-content{ position:relative; background:#181818; margin:60px auto auto; padding:0; width:90%; max-width:800px; }
    .close-btn{ color:#fff; float:left; font-size:38px; font-weight:bold; padding:4px 10px; cursor:pointer; }
    .video-container{ position:relative; padding-bottom:56.25%; height:0; overflow:hidden; }
    .video-container iframe{ position:absolute; inset:0; width:100%; height:100%; }

    /* IMDb – לוגו וטקסט באותה שורה */
    .imdb-link{ display:inline-flex; align-items:center; gap:8px; white-space:nowrap; text-decoration:none; }
    .imdb-logo{ height:44px; width:auto; display:block; }
    .imdb-text{ line-height:1; }
    .imdb-link { flex-direction: row-reverse; }

    <?php echo $bar_style; ?>

  </style>
</head>
<body>


  <h1>🌐 פוסטרים בשפה <?= h($lang_label) ?>
    <?php if ($lang_flag): ?>
      <img src="<?= h($lang_flag) ?>" alt="<?= h($lang_label) ?>" style="height:20px; vertical-align:middle;">
    <?php endif; ?>
  </h1>

  <div class="results-summary">
    <b>הצגת <?= $start_i ?>–<?= $end_i ?> מתוך <?= $total_rows ?> — עמוד <?= $page ?> מתוך <?= $total_pages ?></b>
  </div>

  <div class="topbar">
    <form method="get" action="">
      <input type="hidden" name="lang_code" value="<?= h($lang_code) ?>">
      <input type="hidden" name="page" value="1">

      <label>כמות:
        <select name="limit" onchange="this.form.submit()">
          <?php foreach ([20,50,100,250,'all'] as $opt):
                 $lbl = ($opt==='all') ? 'הכול' : $opt;
                 $sel = ((string)$opt === (string)$limit_param) ? 'selected' : ''; ?>
            <option value="<?= h((string)$opt) ?>" <?= $sel ?>><?= h((string)$lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>מיון לפי:
        <select name="sort" onchange="this.form.submit()">
          <?php
          $sort_opts = [
            'new'         => 'אחרון שנוסף (ברירת־מחדל)',
            'old'         => 'ראשון שנוסף',
            'year_desc'   => 'שנה ↓',
            'year_asc'    => 'שנה ↑',
            'rating_desc' => 'דירוג IMDb ↓',
            'title_az'    => 'כותרת A→Z',
            'title_za'    => 'כותרת Z→A',
          ];
          foreach ($sort_opts as $val=>$label):
            $sel = ($sort === $val) ? 'selected' : '';
          ?>
            <option value="<?= h($val) ?>" <?= $sel ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>גודל פוסטר:
        <select name="size" onchange="this.form.submit()">
          <?php foreach ($allowed_sizes as $sz):
                 $sel = ($card_w === $sz) ? 'selected' : ''; ?>
            <option value="<?= (int)$sz ?>" <?= $sel ?>><?= (int)$sz ?>px</option>
          <?php endforeach; ?>
        </select>
      </label>
      <noscript><button type="submit">החל</button></noscript>
    </form>

    <?= render_pager($page, $total_pages) ?>
  </div>

  <?php if (empty($rows)): ?>
    <p style="text-align:center; color:#888;">😢 לא נמצאו פוסטרים בשפה <?= h($lang_label) ?></p>
  <?php else: ?>
    <div class="poster-wall">
      <?php foreach ($rows as $row): ?>
        <?php
          $img = $row['image_url'] ?: 'images/no-poster.png';
          $genres = array_values(array_filter(array_map('trim', explode(',', (string)($row['genres'] ?? '')))));
          $tags   = $user_tags_by_poster_id[$row['id']] ?? [];
          $rating_raw = trim((string)($row['imdb_rating'] ?? ''));
          $rating_txt = $rating_raw !== '' && strpos($rating_raw, '/') === false ? ($rating_raw.' / 10') : $rating_raw;
        ?>
        <div class="poster-plain">
          <a href="poster.php?id=<?= (int)$row['id'] ?>">
            <img class="poster-img" src="<?= h($img) ?>" alt="Poster">
          </a>

          <div class="title"><a href="poster.php?id=<?= (int)$row['id'] ?>"><?= h($row['title_en']) ?></a></div>
          <?php if (!empty($row['title_he'])): ?>
            <div class="subtitle"><a href="poster.php?id=<?= (int)$row['id'] ?>"><?= h($row['title_he']) ?></a></div>
          <?php endif; ?>

          <div class="meta">
            <?php if (!empty($row['year'])): ?>
              <a href="home.php?year=<?= h($row['year']) ?>">🗓 <?= h($row['year']) ?></a>
            <?php endif; ?>

            <?php if ($rating_raw !== '' && !empty($row['imdb_id'])): ?>
              <a class="imdb-link" href="https://www.imdb.com/title/<?= h($row['imdb_id']) ?>/" target="_blank" rel="noopener" title="Open on IMDb">
                <img class="imdb-logo" src="images/imdb.png" alt="IMDb">
                <span class="imdb-text">IMDb <?= h($rating_txt) ?></span>
              </a>
            <?php endif; ?>
          </div>

          <?php if (!empty($row['type_label']) || !empty($row['trailer_url'])): ?>
          <div class="meta2">
            <?php if (!empty($row['type_label'])): ?>
              <a class="type-pill" href="home.php?type[]=<?= (int)$row['type_id'] ?>">
                <?php if (!empty($row['type_image'])): ?>
                  <img class="type-icon" src="images/types<?= ($row['type_image'][0] === '/' ? '' : '/') . h($row['type_image']) ?>" alt="">
                <?php endif; ?>
                <span><?= h($row['type_label']) ?></span>
              </a>
            <?php endif; ?>

            <?php if (!empty($row['trailer_url'])): ?>
              <button class="trailer-btn" data-trailer-url="<?= h($row['trailer_url']) ?>">🎬 טריילר</button>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($genres) || !empty($tags)): ?>
            <div class="tags">
              <?php foreach ($genres as $g): ?>
                <a class="tag-badge" href="home.php?genre=<?= urlencode($g) ?>"><?= h($g) ?></a>
              <?php endforeach; ?>
              <?php foreach ($tags as $t): ?>
                <a class="tag-badge tag-user" href="home.php?user_tag=<?= urlencode($t) ?>"><?= h($t) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?= render_pager($page, $total_pages) ?>

  <div style="text-align:center; margin:12px 0 18px;">
    <a href="index.php">⬅ חזרה לרשימה</a>
  </div>

  <div id="trailer-modal" class="modal">
    <div class="modal-content ltr">
      <span class="close-btn">&times;</span>
      <div class="video-container" id="video-container"></div>
    </div>
  </div>

  <script>
    (function(){
      const modal = document.getElementById('trailer-modal');
      const videoContainer = document.getElementById('video-container');
      const closeBtn = modal.querySelector('.close-btn');

      function getYouTubeEmbedUrl(url) {
        try {
          const u = new URL(url);
          if (u.hostname === 'youtu.be') return 'https://www.youtube.com/embed/'+u.pathname.slice(1)+'?autoplay=1&rel=0';
          const v = u.searchParams.get('v');
          if (v) return 'https://www.youtube.com/embed/'+v+'?autoplay=1&rel=0';
        } catch(e) {}
        return null;
      }
      function openModal(url){
        const emb = getYouTubeEmbedUrl(url);
        if (!emb) return;
        videoContainer.innerHTML = '<iframe src="'+emb+'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        modal.style.display = 'block';
      }
      function closeModal(){
        modal.style.display = 'none';
        videoContainer.innerHTML = '';
      }

      document.addEventListener('click', function(e){
        const btn = e.target.closest('.trailer-btn');
        if (btn && btn.dataset.trailerUrl) {
          e.preventDefault();
          openModal(btn.dataset.trailerUrl);
        }
      });
      closeBtn.addEventListener('click', closeModal);
      window.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });
    })();
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // נקה בחירות שפה בבר בעמודים שאינם home.php
    if (!location.pathname.endsWith('/home.php')) {
      document.querySelectorAll('input[name="languages[]"]').forEach(cb => cb.checked = false);
      const lc = document.querySelector('[name="lang_code"]');
      if (lc && lc.tagName === 'INPUT') lc.value = '';
      if (lc && lc.tagName === 'SELECT') lc.selectedIndex = -1;
    }
  });
  </script>
</body>
</html>

<?php include 'footer.php'; ?>