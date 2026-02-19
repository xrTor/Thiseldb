<?php
require_once 'server.php';

if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ==========================================================
   שמירת בחירות בעוגייה
   ========================================================== */
$cookieKeys = ['min','limit','last','q'];
foreach ($cookieKeys as $ck) {
    if (isset($_GET[$ck])) {
        setcookie('collabs_'.$ck, (string)$_GET[$ck], time() + 86400*30, "/");
    }
}
$minShared = (int)($_GET['min'] ?? ($_COOKIE['collabs_min'] ?? 3));
$limitTop  = (int)($_GET['limit'] ?? ($_COOKIE['collabs_limit'] ?? 200));
$lastN     = (int)($_GET['last'] ?? ($_COOKIE['collabs_last'] ?? 3000)); // כמה פוסטרים אחרונים לחשב
$q         = trim((string)($_GET['q'] ?? ($_COOKIE['collabs_q'] ?? '')));

if ($minShared < 2) $minShared = 2;
if ($limitTop < 50) $limitTop = 50;
if ($limitTop > 5000) $limitTop = 5000;
if ($lastN < 200) $lastN = 200;
if ($lastN > 50000) $lastN = 50000;

/* ==========================================================
   זיהוי שדות קיימים בטבלת posters
   ========================================================== */
$possibleFields = [
    'actors',
    'directors',
    'writers',
    'creators',
    'producer',
    'producers',
    'cast',
    'crew',
    'stars',
    'author',
    'composer',
    'cinematography',
    'editor'
];

$existingCols = [];
$resCols = $conn->query("SHOW COLUMNS FROM posters");
if ($resCols) {
    while ($row = $resCols->fetch_assoc()) {
        $existingCols[strtolower($row['Field'])] = true;
    }
}

$peopleFields = [];
foreach ($possibleFields as $f) {
    if (!empty($existingCols[strtolower($f)])) $peopleFields[] = $f;
}

// אם לא מצא כלום – fallback לשדות סטנדרטיים
if (!$peopleFields) {
    if (!empty($existingCols['actors'])) $peopleFields[] = 'actors';
    if (!empty($existingCols['directors'])) $peopleFields[] = 'directors';
    if (!empty($existingCols['writers'])) $peopleFields[] = 'writers';
}

// כותרות
$titleEnCol = !empty($existingCols['title_en']) ? 'title_en' : (!empty($existingCols['title']) ? 'title' : null);
$titleHeCol = !empty($existingCols['title_he']) ? 'title_he' : null;
$yearCol    = !empty($existingCols['year']) ? 'year' : null;
$typeIdCol  = !empty($existingCols['type_id']) ? 'type_id' : null;

/* ==========================================================
   פירוק שמות אנשים מתוך טקסט
   ========================================================== */
function split_people(?string $s): array {
    if ($s === null) return [];
    $s = trim($s);
    if ($s === '' || $s === 'N/A') return [];

    // מחליפים מפרידים שונים לפסיק אחיד
    $s = str_replace(["|", ";", " / "], ",", $s);

    $parts = array_map('trim', explode(',', $s));
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || $p === 'N/A') continue;

        // ניקוי כפול רווחים
        $p = preg_replace('/\s+/', ' ', $p);

        // אל תכניס ערכים מוזרים
        if (mb_strlen($p) < 2) continue;

        $out[] = $p;
    }

    // הסרת כפילויות (case-insensitive)
    $uniq = [];
    $seen = [];
    foreach ($out as $name) {
        $k = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $uniq[] = $name;
    }
    return $uniq;
}

/* ==========================================================
   מצב "צפייה בזוג" (דף פרטים)
   ========================================================== */
$pairA = trim((string)($_GET['a'] ?? ''));
$pairB = trim((string)($_GET['b'] ?? ''));

/* ==========================================================
   שליפת פוסטרים (אחרונים)
   ========================================================== */
$selectCols = ["id"];
if ($titleEnCol) $selectCols[] = $titleEnCol;
if ($titleHeCol) $selectCols[] = $titleHeCol;
if ($yearCol)    $selectCols[] = $yearCol;
if ($typeIdCol)  $selectCols[] = $typeIdCol;
foreach ($peopleFields as $f) $selectCols[] = $f;

$selectColsSql = implode(", ", array_map(fn($c)=>"p.`$c`", $selectCols));

// נבחר לפי id יורד כדי "אחרונים"
$sql = "SELECT $selectColsSql FROM posters p ORDER BY p.id DESC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lastN);
$stmt->execute();
$res = $stmt->get_result();

$posters = [];
while ($row = $res->fetch_assoc()) {
    $posters[] = $row;
}
$stmt->close();

/* ==========================================================
   אם נבחר זוג – מציג רק את המשותפים
   ========================================================== */
