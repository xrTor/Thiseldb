<?php
include 'header.php';
$host = 'localhost';
$db = 'media';
$user = 'root';
$pass = '123456';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// הוספת תגית
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_category'])) {
    $name = trim($_POST['new_category']);
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->close();
    }
}

// מחיקת תגית
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM categories WHERE id = $id");
}

$categories = $conn->query("SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>ניהול תגיות</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2 style="text-align:center;">🏷️ ניהול תגיות / קטגוריות</h2>

  <form method="post" style="text-align:center; margin-bottom:20px;">
    <input type="text" name="new_category" placeholder="שם תגית חדשה" required>
    <button type="submit">➕ הוסף</button>
  </form>

  <div style="max-width:400px; margin:auto;">
    <ul style="list-style-type:none; padding:0;">
      <?php while($row = $categories->fetch_assoc()): ?>
        <li style="margin-bottom:10px;">
          <?= htmlspecialchars($row['name']) ?>
          <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('למחוק תגית זו?')">🗑️</a>
        </li>
      <?php endwhile; ?>
    </ul>
  </div>

  <div style="text-align:center;margin-top:30px;">
    <a href="index.php">⬅ חזרה</a>
  </div>
</body>
</html>
