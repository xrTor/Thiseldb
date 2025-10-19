<?php
/****************************************************
 * collection_actions.php — מרכז פעולות על אוספים/פוסטרים (POST בלבד + CSRF)
 * דרישות: server.php (חיבור DB), session + $_SESSION['csrf_token'] מהעמודים הקוראים
 ****************************************************/
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

if (function_exists('opcache_reset')) { @opcache_reset(); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Invalid method');
}

require_once __DIR__ . '/server.php';

// ==== אימות CSRF ====
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  http_response_code(403);
  exit('CSRF check failed');
}

// (אופציונלי) הקשחת מקור/מפנה — אפשר לכבות אם מפריע בסביבת dev
$origin_ok = false;
if (!empty($_SERVER['HTTP_ORIGIN'])) {
  $host = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
  if ($host === $_SERVER['SERVER_NAME']) $origin_ok = true;
}
if (!empty($_SERVER['HTTP_REFERER'])) {
  $ref = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
  if ($ref === $_SERVER['SERVER_NAME']) $origin_ok = true;
}
// if (!$origin_ok) { exit('Bad origin'); } // בטל הערה כדי לאכוף

$action        = isset($_POST['action']) ? trim($_POST['action']) : '';
$collection_id = isset($_POST['collection_id']) ? (int)$_POST['collection_id'] : 0;
$poster_id     = isset($_POST['poster_id']) ? (int)$_POST['poster_id'] : 0;

if ($collection_id < 0 || $poster_id < 0) {
  http_response_code(400);
  exit('Bad input');
}

try {
  // מחיקת אוסף
  if ($action === 'delete_collection') {
    if ($collection_id === 0) throw new Exception('Missing collection id');
    @mysqli_begin_transaction($conn);

    // מחיקת קשרי פוסטרים לאוסף (אם קיימת טבלת mapping בשם poster_collections)
    if ($stmt = $conn->prepare("DELETE FROM poster_collections WHERE collection_id = ?")) {
      $stmt->bind_param("i", $collection_id);
      $stmt->execute();
      $stmt->close();
    }

    if ($stmt = $conn->prepare("DELETE FROM collections WHERE id = ? LIMIT 1")) {
      $stmt->bind_param("i", $collection_id);
      $stmt->execute();
      $stmt->close();
    }

    @mysqli_commit($conn);
    header("Location: collections.php?deleted={$collection_id}");
    exit;
  }

  // נעיצת/ביטול נעיצת אוסף
  if ($action === 'pin_collection') {
    if ($collection_id === 0) throw new Exception('Missing collection id');
    if ($stmt = $conn->prepare("UPDATE collections SET is_pinned = 1, pinned_at = NOW() WHERE id = ?")) {
      $stmt->bind_param("i", $collection_id);
      $stmt->execute();
      $stmt->close();
    }
    header("Location: collections.php?pinned={$collection_id}");
    exit;
  }
  if ($action === 'unpin_collection') {
    if ($collection_id === 0) throw new Exception('Missing collection id');
    if ($stmt = $conn->prepare("UPDATE collections SET is_pinned = 0 WHERE id = ?")) {
      $stmt->bind_param("i", $collection_id);
      $stmt->execute();
      $stmt->close();
    }
    header("Location: collections.php?unpinned={$collection_id}");
    exit;
  }

  // נעיצת/ביטול נעיצת פוסטר בתוך אוסף
  if ($action === 'pin_poster') {
    if ($collection_id === 0 || $poster_id === 0) throw new Exception('Missing ids');
    if ($stmt = $conn->prepare("UPDATE poster_collections SET is_pinned = 1 WHERE collection_id = ? AND poster_id = ?")) {
      $stmt->bind_param("ii", $collection_id, $poster_id);
      $stmt->execute();
      $stmt->close();
    }
    header("Location: collection.php?id={$collection_id}&poster_pinned={$poster_id}");
    exit;
  }
  if ($action === 'unpin_poster') {
    if ($collection_id === 0 || $poster_id === 0) throw new Exception('Missing ids');
    if ($stmt = $conn->prepare("UPDATE poster_collections SET is_pinned = 0 WHERE collection_id = ? AND poster_id = ?")) {
      $stmt->bind_param("ii", $collection_id, $poster_id);
      $stmt->execute();
      $stmt->close();
    }
    header("Location: collection.php?id={$collection_id}&poster_unpinned={$poster_id}");
    exit;
  }

  // מחיקת פוסטר מתוך אוסף
  if ($action === 'delete_poster_from_collection') {
    if ($collection_id === 0 || $poster_id === 0) throw new Exception('Missing ids');
    if ($stmt = $conn->prepare("DELETE FROM poster_collections WHERE collection_id = ? AND poster_id = ? LIMIT 1")) {
      $stmt->bind_param("ii", $collection_id, $poster_id);
      $stmt->execute();
      $stmt->close();
    }
    header("Location: collection.php?id={$collection_id}&poster_deleted={$poster_id}");
    exit;
  }

  http_response_code(400);
  exit('Unknown action');

} catch (Throwable $e) {
  @mysqli_rollback($conn);
  http_response_code(500);
  exit('Error: ' . $e->getMessage());
}
