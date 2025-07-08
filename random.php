<?php include 'header.php'; ?>

<?php
$conn = new mysqli("localhost", "root", "123456", "media");

// משיכת פוסטר אקראי אחד
$res = $conn->query("SELECT * FROM posters ORDER BY RAND() LIMIT 1");
$p = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>🎲 פוסטר אקראי</title>
  <style>
    body { font-family: sans-serif; background: #fafafa; text-align: center; }
    img { max-width: 300px; margin-bottom: 20px; }
  </style>
</head>
<body>
  <h2>🎲 סרט אקראי או סדרה מתוך הקטלוג</h2>
  <img src="<?= $p['image_url'] ?>" alt="Poster">
  <h3><?= htmlspecialchars($p['title_en']) ?> (<?= $p['year'] ?>)</h3>
  <p><?= nl2br(htmlspecialchars($p['plot'])) ?></p>
  <p><a href="poster.php?id=<?= $p['id'] ?>">📍 לצפייה בדף הסרט</a></p>
  <p><a href="random.php">🔁 פוסטר אקראי נוסף</a></p>
</body>
</html>
<?php include 'footer.php'; ?>