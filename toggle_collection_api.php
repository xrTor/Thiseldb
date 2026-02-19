<?php
require_once 'server.php';

if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

header('Content-Type: application/json; charset=UTF-8');

function jexit($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jexit(['ok'=>0,'error'=>'Method not allowed']);
}

$poster_id = isset($_POST['poster_id']) ? (int)$_POST['poster_id'] : 0;
$collection_id = isset($_POST['collection_id']) ? (int)$_POST['collection_id'] : 0;

$allowed = [231, 196];
if ($poster_id <= 0 || !in_array($collection_id, $allowed, true)) {
  jexit(['ok'=>0,'error'=>'Bad request']);
}

// verify poster exists (optional but helps avoid junk rows)
$stmt = $conn->prepare("SELECT id FROM posters WHERE id=? LIMIT 1");
$stmt->bind_param("i", $poster_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
  $stmt->close();
  jexit(['ok'=>0,'error'=>'Poster not found']);
}
$stmt->close();

// check membership
$stmt = $conn->prepare("SELECT 1 FROM poster_collections WHERE poster_id=? AND collection_id=? LIMIT 1");
$stmt->bind_param("ii", $poster_id, $collection_id);
$stmt->execute();
$res = $stmt->get_result();
$exists = ($res && $res->num_rows > 0);
$stmt->close();

if ($exists) {
  $stmt = $conn->prepare("DELETE FROM poster_collections WHERE poster_id=? AND collection_id=? LIMIT 1");
  $stmt->bind_param("ii", $poster_id, $collection_id);
  $ok = $stmt->execute();
  $stmt->close();
  if (!$ok) jexit(['ok'=>0,'error'=>'Delete failed']);
  jexit(['ok'=>1,'in_collection'=>false]);
} else {
  $stmt = $conn->prepare("INSERT IGNORE INTO poster_collections (poster_id, collection_id) VALUES (?, ?)");
  $stmt->bind_param("ii", $poster_id, $collection_id);
  $ok = $stmt->execute();
  $stmt->close();
  if (!$ok) jexit(['ok'=>0,'error'=>'Insert failed']);
  jexit(['ok'=>1,'in_collection'=>true]);
}
