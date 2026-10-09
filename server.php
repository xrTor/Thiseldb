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
define('TMDB_KEY',     'YOUR_KEY');
define('RAPIDAPI_KEY', 'YOUR_KEY'); // IMDb rating/votes בלבד
define('TVDB_KEY',     'YOUR_KEY');               // TheTVDB v4
define('OMDB_KEY',     'YOUR_KEY');                                          // OMDb — RT/MC
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

$tmdb_key = 'YOUR_KEY';
$omdb_key = 'YOUR_KEY';
$omdbKey = 'YOUR_KEY';
$tvdbKey = 'YOUR_KEY'; // TVDB v4
$rapidApiKey = 'YOUR_KEY'; // הוספת מפתח RapidAPI

$TMDB_KEY      = 'YOUR_KEY';
$RAPIDAPI_KEY  = 'YOUR_KEY';
$TVDB_KEY      = 'YOUR_KEY'; // <<< הוסף כאן את המפתח שלך ל-TheTVDB v4

?>
