<?php
require_once 'server.php';
$conn->set_charset("utf8");

$posterId = isset($_POST['poster_id']) ? intval($_POST['poster_id']) : 0;

if ($posterId > 0) {
    // איפוס העמודה המקורית trailer_url
    $stmt = $conn->prepare("UPDATE posters SET trailer_url = '' WHERE id = ?");
    $stmt->bind_param("i", $posterId);
    $stmt->execute();
    $stmt->close();
}

header("Location: manage_trailers.php");
exit;
?>