<?php
/**
 * force_post.php
 *
 * Enforce POST-only transport while keeping legacy code that still reads $_GET working.
 * - If a request comes with GET query params, we auto-relay them as a POST via an auto-submitting form.
 * - If the request is already POST, we mirror POST values into $_GET so existing code that uses $_GET continues to work.
 * - CLI (cron/CLI PHP) is left untouched.
 */

if (php_sapi_name() !== 'cli') {
    // If GET has parameters, relay as POST (single hop)
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
        // Build a self-posting HTML form that carries over all GET params as POST (including arrays)
        function _fp_print_input($name, $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    _fp_print_input($name . '[' . $k . ']', $v);
                }
            } else {
                $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                $v = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                echo '<input type="hidden" name="' . $n . '" value="' . $v . '">';
            }
        }
        echo '<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><title>מעביר לבקשת POST…</title></head><body>';
        echo '<form id="relay" method="post" action="">';

        foreach ($_GET as $k => $v) {
            _fp_print_input($k, $v);
        }

        echo '</form>';
        echo '<script>document.getElementById("relay").submit();</script>';
        echo '</body></html>';
        exit;
    }

    // If already POST, mirror POST into GET so legacy code "using $_GET" still sees the values
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST as $k => $v) {
            if (!array_key_exists($k, $_GET)) {
                $_GET[$k] = $v;
            }
        }
    }
}
