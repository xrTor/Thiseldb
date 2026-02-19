<?php
include 'header.php';
require_once 'server.php';
// --- הכללת watch_totals.php נשארת בחוץ וצריכה להיות מטופלת בנפרד אם נדרש ---

/* ===== קבועי נתיב ===== */
if (!defined('BASE_PATH')) {
  define('BASE_PATH', '/Thiseldb/'); // עדכן אם שונה אצלך
}
if (!defined('IMAGE_BASE_PATH')) {
  define('IMAGE_BASE_PATH', 'images/types/');
}

/* ===== sitemap structure =====
   'חיפוש מתקדם' = flags + poster types + networks
*/
$sitemap = [
  'ראשי' => [
    '🪁 עמוד ראשי'                 => 'index.php',
    '🔎 חיפוש מתקדם'               => 'home.php',
    '🎉 אודות'                     => 'about.php',
    '📩 צור קשר'                   => 'contact.php',
    '🎲 סרט רנדומלי'               => 'random.php',
    '🎞️ סרט חדש'                  => 'new-movie-imdb.php',
    '📁 אוספים'                    => 'collections.php',
    '🌌 ציר זמן'                   => 'universe.php',
    '🎯 זרקור'                     => 'spotlight.php',
    '🏆 TOP 10'                    => 'top.php',
    '🧪 מסוף'                      => 'full-info.php',
    '🎞️ קישורים לפי סרטים דומים' => 'similar_all.php',
    '📈 סטטיסטיקה'                => 'stats.php',
    '💾 ייצוא לCSV'               => 'export.php',
    'ㅤ'                            => '',
    '➕ הוסף פוסטר חדש'           => 'add.php',
    '⚡הוספה אוטומטית'             => 'auto-add.php',
    '🛠️ Toolbox - פאנל ניהול'     => 'toolbox.php',
  ],

  'סטטיסטיקה' => [
    'קשרים לפי סוג (IMDb Connections):' => '',
    'Followed by'   => 'connections.php?label=Followed+by',
    'Follows'       => 'connections.php?label=Follows',
    'Version of'    => 'connections.php?label=Version+of',
    'Spin-off'      => 'connections.php?label=Spin-off',
    'Remade as'     => 'connections.php?label=Remade+as',
    'Remake of'     => 'connections.php?label=Remake+of',
    'Spin-off from' => 'connections.php?label=Spin-off+from',

    
    // --- הוספת מפתח חדש עבור watch_totals.php ---
    '__watch_totals__' => 'watch_totals.php', // נטען את הקובץ במקום קישור
  ],

  'חיפוש מתקדם' => [
    '__flags__'        => 'links_flags.php',   // דגלים + פילטרי שפה/מדינה (כבר קיים אצלך)
    '__networks__'     => 'networks-full.php', // רשימת רשתות/סטרימינג
  ],

  'מסוף' => [
    '📚 כל הערכים באתר'                                        => 'full-info.php',
    '📚 ערכים מתורגמים — עברית בלבד'                           => 'full-info-he.php',
    '📚 כל הערכים באתר בגרסא טקסטואלית'                        => 'full-info-text.php',
    '📚 ערכים מתורגמים — עברית בלבד בגרסא טקסטואלית'          => 'full-info-text-he.php',
  ],

  'Toolbox - פאנל ניהול' => [
    '🎬 ניהול פוסטרים'           => 'manage_posters.php',
    '🛠️ פוסטרים כפולים'         => 'cleanup_duplicates.php',
    '📩 ניהול פניות'             => 'manage_contacts.php',
    '🚨 ניהול דיווחים'           => 'manage_reports.php',
    '▶️ ניהול טריילרים'          => 'manage_trailers.php',
    "🎭 ניהול ז'אנרים"          => 'manage_genres.php',
    '🏷️ ניהול תגיות'            => 'manage_user_tag.php',
    "✍️ ניהול שמות ז'אנרים"     => 'manage_name_genres.php',
    '✍️ ניהול שמות תגיות'        => 'manage_name_user_tag.php',
    '✍️ ניהול שמות שפות'        => 'manage_name_language.php',
    '✍️ ניהול שמות מדינות'      => 'manage_name_country.php',
    '🌐 ניהול שפות (דגלים)'     => 'manage_languages.php',
    '📋 ניהול סוגים'            => 'manage_types.php',
    '🔗 שיוך סוגים'             => 'manage_type_admin.php',
    '🔤 ניהול כותרות'           => 'manage_titles.php',
    '❓ פוסטרים חסרים'          => 'manage_missing.php',
    '📝 ניהול תקצירים'          => 'manage_plots.php',
    '🔄 סנכרון תמונות פוסטרים'          => 'manage_sync.php',
    '🔄סנכרון תמונות רשתות'          => 'manage_sync_network_logos.php',

  ],

  'אוספים' => [
    '➕ יצירת אוסף חדש' => 'create_collection.php',
    '📜 מדריך BBCode'   => 'bbcode_guide.php',
    '📚 רשימת אוספים עם פוסטרים'   => 'collections_miniview.php',
  ],

  'קישורים כלליים' => [
    '🔢 סטטיסטיקות קישורים' => 'connections_stats.php',
    '📝 פוסטרים עם תגית'     => 'user_tags.php',
    '💬 צ\'אט'               => 'chat.php',
  ],
];

