<?php
require_once 'bbcode.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📘 מדריך BBCode</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f9f9f9; padding:20px; max-width:1100px; margin:auto; direction: rtl; }
    h1 { text-align:center; margin-bottom:30px; }
    .bb-block {
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:20px;
      background:#fff;
      border:1px solid #ddd;
      border-radius:8px;
      padding:15px;
      margin-bottom:20px;
      box-shadow:0 2px 6px #0001;
    }
    .bb-code {
      background:#f7f7f7;
      border:1px solid #ccc;
      padding:10px;
      border-radius:6px;
      font-family:monospace;
      white-space:pre-wrap;
      direction:ltr;
    }
    .bb-preview {
      background:#fff;
      padding:10px;
      border-radius:6px;
      border:1px solid #eee;
      min-height:40px;
    }
    .bb-block h3 {
      grid-column:1 / span 2;
      margin:0 0 10px 0;
      font-size:16px;
      background:#e9f3ff;
      padding:5px 10px;
      border-radius:4px;
    }
  </style>
</head>
<body>

<h1>📘 מדריך שימוש ב-BBCode</h1>

<!-- === טקסט === -->
<div class="bb-block">
  <h3>[b] מודגש</h3>
  <div class="bb-code">[b]מודגש[/b]</div>
  <div class="bb-preview"><?= bbcode_to_html("[b]מודגש[/b]") ?></div>
</div>
<div class="bb-block">
  <h3>[i] נטוי</h3>
  <div class="bb-code">[i]נטוי[/i]</div>
  <div class="bb-preview"><?= bbcode_to_html("[i]נטוי[/i]") ?></div>
</div>
<div class="bb-block">
  <h3>[u] קו תחתון</h3>
  <div class="bb-code">[u]קו תחתון[/u]</div>
  <div class="bb-preview"><?= bbcode_to_html("[u]קו תחתון[/u]") ?></div>
</div>
<div class="bb-block">
  <h3>[s] קו חוצה</h3>
  <div class="bb-code">[s]קו חוצה[/s]</div>
  <div class="bb-preview"><?= bbcode_to_html("[s]קו חוצה[/s]") ?></div>
</div>

<!-- === כותרות === -->
<div class="bb-block">
  <h3>[h1] כותרת H1</h3>
  <div class="bb-code">[h1]כותרת ראשית[/h1]</div>
  <div class="bb-preview"><?= bbcode_to_html("[h1]כותרת ראשית[/h1]") ?></div>
</div>
<div class="bb-block">
  <h3>[h3] כותרת H3</h3>
  <div class="bb-code">[h3]כותרת משנית[/h3]</div>
  <div class="bb-preview"><?= bbcode_to_html("[h3]כותרת משנית[/h3]") ?></div>
</div>

<!-- === יישור === -->
<div class="bb-block">
  <h3>[center] מרכז</h3>
  <div class="bb-code">[center]מרכז[/center]</div>
  <div class="bb-preview"><?= bbcode_to_html("[center]מרכז[/center]") ?></div>
</div>
<div class="bb-block">
  <h3>[right] ימין</h3>
  <div class="bb-code">[right]טקסט מימין[/right]</div>
  <div class="bb-preview"><?= bbcode_to_html("[right]טקסט מימין[/right]") ?></div>
</div>
<div class="bb-block">
  <h3>[left] שמאל</h3>
  <div class="bb-code">[left]Text left[/left]</div>
  <div class="bb-preview"><?= bbcode_to_html("[left]Text left[/left]") ?></div>
</div>

<!-- === צבע וגודל === -->
<div class="bb-block">
  <h3>[color] צבע</h3>
  <div class="bb-code">[color=red]אדום[/color]</div>
  <div class="bb-preview"><?= bbcode_to_html("[color=red]אדום[/color]") ?></div>
</div>
<div class="bb-block">
  <h3>[size] גודל</h3>
  <div class="bb-code">[size=24]גדול[/size]</div>
  <div class="bb-preview"><?= bbcode_to_html("[size=24]גדול[/size]") ?></div>
</div>

