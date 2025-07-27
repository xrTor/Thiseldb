<?php include 'header.php'; 
require_once 'server.php';

$conn->set_charset("utf8");

// קבלת פרטי הטופס
$posterId = intval($_POST['poster_id'] ?? 0);
$trailerLink = trim($_POST['youtube_trailer'] ?? '');

if ($posterId && filter_var($trailerLink, FILTER_VALIDATE_URL)) {
    // שמירת הקישור במסד
    $stmt = $conn->prepare("UPDATE posters SET youtube_trailer = ? WHERE id = ?");
    $stmt->bind_param("si", $trailerLink, $posterId);
    $stmt->execute();
    $stmt->close();

    // הפניה חזרה לעמוד הראשי
    header("Location: poster_trailers.php");
    exit;
} else {
    // הודעה בשגיאה בסיסית
    echo "❌ קישור לא תקין או מזהה חסר.";
}
?>
<?php include 'footer.php'; ?>