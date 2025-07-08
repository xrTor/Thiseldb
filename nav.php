
<?php
$current = basename($_SERVER['PHP_SELF']);
echo "<!-- current page: $current -->";
?>


<div class="w3-bar w3-light-grey w3-padding w3-white" style="text-align:center; ">
  <?php
  $pages = [

    'index.php' => 'עמוד ראשי',
    'movies.php' => '🎬 סרטים',
    'series.php' => '📺 סדרות',
    'random.php' => '📊 סרט רנדומלי',
    'top.php' => '🏆 TOP 10',
    'dashboard.php' => '📊 Dashboard',
     'stats.php' => '📈 סטטיסטיקה',
     'contact.php' => 'צור קשר',
  ];

    foreach ($pages as $file => $label) {
    $active = $current == $file ? 'active w3-black' : '';
    echo "<a href='$file' class='w3-button $active'>$label</a>";
  }/*w3-white*/
  ?>
</div>

<div class="w3-bar w3-light-grey w3-padding" style="text-align:center;">
  <?php
  $pages = [

    'add.php' => '➕ הוסף פוסטר חדש',
    'manage_categories.php' => '🏷️ ניהול תגיות',
    'export.php' => '📤 ייצוא לCSV'
  ];

    foreach ($pages as $file => $label) {
    $active = $current == $file ? 'active w3-black' : '';
    echo "<a href='$file' class='w3-button $active'>$label</a>";
  }
  ?>
</div>