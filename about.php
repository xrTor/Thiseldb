<?php include 'header.php'; ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body class="rtl">

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>אודות האתר</title>
  <link rel="stylesheet" href="style.css"> <!-- אם יש לך קובץ עיצוב קיים -->
  <style>
    .about-wrapper {
      max-width: 900px;
      margin: 50px auto;
      padding: 30px;
      font-family: sans-serif;
      background: #f9f9f9;
      border-radius: 10px;
      box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }
    .about-wrapper h2 {
      text-align: right;
      font-size: 28px;
      margin-bottom: 20px;
      color: #444;
    }
    .about-section {
      margin-bottom: 30px;
    }
    .about-section h3 {
      font-size: 20px;
      margin-bottom: 10px;
      color: #5CABED;
    }
    .about-section p, .about-section li {
      font-size: 15px;
      color: #333;
      line-height: 1.6;
    }
    .about-section ul {
      padding-right: 20px;
    }
    body, div {
  direction: rtl;
  text-align: right;
}
a {color: #2d89ef !important;}
  </style>
</head>
<body>
<div class="about-wrapper">
  <h2>על האתר Thiseldb 🎬</h2>

<img src="images/logo.png" height="240px"><br>
  <div class="about-section">

  <h3><b><u>שלום!</u></h3></b><br>
אז אני מייקל, חובב סרטים וקולנוע מושבע<br>
תמיד שאני מחפש לדוגמא סרט קומדיה עם אחותי אנחנו מחפשים שעות במה לצפות, אז חשבתי להקים פרוייקט המלצות לתכנים רלוונטים לקהילה.<br><br>

    <h3><b><u>מטרת האתר:</u></h3></b>    
    <p>
      האתר נועד להציג פוסטרים, מידע ודירוגים מתוך עולם הקולנוע והטלוויזיה בצורה ידידותית, מהירה ומדויקת. הוא מאפשר חיפוש וסינון חכם לפי סוג, ז׳אנר, שנה, שפה ועוד.
    </p>


<h3><b><u>איפה ההורדה? איפה צופים בתוכן?</u></h3></b>
האתר מכיל תוכן שזמין לצפייה בשירותי הסטרימינג השונים או באמצעים פיראטיים כאלו ואחרים
האתר אינו כולל לינקים לצפייה או לפלטפורמות כאלו ואחרות<br>
אם סרט זמין בבלעדיות בישראל בשירות סטרימינג כל שהוא יסומן עם מדבקה.<br>
רוב התוכן באתר מבוסס על תרגומים פיראטיים של הקהילה, אני מביא סרטים שאין להם תרגום עברי ברשת ולא משודרים בישראל רק כשהם נדרשים ל'שושלת' (סאגות, אוספים) או תוכן שאני ממליץ עליו בגלל שאהבתי אותו גם אם צפיתי בו בגרסא ללא תרגום עברי, אבל שוב מדובר במיעוט של התוכן (300~ סרטים)<br>
<h3><b><u>פיתוח האתר:</u></h3></b>
האתר זמין כ<a href="https://github.com/xrTor/Thiseldb-PHP">קוד מקור בGitHub</a>
 תחת רישיון GPL הוא נכתב עם PHP 8 בצד שרת יחד עם דאטה-בייס מבוסס MySQL בעזרה של הכלי CoPilot<br>
אם אתם רוצים לקבל את ארכיון האתר כולו אנא בקשו בעמוד '<a href="contact.php">צור קשר</a>'.<br>
הארכיון של האתר משוחרר בחינם לחלוטין וגם האתר עצמו, אתם מוזמנים להוריד ולעשות כל מה שיתחשק לכם עם המאגר או עם קוד המקור של האתר עצמו.<br><br>


<h3><b><u>כלים:</u></h3></b>
<a href="https://www.omdbapi.com">פרוייקט API של omdbapi </a><br>
<a href="https://github.com/FabianBeiner/PHP-IMDB-Grabber
">PHP-IMDB-Grabber</a><br>
<br>
<h3><b><u>הצעות לשיפור ודיווח על באגים:</u></h3></b>
אם חשבתם על דרך לשפר תאתר או נתקלתם בשגיאה או באג או נתון לא נכון, בעמודי הפוסטרים השתמשו בכפתור ה'דיווח', לכל פנייה אחרת נא שלחו טופס דרך עמוד '<a href="contact.php">צור קשר</a>'.<br>
תהנו : )
</div>
</div>

</body>
</html>
<?php include 'footer.php'; ?>