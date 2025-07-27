<?php
require_once 'server.php';

$cid = intval($_POST['collection_id'] ?? 0);
$pid = intval($_POST['poster_id'] ?? 0);

if ($cid > 0 && $pid > 0) {
  $stmt = $conn->prepare("DELETE FROM poster_collections WHERE poster_id = ? AND collection_id = ?");
  $stmt->bind_param("ii", $pid, $cid);
  $stmt->execute();
  $stmt->close();
}

header("Location: collection.php?id=$cid");
exit;
$conn->close();
