<?
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // במקום להדפיס הודעה גנרית, נציג את השגיאה המדויקת מ-MySQL
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}
