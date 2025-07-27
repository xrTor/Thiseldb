<?php
require_once 'server.php';
 
$conn = new mysqli($host, $user, $pass, $db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poster_id = intval($_POST['poster_id']);
    $rating = intval($_POST['rating']);

    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO ratings (poster_id, rating) VALUES (?, ?)");
        $stmt->bind_param("ii", $poster_id, $rating);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: index.php");
exit;
