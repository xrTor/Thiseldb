<?php
include 'header.php';
require_once 'server.php';

/* ===== הגדרת בסיס קישורים חד-פעמית (למשל /Thiseldb/) ===== */
if (!defined('BASE_PATH')) {
  // שנה כאן אם הנתיב שונה אצלך (למשל '/thiseldb/' בסביבת לוקאל)
  define('BASE_PATH', '/Thiseldb/');
}

/* ===== בסיס תמונות לסוגי פוסטרים (נוסף בלבד, לא מחליף כלום) ===== */
if (!defined('IMAGE_BASE_PATH')) {
  // נתיב יחסי כמו ב-stats: נשתמש ב-URL יחסי כדי שיעבוד בלי תלות באותיות/תחנות ביניים
  define('IMAGE_BASE_PATH', 'images/types/');
}

/* ===== מפת אתר: קבצי PHP בשורש, ללא דפים שלא קיימים ===== */
$sitemap = [
  'ראשי' => [

    '🪁 עמוד ראשי' => 'index.php'  ,
    '🔎 חיפוש מתקדם'   => 'home.php',
    '🎉 אודות' => 'about.php',
    '📩 צור קשר' => 'contact.php',
'🎲 סרט רנדומלי' => 'random.php',
'🎞️ סרט חדש' => 'new-movie-imdb.php',
'📁 אוספים' => 'collections.php',
'🌌 ציר זמן' => 'universe.php',
'🎯 זרקור' => 'spotlight.php',
'🏆 TOP 10' => 'top.php',
'🧪 מסוף' => 'full-info.php',
'🎞️ סרטים דומים' => 'similar_all.php',
'📈 סטטיסטיקה' => 'stats.php',
'💾 ייצוא לCSV' => 'export.php',
    
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

   '__include__' => 'links_flags.php',
],

  'מסוף' => [
    '📚 כל הערכים באתר'  => 'full-info.php',
    '📚 ערכים מתורגמים — עברית בלבד'  => 'full-info-he.php',
    '📚 כל הערכים באתר בגרסא טקסטואלית'      => 'full-info-text.php',
    '📚 ערכים מתורגמים — עברית בלבד בגרסא טקסטואלית'  => 'full-info-text-he.php',
  ],

  // 'קישורים וקשרים' => [
  //   'קישורים'                 => 'connections.php',
  //   'קישורים לפי סרטים דומים' => 'similar_all.php',
  //   'סטטיסטיקות קישורים'     => 'connections_stats.php',
  //   'ניהול סנכרון'            => 'manage_sync.php',
  // ],

  // 'ניהול נתונים' => [
  //   'ניהול פוסטרים' => 'manage_posters.php',
  //   'ניהול סוגים'   => 'manage_types.php',
  //   'ניהול תגיות'   => 'manage_tags.php',
  //   'ניהול שפות'    => 'manage_languages.php',
  //   'ניהול מדינות'  => 'manage_countries.php',
  //   'ניהול רשתות'   => 'manage_networks.php',
  //   'ניהול שמות'    => 'manage_titles.php',
  //   'ניהול תקצירים' => 'manage_plots.php', // במקום manage_overviews.php
  // ],

  // 'נתונים מיוחדים' => [
  //   'מזהי IMDb'                    => 'imdb.php',
  //   'נתוני TMDb'                   => 'tmdb.php',
  //   'נתוני TVDb'                   => 'tvdb.php',
  //   'Rotten Tomatoes / Metacritic' => 'rt_mc.php',
  //   'חסרים'                        => 'missing.php',
  //   'סטטיסטיקות כלליות'           => 'stats.php',
  // ],

  // 'תוספים וכלים' => [
  //   'עורך BBCode'                 => 'bbcode_editor.php',
  //   'מדריך BBCode'                => 'bbcode_guide.php',
  //   'פרסר BBCode'                 => 'bbcode.php',
  //   'ייצוא נתונים'                => 'export.php',
  //   'מפת אתר צבעונית'             => 'full-info.php',
  //   'מפת אתר טקסטואלית'           => 'full-info-text.php',
  //   'מפת אתר בעברית'              => 'full-info-he.php',
  //   'מפת אתר טקסטואלית (עברית)'  => 'full-info-text-he.php',
  // ],

  // 'עמודי ישות' => [
  //   'שחקן/ת' => 'actor.php',
  //   'מדינה'  => 'country.php',
  // ],

  // 'מערכת' => [
  //   'header.php' => 'header.php',
  //   'footer.php' => 'footer.php',
  //   'server.php' => 'server.php',
  //   'alias.php'  => 'alias.php',
  //   'sitemap.php'=> 'sitemap.php', // הדף הנוכחי
  //   'nav.php'    => 'nav.php',
  // ],
];