/**
 * render_group:
 * - פריטים רגילים => קישור
 * - מחרוזת ריקה => טקסט בלבד
 * - __flags__ => flags + poster types grid
 * - __networks__ => include networks-full.php
 * - __watch_totals__ => include watch_totals.php
 */
function render_group($title, $items) {
  echo '<section class="block" data-open="1">';
  echo '  <h2 class="blk-h" tabindex="0">';
  echo '    <button class="toggle" type="button">סגור</button>';
  echo '    <span class="ttl">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</span>';
  echo '  </h2>';
  echo '  <ul class="links open">';

  global $conn; // גישה לאובייקט החיבור לבסיס הנתונים

  foreach ($items as $label => $file) {
    $display_label = htmlspecialchars(str_replace(['**', '*'], '', $label), ENT_QUOTES, 'UTF-8');
    $is_bold = str_contains($label, '**');

    // --- WATCH TOTALS ---
    if ($label === '__watch_totals__') {
      echo '<li>';
      echo '<div class="watch-totals-map-block">';
      
      // הכללה ישירה של הקובץ (כפי שזה עובד ב-stats.php)
      if (file_exists($file)) {
         include $file; 
      } else {
         echo '<div class="note note-warn">⚠ שגיאה: קובץ '.htmlspecialchars($file, ENT_QUOTES, 'UTF-8').' לא נמצא.</div>';
      }
      echo '</div>';
      echo '</li>';
      continue;
    }
    
    // --- FLAGS + סוגי תוכן ---
    if ($label === '__flags__') {
      echo '<li>';
      // ... (לוגיקה קיימת)
      if (file_exists($file)) {
        include $file; // כולל את links_flags.php עם ה-onclick
      } else {
        echo '<div class="note note-warn">⚠ הקובץ '.htmlspecialchars($file, ENT_QUOTES, 'UTF-8').' לא נמצא.</div>';
      }

      // גריד סוגי התוכן (poster_types)
      $sql = "
        SELECT pt.id, pt.label_he, pt.image
        FROM poster_types pt
        LEFT JOIN posters p ON p.type_id = pt.id
        GROUP BY pt.code
        ORDER BY COUNT(p.id) DESC
      ";
      $res = $conn->query($sql);
      if ($res && $res->num_rows > 0) {
        echo '<div class="poster-type-grid">';
        while ($row = $res->fetch_assoc()) {
          $type_id  = (int)$row['id'];
          $label_he = htmlspecialchars($row['label_he'] ?? '', ENT_QUOTES, 'UTF-8');
          $image    = trim((string)($row['image'] ?? ''));

          if ($image !== '' && preg_match('#^https?://#i', $image)) {
            $imgUrl = $image; 
          } elseif ($image !== '') {
            $imgUrl = rtrim(IMAGE_BASE_PATH, '/').'/'.ltrim($image, '/');
          } else {
            $imgUrl = '';
          }

          $href = BASE_PATH . 'home.php?type%5B%5D=' . $type_id;

          echo '<a class="poster-type-item" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">';
          if ($imgUrl !== '') {
            echo '<img src="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" alt="" title="'.$label_he.'" onerror="this.style.display=\'none\'">';
          }
          echo '<span>'.$label_he.'</span>';
          echo '</a>';
        }
        echo '</div>';
      }

      echo '</li>';
      continue;
    }

    // --- NETWORKS / רשתות ---
    if ($label === '__networks__') {
      echo '<li>';
      global $conn;

      if (file_exists($file)) {
        include $file; // networks-full.php
      } else {
        echo '<div class="note note-warn">⚠ הקובץ '.htmlspecialchars($file, ENT_QUOTES, 'UTF-8').' לא נמצא.</div>';
      }

      echo '</li>';
      continue;
    }

    // --- טקסט בלבד ---
    if ($file === '') {
      echo '<li><span>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span></li>';
      continue;
    }
    
    // --- קישור רגיל ---
    $href = BASE_PATH . ltrim($file, '/');
    
    // בניית תוכן הקישור כולל ה-<strong> אם הוגדר
    $link_content = $display_label;
    if ($is_bold) {
        $link_content = '<strong>'.$link_content.'</strong>';
    }
    
    echo '<li><a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.$link_content.'</a></li>';
  }

  echo '  </ul>';
  echo '</section>';
}
?>

