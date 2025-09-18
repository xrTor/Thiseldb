<?php
// Close the database connection that was opened in init.php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<footer style="text-align:center; margin-top:30px; font-size:14px;">
   <a href="index.php"><img src="images/logo1.png" style="width:100px" alt="Thiseldb" title="Thiseldb"></a>
   <br> 
   <p>&copy; <?= date("Y")?></p>
   <a class="white" href="mailto:Thisel.db1@gmail.com">Thisel.db1@gmail.com</a>

   <br><br>
   סטטיסטיקה:<br><br>
   <div class="box center">
     <span><strong><img src="images/types/posters.png" alt="Poster" width="30px" style="vertical-align: middle;"> פוסטרים: </strong><?= number_format($total_posters) ?></span> |
     <a href="collections.php" target="_blank" class="white"><strong><img style="vertical-align: middle;" src="images/types/archive.png" alt="Archive" width="27px"> אוספים: </strong><?= number_format($total_collections) ?></a> | 
     <a href="home.php?type%5B%5D=3" target="_blank" class="white"><strong><img src="images/types/movie.png" alt="Movie" width="35px" style="vertical-align: middle;"> סרטים: </strong><?= number_format($total_movies) ?></a> | 
     <a href="home.php?type%5B%5D=4" target="_blank" class="white"><strong><img style="vertical-align: middle;" src="images/types/series.png" alt="Series" width="35px"> סדרות: </strong><?= number_format($total_series) ?></a> | 
     <span><strong><img src="images/types/visitors.png" alt="Visitors" width="30px" style="vertical-align: middle;"> קליקים: </strong><?= number_format($total_views) ?></span> |
     <span><strong><img src="images/types/visitors.png" alt="Unique Visitors" width="30px" style="vertical-align: middle;"> מבקרים: </strong><?= number_format($unique_visitors) ?></span> |
     <a href="https://github.com/xrTor/Thiseldb" target="_blank" class="white"><img src="images/types/github.png" class="logo" alt="Github" width="35px" style="vertical-align: middle;"> קוד מקור</a>
   </div><br><br>
</footer>

</body>
</html>