/* ===== רנדר בלוק ===== */
function render_group($title, $items) {
  echo '<section class="block" data-open="1">';
  echo '  <h2 class="blk-h" tabindex="0">';
  echo '    <button class="toggle" type="button">סגור</button>';
  echo '    <span class="ttl">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</span>';
  echo '  </h2>';
  echo '  <ul class="links open">';
  foreach ($items as $label => $file) {
  if ($label === '__include__') {
    // תמיכה ב־include: מריץ קובץ ומציג את תוצרי ה־HTML שלו בתוך <li>
    if (file_exists($file)) {
      echo '    <li>';
      include $file;

      // ===== גריד סוגי פוסטרים: תמונה + טקסט (ללא כמויות) =====
      // מופיע ישירות מתחת לדגלים, וכל פריט מקשר ל-home.php?type_id=...
      global $conn; // שימוש בחיבור שכבר קיים בראש הקובץ
      $sql = "
        SELECT pt.id, pt.label_he, pt.image
        FROM poster_types pt
        LEFT JOIN posters p ON p.type_id = pt.id
        GROUP BY pt.id
        ORDER BY COUNT(p.id) DESC
      ";
      $res = $conn->query($sql);
      if ($res && $res->num_rows > 0) {
        echo '<div class="poster-type-grid">';
        while ($row = $res->fetch_assoc()) {
          $type_id  = (int)$row['id'];
          $label_he = htmlspecialchars($row['label_he'] ?? '', ENT_QUOTES, 'UTF-8');
          $image    = trim((string)($row['image'] ?? ''));

          // נבנה URL יחסי כמו ב-stats: images/types/ + שם קובץ (או URL חיצוני כמות שהוא)
          if ($image !== '' && preg_match('#^https?://#i', $image)) {
            $imgUrl = $image; // URL חיצוני
          } elseif ($image !== '') {
            $imgUrl = rtrim(IMAGE_BASE_PATH, '/').'/'.ltrim($image, '/'); // יחסי, ללא BASE_PATH כדי למנוע בעיות אותיות
          } else {
            $imgUrl = '';
          }

          $href = BASE_PATH . 'home.php?type%5B%5D=' . $type_id;

          echo '<a class="poster-type-item" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">';
          if ($imgUrl !== '') {
            // alt="" כדי למנוע טקסט כפול אם יש שגיאת טעינה; onerror מסתיר תמונה שבורה
            echo '<img src="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" alt="" title="'.$label_he.'" onerror="this.style.display=\'none\'">';
          }
          echo '<span>'.$label_he.'</span>';
          echo '</a>';
        }
        echo '</div>';
      }

      echo '    </li>';
    } else {
      echo '    <li><em>⚠ הקובץ '.htmlspecialchars($file, ENT_QUOTES, 'UTF-8').' לא נמצא.</em></li>';
    }
  } elseif (empty($file)) {
    echo '    <li><span>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span></li>';
  } else {
    $href = BASE_PATH . ltrim($file, '/');
    echo '    <li><a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a></li>';
  }
}
  echo '  </ul>';
  echo '</section>';
}
?>

<style>
/* ממוסגר – לא נוגע לשאר האתר */
.site-map { direction: rtl; text-align: right; max-width: 980px; margin: 24px auto; padding: 0 12px; }
.site-map h1 { margin: 0 0 16px; text-align: right; font-size: 24px; }

