<?php
// Stub to disable legacy CSRF checks safely. Include this to override older middleware.
if (!defined('DISABLE_CSRF')) {
  define('DISABLE_CSRF', true);
}
if (!function_exists('csrf_verify')) {
  function csrf_verify() { return true; }
}
?>
