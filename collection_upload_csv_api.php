<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'server.php';
set_time_limit(3000000);

function jerr($msg, $extra = []) {
  http_response_code(400);
  echo json_encode(array_merge(['error' => $msg], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

$collection_id = isset($_POST['collection_id']) ? intval($_POST['collection_id']) : 0;
if ($collection_id === 0) jerr('אוסף לא צוין.');

$stmt = $conn->prepare("SELECT id FROM collections WHERE id = ?");
$stmt->bind_param("i", $collection_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) jerr('האוסף לא נמצא.');
$stmt->close();

if (!isset($_FILES['ids_file']) || $_FILES['ids_file']['error'] !== UPLOAD_ERR_OK) {
  jerr('לא הועלה קובץ תקין.', ['php_error' => $_FILES['ids_file']['error'] ?? null]);
}

$file = $_FILES['ids_file'];
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) jerr('הקובץ גדול מדי (מקסימום 5MB).');

$raw = @file_get_contents($file['tmp_name']);
if ($raw === false) jerr('קריאת הקובץ נכשלה.');

// הסרת BOM
$raw = preg_replace("/^\xEF\xBB\xBF/", '', $raw);
$lines = preg_split("/\r\n|\n|\r/", $raw);
$lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

// זיהוי מפריד משוער מהשורה הראשונה
$delimiter = null;
if (!empty($lines)) {
  $first = $lines[0];
  $cands = [",",";","\t","|"];
  $best = null; $count = -1;
  foreach ($cands as $c) {
    $cnt = substr_count($first, $c);
    if ($cnt > $count) { $count = $cnt; $best = $c; }
  }
  $delimiter = ($count > 0) ? $best : null;
}

$tokens = [];
$isYear = function($s){
  if (strlen($s) !== 4) return false;
  $y = (int)$s;
  return ($y >= 1888 && $y <= 2100);
};

foreach ($lines as $line) {
  $cells = $delimiter ? str_getcsv($line, $delimiter) : [$line];
  foreach ($cells as $cellRaw) {
    $cell = trim($cellRaw);
    if ($cell === '') continue;

    if (preg_match('/tt\d{4,12}/i', $cell, $m)) {
      $tokens[] = strtolower($m[0]);
      continue;
    }
    if (preg_match('/^\d{1,10}$/', $cell)) {
      if (!$isYear($cell)) $tokens[] = (string)intval($cell);
      continue;
    }
    if (preg_match('/tt\d{4,12}/i', $cell, $m2)) {
      $tokens[] = strtolower($m2[0]);
      continue;
    }
  }
}

// ייחוד
$seen = []; $unique = [];
foreach ($tokens as $t) if (!isset($seen[$t])) { $seen[$t] = 1; $unique[] = $t; }
$tokens = $unique;

$imdbs = []; $locals = [];
foreach ($tokens as $t) {
  if (stripos($t, 'tt') === 0) $imdbs[] = strtolower($t);
  elseif (ctype_digit($t)) $locals[] = (int)$t;
}

// מציאת פוסטרים לפי imdb_id
$foundByImdb = [];
if ($imdbs) {
  $chunks = array_chunk($imdbs, 500);
  foreach ($chunks as $ch) {
    $ph = implode(',', array_fill(0, count($ch), '?'));
    $types = str_repeat('s', count($ch));
    $sql = "SELECT id, LOWER(imdb_id) AS imdb_id FROM posters WHERE LOWER(imdb_id) IN ($ph)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ch);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
      if (!empty($row['imdb_id'])) $foundByImdb[strtolower($row['imdb_id'])] = (int)$row['id'];
    }
    $stmt->close();
  }
}

// מציאת פוסטרים לפי id מקומי
$foundByLocal = [];
if ($locals) {
  $chunks = array_chunk($locals, 500);
  foreach ($chunks as $ch) {
    $ph = implode(',', array_fill(0, count($ch), '?'));
    $types = str_repeat('i', count($ch));
    $sql = "SELECT id FROM posters WHERE id IN ($ph)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ch);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
      $foundByLocal[(int)$row['id']] = (int)$row['id'];
    }
    $stmt->close();
  }
}

// רשימת poster_id סופית
$posterIds = [];
foreach ($imdbs as $tt) if (isset($foundByImdb[$tt])) $posterIds[$foundByImdb[$tt]] = true;
foreach ($locals as $lid) if (isset($foundByLocal[$lid])) $posterIds[$foundByLocal[$lid]] = true;
$posterIds = array_keys($posterIds);

// הוספה לאוסף בלי כפילויות
$inserted = 0; $already = 0;
if ($posterIds) {
  $ins = $conn->prepare("INSERT IGNORE INTO poster_collections (collection_id, poster_id, added_at) VALUES (?, ?, NOW())");
  foreach ($posterIds as $pid) {
    $ins->bind_param("ii", $collection_id, $pid);
    $ins->execute();
    if ($ins->affected_rows === 1) $inserted++;
    else $already++;
  }
  $ins->close();
}

// לא נמצאו במסד
$notFound = [];
foreach ($imdbs as $tt) if (!isset($foundByImdb[$tt])) $notFound[] = $tt;
foreach ($locals as $lid) if (!isset($foundByLocal[$lid])) $notFound[] = (string)$lid;

echo json_encode([
  'total_lines'  => count($lines),
  'total_tokens' => count($tokens),
  'tt_count'     => count($imdbs),
  'local_count'  => count($locals),
  'resolved'     => count($posterIds),
  'inserted'     => $inserted,
  'already'      => $already,
  'not_found'    => $notFound,
], JSON_UNESCAPED_UNICODE);