.site-map .toolbar { text-align: left; margin: 0 0 12px; }
.site-map .toolbar .btn { margin-inline-start: 8px; padding: 6px 12px; border: 1px solid #cfd8ea; border-radius: 10px; background: #f1f6ff; cursor: pointer; }
.site-map .toolbar .btn:hover { background: #e7f1ff; }

.site-map .block { background: #fff; border: 1px solid #ccc; border-radius: 8px; margin: 14px 0; overflow: hidden; }
.site-map .blk-h { margin: 0; padding: 10px 12px; display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 10px; background: #f7f7f7; border-bottom: 1px solid #e5e5e5; }
.site-map .blk-h .ttl { font-weight: 700; }
.site-map .blk-h .toggle { padding: 4px 10px; border: 1px solid #cfd8ea; border-radius: 10px; background: #f1f6ff; cursor: pointer; }
.site-map .blk-h .toggle:hover { background: #e7f1ff; }

.site-map .links { list-style: none; margin: 0; padding: 10px 16px; display: block; }
.site-map .links li { margin: 6px 0; }
.site-map .links a { color: #0b5ed7; text-decoration: none; font-weight: 700; }
.site-map .links a:hover { text-decoration: underline; }

/* מצב סגור */
.site-map .links.closed { display: none; }

/* ===== גריד סוגי פוסטרים (תמונה + טקסט) – נוספה כתוספת בלבד ===== */
.poster-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 12px;
  margin-top: 14px;
  text-align: center;
}
.poster-type-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  /* background: #f9f9f9; */
  /* border: 1px solid #ddd; */
  border-radius: 12px;
  padding: 10px;
  transition: .2s;
}
.poster-type-item:hover { background: #eef5ff; transform: scale(1.03); }
.poster-type-item img { max-width: 72px; max-height: 72px; object-fit: contain; margin-bottom: 6px; }
.poster-type-item span { color: #222; font-size: 13px; font-weight: 700; }
</style>

<div class="site-map">
  <h1>📚 מפת אתר גרסת משתמש בלבד</h1>

  <div class="toolbar">
    <button class="btn" id="btnCloseAll" type="button">הסתר הכל</button>
    <button class="btn" id="btnOpenAll" type="button">הצג הכל</button>
  </div>

  <?php foreach ($sitemap as $group => $items) { render_group($group, $items); } ?>
</div>

<script>
(function(){
  function setOpen(section, open){
    const ul = section.querySelector('.links');
    const btn = section.querySelector('.toggle');
    if (!ul || !btn) return;
    if (open) {
      ul.classList.remove('closed'); ul.classList.add('open');
      btn.textContent = 'סגור';
    } else {
      ul.classList.remove('open'); ul.classList.add('closed');
      btn.textContent = 'פתח';
    }
  }

  // פתוח כברירת מחדל
  document.querySelectorAll('.site-map .block').forEach(sec => setOpen(sec, true));

  // פתיחה/סגירה לכל בלוק
  document.addEventListener('click', function(e){
    const t = e.target;
    if (t.classList.contains('toggle')) {
      const sec = t.closest('.block');
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

<!-- 
📂 קבצים ותיקיות במאגר
שם קובץ / תיקיה	הערה / סוג
images/	תיקיית תמונות 
header.php	קובץ “header” 
ריק.php	קובץ “ריק” (empty) 
LICENSE	רישיון הקוד (GPL-3.0) 
README.md	תיעוד ראשוני של הפרויקט 
RapidAPI.php	קובץ שמטפל ב-API חיצוני 
about.php	עמוד “אודות” 
actor.php	עמוד “שחקן / שחקנית” 
add-מקורי.php	גרסה מקורי של “הוספה” 
add.php	עמוד הוספת פריט 
add_new.php	עמוד הוספת פריט חדש 
add_to_collection.php	הוספה לאוסף־יחיד 
add_to_collection_batch.php	הוספה באצווה לאוסף 
alias.php	טיפול בכינויים או הפניות פנימיות 
auto-add.php	הוספה אוטומטית (לפי מקור חיצוני) 
bar.php	עמוד חיפוש / תפריט בר 
bbcode.css	סגנון BBCode 
bbcode.js	סקריפט BBCode 
bbcode.php	פרסר BBCode / טיפול בטקסטים עם BBCode 
bbcode_editor.php	עורך BBCode לממשק משתמש 
bbcode_guide.php	מדריך לשימוש ב-BBCode 
cleanup_duplicates.php	ניקוי כפילויות (לוגיקה) 
collection.php	עמוד אוסף 
collection_csv.php	ייצוא / טיפול באוסף לקובץ CSV 
collection_upload_csv_api.php	העלאת CSV של אוספים דרך API 
collections.php	רשימת אוספים 
collections_search.php	חיפוש באוספים 
conn_min.php	קובץ חיבור מינימלי / קונפיגורציה חיבור 
connections.php	עמוד קישורים / יחסים בין פריטים 
connections_stats.php	סטטיסטיקות על הקישורים 
contact.php	עמוד “צור קשר” 
country.php	עמוד מדינה (entity) 
create_collection.php	יצירת אוסף חדש 
delete.php	מחיקה של פריט 
delete_trailer.php	מחיקה של טריילר (קובץ) 
dump_table.php	ייצוא / “דאמפ” של טבלה במסד הנתונים 
edit.php	עמוד עריכה לפריטים 
edit_collection.php	עמוד עריכת אוסף 
edit_collection_new.php	עריכת אוסף חדש / גרסה שונה של עריכה 
export.php	ייצוא מידע במצבים שונים 
fetch_posters.php	שליפת פוסטרים (API / AJAX) 
flags.php	טיפול בדגלים / סימונים 
footer.php	קובץ “footer” 
full-info-he.php	מפת אתר / מידע מלא בעברית 
full-info-text-he.php	גרסת טקסט של המידע בעברית 
full-info-text.php	גרסת טקסט של מפת האתר / מידע 
full-info.php	מידע מלא / מפת אתר 
functions.php	פונקציות עזר + לוגיקה פנימית 
genre.php	עמוד ז’אנר / קטגוריה 
get_omdb.php	קובץ שאחראי על קריאה ל־OMDb API 
home.php	עמוד הבית 
imdb.class.php	מחלקת IMDb – קוד עזר / מודול 
imdb.php	עמוד IMDb / טיפול בנתוני IMDb 
index.html	קובץ HTML סטטי (כנראה דף ברירת מחדל) 
index.php	עמוד כניסה / עמוד ראשי PHP 
init.php	קובץ איתחול / קבועים ראשוניים 
language.php	קובץ ניהול שפות / הגדרות שפה 
language_imdb.php	הגדרות שפה ל־IMDb / תרגום נתונים של IMDb 
languages.php	עמוד ניהול שפות 
lib.svg	קובץ תמונה / אייקון / לוגו (SVG) +1
likes.php	קובץ שמנהל “לייקים” / דירוגים / סימונים 
links_flags.php	טיפול בקישורים + דגלים/סימונים למשתמשים או פריטים 
links_genres.php	קישורים לפי ז’אנרים / סיווגים 
links_network.php	קישורים לפי רשתות שידור / ערוצים 
links_user_tag.php	קישורים של משתמשים לתגיות / פריטים 
load_more.php	עמוד “טען עוד” (AJAX או דינמי) 
manage_collections.php	ניהול אוספים 
manage_contacts.php	ניהול צור קשר / פניות משתמשים 
manage_genres.php	ניהול ז’אנרים / קטגוריות 
manage_languages.php	ניהול שפות 
manage_missing.php	עמוד ניהול “חסרים” / פריטים חסרים 
manage_name_country.php	ניהול שמות + מדינות / קשרים בין מדינות לשמות 
manage_name_genres.php	ניהול קשרים בין שמות לפריטי ז’אנרים 
manage_name_language.php	ניהול שמות + שפה / קשרים לשפה 
manage_name_user_tag.php	ניהול קשרים בין משתמשים, שמות ותגיות 
manage_plots.php	ניהול תקצירים / תיאורים (plots) 
manage_posters.php	ניהול פוסטרים / תמונות 
manage_reports.php	ניהול דיווחים / תלונות משתמשים 
manage_sync.php	ניהול סנכרון / עדכונים חיצוניים 
manage_titles.php	ניהול כותרות / שמות סרטים / סדרות 
manage_trailers.php	ניהול טריילרים / קטעי וידיאו 
manage_type_admin.php	ניהול סוגי פריטים (admin) 
manage_types.php	ניהול סוגי פריטים (public / כלליים) 
manage_user_tag.php	ניהול תגיות משתמש / קישורים בין משתמש לתגיות 
mange_types.php	יש כאן קובץ בשם “mange_types.php” — נראה כמו טעות שאמור להיות “manage_types.php” 
menu_component.php	רכיב תפריט (menu) 
nav.php	תפריט ניווט / קובץ nav 
network.php	עמוד רשתות שידור / ערוצים 
new-movie-imdb.php	עמוד הוספת סרט חדש דרך IMDb API 
pagination.php	מנגנון דפדוף / פאגינציה 
poster.php	עמוד פוסטר / פריט תצוגה 
poster_trailers.php	עמוד טריילרים בפריט 
poster_user_tag.php	קישורים / תגיות משתמש לפריט 
preview_bbcode.php	תצוגת קדם של BBCode (preview) 
random.php	עמוד “אקראי” (להראות פריט אקראי) 
rate.php	עמוד דירוג / נתינת ציון לפריט 
remove_from_collection.php	הסרת פריט מאוסף 
remove_from_collection_batch.php	הסרת באצווה מאוסף 
report.php	עמוד דיווח / תלונה למנהל 
 -->

<?php include 'footer.php'; ?>
