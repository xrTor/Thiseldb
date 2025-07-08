<?php
include 'header.php';
$conn = new mysqli('localhost','root','123456','media');
if ($conn->connect_error) die("Connection failed");

// ספירה לפי סוג
$types = $conn->query("
  SELECT type, COUNT(*) AS total 
  FROM posters 
  GROUP BY type
");

$labels = [];
$data = [];
while ($row = $types->fetch_assoc()) {
  $labels[] = $row['type'] === 'movie' ? '🎬 סרטים' : '📺 סדרות';
  $data[] = $row['total'];
}

// ספירה לפי שנים
$years = $conn->query("
  SELECT year, COUNT(*) AS total 
  FROM posters 
  WHERE year IS NOT NULL 
  GROUP BY year 
  ORDER BY year ASC
");

$year_labels = [];
$year_data = [];
while ($row = $years->fetch_assoc()) {
  $year_labels[] = $row['year'];
  $year_data[] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📊 דשבורד</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family:sans-serif; max-width:1000px; margin:40px auto; background:#f4f4f4; padding:20px; }
    h2 { text-align:center; }
    .chart-container { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 6px rgba(0,0,0,0.1); margin-bottom:40px; }
  </style>
</head>
<body>

<h2>📊 לוח ניתוח מידע</h2>

<div class="chart-container">
  <h3>🔢 חלוקה לפי סוג</h3>
  <canvas id="typeChart" height="100"></canvas>
</div>

<div class="chart-container">
  <h3>📅 פוסטרים לפי שנה</h3>
  <canvas id="yearChart" height="120"></canvas>
</div>

<p style="text-align:center;"><a href="index.php">⬅ חזרה לדף הבית</a></p>

<script>
  const typeCtx = document.getElementById('typeChart').getContext('2d');
  new Chart(typeCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [{
        data: <?= json_encode($data) ?>,
        backgroundColor: ['#4CAF50', '#2196F3'],
      }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });

  const yearCtx = document.getElementById('yearChart').getContext('2d');
  new Chart(yearCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($year_labels) ?>,
      datasets: [{
        label: 'מס׳ פוסטרים',
        data: <?= json_encode($year_data) ?>,
        backgroundColor: '#FF9800'
      }]
    },
    options: {
      scales: { y: { beginAtZero: true } },
      plugins: { legend: { display: false } }
    }
  });
</script>

</body>
</html>
