
<?php require_once 'init.php'; ?>

<!DOCTYPE html>
<html lang="he">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Arimo&family=Assistant&family=Bellefair&family=David+Libre&family=Fira+Code&family=Frank+Ruhl+Libre&family=Heebo&family=Inter&family=Lato&family=Lobster&family=Merriweather&family=Montserrat&family=Open+Sans&family=Oswald&family=Playfair+Display&family=Roboto&family=Rubik&display=swap" rel="stylesheet">
  <meta charset="UTF-8">
  <title>Thiseldb :: ת'יסל </title>
  <style>
      /* ============================================== */
    /* ==   סגנונות שנלקחו מהקובץ w3.css   == */
    /* ============================================== */

    /* הגדרות בסיס ואיפוס */
    html {
        box-sizing: border-box;
    }
    *, *:before, *:after {
        box-sizing: inherit;
    }
    a {
        color: inherit;
        background-color: white;
    }
    
    /* .w3-bar */
    .w3-bar {
        width: 100%;
        overflow: hidden;
    }
    .w3-bar .w3-bar-item {
        padding: 8px 16px;
        float: left; /* הדפדפן הופך אוטומטית לימין ב-RTL */
        width: auto;
        border: none;
        display: block;
        outline: 0;
    }
    .w3-bar .w3-button {
        white-space: normal;
    }
    .w3-bar:before, .w3-bar:after {
        content: "";
        display: table;
        clear: both;
    }

    /* .w3-padding */
    .w3-padding {
        padding: 8px 16px !important;
    }

    /* .w3-button */
    .w3-button {
        border: none;
        display: inline-block;
        padding: 8px 16px;
        vertical-align: middle;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        background-color: white;
        text-align: center;
        cursor: pointer;
        white-space: nowrap;
    }
    
    /* צבעים */
    .w3-black, .w3-hover-black:hover {
        color: #fff !important;
        background-color: white;
    }
    .w3-white, .w3-hover-white:hover {
        color: #000 !important;
        background-color: #fff !important;
    }

    /* ===== כפתור גלובלי: חזרה למעלה ===== */
    #scrollTopBtn {
      display: none;             /* מוסתר כברירת מחדל */
      position: fixed;
      bottom: 20px;
      right: 30px;
      z-index: 9999;
      border: none;
      outline: none;
      background-color: #555;
      color: #fff;
      cursor: pointer;
      padding: 15px;
      border-radius: 10px;
      font-size: 18px;
      transition: background-color 0.3s, opacity 0.3s;
    }
    #scrollTopBtn:hover {
      background-color: #007bff;
    }
    .transparent:hover {content: url('images/transparent.png?v=3');
     /* filter: brightness(94%); */
    }
  </style>
  <link rel="stylesheet" href="style.css?v=5">
  <link rel="icon" type="image/x-icon" href="images/favicon.ico?v=3">
  <link rel="script" href="script.js">
  <?php $current = basename($_SERVER['PHP_SELF']); ?>
</head>
<body class="rtl" style="text-align: center!important">
<center>
<a href="index.php">
  <h1 style="text-align:center;">
    <img src="images/name.png" class="transparent">   <!-- class="logo" -->
  </h1>
</a>
<?php include 'nav.php';?>
<?php include 'menu_component.php'; ?>

<!-- כפתור גלובלי: חזרה למעלה -->
<button id="scrollTopBtn" type="button" title="חזרה למעלה" aria-label="חזרה למעלה">↑</button>

<!-- <b>Thiseldb</b></a> -->

<?php $current = basename($_SERVER['PHP_SELF']); ?>

    </div>
    </div>
  </header>

<script>
/* גלילה למעלה — גלובלי בכל העמודים */
(function(){
  var btn = document.getElementById('scrollTopBtn');
  if (!btn) return;

  function onScroll(){
    var y = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    btn.style.display = (y > 20) ? 'block' : 'none';
  }
  function goTop(){
    try {
      window.scrollTo({top:0, behavior:'smooth'});
    } catch(e) {
      document.documentElement.scrollTop = 0;
      document.body.scrollTop = 0;
    }
  }

  btn.addEventListener('click', goTop);
  window.addEventListener('scroll', onScroll, {passive:true});
  document.addEventListener('DOMContentLoaded', onScroll);
})();
</script>

<?php
/*
*.php

<a href="">
</a>
*/
?>
