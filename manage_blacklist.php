<?php include 'header.php'; 
require_once 'server.php';
$message = "";

// הוספת מזהה חדש
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $type = $_POST['type'];
    $identifier = trim($_POST['identifier']);
    
    if (!empty($identifier)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO blacklist (type, identifier) VALUES (?, ?)");
        $stmt->bind_param("ss", $type, $identifier);
        if ($stmt->execute()) {
            $message = "המזהה $identifier ($type) נוסף לרשימה השחורה.";
        }
    }
}

// מחיקת מזהה מהרשימה
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM blacklist WHERE id = $id");
    $message = "המזהה הוסר מהרשימה השחורה.";
}

// שליפת הרשימה להצגה
$list = $conn->query("SELECT * FROM blacklist ORDER BY added_at DESC");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Thiseldb - ניהול Blacklist</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; margin: 0px; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
        form { display: flex; gap: 10px; margin-bottom: 30px; }
        select, input, button { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #d9534f; color: white; border: none; cursor: pointer; }
        button:hover { background: #c9302c; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: right; }
        .delete-link { color: #d9534f; text-decoration: none; font-weight: bold; }
        .msg { background: #dff0d8; color: #3c763d; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1>ניהול רשימה שחורה (Blacklist)</h1>
    
    <?php if ($message) echo "<div class='msg'>$message</div>"; ?>

    <form method="POST">
        <input type="hidden" name="action" value="add">
        <select name="type">
            <option value="imdb">IMDB ID</option>
            <option value="tmdb">TMDB ID</option>
            <option value="tvdb">TVDB ID</option>
        </select>
        <input type="text" name="identifier" placeholder="הכנס מזהה (למשל tt12345)" required>
        <button type="submit" style="background: #337ab7;">הוסף לחסימה</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>סוג</th>
                <th>מזהה</th>
                <th>תאריך הוספה</th>
                <th>פעולות</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $list->fetch_assoc()): ?>
            <tr>
                <td><strong><?php echo strtoupper($row['type']); ?></strong></td>
                <td><?php echo $row['identifier']; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($row['added_at'])); ?></td>
                <td><a href="?delete=<?php echo $row['id']; ?>" class="delete-link" onclick="return confirm('להסיר מהחסימה?')">מחיקה</a></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>

<?php include 'footer.php'; ?>