<?php
$imdb_id = $_GET['imdb'] ?? '';

$conn = new mysqli("localhost", "root", "123456", "media");
if ($conn->connect_error) {
    die("DB error");
}

$stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
$stmt->bind_param("s", $imdb_id);
$stmt->execute();
$stmt->store_result();

echo ($stmt->num_rows > 0) ? 'true' : 'false';

$stmt->close();
$conn->close();
?>
