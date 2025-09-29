<?php
/****************************************************
 * similar_all.php — מציג את כל ה-Similar Movies באתר
 * מבוסס על poster_similar בלבד.
 ****************************************************/

mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/server.php';
if (file_exists(__DIR__ . '/header.php')) {
    include __DIR__ . '/header.php';
}

if (function_exists('mysqli_set_charset')) {
    @mysqli_set_charset($conn, 'utf8mb4');
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ===== עימוד ===== */
$allowed_limits = [20, 50, 100, 250, 500, 999999]; //999999 = הכל
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if (!in_array($limit, $allowed_limits)) $limit = 50;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/* --- סה"כ פוסטרים --- */
$count_res = $conn->query("
    SELECT COUNT(DISTINCT p.id) as c
    FROM posters p
    JOIN poster_similar ps ON ps.poster_id = p.id
");
$total_rows = ($count_res && $row = $count_res->fetch_assoc()) ? (int)$row['c'] : 0;
$total_pages = ($limit >= 999999) ? 1 : ceil($total_rows / $limit);

/* --- שליפת פוסטרים --- */
$sql = "
    SELECT DISTINCT p.id, p.title_he, p.title_en, p.year, p.image_url, p.imdb_id, p.imdb_rating
    FROM posters p
    JOIN poster_similar ps ON ps.poster_id = p.id
    ORDER BY p.title_en ASC
    LIMIT $limit OFFSET $offset
";
$res = $conn->query($sql);
$base_posters = [];
while ($row = $res->fetch_assoc()) $base_posters[] = $row;

/* --- שליפת דומים --- */
function fetch_similars(mysqli $conn, int $poster_id): array {
    $rows = [];
    $sql = "
        SELECT p.id, p.title_he, p.title_en, p.year, p.image_url, p.imdb_id, p.imdb_rating
        FROM poster_similar ps
        JOIN posters p ON p.id = ps.similar_id
        WHERE ps.poster_id = ?
        ORDER BY p.year DESC, p.title_en ASC
    ";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $poster_id);
    $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_assoc()) $rows[] = $x;
    $st->close();
    return $rows;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<title>כל הסרטים הדומים</title>
<style>
/* ===== CSS שלך ===== */
body {
  background:#0a0a0a !important;
  color:#f1f5f9;
  font-family:Arial, sans-serif;
  margin:0;
}
.container {
  max-width:1400px;
  margin:0 auto;
  padding:20px;
}
.section {
  background:#111827;
  border:1px solid #1f2937;
  border-radius:12px;
  margin-bottom:20px;
  padding:16px;
}
.section-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:12px;
}
.section-header h2 {
  margin:0;
  font-size:20px;
  color:#f8fafc;
}
.section-header h2 span {
  font-size:20px; 
  color:#f8fafc; 
  margin-right:6px;
}
.meta { color:#9ca3af; font-size:13px; }
.grid {
  display:grid;
  gap:12px;
  grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
}
.card {
  background:#0b1220;
  border:1px solid #1f2937;
  border-radius:8px;
  overflow:hidden;
  text-align:center;
}
.card img {
  width:100%;
  aspect-ratio:2/3;
  object-fit:cover;
  display:block;
}
.card .body { padding:6px; }
.card .name { font-weight:bold; font-size:14px; color:#f8fafc; }
.card .muted { color:#9ca3af; font-size:12px; }
.section-content {
  display:grid;
  grid-template-columns: 300px 1fr;
  gap:16px;
  align-items:start;
}
.main-poster img {
  width:250px !important;
  height:350px !important;
  object-fit:cover;
  display:block;
  margin:0 auto;
  border:2px solid #60a5fa;
  border-radius:8px;
}
.sim-grid {
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap:12px;
}
.sim-grid .card img {
  width:150px !important;
  height:225px !important;
  object-fit:cover;
  display:block;
  margin:0 auto;
}
.rating { color:#ffffff; font-weight:bold; }
.rating a { color:#ffffff !important; text-decoration:none; }
.rating a:hover { color:#2d89ef !important; }

/* ===== עימוד ===== */
.pagination { text-align:center; margin:20px 0; }
.pagination a {
  margin:0 3px; padding:6px 12px; border:1px solid #1f2937;
  background:#111827; color:#fff; text-decoration:none; border-radius:4px; color: white !important;
}
.pagination a.active { background:#2563eb; }
.pagination a:hover { background:#1e40af; }
.limit-buttons { text-align:center; margin-bottom:20px; }
.limit-buttons a {
  margin:0 5px; padding:6px 12px; border:1px solid #1f2937;
  background:#111827; color:#fff; text-decoration:none; border-radius:4px; color: white !important
}
.limit-buttons a.active { background:#2563eb;  color: white !important}
.limit-buttons a:hover { background:#1e40af;  color: white !important}

/* ===== CSS שהשארת קודם ===== */
html { box-sizing: border-box; }
*, *:before, *:after { box-sizing: inherit; }
.w3-bar { width: 100%; overflow: hidden; }
.w3-bar .w3-bar-item { padding: 8px 16px; float: left; width: auto; border: none; display: block; outline: 0; }
.w3-bar .w3-button { color: white !important; white-space: normal; }
.w3-bar:before, .w3-bar:after { content: ""; display: table; clear: both; }
.w3-padding { padding: 8px 16px !important; }
.w3-button {
  border: none; display: inline-block; padding: 8px 16px;
  vertical-align: middle; overflow: hidden; text-decoration: none;
  color: inherit; text-align: center; cursor: pointer; white-space: nowrap;
}
.w3-black, .w3-hover-black:hover { color: #fff !important; background-color: white; }
.w3-white, .w3-hover-white:hover { color: #000 !important; background-color: #fff !important; }
.white { color: #f1f1f1 !important; }
.w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover {
  color:#000!important;background-color:#f1f1f1!important;
}
.logo {
  filter: saturate(500%) contrast(800%) brightness(500%)
          invert(100%) sepia(50%) hue-rotate(120deg);
}
</style>
</head>
<body>
<div class="container">
  <h1>🎬 כל הסרטים הדומים</h1>

  <!-- כפתורי בחירת כמות -->
  <div class="limit-buttons">
    <?php foreach ($allowed_limits as $opt): ?>
      <a href="?limit=<?= $opt ?>&page=1"
         class="<?= ($opt==$limit ? 'active' : '') ?>">
        <?= ($opt==999999 ? 'הכל' : $opt) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($base_posters)): ?>
    <div class="section"><div>❌ לא נמצאו פוסטרים עם דומים.</div></div>
  <?php endif; ?>

  <?php foreach ($base_posters as $bp):
      $sim = fetch_similars($conn, (int)$bp['id']);
      if (empty($sim)) continue;
  ?>
    <div class="section">
      <div class="section-header">
        <h2>
          <?= h($bp['title_he'] ?: $bp['title_en']) ?>
          <?php if (!empty($bp['title_en']) && !empty($bp['title_he'])): ?>
            <span>/ <?= h($bp['title_en']) ?></span>
          <?php endif; ?>
        </h2>
        <div class="meta">דומים: <?= count($sim) ?></div>
      </div>

      <div class="section-content">
        <!-- פוסטר ראשי -->
        <div class="card main main-poster">
          <a href="poster.php?id=<?= (int)$bp['id'] ?>">
            <img src="<?= h($bp['image_url'] ?: 'images/no-poster.png') ?>" alt="">
          </a>
          <div class="body">
            <div class="name"><?= h($bp['title_he'] ?: $bp['title_en']) ?></div>
            <div class="muted">
              <?= h($bp['title_en'] ?: $bp['title_he']) ?>
              <?php if (!empty($bp['year'])): ?> · <?= h($bp['year']) ?><?php endif; ?>
              · דומים: <?= count($sim) ?>
            </div>
            <?php if (!empty($bp['imdb_id']) && !empty($bp['imdb_rating'])): ?>
              <div class="rating">
                <a href="https://www.imdb.com/title/<?= h($bp['imdb_id']) ?>/" target="_blank">
                  ⭐ <?= h($bp['imdb_rating']) ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- הדומים -->
        <div class="sim-grid">
          <?php foreach ($sim as $row): ?>
            <div class="card">
              <a href="poster.php?id=<?= (int)$row['id'] ?>">
                <img src="<?= h($row['image_url'] ?: 'images/no-poster.png') ?>" alt="">
                <div class="body">
                  <div class="name"><?= h($row['title_he'] ?: $row['title_en']) ?></div>
                  <div class="muted">
                    <?= h($row['title_en'] ?: $row['title_he']) ?>
                    <?php if (!empty($row['year'])): ?> · <?= h($row['year']) ?><?php endif; ?>
                  </div>
                  <?php if (!empty($row['imdb_id']) && !empty($row['imdb_rating'])): ?>
                    <div class="rating">
                      <a href="https://www.imdb.com/title/<?= h($row['imdb_id']) ?>/" target="_blank">
                        ⭐ <?= h($row['imdb_rating']) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- עימוד -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=1&limit=<?= $limit ?>">« ראשון</a>
        <a href="?page=<?= $page-1 ?>&limit=<?= $limit ?>">‹ קודם</a>
      <?php endif; ?>

      <?php
        $max_links = 10;
        $start = max(1, $page - floor($max_links/2));
        $end = min($total_pages, $start + $max_links - 1);
        if ($end - $start < $max_links) $start = max(1, $end - $max_links + 1);
      ?>
      <?php if ($start > 1): ?><span>…</span><?php endif; ?>
      <?php for ($i=$start; $i <= $end; $i++): ?>
        <a href="?page=<?= $i ?>&limit=<?= $limit ?>" class="<?= ($i==$page ? 'active' : '') ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
      <?php if ($end < $total_pages): ?><span>…</span><?php endif; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>&limit=<?= $limit ?>">הבא ›</a>
        <a href="?page=<?= $total_pages ?>&limit=<?= $limit ?>">אחרון »</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php include 'footer.php'; ?>