if ($pairA !== '' && $pairB !== '') {
    $pairA_l = mb_strtolower($pairA, 'UTF-8');
    $pairB_l = mb_strtolower($pairB, 'UTF-8');

    $shared = [];
    foreach ($posters as $p) {
        $people = [];
        foreach ($peopleFields as $f) {
            $people = array_merge($people, split_people($p[$f] ?? ''));
        }

        // דה-דופ
        $seen = [];
        $uniq = [];
        foreach ($people as $nm) {
            $k = mb_strtolower($nm,'UTF-8');
            if (isset($seen[$k])) continue;
            $seen[$k]=true;
            $uniq[]=$nm;
        }

        $hasA = false; $hasB = false;
        foreach ($uniq as $nm) {
            $k = mb_strtolower($nm,'UTF-8');
            if ($k === $pairA_l) $hasA = true;
            if ($k === $pairB_l) $hasB = true;
            if ($hasA && $hasB) break;
        }

        if ($hasA && $hasB) {
            $shared[] = $p;
        }
    }

    $pageTitle = "🤝 משותפים: $pairA + $pairB";
    ?>
    <!doctype html>
    <html lang="he" dir="rtl">
    <head>
        <meta charset="utf-8">
        <title><?= h($pageTitle) ?></title>
        <style>
            body{font-family:Arial;background:#0b0f19;color:#e9eefc;margin:0;padding:16px}
            a{color:#8ab4ff;text-decoration:none}
            .wrap{max-width:1200px;margin:0 auto}
            .topbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
            .btn{background:#1b2335;color:#fff;border:1px solid #2a3550;padding:9px 12px;border-radius:10px;cursor:pointer}
            .btn:hover{filter:brightness(1.1)}
            .card{background:#111a2b;border:1px solid #243252;border-radius:14px;padding:12px;margin:10px 0}
            .meta{opacity:.85;font-size:13px}
            .title{font-size:16px;font-weight:bold}
            .tag{display:inline-block;background:#16243f;border:1px solid #2a3c65;padding:4px 8px;border-radius:999px;font-size:12px;margin-left:6px}
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="topbar">
            <a class="btn" href="collabs.php">⬅ חזרה לרשימת זוגות</a>
            <div class="btn" style="background:#6f42c1;border:0;">🧬 <?= h($pairA) ?> + <?= h($pairB) ?></div>
            <div class="tag">נמצאו: <?= count($shared) ?> פוסטרים</div>
            <div class="tag">נסרקו: <?= count($posters) ?> אחרונים</div>
        </div>

        <?php if (!$shared): ?>
            <div class="card">לא נמצאו פוסטרים משותפים בטווח שנסרק.</div>
        <?php endif; ?>

        <?php foreach ($shared as $p): 
            $tEn = $titleEnCol ? ($p[$titleEnCol] ?? '') : '';
            $tHe = $titleHeCol ? ($p[$titleHeCol] ?? '') : '';
            $yr  = $yearCol ? ($p[$yearCol] ?? '') : '';
            $id  = (int)$p['id'];
        ?>
            <div class="card">
                <div class="title">
                    <a href="collection.php?id=<?= $id ?>" style="display:none"></a>
                    <?= h($tHe ?: $tEn ?: ('ID '.$id)) ?>
                </div>
                <div class="meta">
                    <?= $tHe && $tEn ? h($tEn) . " • " : "" ?>
                    <?= $yr ? "[$yr] • " : "" ?>
                    ID: <?= $id ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

/* ==========================================================
   חישוב זוגות (Collabs)
   ========================================================== */
$pairCounts = [];        // key => count
$pairSample = [];        // key => sample poster ids (up to 5)

foreach ($posters as $p) {
    $people = [];
    foreach ($peopleFields as $f) {
        $people = array_merge($people, split_people($p[$f] ?? ''));
    }

    // דה-דופ + הגבלה (כדי לא להעמיס בפוסטר עם 200 שמות)
    $seen = [];
    $uniq = [];
    foreach ($people as $nm) {
        $k = mb_strtolower($nm,'UTF-8');
        if (isset($seen[$k])) continue;
        $seen[$k]=true;
        $uniq[]=$nm;
        if (count($uniq) >= 40) break;
    }

    $n = count($uniq);
    if ($n < 2) continue;

    // בונים זוגות
    for ($i=0; $i<$n; $i++) {
        for ($j=$i+1; $j<$n; $j++) {
            $a = $uniq[$i];
            $b = $uniq[$j];

            // סדר קבוע למפתח
            $al = mb_strtolower($a,'UTF-8');
            $bl = mb_strtolower($b,'UTF-8');
            if ($al > $bl) { $tmp=$a; $a=$b; $b=$tmp; }

            $key = $a . " || " . $b;

            if (!isset($pairCounts[$key])) $pairCounts[$key] = 0;
            $pairCounts[$key]++;

            if (!isset($pairSample[$key])) $pairSample[$key] = [];
            if (count($pairSample[$key]) < 5) {
                $pairSample[$key][] = (int)$p['id'];
            }
        }
    }
}

// מסננים לפי מינימום
$filtered = [];
foreach ($pairCounts as $k => $c) {
    if ($c >= $minShared) {
        if ($q !== '') {
            if (mb_stripos($k, $q, 0, 'UTF-8') === false) continue;
        }
        $filtered[$k] = $c;
    }
}

// מיון יורד
arsort($filtered);

// לקיחת top
$topPairs = array_slice($filtered, 0, $limitTop, true);

?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>🤝 שיתופי פעולה (שחקנים + צוות)</title>
    <style>
        body{font-family:Arial;background:#0b0f19;color:#e9eefc;margin:0;padding:16px}
        a{color:#8ab4ff;text-decoration:none}
        .wrap{max-width:1400px;margin:0 auto}
        .head{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:12px}
        .title{font-size:22px;font-weight:bold}
        .sub{opacity:.85;font-size:13px}
        .panel{background:#111a2b;border:1px solid #243252;border-radius:14px;padding:12px;margin:12px 0}
        .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .inp{background:#0f1728;border:1px solid #2a3550;color:#fff;padding:9px 10px;border-radius:10px;min-width:220px}
        .btn{background:#1b2335;color:#fff;border:1px solid #2a3550;padding:9px 12px;border-radius:10px;cursor:pointer}
        .btn:hover{filter:brightness(1.1)}
        .btn-purple{background:#6f42c1;border:0}
        .btn-red{background:#b02a37;border:0}
        .tag{display:inline-block;background:#16243f;border:1px solid #2a3c65;padding:4px 8px;border-radius:999px;font-size:12px}
        table{width:100%;border-collapse:collapse;margin-top:8px}
        th,td{padding:10px;border-bottom:1px solid #23314f;text-align:right;vertical-align:top}
        th{opacity:.9;font-size:13px}
        .pair{font-weight:bold}
        .count{font-size:14px}
        .muted{opacity:.85;font-size:12px}
        .pill{display:inline-block;padding:3px 8px;border-radius:999px;background:#1b2a49;border:1px solid #2a3c65;font-size:12px}
        .linkbtn{display:inline-block;padding:7px 10px;border-radius:10px;background:#14213d;border:1px solid #2a3c65}
    </style>
</head>
<body>
<div class="wrap">

    <div class="head">
        <div>
            <div class="title">🤝 שיתופי פעולה – כל הצוות והשחקנים</div>
            <div class="sub">
                נסרקו <span class="pill"><?= count($posters) ?></span> פוסטרים אחרונים •
                נמצאו <span class="pill"><?= count($filtered) ?></span> זוגות (אחרי סינון) •
                מציג Top <span class="pill"><?= count($topPairs) ?></span>
            </div>
        </div>
    </div>

    <div class="panel">
        <form method="get" class="row">
            <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="חיפוש שם… (למשל Nolan / DiCaprio)" />
            <label class="tag">מינימום יחד:
                <input class="inp" style="min-width:90px" type="number" name="min" value="<?= (int)$minShared ?>" min="2" max="50">
            </label>
            <label class="tag">Top:
                <input class="inp" style="min-width:110px" type="number" name="limit" value="<?= (int)$limitTop ?>" min="50" max="5000">
            </label>
            <label class="tag">כמה אחרונים לסרוק:
                <input class="inp" style="min-width:120px" type="number" name="last" value="<?= (int)$lastN ?>" min="200" max="50000">
            </label>
            <button class="btn btn-purple" type="submit">🔎 חשב / סנן</button>
            <a class="btn btn-red" href="collabs.php?min=3&limit=200&last=3000&q=">🔄 איפוס</a>
        </form>

        <div class="sub" style="margin-top:8px">
            שדות שנמצאו אצלך בטבלה: <span class="pill"><?= h(implode(', ', $peopleFields ?: ['(לא נמצאו שדות אנשים)'])) ?></span>
        </div>
    </div>

    <div class="panel">
        <?php if (!$topPairs): ?>
            <div>לא נמצאו זוגות שעובדים יחד לפחות <?= (int)$minShared ?> פעמים בטווח שנסרק.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:56%">זוג</th>
                        <th style="width:10%">כמות יחד</th>
                        <th style="width:34%">פעולות</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topPairs as $key => $cnt):
                    [$a,$b] = explode(' || ', $key, 2);
                    $url = 'collabs.php?a=' . urlencode($a) . '&b=' . urlencode($b) . '&last='.(int)$lastN;
                ?>
                    <tr>
                        <td class="pair"><?= h($a) ?> <span class="muted">+</span> <?= h($b) ?></td>
                        <td class="count">🔥 <?= (int)$cnt ?></td>
                        <td>
                            <a class="linkbtn" href="<?= h($url) ?>">📌 הצג משותפים</a>
                            <?php if (!empty($pairSample[$key])): ?>
                                <span class="muted" style="margin-right:8px">
                                    דוגמא IDs: <?= h(implode(', ', $pairSample[$key])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