<style>
/* ===== בסיס עמוד site-map (לפני הרשתות) ===== */

.site-map {
  direction: rtl;
  text-align: right;
  max-width: 1280px;
  margin: 24px auto;
  padding: 0 12px;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.site-map h1 {
  margin: 0 0 16px;
  text-align: right;
  font-size: 24px;
  font-weight: 700;
  line-height: 1.3;
}

.site-map .toolbar {
  text-align: left;
  margin: 0 0 12px;
}
.site-map .toolbar .btn {
  margin-inline-start: 8px;
  padding: 6px 12px;
  border: 1px solid #cfd8ea;
  border-radius: 10px;
  background: #f1f6ff;
  cursor: pointer;
  font-size: 18px;
  line-height: 1.3;
}
.site-map .toolbar .btn:hover {
  background: #e7f1ff;
}

.site-map .block {
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 8px;
  margin: 14px 0;
  overflow: hidden;
}

.site-map .blk-h {
  margin: 0;
  padding: 10px 12px;
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 10px;
  background: #f7f7f7;
  border-bottom: 1px solid #e5e5e5;
}
.site-map .blk-h .ttl {
  font-weight: 700;
  font-size: 18px;
  line-height: 1.3;
}
.site-map .blk-h .toggle {
  padding: 4px 10px;
  border: 1px solid #cfd8ea;
  border-radius: 10px;
  background: #f1f6ff;
  cursor: pointer;
  font-size: 13px;
  line-height: 1.3;
}
.site-map .blk-h .toggle:hover {
  background: #e7f1ff;
}

/* גוף הבלוק */
.site-map .links {
  list-style: none;
  margin: 0;
  padding: 10px 16px;
  display: block;
}
.site-map .links li {
  margin: 6px 0;
}
/* הסרת הפאדינג והמאפיינים של ה-<li> סביב הבלוק watch totals */
.site-map .links .watch-totals-map-block {
  margin: 0 -16px; /* קיזוז הפאדינג של ה-<ul> */
  padding: 0;
}

.site-map .links a {
  color: #0b5ed7;
  text-decoration: none;
  font-weight: 700;
  font-size: 18px; /* כאן קובעים גודל טקסט */
  line-height: 1.4;
}
.site-map .links a:hover {
  text-decoration: underline;
}

/* מצב סגור */
.site-map .links.closed {
  display: none;
}

/* הערות / התראות */
.note {
  color: #444;
  background: #fafafa;
  border: 1px solid #eee;
  border-radius: 10px;
  padding: 10px;
  margin: 8px 0;
  font-size: 13px;
  line-height: 1.4;
}
.note-warn {
  color: #b00;
  background: #fff5f5;
  border-color: #f1c0c0;
}

/* ===== גריד סוגי תוכן (poster_types) מתחת לדגלים ===== */

.poster-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px,1fr));
  gap: 12px;
  margin-top: 14px;
  text-align: center;
}
.poster-type-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  border-radius: 12px;
  padding: 10px;
  transition: background .15s, transform .15s;
  background: transparent;
  border: 0;
}
.poster-type-item:hover {
  background: #eef5ff;
  transform: scale(1.03);
}
.poster-type-item img {
  max-width: 72px;
  max-height: 72px;
  object-fit: contain;
  margin-bottom: 6px;
}
.poster-type-item span {
  color: #222;
  font-size: 13px;
  line-height: 1.3;
  font-weight: 700;
  text-align: center;
}
</style>

<div class="site-map">
  <h1>📚 מפת אתר גרסת משתמש בלבד</h1>

  <div class="toolbar">
    <button class="btn" id="btnCloseAll" type="button">הסתר הכל</button>
    <button class="btn" id="btnOpenAll" type="button">הצג הכל</button>
  </div>

  <?php
  foreach ($sitemap as $group => $items) {
    render_group($group, $items);
  }
  ?>
</div>
<style>
/* ===== OVERRIDE לרשתות (קומפקט, קריא יותר) ===== */

.site-map .links .networks-index h1 {
  font-size: 18px !important;
  line-height: 1.3 !important;
  margin: 8px 0 10px !important;
  text-align: right !important;
  font-weight: 700 !important;
  color: #222 !important;
}

/* בלוק "עם לוגו" / "בלי לוגו" */
.site-map .links .section {
  margin: 12px 0 18px !important;
}