<!-- === קישורים === -->
<div class="bb-block">
  <h3>[url] קישור</h3>
  <div class="bb-code">[url]https://imdb.com[/url]</div>
  <div class="bb-preview"><?= bbcode_to_html("[url]https://imdb.com[/url]") ?></div>
</div>
<div class="bb-block">
  <h3>[url=...] קישור עם טקסט</h3>
  <div class="bb-code">[url=https://example.com]לחץ כאן[/url]</div>
  <div class="bb-preview"><?= bbcode_to_html("[url=https://example.com]לחץ כאן[/url]") ?></div>
</div>
<div class="bb-block">
  <h3>[email] אימייל</h3>
  <div class="bb-code">[email]someone@example.com[/email]</div>
  <div class="bb-preview"><?= bbcode_to_html("[email]someone@example.com[/email]") ?></div>
</div>

<!-- === תמונות === -->
<div class="bb-block">
  <h3>[img] תמונה</h3>
  <div class="bb-code">[img]https://via.placeholder.com/120x80.png[/img]</div>
  <div class="bb-preview"><?= bbcode_to_html("[img]https://via.placeholder.com/120x80.png[/img]") ?></div>
</div>

<!-- === יוטיוב === -->
<div class="bb-block">
  <h3>[youtube] וידאו</h3>
  <div class="bb-code">[youtube]dQw4w9WgXcQ[/youtube]</div>
  <div class="bb-preview"><?= bbcode_to_html("[youtube]dQw4w9WgXcQ[/youtube]") ?></div>
</div>

<!-- === ציטוט, ספוילר, קוד === -->
<div class="bb-block">
  <h3>[quote] ציטוט</h3>
  <div class="bb-code">[quote]טקסט מצוטט[/quote]</div>
  <div class="bb-preview"><?= bbcode_to_html("[quote]טקסט מצוטט[/quote]") ?></div>
</div>
<div class="bb-block">
  <h3>[spoiler] ספוילר</h3>
  <div class="bb-code">[spoiler]הסתרה[/spoiler]</div>
  <div class="bb-preview"><?= bbcode_to_html("[spoiler]הסתרה[/spoiler]") ?></div>
</div>
<div class="bb-block">
  <h3>[code] קוד</h3>
  <div class="bb-code">[code]&lt;?php echo "Hello"; ?&gt;[/code]</div>
  <div class="bb-preview"><?= bbcode_to_html("[code]<?php echo 'Hello'; ?>[/code]") ?></div>
</div>

<!-- === רשימות === -->
<div class="bb-block">
  <h3>[list] רשימה</h3>
  <div class="bb-code">[list]
[*] אחד
[*] שני
[/list]</div>
  <div class="bb-preview"><?= bbcode_to_html("[list]\n[*] אחד\n[*] שני\n[/list]") ?></div>
</div>
<div class="bb-block">
  <h3>[list=1] רשימה ממוספרת</h3>
  <div class="bb-code">[list=1]
[*] One
[*] Two
[/list]</div>
  <div class="bb-preview"><?= bbcode_to_html("[list=1]\n[*] One\n[*] Two\n[/list]") ?></div>
</div>

<!-- === טבלה === -->
<div class="bb-block">
  <h3>[table] טבלה</h3>
  <div class="bb-code">[table]
[tr][td]A1[/td][td]B1[/td][/tr]
[tr][td]A2[/td][td]B2[/td][/tr]
[/table]</div>
  <div class="bb-preview"><?= bbcode_to_html("[table]\n[tr][td]A1[/td][td]B1[/td][/tr]\n[tr][td]A2[/td][td]B2[/td][/tr]\n[/table]") ?></div>
</div>

<!-- === עברית / אנגלית === -->
<div class="bb-block">
  <h3>[עברית] [אנגלית]</h3>
  <div class="bb-code">[עברית]שלום[/עברית]
[אנגלית]Hello[/אנגלית]</div>
  <div class="bb-preview"><?= bbcode_to_html("[עברית]שלום[/עברית]\n[אנגלית]Hello[/אנגלית]") ?></div>
</div>

</body>
</html>
