<?php
require_once 'server.php';

// קלט מהטופס
$cid = intval($_POST['collection_id'] ?? 0);
$pid = intval($_POST['poster_id'] ?? 0);

// בדיקת תקינות
if ($cid && $pid) {
  $check = $conn->query("SELECT 1 FROM poster_collections WHERE poster_id=$pid AND collection_id=$cid");

  if ($check->num_rows == 0) {
    $conn->query("INSERT INTO poster_collections (poster_id, collection_id) VALUES ($pid, $cid)");
    header("Location: manage_collections.php?msg=linked");
  } else {
    header("Location: manage_collections.php?msg=exists");
  }
} else {
  header("Location: manage_collections.php?msg=error");
}

$conn->close();
?>
