<?php
// Require POST for this endpoint. Include at the very top of mutating scripts.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  require __DIR__ . '/403.php';
  exit;
}
?>
