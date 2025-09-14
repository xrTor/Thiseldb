<?php

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

// בדוק אם החיבור קיים ופתוח לפני הסגירה
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<footer style="text-align:center; margin-top:30px; font-size:14px;">
   <a href="index.php"><img src="images/logo1.png" style="width:100px" alt="Thiseldb" title:"Thiseldb"></a>
   <br> 
   <p>&copy; <?= date("Y")?></p>
   <a class="white" href=mailto:"Thisel.db1@gmail.com">Thisel.db1@gmail.com</a>

   <br><br>
   סטטיסטיקה:<br><br>
   <div class="box center">
     <span><strong><img src="images/types/posters.png" alt="Poster" width="30px" style="vertical-align: middle;">
     פוסטרים: </strong><?= number_format($total) ?> |
     <span class="white"><a href="collections.php" target="_blank" class="white"><strong>  
       <img style="vertical-align: middle;" src="images/types/archive.png" alt="Archive" width="27px"> אוספים: </strong></span></a><?= number_format($stats['collections']) ?> | 
     <span class="white"><a href="home.php?type%5B%5D=3" target="_blank" class="white"><strong>
       <img src="images/types/movie.png" alt="Movie" width="35px" style="vertical-align: middle;"> סרטים: </strong></span></a><?= number_format($count_movies) ?> | 
     <span class="white"><a href="home.php?type%5B%5D=4" target="_blank" class="white"><strong>
       <img style="vertical-align: middle;" src="images/types/series.png" alt="Series" width="35px"> סדרות: </strong></span><?= number_format($count_series) ?></a> | 

     <span class="white"><a href="https://github.com/xrTor/Thiseldb" target="_blank" class="white">
       <img src="images/types/github.png" class="logo" alt="Github" width="35px" style="vertical-align: middle;">קוד מקור</span></a><br>
   </div><br><br>
</footer>

</body>
</html>
