<?php

$rapidKey  = 'f5d4bd03c8msh29a2dc12893f4bfp157343jsn2b5bfcad5ae1';
$rapidHost = 'imdb236.p.rapidapi.com';

// ✅ שים כאן IMDb ID אמיתי
$imdbId = 'tt0133093'; // The Matrix

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://{$rapidHost}/api/imdb/" . rawurlencode($imdbId),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => [
    "X-RapidAPI-Host: {$rapidHost}",
    "X-RapidAPI-Key: {$rapidKey}",
  ],
]);

$response = curl_exec($curl);
$err      = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

header('Content-Type: text/plain; charset=utf-8');
echo "HTTP: {$httpCode}\n\n";

if ($err) {
  echo "cURL Error #: " . $err;
} else {
  echo $response;
}
