<?php
 require_once 'server.php';
 
$result = $conn->query("SHOW CREATE TABLE posters");
$row = $result->fetch_assoc();

echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
?>
