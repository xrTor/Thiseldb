<?php
ob_start(); // אגירת פלט

// ==========================================================
// חלק 1: הגדרות וחיבור לדאטאבייס
// ==========================================================

require_once 'server.php';
include 'header.php'; 

try {
    $h = isset($servername) ? $servername : (isset($host) ? $host : 'localhost');
    $d = isset($dbname) ? $dbname : (isset($db) ? $db : 'thiseldb');
    $u = isset($username) ? $username : (isset($user) ? $user : 'root');
    $p = isset($password) ? $password : (isset($pass) ? $pass : '');

    $pdo = new PDO("mysql:host=$h;dbname=$d;charset=utf8mb4", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean(); 
    die("שגיאת חיבור: " . $e->getMessage());
}

ini_set('max_execution_time', 600);
$timeTreshhold = "DATE_SUB(NOW(), INTERVAL 15 MINUTE)"; 

// ==========================================================
// חלק 2: API פנימי (AJAX)
// ==========================================================

// --- פעולה 1: עדכון ידני של ID ---
if (isset($_POST['action']) && $_POST['action'] === 'quick_update_id') {
    ob_clean(); 
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        $new_imdb = trim($_POST['imdb_id']);
        $stmt = $pdo->prepare("UPDATE posters SET imdb_id = ?, last_updated = NULL WHERE id = ?");
        $stmt->execute([$new_imdb, $id]);
        echo json_encode(['status' => 'success', 'msg' => 'ID עודכן!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// --- פעולה 2: המרת רשימת טקסט לתור (חדש!) ---
if (isset($_POST['action']) && $_POST['action'] === 'parse_custom_list') {
    ob_clean();
    header('Content-Type: application/json');
    
    $rawText = $_POST['list'] ?? '';
    // חילוץ כל ה-tt1234567 מהטקסט
    preg_match_all('/tt\d{6,10}/i', $rawText, $matches);
    $cleanIds = array_unique($matches[0]);

    if (empty($cleanIds)) {
        echo json_encode(['status' => 'error', 'msg' => 'לא נמצאו מזהי IMDb תקינים בטקסט (פורמט tt1234567).']);
        exit;
    }

    try {
        // יצירת מחרוזת לשאילתה (IN clause)
        $placeholders = str_repeat('?,', count($cleanIds) - 1) . '?';
        $sql = "SELECT id, title_he, title_en, imdb_id FROM posters WHERE imdb_id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($cleanIds));
        $queue = $stmt->fetchAll();

        if (count($queue) === 0) {
            echo json_encode(['status' => 'error', 'msg' => 'המזהים נמצאו בטקסט, אך לא קיימים במסד הנתונים שלנו.']);
        } else {
            echo json_encode(['status' => 'success', 'queue' => $queue, 'found' => count($queue)]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// --- פעולה 3: שליפת התור הרגיל ---
if (isset($_GET['mode']) && $_GET['mode'] === 'get_queue') {
    ob_clean();
    header('Content-Type: application/json');
    $scope = isset($_GET['scope']) ? $_GET['scope'] : 'all';
    
    try {
        if ($scope === 'all') {
            $sql = "SELECT id, title_he, title_en, imdb_id FROM posters 
                    WHERE last_updated IS NULL OR last_updated < $timeTreshhold
                    ORDER BY id DESC";
        } else {
            $limit = (int)$scope;
            $sql = "SELECT id, title_he, title_en, imdb_id FROM (
                        SELECT id, title_he, title_en, imdb_id, last_updated 
                        FROM posters 
                        ORDER BY id DESC LIMIT $limit
                    ) AS sub 
                    WHERE last_updated IS NULL OR last_updated < $timeTreshhold
                    ORDER BY id DESC";
        }
        $stmt = $pdo->query($sql);
        $queue = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'queue' => $queue]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// --- פעולה 4: סנכרון בפועל ---
if (isset($_GET['mode']) && $_GET['mode'] === 'ajax_sync') {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ob_clean(); 
    header('Content-Type: application/json');

    if (!file_exists('imdb.class.php')) {
        echo json_encode(['status' => 'error', 'updated' => [], 'errors' => ["<div class='text-danger'>חסר imdb.class.php</div>"], 'unchanged' => []]);
        exit;
    }
    require_once 'imdb.class.php';
    
    $batchSize = 1; 

    try {
        // בדיקה: האם נשלח ID ספציפי לעדכון? (עבור סנכרון מרשימה)
        if (isset($_GET['specific_id'])) {
            $specId = (int)$_GET['specific_id'];
            $sql = "SELECT id, title_he, title_en, imdb_id, imdb_rating, imdb_votes FROM posters WHERE id = $specId";
        } else {
            // מצב רגיל - שליפה לפי תאריך
            $scope = isset($_GET['scope']) ? $_GET['scope'] : 'all';
            if ($scope === 'all') {
                $sql = "SELECT id, title_he, title_en, imdb_id, imdb_rating, imdb_votes 
                        FROM posters 
                        WHERE last_updated IS NULL OR last_updated < $timeTreshhold
                        ORDER BY id DESC LIMIT $batchSize";
            } else {
                $limit = (int)$scope;
                $sql = "SELECT * FROM (
                            SELECT id, title_he, title_en, imdb_id, imdb_rating, imdb_votes, last_updated 
                            FROM posters 
                            ORDER BY id DESC LIMIT $limit
                        ) AS sub 
                        WHERE last_updated IS NULL OR last_updated < $timeTreshhold
                        ORDER BY id DESC LIMIT $batchSize";
            }
        }

        $stmt = $pdo->query($sql);
        $movies = $stmt->fetchAll();

        if (count($movies) > 0) {
            $idsToLock = array_column($movies, 'id');
            $idList = implode(',', $idsToLock);
            $pdo->query("UPDATE posters SET last_updated = NOW() WHERE id IN ($idList)");
        }

    } catch (PDOException $e) { $movies = []; }

    if (count($movies) === 0) {
        echo json_encode(['status' => 'done']);
        exit;
    }

    $logs_updated = [];
    $logs_errors = [];
    $logs_unchanged = [];
    $processed_ids = []; 

    foreach ($movies as $movie) {
        $processed_ids[] = $movie['id']; 
        
        $displayTitle = mb_strimwidth($movie['title_he'] ?: $movie['title_en'], 0, 45, "...");
        $safeTitle = htmlspecialchars($movie['title_he'] ?: $movie['title_en'], ENT_QUOTES);
        $fixBtn = "<button onclick='openFixModal({$movie['id']}, \"{$safeTitle}\", \"{$movie['imdb_id']}\")' class='btn btn-sm btn-outline-secondary py-0 px-2 ms-2' style='font-size:0.7em'>תיקון</button>";

        if (empty($movie['imdb_id'])) {
            $logs_errors[] = "<div class='text-danger small'>⚠ {$displayTitle} (חסר ID) $fixBtn</div>";
            continue;
        }

        try {
            $imdbObj = new IMDB($movie['imdb_id'], 60); 
            
            if ($imdbObj->isReady) {
                $newRating = $imdbObj->getRating();
                $newVotesStr = $imdbObj->getRatingCount();
                $newVotes = (int)str_replace(',', '', (string)$newVotesStr);

                if ($newRating === 'n/A' || $newRating === '') $newRating = null;
                if ($newVotes === 0 && $newRating === null) $newVotes = null;

                if ($newRating !== null) {
                    $currentRating = (float)$movie['imdb_rating'];
                    $apiRating = (float)$newRating;
                    $currentVotes = (int)$movie['imdb_votes'];
                    $apiVotes = (int)$newVotes;

                    if ($apiRating != $currentRating || $apiVotes != $currentVotes) {
                        $upd = $pdo->prepare("UPDATE posters SET imdb_rating = ?, imdb_votes = ? WHERE id = ?");
                        $upd->execute([$newRating, $newVotes, $movie['id']]);
                        
                        $logs_updated[] = "<div class='border-bottom border-secondary pb-1 mb-1' style='border-color: #444 !important;'>" .
                                          "<span class='text-success fw-bold'>✔ עודכן:</span> <a href='poster.php?id={$movie['id']}' target='_blank' class='text-white text-decoration-none'>{$displayTitle}</a><br>" .
                                          "<div style='font-size: 0.85em; color: #ccc; margin-top:2px;'>" . 
                                          "⭐ <span class='text-warning'>{$currentRating}</span> ← <span class='text-white fw-bold'>{$newRating}</span> &nbsp;|&nbsp; " . 
                                          "🗳️ <span class='text-warning'>{$currentVotes}</span> ← <span class='text-white fw-bold'>{$newVotes}</span>" . 
                                          "</div></div>";
                    } else {
                        $logs_unchanged[] = "<div class='text-secondary' style='font-size:0.85em; border-bottom: 1px solid #333; padding-bottom: 2px; margin-bottom: 2px;'>• <a href='poster.php?id={$movie['id']}' target='_blank' class='text-secondary text-decoration-none'>{$displayTitle}</a></div>";
                    }
                } else {
                    $logs_errors[] = "<div class='text-warning small'>✖ {$displayTitle} (ללא דירוג) $fixBtn</div>";
                }
            } else {
                $logs_errors[] = "<div class='text-danger small'>✖ {$displayTitle} (שגיאת קריאה) $fixBtn</div>";
            }
        } catch (Exception $e) {
            $logs_errors[] = "<div class='text-danger small'>✖ {$displayTitle} (קריסה: {$e->getMessage()})</div>";
        }
    }

    echo json_encode([
        'status' => 'running', 
        'updated' => $logs_updated, 
        'errors' => $logs_errors, 
        'unchanged' => $logs_unchanged,
        'processed_ids' => $processed_ids
    ]);
    exit;
}

$totalMovies = $pdo->query("SELECT COUNT(*) FROM posters")->fetchColumn();
ob_end_flush(); 
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול Thiseldb</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: system-ui, -apple-system, sans-serif; }
        .console-box { 
            height: 400px; 
            overflow-y: auto; 
            background: #1e1e1e; 
            color: #00ff9d; 
            font-family: 'Segoe UI', monospace; 
            padding: 15px; 
            border-radius: 8px; 
            font-size: 0.9rem;
            line-height: 1.6;
            text-align: right; 
            direction: rtl;
        }
        #log-unchanged { color: #888; font-size: 0.85rem; }
        #log-errors { color: #ff6b6b; font-size: 0.85rem; }
        .console-box a:hover { text-decoration: underline !important; }
        .main-card { max-width: 1200px; margin: 0 auto; }
        
        #queue-list {
            background: #2b2b2b;
            color: #ddd;
            height: 250px;
            font-size: 0.8rem;
        }
        .queue-item { 
            border-bottom: 1px solid #444; 
            padding: 4px 0; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .queue-item:hover { background-color: #333; }
        
        /* אזור הדבקה מותאם אישית */
        .custom-paste-area {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px dashed #ced4da;
        }
    </style>
</head>
<body class="p-4">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 main-card">
        <h2 class="fw-bold mb-0"><i class="fas fa-film text-primary"></i> סנכרון IMDb <img src="images/imdb.png" alt="" width="60px"></h2>
        <a href="index.php" class="btn btn-outline-dark" target="_blank">לאתר</a>
    </div>

    <div class="card border-0 shadow-sm p-4 main-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-muted">סה"כ במאגר: <?= number_format($totalMovies) ?></h5>
        </div>
        
        <div class="custom-paste-area">
            <label class="form-label fw-bold text-dark"><i class="fas fa-paste"></i> הדבק רשימת IDs לעדכון (tt1234567...):</label>
            <textarea id="customIdText" class="form-control mb-2" rows="8" placeholder="הדבק כאן רשימה (מופרדת בשורות, פסיקים או רווחים)"></textarea>
            <button id="btnLoadCustom" class="btn btn-primary btn-sm w-100 fw-bold">
                <i class="fas fa-list-check"></i> טען רשימה והחלף תור
            </button>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">בחר טווח סריקה (אוטומטי):</label>
            <div class="btn-group w-100" role="group" id="syncScopeGroup">
                <input type="radio" class="btn-check" name="sync_scope" id="scope_100" value="100">
                <label class="btn btn-outline-primary" for="scope_100">100 אחרונים</label>

                <input type="radio" class="btn-check" name="sync_scope" id="scope_1000" value="1000" checked>
                <label class="btn btn-outline-primary" for="scope_1000">1,000 אחרונים</label>

                <input type="radio" class="btn-check" name="sync_scope" id="scope_all" value="all">
                <label class="btn btn-outline-primary" for="scope_all">כל המאגר</label>
            </div>
        </div>

        <div class="d-flex gap-2 mb-3">
            <button id="startSync" class="btn btn-success flex-grow-1 py-2 fw-bold"><i class="fas fa-play"></i> התחל/המשך סנכרון</button>
            <button id="stopSync" class="btn btn-danger flex-grow-1 py-2 d-none"><i class="fas fa-stop"></i> עצור</button>
        </div>
        
        <div class="row g-3">
            <div class="col-md-4">
                <h6 class="fw-bold text-success"><i class="fas fa-sync-alt"></i> עודכן</h6>
                <div id="log-updated" class="console-box">> ממתין...</div>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-danger"><i class="fas fa-exclamation-triangle"></i> שגיאות</h6>
                <div id="log-errors" class="console-box">> ממתין...</div>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-secondary"><i class="fas fa-check-circle"></i> ללא שינוי</h6>
                <div id="log-unchanged" class="console-box">> ממתין...</div>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <h6 class="fw-bold text-muted mb-0"><i class="fas fa-list-ol"></i> תור לעדכון (נשאר: <span id="queueCount">0</span>)</h6>
                <div>
                    <button onclick="copyAllQueueIds()" class="btn btn-sm btn-outline-secondary ms-1" id="btnCopyAll">
                        <i class="fas fa-copy"></i> העתק הכל
                    </button>
                    <button onclick="clearStorage()" class="btn btn-sm btn-outline-danger" title="נקה רשימה מהזיכרון">
                        <i class="fas fa-trash-alt"></i> נקה זיכרון
                    </button>
                </div>
            </div>
            
            <div id="queue-list" class="console-box">
                <div class="text-muted p-2">הרשימה תתמלא עם תחילת הסנכרון...</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fixIdModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">תיקון ID</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="fixDbId">
                <input type="text" id="fixImdbVal" class="form-control font-monospace mb-2" placeholder="tt...">
                <a href="#" id="searchLink" target="_blank" class="small text-decoration-none">🔎 חפש ב-IMDb</a>
            </div>
            <div class="modal-footer p-1"><button class="btn btn-primary w-100" onclick="saveFixedId()">שמור</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let isSyncing = false;
    let isCustomMode = false; // דגל למצב ידני
    
    const logUpdated = document.getElementById('log-updated');
    const logErrors = document.getElementById('log-errors');
    const logUnchanged = document.getElementById('log-unchanged');
    const queueList = document.getElementById('queue-list');
    const queueCount = document.getElementById('queueCount');
    
    let currentQueue = [];

    document.addEventListener('DOMContentLoaded', () => {
        const savedQueue = localStorage.getItem('imdb_sync_queue');
        if (savedQueue) {
            try {
                currentQueue = JSON.parse(savedQueue);
                if (currentQueue.length > 0) {
                    // בדיקה אם המצב הקודם היה "ידני"
                    const savedMode = localStorage.getItem('imdb_sync_mode');
                    if(savedMode === 'custom') isCustomMode = true;

                    renderQueue(currentQueue);
                    logUpdated.innerHTML += "<div class='text-warning mb-2'>⚠ שוחזרה רשימה מהפעלה קודמת!</div>";
                }
            } catch (e) {
                console.error("Storage Error", e);
                localStorage.removeItem('imdb_sync_queue');
            }
        }
    });

    // --- לוגיקה לטעינת רשימה מותאמת אישית ---
    document.getElementById('btnLoadCustom').addEventListener('click', function() {
        const text = document.getElementById('customIdText').value;
        if (!text.trim()) { alert('אנא הדבק רשימת מזהים'); return; }

        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מעבד...';
        
        const formData = new FormData();
        formData.append('action', 'parse_custom_list');
        formData.append('list', text);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            document.getElementById('btnLoadCustom').innerHTML = '<i class="fas fa-list-check"></i> טען רשימה והחלף תור';
            if (data.status === 'success') {
                currentQueue = data.queue;
                isCustomMode = true; // הפעלת מצב ידני
                localStorage.setItem('imdb_sync_queue', JSON.stringify(currentQueue));
                localStorage.setItem('imdb_sync_mode', 'custom');
                renderQueue(currentQueue);
                document.getElementById('customIdText').value = ''; // ניקוי
                alert('נטענו ' + data.found + ' סרטים לתור. לחץ על "התחל" כדי לעבד אותם.');
            } else {
                alert(data.msg);
            }
        })
        .catch(err => {
            document.getElementById('btnLoadCustom').innerHTML = 'טען רשימה';
            alert('שגיאת שרת');
        });
    });
    // ------------------------------------------

    document.getElementById('startSync').addEventListener('click', function(){ 
        const scope = document.querySelector('input[name="sync_scope"]:checked').value;
        
        this.classList.add('d-none'); 
        document.getElementById('stopSync').classList.remove('d-none'); 
        document.querySelectorAll('input[name="sync_scope"]').forEach(el => el.disabled = true);
        document.getElementById('btnLoadCustom').disabled = true; // נעל כפתור טעינה
        
        const loader = `<div class='text-info mb-2'>⏳ מתחיל...</div>`;
        if (logUpdated.innerHTML.indexOf('ממתין') !== -1) logUpdated.innerHTML = loader;
        else logUpdated.innerHTML += loader;

        // אם התור ריק, או שלא בחרנו מצב מותאם - נטען מהשרת אוטומטית
        if (currentQueue.length === 0 && !isCustomMode) {
            queueList.innerHTML = `<div class='text-info p-2'>טוען רשימה מהשרת...</div>`;
            fetch('?mode=get_queue&scope=' + scope)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        currentQueue = data.queue;
                        isCustomMode = false; 
                        localStorage.setItem('imdb_sync_queue', JSON.stringify(currentQueue));
                        localStorage.setItem('imdb_sync_mode', 'auto');
                        renderQueue(currentQueue);
                        isSyncing = true;
                        runBatch(); 
                    } else {
                        alert('שגיאה בטעינת התור: ' + data.msg);
                        stopSync();
                    }
                })
                .catch(err => { stopSync(); });
        } else {
            // יש כבר תור (או משוחזר, או ידני)
            isSyncing = true;
            runBatch();
        }
    });

    document.getElementById('stopSync').addEventListener('click', stopSync);

    function stopSync() {
        isSyncing = false; 
        document.getElementById('stopSync').classList.add('d-none'); 
        document.getElementById('startSync').classList.remove('d-none');
        document.querySelectorAll('input[name="sync_scope"]').forEach(el => el.disabled = false);
        document.getElementById('btnLoadCustom').disabled = false;
        logUpdated.innerHTML += "<div class='text-warning border-top pt-2'>🛑 נעצר. הרשימה נשמרה בזיכרון.</div>";
    }
    
    function clearStorage() {
        if (confirm('האם אתה בטוח?')) {
            localStorage.removeItem('imdb_sync_queue');
            localStorage.removeItem('imdb_sync_mode');
            currentQueue = [];
            isCustomMode = false;
            queueList.innerHTML = "<div class='text-muted p-2'>הרשימה נוקה.</div>";
            queueCount.innerText = "0";
            alert('הזיכרון נוקה.');
        }
    }

    function renderQueue(items) {
        queueCount.innerText = items.length;
        if (items.length === 0) {
            queueList.innerHTML = "<div class='text-success p-2'>אין פריטים לעדכון!</div>";
            return;
        }
        let html = '';
        items.forEach(item => {
            const title = item.title_he || item.title_en;
            const imdbId = item.imdb_id || '';
            html += `<div id="q-item-${item.id}" class="queue-item px-2">
                        <a href="poster.php?id=${item.id}" target="_blank" class="text-info text-decoration-none text-truncate" style="flex:1">${item.id}: ${title}</a>
                        <input type="text" value="${imdbId}" class="imdb-id-input form-control form-control-sm bg-dark text-warning border-secondary ms-2 font-monospace text-center" style="width: 110px; height: 24px; font-size: 0.8rem; padding: 0 5px;" readonly onclick="this.select()">
                     </div>`;
        });
        queueList.innerHTML = html;
    }

    function copyAllQueueIds() {
        if (currentQueue.length === 0) { alert('אין מזהים ברשימה'); return; }
        const allIds = currentQueue.map(item => item.imdb_id ? item.imdb_id.trim() : '').filter(id => id !== '');
        if (allIds.length === 0) { alert('נמצאו פריטים אך הם ללא ID'); return; }
        navigator.clipboard.writeText(allIds.join('\n')).then(() => { alert('הועתק ללוח'); });
    }

    function runBatch() {
        if (!isSyncing) return;
        
        let url = '?mode=ajax_sync';
        const scope = document.querySelector('input[name="sync_scope"]:checked').value;

        // אם אנחנו במצב "מותאם אישית", אנחנו לוקחים את ה-ID הראשון בתור ואומרים לשרת לעדכן רק אותו
        if (isCustomMode) {
            if (currentQueue.length === 0) {
                finishJob();
                return;
            }
            const nextItem = currentQueue[0];
            url += '&specific_id=' + nextItem.id; 
        } else {
            // מצב אוטומטי - השרת בוחר לפי תאריך
            url += '&scope=' + scope;
        }

        fetch(url)
            .then(async res => {
                const text = await res.text();
                try { return JSON.parse(text); } catch (e) { throw new Error("תגובת שרת לא תקינה"); }
            })
            .then(data => {
                if (data.status === 'done' && !isCustomMode) { 
                    finishJob(); return; 
                }
                
                // עדכון התור
                if (data.processed_ids && data.processed_ids.length > 0) {
                    currentQueue = currentQueue.filter(item => !data.processed_ids.includes(item.id));
                    data.processed_ids.forEach(id => {
                        const el = document.getElementById(`q-item-${id}`);
                        if (el) el.remove();
                    });
                    queueCount.innerText = currentQueue.length;
                    localStorage.setItem('imdb_sync_queue', JSON.stringify(currentQueue));
                } 
                // אם במצב ידני ולא הוחזרו ID מעובדים (למשל שגיאה), נדלג על הפריט כדי למנוע לולאה אינסופית
                else if (isCustomMode && currentQueue.length > 0) {
                     // אופציונלי: מנגנון מתוחכם יותר, כרגע נשאיר אותו שינסה שוב או יתקע עד עצירה
                }

                // לוגים
                if (data.updated && data.updated.length > 0) data.updated.forEach(log => logUpdated.innerHTML += `<div>${log}</div>`); 
                if (data.errors && data.errors.length > 0) data.errors.forEach(log => logErrors.innerHTML += `<div>${log}</div>`); 
                if (data.unchanged && data.unchanged.length > 0) data.unchanged.forEach(log => logUnchanged.innerHTML += `<div>${log}</div>`); 
                
                logUpdated.scrollTop = logUpdated.scrollHeight; 
                logErrors.scrollTop = logErrors.scrollHeight; 
                logUnchanged.scrollTop = logUnchanged.scrollHeight; 

                // בדיקת סיום במצב ידני
                if (isCustomMode && currentQueue.length === 0) {
                    finishJob();
                } else if (isSyncing) {
                    setTimeout(runBatch, 200); 
                } 
            })
            .catch(err => {
                logErrors.innerHTML += `<div class='text-danger small'>שגיאה: ${err.message} - מנסה שוב...</div>`;
                if (isSyncing) setTimeout(runBatch, 3000); 
            });
    }

    function finishJob() {
        logUpdated.innerHTML += "<div class='text-success fw-bold border-top pt-2 mt-2'>--- ✅ הסתיים! ---</div>"; 
        queueList.innerHTML = "<div class='text-success p-2 fw-bold'>התור התרוקן!</div>";
        queueCount.innerText = "0";
        localStorage.removeItem('imdb_sync_queue');
        localStorage.removeItem('imdb_sync_mode');
        currentQueue = [];
        isCustomMode = false;
        stopSync(); 
    }

    const fixModal = new bootstrap.Modal(document.getElementById('fixIdModal'));
    function openFixModal(id, title, currentImdb) {
        document.getElementById('fixDbId').value = id;
        document.getElementById('fixImdbVal').value = currentImdb || '';
        document.getElementById('searchLink').href = "https://www.imdb.com/find/?q=" + encodeURIComponent(title);
        fixModal.show();
    }
    function saveFixedId() {
        const id = document.getElementById('fixDbId').value;
        const newImdb = document.getElementById('fixImdbVal').value;
        const formData = new FormData();
        formData.append('action', 'quick_update_id'); formData.append('id', id); formData.append('imdb_id', newImdb);
        fetch(window.location.href, { method: 'POST', body: formData }).then(res=>res.json()).then(data => {
            fixModal.hide();
            logUpdated.innerHTML += `<div class='text-info'>✨ ID תוקן ל-${newImdb}</div>`;
        });
    }
</script>
</body>
<?php include 'footer.php'; ?>
</html>