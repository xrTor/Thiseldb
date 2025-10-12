<?php
/****************************************************
 * server.php — חיבור למסד + מפתחות API
 ****************************************************/

// ===== חיבור למסד =====
$servername = "localhost";
$username   = "root";
$password   = "";           // בXAMPP אין סיסמא כברירת מחדל
$dbname     = "thiseldb"; // תוכלו לקרוא איך שתרצו למסד טבלה 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ===== מפתחות API =====
define('TMDB_KEY',     'Insert_KEY');
define('RAPIDAPI_KEY', 'Insert_KEY');                // IMDb rating/votes בלבד
define('TVDB_KEY',     'Insert_KEY');               // TheTVDB v4
define('OMDB_KEY',     'Insert_KEY');               // OMDb — RT/MC


$tmdb_key = 'Insert_KEY';
$omdb_key = 'Insert_KEY';

$TMDB_KEY      = 'Insert_KEY';
$RAPIDAPI_KEY  = 'Insert_KEY';
$TVDB_KEY      = 'Insert_KEY'; 


$host = 'localhost';
$db   = 'thiseldb';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// שדה הסברה

// // ===== מפתחות API =====
// // החליפו רק את הInsert_KEY במפתחות שקיבלת מהשירות השונים
// define('TMDB_KEY',     'Insert_KEY');           // TMDB
// define('RAPIDAPI_KEY', 'Insert_KEY');          // IMDb rating/votes בלבד
// define('TVDB_KEY',     'Insert_KEY');         // TheTVDB v4
// define('OMDB_KEY',     'Insert_KEY');       // OMDb — RT/MC

// לינקים
// https://www.themoviedb.org/settings/api/request
// https://rapidapi.com/rapidapi-org1-rapidapi-org-default/api/imdb236
// https://www.thetvdb.com/api-information/signup
// https://www.omdbapi.com/apikey.aspx
?>
