<?php
/****************************************************
 * server.php — חיבור למסד + מפתחות API
 ****************************************************/

// ===== חיבור למסד =====
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "thiseldb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ===== מפתחות API =====
define('TMDB_KEY',     '931b94936ba364daf0fd91fb38ecd91e');
define('RAPIDAPI_KEY', 'f5d4bd03c8msh29a2dc12893f4bfp157343jsn2b5bfcad5ae1'); // IMDb rating/votes בלבד
define('TVDB_KEY',     '1c93003f-ab80-4baf-b5c4-58c7b96494a2');               // TheTVDB v4
define('OMDB_KEY',     'f7e4ae0b');                                          // OMDb — RT/MC
?>
<?php

$host = 'localhost';
$db   = 'thiseldb';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$tmdb_key = '931b94936ba364daf0fd91fb38ecd91e';
$omdb_key = 'f7e4ae0b';
$tvdbKey = '1c93003f-ab80-4baf-b5c4-58c7b96494a2'; // TVDB v4
$rapidApiKey = 'f5d4bd03c8msh29a2dc12893f4bfp157343jsn2b5bfcad5ae1'; // הוספת מפתח RapidAPI

$TMDB_KEY      = '931b94936ba364daf0fd91fb38ecd91e';
$RAPIDAPI_KEY  = 'f5d4bd03c8msh29a2dc12893f4bfp157343jsn2b5bfcad5ae1';
$TVDB_KEY      = '1c93003f-ab80-4baf-b5c4-58c7b96494a2'; // <<< הוסף כאן את המפתח שלך ל-TheTVDB v4

?>
