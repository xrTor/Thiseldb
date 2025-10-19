<?php
http_response_code(403);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<title>403 — חסום / Forbidden</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,Arial,Helvetica,sans-serif;background:#fafafa;color:#222;margin:0;padding:40px}
  .card{max-width:640px;margin:auto;background:#fff;border:1px solid #eee;border-radius:10px;box-shadow:0 4px 18px #0000000d;padding:26px}
  h1{margin:0 0 10px 0;font-size:28px}
  p{margin:8px 0}
  .en{direction:ltr;text-align:left;color:#555;margin-top:14px;font-size:15px}
  .he{direction:rtl;text-align:right;color:#333;font-size:16px}
  code{background:#f0f0f0;border:1px solid #e4e4e4;border-radius:6px;padding:2px 6px}
</style>
</head>
<body>
  <div class="card">
    <h1>🚫 גישה נחסמה (403)</h1>
    <div class="he">
      הבקשה נדחתה. פעולה זו דורשת <strong>POST</strong> או שאין לך הרשאות לבצעה.<br>
      אם הגעת לכאן דרך קישור ישיר, נסה לחזור לדף הקודם ולבצע את הפעולה מתוך הממשק.
    </div>
    <div class="en">
      <strong>Forbidden (403)</strong> — This action requires <code>POST</code> or you are not authorized.<br>
      If you followed a direct link, please return to the previous page and use the in‑app button.
    </div>
  </div>
</body>
</html>
