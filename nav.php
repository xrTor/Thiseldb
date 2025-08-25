<?php
$current = basename($_SERVER['PHP_SELF']);
echo "";
?>
<link rel="stylesheet" href="style.css">

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
        background-color: transparent;
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
        background-color: inherit;
        text-align: center;
        cursor: pointer;
        white-space: nowrap;
    }
    
    /* צבעים */
    .w3-black, .w3-hover-black:hover {
        color: #fff !important;
        background-color: #000 !important;
    }
    .w3-white, .w3-hover-white:hover {
        color: #000 !important;
        background-color: #fff !important;
    }
    .w3-button:hover {background-color:grey; color: white !important;}
</style>
<div class="w3-bar w3-padding" style="text-align:center; ">
  <?php
  $pages = [
    'index.php' => '🪁 עמוד ראשי',
    'home.php' => '🔎 חיפוש',
    //'movies.php' => '🎬 סרטים',
    //'series.php' => '📺 סדרות', 
    'random.php' => '🎲 סרט רנדומלי',
    'new-movie-imdb.php' => '🎞️ סרט חדש', 
    'collections.php' => '📦 אוספים',
    'universe.php' => '🌌 ציר זמן',
    'spotlight.php' => '🎯 זרקור', 
    'top.php' => '🏆 TOP 10',
     'stats.php' => '📈 סטטיסטיקה',
     'contact.php' => '📩 צור קשר',
     'about.php' => '🎉 אודות',
    'export.php' => '💾 ייצוא לCSV',
  ];

    foreach ($pages as $file => $label) {
    $active = $current == $file ? 'active w3-black' : '';
    echo "<a href='$file' class='w3-button $active'>$label</a>";
  }
  ?>
  <a href="javascript:history.back()" class="w3-button">⬅️ חזור</a>
</div>

<div class="w3-bar w3-padding" style="text-align:center;">
  <?php
  $pages = [
    'add.php' => '➕ הוסף פוסטר חדש',
    'auto-add.php' => 'הוספה אוטומטית',
    'panel.php' => 'פאנל ניהול',
  ];

    foreach ($pages as $file => $label) {
    $active = $current == $file ? 'active w3-black' : '';
    echo "<a href='$file' class='w3-button $active'>$label</a>";
  }
  ?>
</div>
<style>
.w3-blue, .w3-hover-blue:hover {
    color: #fff !important;
    background-color: #2196F3 !important;}

.search-button {
  
    border: none;
    display: inline-block;
    padding: 8px 16px;
    background-color: #2196F3 !important;
    color: #fff !important;
    vertical-align: middle;
    overflow: hidden;
    text-decoration: none;
    background-color: /*inherit*/;
    text-align: center;
    cursor: pointer;
    white-space: nowrap;"
}
button, input, select, textarea, optgroup {
    font: inherit;
    margin: 0;
}
.search-container {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 20px;
  flex-wrap: wrap;
}

.search-container input[type="text"] {
  padding: 12px 16px;
  font-size: 16px;
  border: 1px solid #ccc;
  border-radius: 12px;
  width: 220px;
  box-sizing: border-box;
  direction: rtl;
}

.search-button{
  padding: 12px 16px;
  font-size: 16px;
  border: 1px solid #ccc;
  border-radius: 12px;
  width: 120px;
  box-sizing: border-box;
  direction: rtl;
}

.search-container button:hover  {
  background: linear-gradient(135deg, #3063c9, #5cb3fd);
  transform: scale(1.05);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.35);
  
}

.search-container button .icon {
  font-size: 18px;
}
</style>
<div class="search-container" class="search-button">
  <form method="get" action="search.php">
    <input type="text" name="q" placeholder="🔎 הקלד מילה לחיפוש">
    <button type="submit" class="search-button">🔍 חפש</button>
  </form>
</div>