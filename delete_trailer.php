<?php
require_once 'server.php';
$conn->set_charset("utf8");

$posterId = intval($_POST['poster_id'] ?? 0);

if ($posterId) {
    $stmt = $conn->prepare("UPDATE posters SET youtube_trailer = '' WHERE id = ?");
    $stmt->bind_param("i", $posterId);
    $stmt->execute();
    $stmt->close();

    header("Location: poster_trailers.php");
    exit;
} else {
    echo "❌ מזהה פוסטר חסר.";
}
?>