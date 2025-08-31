<?php include 'header.php'; 
require_once 'server.php';
?>

<?php
require_once 'bbcode.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📘 מדריך BBCode</title>
  <link rel="stylesheet" href="bbcode.css"> <!-- CSS חיצוני -->
  <style>
    body {
      font-family: Arial, sans-serif;
      background:#f9f9f9;
      padding:20px;
      max-width:1200px;
      margin:auto;
      direction: rtl;
    }
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
      text-align:left;
      color:#111;
    }
    .bb-preview {
      background:#fff;
      padding:10px;
      border-radius:6px;
      border:1px solid #eee;
      min-height:40px;
      color:#000;
      text-align:right;        /* ברירת מחדל לימין */
      white-space:normal;      
      word-break:break-word;   
      overflow-wrap:break-word;
    }
    .bb-block h3 {
      grid-column:1 / span 2;
      margin:0 0 10px 0;
      font-size:16px;
      background:#cce5ff;
      padding:5px 10px;
      border-radius:4px;
    }

    /* תוכן טבלה בתוך preview */
    .bb-preview .bb-table td,
    .bb-preview .bb-table th {
      text-align:center;
      vertical-align:middle;
    }

    /* רשימות בתוך preview */
    .bb-preview ul,
    .bb-preview ol {
      padding-inline-start: 20px;
      margin: 0 0 0.5em 0;
      list-style-position: inside;
      white-space:normal;
      overflow-wrap: break-word;
    }
  </style>
</head>
<body>

<h1>📘 מדריך שימוש ב-BBCode</h1>
העמוד מציג דוגמאות לכל הפקודות הנתמכות. בעמודה השמאלית תראה את קוד ה-BBCode, ובעמודה הימנית איך זה יוצג בפועל באתר.
<br><br>

<!-- === טקסט === -->
<?php
$examples = [
  "[b]מודגש[/b]",
  "[i]נטוי[/i]",
  "[u]קו תחתון[/u]",
  "[s]קו חוצה[/s]",
  "[sub]תת־טקסט[/sub]",
  "[sup]על־טקסט[/sup]",
  "[small]קטן[/small]",
  "[big]גדול[/big]",
  "[mark]הדגשה[/mark]",
  "[kbd]Ctrl+C[/kbd]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === כותרות H1–H6 === -->
<?php
for ($i=1;$i<=6;$i++) {
  $ex = "[h{$i}]כותרת H{$i}[/h{$i}]";
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === יישור === -->
<?php
$examples = [
  "[left]שמאל[/left]",
  "[right]ימין[/right]",
  "[center]מרכז[/center]",
  "[justify]מיושר[/justify]",
  "[align=center]יישור דרך align[/align]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === צבע, גודל, גופן === -->
<?php
$examples = [
  "[color=red]אדום[/color]",
  "[color=#00f]כחול[/color]",
  "[size=24]גודל 24px[/size]",
  "[font=Arial]Arial font[/font]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === לינקים/מייל === -->
<?php
$examples = [
  "[url]https://imdb.com[/url]",
  "[url=https://example.com]לחץ כאן[/url]",
  "[email]someone@example.com[/email]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === תמונות === -->
<?php

$examples = [
  "[img]https://thiseldb.me/images/name.png[/img]",
  "[img=100x50]https://thiseldb.me/images/name.png[/img]",
  "[img left]https://thiseldb.me/images/name.png[/img]",
  "[img right]https://thiseldb.me/images/name.png[/img]",
  "[img center]https://thiseldb.me/images/name.png[/img]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === וידאו/אודיו === -->
<?php
$examples = [
  "[youtube]dQw4w9WgXcQ[/youtube]",
  "[video]https://www.w3schools.com/html/mov_bbb.mp4[/video]",
  "[audio]https://www.w3schools.com/html/horse.mp3[/audio]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === ציטוטים וספוילר === -->
<?php
$examples = [
  "[quote]טקסט מצוטט[/quote]",
  "[quote=משתמש]ציטוט עם מקור[/quote]",
  "[spoiler]תוכן מוסתר[/spoiler]",
  "[spoiler=לחץ כאן]תוכן מוסתר עם כותרת[/spoiler]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === קוד / noparse === -->
<?php
$examples = [
  "[code]<?php echo 'Hello'; ?>[/code]",
  "[code=php]<?php echo 'PHP Highlight'; ?>[/code]",
  "[noparse][b]לא להמיר[/b][/noparse]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === רשימות === -->
<?php
$examples = [
  "[list]\n[*] אחד\n[*] שני\n[/list]",
  "[list=1]\n[*] אחד\n[*] שני\n[/list]",
  "[list=a]\n[*] alpha\n[*] beta\n[/list]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars(str_replace("\n"," ",$ex))."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === טבלאות === -->
<?php
$examples = [
  "[table][tr][th]A[/th][th]B[/th][/tr][tr][td]1[/td][td]2[/td][/tr][/table]",
  "[table][tr][td=2x1]תא משולב[/td][/tr][/table]",
];
foreach ($examples as $ex) {
  echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
  <div class='bb-code'>".htmlspecialchars($ex)."</div>
  <div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
}
?>

<!-- === דו-לשוני === -->
<?php
$ex = "[עברית]שלום[/עברית]\n[אנגלית]Hello[/אנגלית]";
echo "<div class='bb-block'><h3>".htmlspecialchars($ex)."</h3>
<div class='bb-code'>".htmlspecialchars($ex)."</div>
<div class='bb-preview'>".bbcode_to_html($ex)."</div></div>";
?>

</body>
</html>


<?php include 'footer.php'; ?>