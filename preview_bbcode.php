<?php
require_once 'bbcode.php';

$text = $_POST['text'] ?? '';
$text = trim($text);

if ($text === '') {
  echo '';
} else {
  echo bbcode_to_html($text);
}
