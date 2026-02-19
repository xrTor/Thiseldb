<?php
// unpin_all_in_collection.php (FIXED: no CSRF token required)
// ---------------------------------------------------------
// Removes pin from all posters in a given collection_id.
// Expected POST: collection_id
// ---------------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) { @session_start(); }

require_once __DIR__ . '/server.php'; // provides $conn (mysqli)

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$collection_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $collection_id = (int)($_POST['collection_id'] ?? 0);
} else {
  // allow GET fallback (optional)
  $collection_id = (int)($_GET['collection_id'] ?? 0);
}

if ($collection_id <= 0) {
  http_response_code(400);
  echo "❌ Missing or invalid collection_id";
  exit;
}

// ✅ Update all pinned rows to unpinned
$stmt = $conn->prepare("UPDATE poster_collections SET is_pinned = 0 WHERE collection_id = ?");
if (!$stmt) {
  http_response_code(500);
  echo "❌ DB error: " . h($conn->error);
  exit;
}
$stmt->bind_param("i", $collection_id);
$stmt->execute();
$stmt->close();

// Redirect back to collection page preserving id
header("Location: collection.php?id=" . $collection_id);
exit;
?>
