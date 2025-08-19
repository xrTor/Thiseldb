<?php

$host = 'localhost';
$db   = 'thiseldb';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}


$tmdb_key = '931b94936ba364daf0fd91fb38ecd91e';
$omdb_key = '1ae9a12e';


?>
