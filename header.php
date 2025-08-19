<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>Thiseldb</title>
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
  </style>
  <link rel="stylesheet" href="style.css?v=2">
  <link rel="icon" type="image/x-icon" href="images/favicon.ico?v=4">
<link rel="script" href="script.js">
<?php $current = basename($_SERVER['PHP_SELF']); ?>

</head>
<body class="rtl" style="text-align: center!important">
<center>
<a href="index.php">
  <h1 style="text-align:center;">
  <img src="images/name.png" class="logo">
  </h1>
  <?php include 'nav.php';?>

<!-- <b>Thiseldb</b></a> -->

<?php $current = basename($_SERVER['PHP_SELF']); ?>


    </div>
    </div>
  </header>



<?php
/*
*.php

<a href="">
</a>
*/
?>
