<?php
/****************************************************
 * chat.php — צ'אט אנונימי עם מצב מנהל
 * PHP + MySQL + Long Polling
 ****************************************************/
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=UTF-8");
require_once __DIR__ . "/server.php"; // חיבור למסד

$isAdmin = isset($_GET['admin']) && $_GET['admin'] == 1;

// === API פנימי ===
if (isset($_GET['action'])) {
    // שליחת הודעה
    if ($_GET['action'] === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nickname = !empty($_POST['nickname']) ? trim($_POST['nickname']) : 'אורח';
        $message  = trim($_POST['message'] ?? '');
        if ($message !== '') {
            $stmt = $conn->prepare("INSERT INTO chat_messages (nickname, message) VALUES (?, ?)");
            $stmt->bind_param("ss", $nickname, $message);
            $stmt->execute();
        }
        exit;
    }

    // שליפה (Long Polling)
    if ($_GET['action'] === 'fetch') {
        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $start = time();
        while (true) {
            $result = $conn->query("SELECT id, nickname, message, created_at FROM chat_messages WHERE id > $lastId ORDER BY id ASC");
            if ($result && $result->num_rows > 0) {
                $messages = [];
                while ($row = $result->fetch_assoc()) {
                    $messages[] = $row;
                }
                echo json_encode($messages, JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (time() - $start > 20) { echo json_encode([]); exit; }
            usleep(500000);
        }
    }

    // מחיקת הודעה (רק למנהל)
    if ($_GET['action'] === 'delete' && $isAdmin && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $conn->query("DELETE FROM chat_messages WHERE id = $id");
        exit;
    }

    // ריקון כל ההיסטוריה (רק למנהל)
    if ($_GET['action'] === 'clear' && $isAdmin) {
        $conn->query("TRUNCATE TABLE chat_messages");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  

<meta charset="UTF-8">
<title>צ'אט בזמן אמת<?php echo $isAdmin ? " — מצב מנהל" : ""; ?></title>
<style>
body { background:#0a0a0a; color:#fff; font-family: Arial, "Noto Sans Hebrew", sans-serif; margin:0; padding:0; }
h1 { text-align:center; padding:15px; margin:0; background:#111827; color:#00ffcc; }
#chat-box { border:1px solid #333; height:250px; overflow-y:auto; padding:10px; background:#111; margin:15px auto; width:90%; max-width:800px; border-radius:10px; }
.message { margin:5px 0; padding:5px 8px; background:#1e1e1e; border-radius:5px; position:relative; }
.message b { color:#00ffcc; }
.message small { color:#aaa; font-size:11px; }
.delete-btn { position:absolute; top:5px; left:5px; background:red; color:white; border:none; padding:2px 5px; cursor:pointer; font-size:10px; border-radius:3px; }
form { text-align:center; margin:10px auto; width:90%; max-width:800px; }
input, button { padding:8px; border-radius:5px; border:1px solid #444; margin:2px; }
#nickname { width:100px; }
#message { width:60%; }
button { background:#00ffcc; color:#000; font-weight:bold; cursor:pointer; }
button:hover { background:#00e6b8; }
#clear-btn { display:block; margin:10px auto; background:#ff3333; color:white; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; }
#clear-btn:hover { background:#cc0000; }
#admin-toggle { position:absolute; top:15px; left:15px; background:#333; color:#fff; border:none; padding:5px 10px; border-radius:5px; cursor:pointer; }

/* .admin-only {
    display: none !important;
} */

    
/* --- Hide Admin Button ---
#admin-toggle {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
} */
</style>
</head>
<body>

<h1>💬 צ'אט בזמן אמת <?php echo $isAdmin ? "(מצב מנהל)" : ""; ?></h1>

<button id="admin-toggle" onclick="toggleAdmin()">
    <?php echo $isAdmin ? "🔓 יציאה ממצב ניהול" : "🔑 מצב ניהול"; ?>
</button>

<div id="chat-box"></div>

<form id="chat-form">
    <input type="text" id="nickname" placeholder="שם (רשות)">
    <input type="text" id="message" placeholder="כתוב הודעה...">
    <button type="submit">שלח</button>
</form>

<?php if ($isAdmin): ?>
<button id="clear-btn">🗑️ ריקון כל ההיסטוריה</button>
<?php endif; ?>

<script>
let lastId = 0;
let isAdmin = <?php echo $isAdmin ? "true" : "false"; ?>;

function fetchMessages() {
    fetch("chat.php?action=fetch&last_id=" + lastId + "<?php echo $isAdmin ? "&admin=1" : ""; ?>")
        .then(res => res.json())
        .then(data => {
            if(data.length > 0){
                data.forEach(m => {
                    let div = document.createElement("div");
                    div.classList.add("message");
                    div.innerHTML = `<b>${m.nickname}:</b> ${m.message} 
                                     <br><small>${m.created_at}</small>`;
                    if(isAdmin){
                        div.innerHTML += `<button class="delete-btn" onclick="deleteMessage(${m.id})">X</button>`;
                    }
                    document.getElementById("chat-box").appendChild(div);
                    lastId = m.id;
                });
                document.getElementById("chat-box").scrollTop = document.getElementById("chat-box").scrollHeight;
            }
            fetchMessages();
        })
        .catch(() => setTimeout(fetchMessages, 2000));
}

document.getElementById("chat-form").addEventListener("submit", function(e){
    e.preventDefault();
    let nickname = document.getElementById("nickname").value;
    let message  = document.getElementById("message").value;
    if(message.trim() !== "") {
        fetch("chat.php?action=send<?php echo $isAdmin ? "&admin=1" : ""; ?>", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "nickname="+encodeURIComponent(nickname)+"&message="+encodeURIComponent(message)
        }).then(() => {
            document.getElementById("message").value = "";
        });
    }
});

if(isAdmin){
    document.getElementById("clear-btn").addEventListener("click", function(){
        if(confirm("למחוק את כל ההיסטוריה?")) {
            fetch("chat.php?action=clear&admin=1").then(() => {
                document.getElementById("chat-box").innerHTML = "";
                lastId = 0;
                fetchMessages();
            });
        }
    });
}

function deleteMessage(id) {
    fetch("chat.php?action=delete&admin=1", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id="+id
    }).then(() => {
        document.getElementById("chat-box").innerHTML = "";
        lastId = 0;
        fetchMessages();
    });
}

function toggleAdmin(){
    if(isAdmin){
        window.location.href = "chat.php";
    } else {
        window.location.href = "chat.php?admin=1";
    }
}

// הפעלה ראשונית
fetchMessages();
</script>

</body>
</html>
