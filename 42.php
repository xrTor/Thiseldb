<?php
/****************************************************
 * sitemap.php — מפת אתר אוטומטית (UI + XML)
 * - סריקה אוטומטית של קבצי PHP בשורש (אופציונלית: רקורסיבי)
 * - שיוך לקבוצות לפי חוקים (Regex)
 * - חיפוש בצד לקוח
 * - מצב XML: ?format=xml
 ****************************************************/

mb_internal_encoding('UTF-8');

/* ===== בסיס קישורים (התאם לסביבה) ===== */
if (!defined('BASE_PATH')) {
  // למשל: '/' על שרת ייצור, או '/Thiseldb/' בלוקאל
  define('BASE_PATH', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/');
}

/* ===== הגדרות סריקה ===== */
$SCAN_ROOT         = __DIR__; // תקיית השורש לסריקה
$RECURSIVE         = false;   // אם true — סורק תתי-תיקיות
$INCLUDE_SELF      = true;    // לכלול sitemap.php עצמו
$INCLUDE_NON_PAGES = true;    // לכלול גם קבצי include/עזר
$EXTENSIONS        = ['php']; // אילו סיומות לכלול

/* ===== החרגות ===== */
$EXCLUDE_FILES = [
  // הכנס כאן קבצים אם רוצים להסתירם
  // 'conn_min.php',
];
$EXCLUDE_DIRS  = [
  'vendor', '.git', '.github', 'node_modules', 'uploads', 'images', 'assets', 'dist', 'build',
];

/* ===== שיוך לקבוצות (Regex => קבוצה) ===== */
$GROUP_RULES = [
  'ראשי' => [
    '/^(index|home|about|contact|bar)$/i',
  ],
  'פוסטרים' => [
    '/^(poster|add|add_new|auto-add|edit|delete|delete_trailer)$/i',
    '/^(add_to_collection|add_to_collection_batch|remove_from_collection|remove_from_collection_batch)$/i',
    '/^(poster_trailers|fetch_posters)$/i',
  ],
  'אוספים' => [
    '/^(collections|collection|create_collection|collections_search|edit_collection|edit_collection_new|collection_csv|collection_upload_csv_api)$/i',
  ],
  'קישורים וקשרים' => [
    '/^(connections|connections_stats|similar_all)$/i',
    '/^(links_.*)$/i',
  ],
  'ניהול נתונים' => [
    '/^manage_.*$/i',
    '/^(manage_types|manage_type_admin)$/i',
  ],
  'נתונים מיוחדים' => [
    '/^(imdb|tmdb|tvdb|rt_mc|missing|stats|dump_table|random|rate)$/i',
    '/^(RapidAPI|get_omdb)$/i',
  ],
  'תוספים וכלים' => [
    '/^(bbcode|bbcode_editor|bbcode_guide|preview_bbcode)$/i',
    '/^(export|load_more|pagination)$/i',
  ],
  'עמודי ישות' => [
    '/^(actor|genre|network|country|language)$/i',
  ],
  'מערכת' => [
    '/^(header|footer|server|init|functions|alias|nav|index)$/i',
    '/^(imdb\.class)$/i',
    '/^(conn_min)$/i',
  ],
  'שונות' => [
    '/.*/', // ברירת מחדל לכל מה שלא תפסו החוקים שמעל
  ],
];

/* ===== מצב XML? ===== */
if (isset($_GET['format']) && strtolower($_GET['format']) === 'xml') {
  header("Content-Type: application/xml; charset=UTF-8");
  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  foreach (scanFiles($SCAN_ROOT, $RECURSIVE, $EXTENSIONS, $EXCLUDE_DIRS, $EXCLUDE_FILES) as $f) {
    if (!$INCLUDE_SELF && basename($f) === basename(__FILE__)) continue;
    $rel = ltrim(str_replace($SCAN_ROOT, '', $f), DIRECTORY_SEPARATOR);
    // רק קבצי PHP בפועל
    if (!preg_match('/\.php$/i', $rel)) continue;
    if (!$INCLUDE_NON_PAGES && isHelper($rel)) continue;

    $loc = BASE_PATH . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    $loc = htmlspecialchars($loc, ENT_QUOTES, 'UTF-8');
    $lastmod = gmdate('Y-m-d\TH:i:s\Z', @filemtime($f) ?: time());
    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "  </url>\n";
  }

  echo "</urlset>\n";
  exit;
}

/* ===== סריקה ===== */
$files = scanFiles($SCAN_ROOT, $RECURSIVE, $EXTENSIONS, $EXCLUDE_DIRS, $EXCLUDE_FILES);

/* ===== שיוך לקבוצות ===== */
$grouped = [];
foreach ($files as $path) {
  $base = basename($path);
  if (!$INCLUDE_SELF && $base === basename(__FILE__)) continue;
  if (!preg_match('/\.php$/i', $base)) continue;
  if (!$INCLUDE_NON_PAGES && isHelper($base)) continue;

  $stem = preg_replace('/\.php$/i', '', $base);
  $group = matchGroup($stem, $GROUP_RULES);
  if (!isset($grouped[$group])) $grouped[$group] = [];
  $grouped[$group][] = [
    'label' => prettyLabel($stem),
    'href'  => BASE_PATH . relPath($path, $SCAN_ROOT),
  ];
}

/* ===== מיון אלפביתי בתוך כל קבוצה ===== */
ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($grouped as $g => &$items) {
  usort($items, fn($a, $b) => strcasecmp($a['label'], $b['label']));
}
unset($items);

/* ===== עמוד HTML ===== */
?>
<?php include 'header.php'; ?>
<style>
  /* ממוסגר – לא נוגע לשאר האתר */
  .site-map { direction: rtl; text-align: right; max-width: 1000px; margin: 24px auto; padding: 0 12px; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans Hebrew", "Rubik", sans-serif; }
  .site-map h1 { margin: 0 0 16px; font-size: 26px; }
  .site-map .sub { color: #666; margin-bottom: 14px; }
  .site-map .toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 10px 0 14px; }
  .site-map .btn { padding: 6px 12px; border: 1px solid #cfd8ea; border-radius: 10px; background: #f1f6ff; cursor: pointer; }
  .site-map .btn:hover { background: #e7f1ff; }
  .site-map .input { padding: 7px 10px; border: 1px solid #cfd8ea; border-radius: 10px; min-width: 220px; }
  .site-map .block { background: #fff; border: 1px solid #ddd; border-radius: 10px; margin: 12px 0; overflow: hidden; }
  .site-map .blk-h { margin: 0; padding: 10px 12px; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; background: #f7f7f7; border-bottom: 1px solid #e9e9e9; }
  .site-map .blk-h .ttl { font-weight: 700; }
  .site-map .blk-h .count { color: #666; font-size: 13px; }
  .site-map .blk-h .toggle { padding: 4px 10px; border: 1px solid #cfd8ea; border-radius: 10px; background: #f1f6ff; cursor: pointer; }
  .site-map .blk-h .toggle:hover { background: #e7f1ff; }
  .site-map .links { list-style: none; margin: 0; padding: 10px 16px; display: block; }
  .site-map .links li { margin: 6px 0; }
  .site-map .links a { color: #0b5ed7; text-decoration: none; font-weight: 700; }
  .site-map .links a:hover { text-decoration: underline; }
  .site-map .links.closed { display: none; }
  .site-map .muted { color: #888; font-size: 13px; }
</style>

<div class="site-map" id="app">
  <h1>📚 מפת אתר אוטומטית</h1>
  <div class="sub">
    קישורים נוצרים אוטומטית מקבצי PHP בתיקייה.
    <span class="muted">קובץ XML: <a href="?format=xml">?format=xml</a></span>
  </div>

  <div class="toolbar">
    <button class="btn" id="btnOpenAll" type="button">הצג הכל</button>
    <button class="btn" id="btnCloseAll" type="button">הסתר הכל</button>
    <input class="input" id="q" type="search" placeholder="חפש קובץ / קבוצה..." />
  </div>

  <?php foreach ($grouped as $group => $items): ?>
    <section class="block" data-open="1" data-group="<?php echo h($group); ?>">
      <h2 class="blk-h" tabindex="0">
        <button class="toggle" type="button">סגור</button>
        <span class="ttl"><?php echo h($group); ?></span>
        <span class="count">(<?php echo count($items); ?>)</span>
      </h2>
      <ul class="links open">
        <?php foreach ($items as $it): ?>
          <li>
            <a href="<?php echo h($it['href']); ?>"><?php echo h($it['label']); ?></a>
            <span class="muted">— <?php echo h(basename($it['href'])); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</div>

<script>
(function(){
  const $ = (s, d=document)=>d.querySelector(s);
  const $$ = (s, d=document)=>Array.from(d.querySelectorAll(s));

  const setOpen = (section, open) => {
    const ul = section.querySelector('.links');
    const btn = section.querySelector('.toggle');
    if (!ul || !btn) return;
    if (open) { ul.classList.remove('closed'); ul.classList.add('open'); btn.textContent='סגור'; }
    else { ul.classList.remove('open'); ul.classList.add('closed'); btn.textContent='פתח'; }
  };

  $$('.site-map .block').forEach(sec => setOpen(sec, true));

  document.addEventListener('click', e => {
    const t = e.target;
    if (t.classList.contains('toggle')) {
      const sec = t.closest('.block');
      const isClosed = sec.querySelector('.links').classList.contains('closed');
      setOpen(sec, isClosed);
    }
  });

  $('#btnOpenAll').addEventListener('click', ()=> $$('.site-map .block').forEach(sec=>setOpen(sec,true)));
  $('#btnCloseAll').addEventListener('click', ()=> $$('.site-map .block').forEach(sec=>setOpen(sec,false)));

  // חיפוש
  const q = $('#q');
  q.addEventListener('input', function(){
    const term = this.value.trim().toLowerCase();
    $$('.site-map .block').forEach(sec=>{
      const group = (sec.getAttribute('data-group')||'').toLowerCase();
      const items = sec.querySelectorAll('li');
      let any = false;
      items.forEach(li=>{
        const text = li.textContent.toLowerCase();
        const hit = !term || text.includes(term) || group.includes(term);
        li.style.display = hit ? '' : 'none';
        if (hit) any = true;
      });
      sec.style.display = any ? '' : 'none';
      if (any && term) setOpen(sec, true);
    });
  });
})();
</script>

<?php include 'footer.php'; ?>

<?php
/***********************
 * פונקציות עזר (PHP)
 ***********************/
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function relPath(string $path, string $root): string {
  $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
  return str_replace(DIRECTORY_SEPARATOR, '/', $rel);
}

function prettyLabel(string $stem): string {
  // הסרת קידומות/מיליות נפוצות והמרה לשם קריא
  $label = preg_replace('/[_\-]+/', ' ', $stem);
  $label = preg_replace('/\b(php|new|old)\b/i', '', $label);
  $label = trim(preg_replace('/\s+/', ' ', $label));
  // אות גדולה בתחילת כל מילה באנגלית; עברית תישאר כפי שהיא
  $parts = preg_split('/(\s+)/u', $label, -1, PREG_SPLIT_DELIM_CAPTURE);
  foreach ($parts as &$p) {
    if (preg_match('/[A-Za-z]/', $p)) $p = ucwords(strtolower($p));
  }
  return implode('', $parts);
}

function matchGroup(string $stem, array $rules): string {
  foreach ($rules as $group => $patterns) {
    foreach ($patterns as $rx) {
      if (@preg_match($rx, $stem)) {
        if (preg_match($rx, $stem)) return $group;
      }
    }
  }
  return 'שונות';
}

function isHelper(string $file): bool {
  // הגדרה גסה לקבצי include/עזר — ניתן להתאים
  $name = strtolower($file);
  return (bool)preg_match('/^(header|footer|server|init|functions|alias|nav|conn|min|preview_bbcode|imdb\.class)\.php$/', basename($name));
}

/**
 * סריקה של קבצים
 */
function scanFiles(string $root, bool $recursive, array $exts, array $excludeDirs, array $excludeFiles): array {
  $out = [];
  $exts_rx = '/\.(' . implode('|', array_map('preg_quote', $exts)) . ')$/i';

  $iter = $recursive
    ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS))
    : new CallbackFilterIterator(new DirectoryIterator($root), function($file){
        return !$file->isDot();
      });

  foreach ($iter as $f) {
    /** @var SplFileInfo $f */
    $isDir = $f->isDir();
    $path  = $f->getPathname();

    // החרגת תיקיות
    foreach ($excludeDirs as $ex) {
      if (stripos($path, DIRECTORY_SEPARATOR.$ex.DIRECTORY_SEPARATOR)!==false || (basename($path)===$ex && $isDir)) {
        continue 2;
      }
    }
    if ($isDir) continue;

    $base = basename($path);
    if (!preg_match($exts_rx, $base)) continue;

    // החרגת קבצים ספציפיים
    if (in_array($base, $excludeFiles, true)) continue;

    $out[] = $path;
  }
  return $out;
}
