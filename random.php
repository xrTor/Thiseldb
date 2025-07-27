<?php include 'header.php'; ?>
<?php require_once 'server.php'; ?>

<?php
$res = $conn->query("SELECT * FROM posters ORDER BY RAND() LIMIT 1");
$p = $res->fetch_assoc();

$img = (!empty($p['image_url'])) ? $p['image_url'] : 'images/no-poster.png';
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>🎲 פוסטר אקראי</title>
  <style>
    body {
      font-family: sans-serif;
      background: #fafafa;
      text-align: center;
      padding: 40px;
    }
    .poster-box {
      max-width: 400px;
      margin: auto;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 0 6px rgba(0,0,0,0.1);
    }
    img {
      max-width: 100%;
      border-radius: 6px;
      margin-bottom: 20px;
    }
    h2 { margin-bottom: 10px; }
    .meta {
      color: #555;
      font-size: 14px;
      margin-bottom: 10px;
    }
    .actions a {
      display: inline-block;
      margin: 10px;
      padding: 10px 16px;
      background: #007bff;
      color: white;
      text-decoration: none;
      border-radius: 6px;
    }
    .actions a:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <h1>🎲 סרט אקראי או סדרה מתוך הקטלוג
</h1>

<div class="poster-box">
  <a href="poster.php?id=<?= $p['id'] ?>" style="text-decoration:none; color:inherit;">
  <img src="<?= htmlspecialchars($img) ?>" alt="Poster">
  <h2><?= htmlspecialchars($p['title_en']) ?></h2>
  <?php if (!empty($p['title_he'])): ?>
    <p><?= htmlspecialchars($p['title_he']) ?></p>
  <?php endif; ?>
</a>

  <div class="meta">🗓️ <?= htmlspecialchars($p['year']) ?></div>
  <?php if (!empty($p['plot'])): ?>
    <p><?= nl2br(htmlspecialchars($p['plot'])) ?></p>
  <?php endif; ?>
  <div class="actions">
    <a href="poster.php?id=<?= $p['id'] ?>">📍 לצפייה בדף הפוסטר</a>
    <a href="random.php">🔁 פוסטר אקראי נוסף</a>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
