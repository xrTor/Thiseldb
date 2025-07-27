<?php
require_once 'server.php';

$cid = intval($_POST['collection_id'] ?? 0);
$raw = trim($_POST['poster_ids_raw'] ?? '');
$lines = explode("\n", $raw);
$target_ids = [];

foreach ($lines as $line) {
  $val = trim($line);
  if (!$val) continue;

  // אם מזהה מסוג IMDB
  if (preg_match('/^tt\d{6,}$/', $val)) {
    $stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
    $stmt->bind_param("s", $val);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $target_ids[] = intval($row['id']);
    $stmt->close();
  }

  // אם מזהה מספרי רגיל
  elseif (ctype_digit($val)) {
    $stmt = $conn->prepare("SELECT id FROM posters WHERE id = ?");
    $idval = intval($val);
    $stmt->bind_param("i", $idval);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $target_ids[] = $idval;
    $stmt->close();
  }
}

$target_ids = array_unique($target_ids);

if ($cid > 0 && count($target_ids) > 0) {
  $del = $conn->prepare("DELETE FROM poster_collections WHERE poster_id = ? AND collection_id = ?");
  foreach ($target_ids as $pid) {
    $del->bind_param("ii", $pid, $cid);
    $del->execute();
  }
  $del->close();

  header("Location: collection.php?id=$cid&msg=removed");
  exit;
} else {
  header("Location: collection.php?id=$cid&msg=error");
  exit;
}

$conn->close();
