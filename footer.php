<?php
 require_once 'server.php';
 
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


// ספירה כללית
$total_query = $conn->query("SELECT COUNT(*) AS total FROM posters");
$total = $total_query->fetch_assoc()['total'] ?? 0;

// ספירה לפי סוג
$count_series = $conn->query("
  SELECT COUNT(*) AS c
  FROM posters p
  JOIN poster_types pt ON pt.id = p.type_id
  WHERE pt.code = 'series'
")->fetch_assoc()['c'];

$count_movies = $conn->query("
  SELECT COUNT(*) AS c
  FROM posters p
  JOIN poster_types pt ON pt.id = p.type_id
  WHERE pt.code = 'movie'
")->fetch_assoc()['c'];

// ספירה לפי תגית
$tags = $conn->query("
  SELECT c.name, COUNT(pc.poster_id) AS total
  FROM categories c
  JOIN poster_categories pc ON c.id = pc.category_id
  GROUP BY c.id
  ORDER BY total DESC
");

// --- התיקון כאן ---
// הגדר את הפונקציה רק אם היא לא קיימת עדיין
if (!function_exists('safeCount')) {
    function safeCount($conn, $table)
    {
        $res = $conn->query("SELECT COUNT(*) as c FROM $table");
        return ($res && $res->num_rows > 0) ? $res->fetch_assoc()['c'] : 0;
    }
}

// המשתנה stats יחושב רק אם הוא לא הוגדר כבר (כמו בעמוד הפאנל)
if (!isset($stats)) {
    $stats = [
        'collections' => safeCount($conn, 'collections'),
    ];
}

?>

<footer style="text-align:center; margin-top:30px; font-size:14px;">
   <a href="index.php"><img src="images/logo1.png" style="width:100px" alt="Thiseldb" title:"Thiseldb"></a>
   <br> 
   <p>&copy; <?= date("Y")?>
</p>
<a href=mailto:"Thisel.db1@gmail.com">Thisel.db1@gmail.com</a>

<br><br>
  סטטיסטיקה:
  <div class="box center">
    
    <span><strong>📁 פוסטרים: </strong><?= $total ?> |
      <span class="white"><a href="collections.php" target="_blank" class="white"><strong>📦אוספים: </strong></span></a><?= $stats['collections'] ?> | 
  <span class="white"><a href="home.php?type%5B%5D=3" target="_blank" class="white"><strong>🎞️ סרטים: </strong></span></a><?= $count_movies ?> | 
  <span class="white"><a href="home.php?type%5B%5D=4" target="_blank" class="white"><strong>📺 סדרות: </strong></span><?= $count_series ?></a> | 

  <span class="white"><a href="https://github.com/xrTor/Thiseldb" target="_blank" class="white">קוד מקור</span></a><br>
 </div><br><br>
</footer>

</body>
</html>