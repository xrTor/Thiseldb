<?php
// ============================================
// watch_totals.php
// זמן צפייה כולל + ממוצעים לפי סוג (סרטים / סדרות / וכו')
// כל הלוגיקה (סכומים, ממוצעים, זיהוי סדרות) נעשית ב-PHP בלבד.
// ה-SQL עושה רק SELECT פשוטים, בלי CASE, בלי SUM/AVG.
// ============================================

// ודא ש-server.php לא נכלל שוב אם stats.php כבר כלל אותו.
// assume $conn (mysqli) is already defined by stats.php via require_once 'server.php';

/* ===== פונקציות עזר ===== */

// תיקון: עטיפת h() בבדיקה למניעת הגדרה כפולה
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * המרת דקות לזמן קריא (שעות/דקות)
 * כולל פסיקים בשעות (לדוגמה: 108,079 שעות ו־52 דקות)
 */
function format_runtime_minutes(?int $minutes): string {
    if (!$minutes || $minutes <= 0) {
        return '—';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    if ($h > 0) {
        $h_disp = number_format($h, 0, '.', ','); // פסיקים בשעות
        if ($m > 0) {
            return "{$h_disp} שעות ו־{$m} דקות";
        }
        return "{$h_disp} שעות";
    }
    return "{$m} דקות";
}

/**
 * זיהוי האם סוג הוא "סדרתי" לפי השדות של poster_types
 * (code / label_en / label_he) – לוגיקה ב-PHP בלבד.
 */
function detect_series_type(array $typeRow): bool {
    $code    = strtolower(trim($typeRow['code'] ?? ''));
    $labelEn = strtolower(trim($typeRow['label_en'] ?? ''));
    $labelHe = trim($typeRow['label_he'] ?? '');

    if ($code !== '' && (str_contains($code, 'series') || str_contains($code, 'tv'))) {
        return true;
    }
    if ($labelEn !== '' && (str_contains($labelEn, 'series') || str_contains($labelEn, 'tv'))) {
        return true;
    }

    if ($labelHe !== '') {
        if (function_exists('mb_stripos')) {
            return mb_stripos($labelHe, 'סדרה', 0, 'UTF-8') !== false;
        } else {
            return strpos($labelHe, 'סדרה') !== false;
        }
    }
    return false;
}

/* ===== 1) שליפת סוגים מטבלת poster_types ===== */

$stats = [];   // סטטיסטיקות שנחשב בפועל לכל type_id

$typesSql = "SELECT id, label_he, icon, image, code, label_en FROM poster_types ORDER BY id ASC";
// מניח ש-`$conn` מגיע מ-`stats.php`
if (!isset($conn) || !($conn instanceof mysqli)) {
    return; // יציאה אם $conn לא קיים
}
$typesRes = $conn->query($typesSql);

if ($typesRes && $typesRes->num_rows > 0) {
    while ($row = $typesRes->fetch_assoc()) {
        $id = (int)$row['id'];
        // סוג "לא ידוע" – לא נחשב בכלל
        if (trim($row['label_he'] ?? '') === 'לא ידוע') {
            continue;
        }

        $isSeries = detect_series_type($row);

        // מאתחלים מבנה סטטיסטיקה לכל סוג
        $stats[$id] = [
            'type_id'          => $id,
            'label_he'         => $row['label_he'] ?? '',
            'icon'             => $row['icon'] ?? '',
            'image'            => $row['image'] ?? '',
            'is_series_type'   => $isSeries,
            'posters_count'    => 0,

            'sum_total_minutes'   => 0, // זמן כולל (לסרטים: runtime, לסדרות: runtime*episodes_count)
            'sum_title_minutes'   => 0, // לסרטים/סדרות: משמש לממוצע זמן כותר
            'count_title'         => 0,

            'sum_episode_minutes' => 0, // רק לסדרות: runtime לפרק
            'count_episode'       => 0,

            'sum_series_minutes'  => 0, // רק לסדרות: runtime*episodes_count לסדרה
            'count_series'        => 0,
        ];
    }
    $typesRes->free();
}

/* ===== 2) שליפת כל הפוסטרים – SQL פשוט בלבד ===== */

$postersSql = "
    SELECT
        id,
        type_id,
        runtime,
        episodes_count
    FROM posters
";

$postersRes = $conn->query($postersSql);

if ($postersRes && $postersRes->num_rows > 0) {
    while ($p = $postersRes->fetch_assoc()) {
        $posterId  = (int)$p['id'];
        $typeIdRaw = $p['type_id'];

        // פוסטר בלי סוג (NULL / 0) – מדלגים לגמרי (לא נספר, לא מצטבר)
        if ($typeIdRaw === null || $typeIdRaw === '' || $typeIdRaw == 0) {
            continue;
        }

        $typeId = (int)$typeIdRaw;

        // אם אין לנו סוג הזה בסטטיסטיקה (למשל נמחק מהטבלה) – מדלגים
        if (!isset($stats[$typeId])) {
            continue;
        }

        $runtime   = isset($p['runtime']) ? (int)$p['runtime'] : 0;
        $episodes  = isset($p['episodes_count']) ? (int)$p['episodes_count'] : 0;

        // עדכון כמות הפוסטרים – נספר תמיד, גם אם runtime=0
        $stats[$typeId]['posters_count']++;

        // אם אין runtime חיובי – אין מה להוסיף לזמנים
        if ($runtime <= 0) {
            continue;
        }

        $isSeriesType = $stats[$typeId]['is_series_type'];

        if ($isSeriesType) {
            // סוג שמזוהה כסדרה לפי poster_types
            if ($episodes > 0) {
                $titleTotal = $runtime * $episodes;

                // זמן כולל
                $stats[$typeId]['sum_total_minutes'] += $titleTotal;

                // ממוצע כותר (סדרה מלאה)
                $stats[$typeId]['sum_title_minutes'] += $titleTotal;
                $stats[$typeId]['count_title']++;

                // ממוצע פרק
                $stats[$typeId]['sum_episode_minutes'] += $runtime;
                $stats[$typeId]['count_episode']++;

                // ממוצע סדרה
                $stats[$typeId]['sum_series_minutes'] += $titleTotal;
                $stats[$typeId]['count_series']++;
            } else {
                // סוג סדרה אבל אין episodes_count – נתייחס ככותר בודד
                $titleTotal = $runtime;
                $stats[$typeId]['sum_total_minutes'] += $titleTotal;
                $stats[$typeId]['sum_title_minutes'] += $titleTotal;
                $stats[$typeId]['count_title']++;
            }
        } else {
            // סוג שאינו סדרה – runtime הוא זמן כותר (סרט רגיל וכו')
            $titleTotal = $runtime;
            $stats[$typeId]['sum_total_minutes'] += $titleTotal;
            $stats[$typeId]['sum_title_minutes'] += $titleTotal;
            $stats[$typeId]['count_title']++;
        }
    }
    $postersRes->free();
}

/* ===== 3) חישוב ממוצעים וסיכום כולל ===== */

$grandMinutes = 0;
$grandCount   = 0;

// נסדר את הסוגים לפי type_id
ksort($stats, SORT_NUMERIC);

foreach ($stats as $typeId => &$st) {
    // חישוב ממוצעים
    $st['total_runtime_minutes'] = $st['sum_total_minutes'];

    $st['avg_title_runtime'] = ($st['count_title'] > 0)
        ? ($st['sum_title_minutes'] / $st['count_title'])
        : null;

    $st['avg_episode_runtime'] = ($st['count_episode'] > 0)
        ? ($st['sum_episode_minutes'] / $st['count_episode'])
        : null;

    $st['avg_series_runtime'] = ($st['count_series'] > 0)
        ? ($st['sum_series_minutes'] / $st['count_series'])
        : null;

    // עדכון סכומים כלליים
    $grandMinutes += $st['total_runtime_minutes'];
    $grandCount   += $st['posters_count'];
}
unset($st);

// המרה לשנים (בערך) – צפייה רצופה
$grandYears = 0.0;
if ($grandMinutes > 0) {
    $grandYears = $grandMinutes / (60 * 24 * 365); // דקות → שנים
}

// ============================================
// התחלת הפלט: עטיפת הכל ב-div.box כדי להתאים ל-stats.php
// ============================================
?>

<div class="box">
    <h2 style="margin-top:0; text-align: center;">⏱️ זמן צפייה כולל לפי סוג</h2>

    <style>
        /* הגדרות כלליות לבלוק הבהיר */
        #watch-totals-container {
            /* מבטל את העיצוב הכהה החיצוני, נותן ל-div.box לשלוט ברקע הלבן */
            background-color: transparent; 
            color: #333;
            padding: 0;
            margin: 0;
        }
        
        #watch-totals-container .summary-box {
            margin: 15px 0;
            padding: 12px 15px;
            border-radius: 10px;
            background: #f9f9f9; /* רקע אפור בהיר לסיכום */
            color: #333;
            text-align: right;
        }

        /* סגנונות הטבלה עצמה - בהיר */
        #watch-totals-table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            background: #fff; /* רקע לבן לטבלה */
            font-size: 13px;
        }
        #watch-totals-table-wrapper th, #watch-totals-table-wrapper td {
            border-bottom: 1px solid #ccc; /* גבולות אפורים דקים */
            border-left: none; 
            border-right: none;
            border-top: none;
            padding: 12px 8px; /* הגדלת ריווח אנכי */
            text-align: right;
            color: #333; /* צבע טקסט שחור */
        }
        #watch-totals-table-wrapper thead tr {
            background: #eee; /* רקע אפור לכותרת */
        }
        #watch-totals-table-wrapper tbody tr:nth-child(even) {
            background: #fff;
        }
        #watch-totals-table-wrapper tbody tr:nth-child(odd) {
            background: #fff;
        }
        
        #watch-totals-table-wrapper .note-muted {
            color: #888;
            font-size: 12px;
        }
        #watch-totals-table-wrapper a {
            color: #007bff; /* צבע קישור כחול */
            text-decoration: none;
        }
        #watch-totals-table-wrapper img.type-icon {
            width: 24px;
            height: 24px;
            object-fit: contain;
            vertical-align: middle;
            margin-left: 5px;
        }
        
        /* === תיקון רוחב עמודות === */
        #watch-totals-table-wrapper table th:nth-child(1),
        #watch-totals-table-wrapper table td:nth-child(1) {
            /* עמודת סוג: קצת יותר רחבה עבור האייקון/טקסט */
            width: 20%;
            min-width: 120px;
        }

        #watch-totals-table-wrapper table th:nth-child(2),
        #watch-totals-table-wrapper table td:nth-child(2) {
            /* עמודת מס' פוסטרים: צרה ומרכזית */
            width: 10%;
            min-width: 80px;
            text-align: center; 
        }

        #watch-totals-table-wrapper table th:nth-child(3),
        #watch-totals-table-wrapper table td:nth-child(3) {
            /* עמודת זמן כולל: רוחב קבוע לזמן הארוך */
            width: 30%;
            min-width: 180px;
        }
        
        #watch-totals-table-wrapper table th:nth-child(4),
        #watch-totals-table-wrapper table td:nth-child(4) {
            /* עמודת ממוצעים: רוחב מקסימלי לתוכן המורכב */
            width: 40%;
            min-width: 200px;
        }
        /* ======================== */


        /* תיקון צבע טקסט בתוך ה-summary-box */
        #watch-totals-container .summary-box span,
        #watch-totals-container .summary-box strong,
        #watch-totals-container .summary-box div {
             color: #333;
        }
        /* התאמת צבעים בתוך הטבלה */
        #watch-totals-table-wrapper td span[style*="font-size:11px"] {
            color: #555 !important;
        }
        #watch-totals-table-wrapper th {
             text-align: center; /* יישור כותרות */
        }
        #watch-totals-table-wrapper th:nth-child(1) {
            text-align: right; /* כותרת סוג נשארת ימין */
        }
    </style>
    
    <div id="watch-totals-container">
        <div class="summary-box">
            <div style="font-size: 14px; margin-bottom: 4px;">
                🎬 כמות פוסטרים כללית:
                <strong><?php echo number_format($grandCount, 0, '.', ','); ?></strong>
            </div>
            <div style="font-size: 14px; margin-bottom: 4px;">
                ⏰ זמן צפייה כולל בכל הפוסטרים:
                <strong><?php echo h(format_runtime_minutes($grandMinutes)); ?></strong>
                <span style="font-size:12px; color:#555;">
                    (<?php echo number_format($grandMinutes, 0, '.', ','); ?> דקות)
                </span>
            </div>
            <?php if ($grandMinutes > 0): ?>
                <div style="font-size: 13px; color:#555;">
                    שזה בערך
                    <strong><?php echo number_format($grandYears, 1, '.', ','); ?></strong>
                    שנים של צפייה רצופה.
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($stats)): ?>
            <div style="background:#f9f9f9;color:#555;padding:10px 12px;border-radius:8px;font-size:13px; text-align:center;">
                לא נמצאו סוגים (poster_types) לאחר סינון "לא ידוע".
            </div>
        <?php else: ?>
            <div style="overflow-x:auto; margin-top: 15px;" id="watch-totals-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>סוג</th>
                            <th>מס' פוסטרים</th>
                            <th>זמן צפייה כולל</th>
                            <th>ממוצעים</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats as $st): ?>
                        <?php
                            $typeId      = $st['type_id'];
                            $labelHe     = $st['label_he'] !== '' ? $st['label_he'] : 'ללא כותרת';
                            $cnt         = $st['posters_count'];
                            $mins        = $st['total_runtime_minutes'];
                            $hasTime     = $mins > 0;
                            $avgTitle    = $st['avg_title_runtime'];
                            $avgEpisode  = $st['avg_episode_runtime'];
                            $avgSeries   = $st['avg_series_runtime'];
                            $img         = $st['image'] ?? '';
                            $icon        = $st['icon'] ?? '';

                            // כתובת החיפוש לסוג (home.php עם type[] כמו אצלך)
                            $searchUrl = 'home.php?search=&type%5B%5D=' . urlencode((string)$typeId);
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo h($searchUrl); ?>" style="color:inherit; text-decoration:none;">
                                    <?php if ($img): ?>
                                        <?php
                                            $imgSrc = (strpos($img, '/') === false)
                                                ? 'images/types/' . $img
                                                : $img;
                                        ?>
                                        <img src="<?php echo h($imgSrc); ?>" alt="" class="type-icon">
                                    <?php elseif ($icon): ?>
                                        <span><?php echo h($icon); ?></span>
                                    <?php endif; ?>
                                    <span><?php echo h($labelHe); ?></span>
                                </a>
                            </td>
                            <td style="text-align:center;">
                                <?php echo number_format($cnt, 0, '.', ','); ?>
                            </td>
                            <td>
                                <?php if ($hasTime): ?>
                                    <strong><?php echo h(format_runtime_minutes($mins)); ?></strong>
                                    <span style="font-size:11px; color:#555;">
                                        (<?php echo number_format($mins, 0, '.', ','); ?> דקות)
                                    </span>
                                <?php else: ?>
                                    <span class="note-muted">אין נתוני runtime</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($avgEpisode !== null || $avgSeries !== null): ?>
                                    <?php if ($avgEpisode !== null): ?>
                                        <div>🕒 פרק: <strong><?php echo (int)round($avgEpisode); ?></strong> דקות בממוצע</div>
                                    <?php endif; ?>
                                    <?php if ($avgSeries !== null): ?>
                                        <div>
                                            📺 סדרה:
                                            <strong><?php echo h(format_runtime_minutes((int)round($avgSeries))); ?></strong>
                                            בממוצע
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($avgTitle !== null): ?>
                                    <div>🎬 כותר: <strong><?php echo (int)round($avgTitle); ?></strong> דקות בממוצע</div>
                                <?php else: ?>
                                    <span class="note-muted">ללא נתוני ממוצע</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// סוף הקובץ watch_totals.php
?>