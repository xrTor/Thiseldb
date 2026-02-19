<?php
require_once 'server.php';
$conn->set_charset("utf8");

// קבלת נתונים
$posterId = isset($_POST['poster_id']) ? intval($_POST['poster_id']) : 0;
// שינוי שם השדה ל-trailer_url
$trailerLink = isset($_POST['trailer_url']) ? trim($_POST['trailer_url']) : '';

if ($posterId > 0 && !empty($trailerLink)) {
    // עדכון העמודה המקורית trailer_url
    $stmt = $conn->prepare("UPDATE posters SET trailer_url = ? WHERE id = ?");
    $stmt->bind_param("si", $trailerLink, $posterId);
    $stmt->execute();
    $stmt->close();
}

header("Location: manage_trailers.php");
exit;
?>