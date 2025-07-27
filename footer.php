<?php
 require_once 'server.php';
 
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

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
?>


<footer style="text-align:center; margin-top:30px; font-size:14px;">
   <a href="index.html"><img src="images/logo1.png" style="width:100px" alt="Thiseldb" title:"Thiseldb"></a>
   <br> 
   <p>&copy; <?= date("Y")?>
</p>
Thisel.db1@gmail.com
<br><br>
  סטטיסטיקה:

  <div class="box">
 <span><a href="movies.php">🎬 סרטים: <strong><?= $count_movies ?></a></strong></span> |
  <span><a href="series.php">📺 סדרות: <strong><?= $count_series ?></a></strong></span> |
  <span><a href="https://github.com/xrTor/Thiseldb_V1" target="_blank">קוד מקור</span></a><br>
 </div><br><br>
</footer>

</body>
</html>
