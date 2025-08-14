<?php
require_once 'server.php';

set_time_limit(3000000);

$cid = intval($_POST['collection_id'] ?? 0);
$raw = trim($_POST['poster_ids_raw'] ?? '');
$final_ids = [];

if ($cid > 0 && $raw !== '') {
  // תומך בפסיקים/נקודות פסיק/שורות
  $raw = str_replace([",", ";"], "\n", $raw);
  $lines = explode("\n", $raw);

  foreach ($lines as $line) {
    $val = trim($line);
    
    // חילוץ מזהה IMDb מהשורה גם אם מופיעה יחד עם טקסט
    if (preg_match('/tt\d{6,}/', $val, $matches)) {
      $imdb = $matches[0];
      $stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
      $stmt->bind_param("s", $imdb);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $final_ids[] = intval($row['id']);
      }
      $stmt->close();
      continue;
    }

    // אם זה מזהה מספרי רגיל
    if (ctype_digit($val)) {
      $idval = intval($val);
      $stmt = $conn->prepare("SELECT id FROM posters WHERE id = ?");
      $stmt->bind_param("i", $idval);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $final_ids[] = $idval;
      }
      $stmt->close();
    }
  }

  $final_ids = array_unique($final_ids);

  if (count($final_ids) > 0) {
    $check = $conn->prepare("SELECT 1 FROM poster_collections WHERE poster_id = ? AND collection_id = ?");
    $insert = $conn->prepare("INSERT INTO poster_collections (poster_id, collection_id) VALUES (?, ?)");

    foreach ($final_ids as $pid) {
      $check->bind_param("ii", $pid, $cid);
      $check->execute();
      $check->store_result();
      if ($check->num_rows === 0) {
        $insert->bind_param("ii", $pid, $cid);
        $insert->execute();
      }
    }

    $check->close();
    $insert->close();
    header("Location: collection.php?id=$cid&msg=linked");
    exit;
  } else {
    header("Location: collection.php?id=$cid&msg=notfound");
    exit;
  }
} else {
  header("Location: collection.php?id=$cid&msg=error");
  exit;
}

$conn->close();
