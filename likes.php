<?php include 'header.php'; ?>
<?php
 require_once 'server.php';
 
// תגובות מעורבות
$mixed = $conn->query("
  SELECT p.*, 
    SUM(CASE WHEN v.vote_type='like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN v.vote_type='dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes v ON v.poster_id = p.id
  GROUP BY p.id
  HAVING likes > 0 AND dislikes > 0
");

// רק לייקים
$only_likes = $conn->query("
  SELECT p.*, 
    SUM(CASE WHEN v.vote_type='like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN v.vote_type='dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes v ON v.poster_id = p.id
  GROUP BY p.id
  HAVING likes > 0 AND dislikes = 0
");

// רק דיסלייקים
$only_dislikes = $conn->query("
  SELECT p.*, 
    SUM(CASE WHEN v.vote_type='like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN v.vote_type='dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes v ON v.poster_id = p.id
  GROUP BY p.id
  HAVING dislikes > 0 AND likes = 0
");

// ספירת שורות לכל טאב
$mixed_count = $mixed ? $mixed->num_rows : 0;
$likes_count = $only_likes ? $only_likes->num_rows : 0;
$dislikes_count = $only_dislikes ? $only_dislikes->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>פוסטרים לפי הצבעות</title>
  <style>
    body { font-family: Arial; background:#f2f2f2; padding:40px; direction:rtl; scroll-behavior:smooth; }
    h2 { margin-bottom:20px; margin-top:40px; }
    .poster-grid { display:flex; flex-wrap:wrap; gap:20px; }
    .poster-item {
      background:#fff; padding:12px; border-radius:8px; width:180px;
      box-shadow:0 0 4px rgba(0,0,0,0.1); text-align:center;
    }
    .poster-item img { width:100%; border-radius:6px; }
    .votes {
      margin-top:6px; font-size:16px; font-weight:bold; color:#333;
    }
    .tabs {
      display:flex; gap:12px; margin-bottom:30px;
    }
    .tabs a {
      background:#007bff; color:#fff; padding:8px 16px;
      text-decoration:none; border-radius:6px;
    }
    .tabs a.active {
      background:#0056b3;
    }
  </style>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const tabs = document.querySelectorAll(".tabs a");

  // הדגשה לפי לחיצה
  tabs.forEach(tab => {
    tab.addEventListener("click", function() {
      tabs.forEach(t => t.classList.remove("active"));
      this.classList.add("active");
    });
  });

  // הדגשה לפי מיקום בעמוד (אם נכנס עם סולמית בכתובת)
  const hash = location.hash;
  if (hash) {
    const targetTab = document.querySelector(`.tabs a[href="${hash}"]`);
    if (targetTab) {
      tabs.forEach(t => t.classList.remove("active"));
      targetTab.classList.add("active");
    }
  }
});
</script>

</head>
<body>

<h1>📊 ניתוח תגובות הגולשים</h1>

<div class="tabs">
  <a href="#mixed" class="active">🤷‍♂️ מעורבים (<?= $mixed_count ?>)</a>
  <a href="#liked">🎖️ אהובים (<?= $likes_count ?>)</a>
  <a href="#disliked">❌ דחויים (<?= $dislikes_count ?>)</a>
</div>

<h2 id="mixed">🤷‍♂️ פוסטרים עם תגובות מעורבות</h2>
<div class="poster-grid">
<?php if ($mixed): while ($row = $mixed->fetch_assoc()): ?>
  <div class="poster-item">
    <a href="poster.php?id=<?= $row['id'] ?>">
      <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster">
      <div><strong><?= htmlspecialchars($row['title_en']) ?></strong></div>
    </a>
    <div class="votes">❤️ <?= $row['likes'] ?> | 💔 <?= $row['dislikes'] ?></div>
  </div>
<?php endwhile; else: ?>
  <p>אין פוסטרים עם תגובות מעורבות.</p>
<?php endif; ?>
</div>

<h2 id="liked">🎖️פוסטרים אהובים לפי הצבעות❤️</h2>
<div class="poster-grid">
<?php if ($only_likes): while ($row = $only_likes->fetch_assoc()): ?>
  <div class="poster-item">
    <a href="poster.php?id=<?= $row['id'] ?>">
      <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster">
      <div><strong><?= htmlspecialchars($row['title_en']) ?></strong></div>
    </a>
    <div class="votes">❤️ <?= $row['likes'] ?> | 💔 <?= $row['dislikes'] ?></div>
  </div>
<?php endwhile; else: ?>
  <p>אין פוסטרים אהובים בלבד.</p>
<?php endif; ?>
</div>

<h2 id="disliked">❌ פוסטרים לא אהובים</h2>
<div class="poster-grid">
<?php if ($only_dislikes): while ($row = $only_dislikes->fetch_assoc()): ?>
  <div class="poster-item">
    <a href="poster.php?id=<?= $row['id'] ?>">
      <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster">
      <div><strong><?= htmlspecialchars($row['title_en']) ?></strong></div>
    </a>
    <div class="votes">❤️ <?= $row['likes'] ?> | 💔 <?= $row['dislikes'] ?></div>
  </div>
<?php endwhile; else: ?>
  <p>אין פוסטרים דחויים בלבד.</p>
<?php endif; ?>
</div>

</body>
</html>

<?php $conn->close(); ?>
<?php include 'footer.php'; ?>