.site-map .links .section-head {
  display: flex !important;
  align-items: center !important;
  gap: 6px !important;
  margin: 4px 0 8px !important;
}
.site-map .links .section-head h2 {
  margin: 0 !important;
  font-size: 13px !important;      /* היה 12px */
  line-height: 1.3 !important;
  font-weight: 700 !important;
  color: #111 !important;
}
.site-map .links .section-count {
  background: #f0f0f0 !important;
  border-radius: 999px !important;
  padding: 0 6px !important;
  font-size: 11px !important;      /* היה 10px */
  line-height: 1.4 !important;
  color: #555 !important;
}

/* הגריד של הרשתות */
.site-map .links .net-grid {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(125px,1fr)) !important; /* היה 110px */
  gap: 6px !important;
  padding: 0 !important;
  margin: 0 !important;
}

/* כרטיס רשת */
.site-map .links .net-card {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  gap: 6px !important;             /* היה 4px */
  padding: 8px !important;         /* היה 6px */
  border: 1px solid #ccc !important;
  border-radius: 8px !important;   /* היה 6px */
  background: #fff !important;
  text-decoration: none !important;
  color: #222 !important;
  font-size: 12px !important;      /* היה 11px */
  line-height: 1.35 !important;    /* היה 1.3 */
  min-height: 0 !important;
  box-shadow: none !important;
}
.site-map .links .net-card:hover {
  background: #f8f8f8 !important;
}

/* לוגו בתוך כרטיס */
.site-map .links .net-card img {
  max-width: 100px !important;     /* היה 90px */
  max-height: 36px !important;     /* היה 32px */
  width: auto !important;
  height: auto !important;
  object-fit: contain !important;
  display: block !important;
  margin: 0 !important;
  padding: 0 !important;
}

/* מצב בלי לוגו */
.site-map .links .net-card.no-logo {
  align-items: stretch !important;
}
.site-map .links .no-logo-box {
  width: 100% !important;
  height: 36px !important;         /* היה 32px */
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: #fafafa !important;
  border: 1px dashed #bbb !important;
  border-radius: 8px !important;   /* היה 6px */
  color: #888 !important;
  font-size: 12px !important;      /* היה 11px */
  line-height: 1.35 !important;
  text-align: center !important;
  padding: 0 6px !important;       /* היה 0 4px */
  box-sizing: border-box !important;
}

/* שם רשת */
.site-map .links .net-name {
  font-weight: 600 !important;
  text-align: center !important;
  font-size: 12px !important;      /* היה 11px */
  line-height: 1.35 !important;
  color: #222 !important;
  margin: 0 !important;
  padding: 0 !important;
  word-break: break-word !important;
  max-width: 100% !important;
}
.site-map .links .net-name .mini {
  font-weight: 400 !important;
  color: #666 !important;
  font-size: 11px !important;      /* היה 10px */
  line-height: 1.35 !important;
}
</style>

<script>
// ===============================================
// תיקון JavaScript: הגדרת פונקציה selectFlag מזויפת
// משתמשת בפרמטר הנכון: flag_code
// ===============================================
if (!window.selectFlag) {
  function selectFlag(code) {
    // הנתיב לחיפוש עם הפרמטר הנכון: flag_code=CODE
    const baseURL = '<?= BASE_PATH ?>home.php';
    const langParam = 'flag_code=' + encodeURIComponent(code);
    
    // הפניה ישירה
    window.location.href = baseURL + '?' + langParam;
    
    // מונע פעולה נוספת (כפי שמוגדר ב-links_flags.php: return false)
    return false;
  }
}
// ===============================================


(function(){
  function setOpen(section, open){
    const ul  = section.querySelector('.links');
    const btn = section.querySelector('.toggle');
    if (!ul || !btn) return;
    if (open) {
      ul.classList.remove('closed');
      ul.classList.add('open');
      btn.textContent = 'סגור';
    } else {
      ul.classList.remove('open');
      ul.classList.add('closed');
      btn.textContent = 'פתח';
    }
  }

  // כברירת מחדל: הכל פתוח
  document.querySelectorAll('.site-map .block').forEach(sec => setOpen(sec, true));

  // פתיחה/סגירה בלחיצה
  document.addEventListener('click', function(e){
    if (e.target.classList.contains('toggle')) {
      const sec = e.target.closest('.block');
      const ul  = sec.querySelector('.links');
      setOpen(sec, ul.classList.contains('closed'));
    }
  });

  // כפתורי "הצג הכל / הסתר הכל"
  document.getElementById('btnOpenAll').addEventListener('click', function(){
    document.querySelectorAll('.site-map .block').forEach(sec => setOpen(sec, true));
  });
  document.getElementById('btnCloseAll').addEventListener('click', function(){
    document.querySelectorAll('.site-map .block').forEach(sec => setOpen(sec, false));
  });
})();
</script>

<?php include 'footer.php'; ?>