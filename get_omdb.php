<?php
if (!isset($_GET['imdb_id'])) die('{}');
preg_match('/tt\d{7,8}/', $_GET['imdb_id'], $m);
$imdb = $m[0] ?? '';
if (!$imdb) die('{}');
$json = @file_get_contents("https://www.omdbapi.com/?apikey=1ae9a12e&i=$imdb&plot=full&r=json");
header('Content-Type: application/json');
echo $json;
