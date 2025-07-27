<?php
require_once 'server.php';
include 'header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 30;
$offset = ($page - 1) * $per_page;

if ($id === 0) {
  echo "<p>❌ אוסף לא צוין</p>";
  include 'footer.php'; exit;
}

$stmt = $conn->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->bind_param("i", $id); $stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<p>❌ האוסף לא נמצא</p>";
  include 'footer.php'; exit;
}
$collection = $result->fetch_assoc();
$stmt->close();

$res = $conn->query("
  SELECT p.* FROM poster_collections pc
  JOIN posters p ON p.id = pc.poster_id
  WHERE pc.collection_id = $id
  LIMIT $per_page OFFSET $offset
");
$poster_list = [];
while ($row = $res->fetch_assoc()) $poster_list[] = $row;

$total_posters = $conn->query("SELECT COUNT(*) FROM poster_collections WHERE collection_id = $id")->fetch_row()[0];
$total_pages = ceil($total_posters / $per_page);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></title>
  <style>
    body { font-family:Arial; background:#f9f9f9; padding:10px; direction:rtl; }
    .container { max-width:1100px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }

    .header-img { max-width:100%; border-radius:1px; margin-top:10px; }
    .description { margin-top:10px; color:#444; }

    .link-btn { background:#eee; padding:6px 12px; border-radius:6px; text-decoration:none; margin:10px 10px 0 0; display:inline-block; }
    .link-btn:hover { background:#ddd; }

    .poster-section { display:flex; flex-direction:row-reverse; gap:30px; margin-top:20px; }

    .poster-grid { flex:3; display:flex; flex-wrap:wrap; }
    .poster-grid.small { gap:2px; }
    .poster-grid.medium { gap:8px; }
    .poster-grid.large { gap:12px; }

    .poster-item { text-align:center; position:relative; }

    .poster-item.small { width:100px; margin-bottom:0 !important; }
    .poster-item.medium { width:160px; }
    .poster-item.large { width:220px; }

    .poster-item img { width:100%; aspect-ratio:2/3; object-fit:cover; border-radius:1px; margin:0; box-shadow:0 0 4px rgba(0,0,0,0.1); }

    .poster-item.small small,
    .poster-item.small .title-he,
    .poster-item.small .imdb-id {
      font-size:10px; line-height:1; margin-top:1px !important; margin-bottom:0 !important;
    }

    .title-he { font-size:12px; color:#555; }
  .imdb-id a {  color: #99999A !important; /* #777 */  font-size: 12px;
  text-decoration: none;
}
    .remove-btn { background:#dc3545; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin-top:6px; }
    .delete-box { display:none; }

    .poster-list-sidebar { flex:1; max-width:300px; background:#f8f8f8; padding:16px; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.05); font-size:14px; color:#333; height:fit-content; text-align:right; }
    .poster-list-sidebar h4 { margin-top:0; font-size:16px; }
    .poster-list-sidebar input { width:100%; padding:6px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; }
    .poster-list-sidebar ul { list-style-type:decimal; padding-right:20px; margin:0; }
    .poster-list-sidebar li { margin-bottom:8px; }

    .form-box { margin-top:30px; background:#f8f8f8; padding:16px; border-radius:6px; }
    .form-box textarea, .form-box button { width:100%; padding:8px; margin-top:8px; border:1px solid #ccc; border-radius:4px; }
    .form-box button { background:#007bff; color:white; cursor:pointer; }
    .form-box button:hover { background:#0056b3; }
    .poster-list-sidebar {
  min-width: 260px;
  max-width: 280px;
  overflow-wrap: break-word;
}
.poster-list-sidebar li {
  margin-bottom: 4px;
  line-height: 1.1;
}

.poster-list-sidebar .title-he,
.poster-list-sidebar .imdb-id {
  font-size: 11px;
  line-height: 1.1;
  margin-top: 1px;
  margin-bottom: 0;
}
.poster-list-sidebar li {
  margin-bottom: 6px;
  line-height: 1.3;
}

.poster-list-sidebar .title-he,
.poster-list-sidebar .imdb-id {
  font-size: 11px;
  line-height: 1.2;
  margin-top: 2px;
  margin-bottom: 0;
}

#poster-list {
  padding-right: 0;
  margin: 0;
}

  </style>
</head>
<body>
<div class="container">
  <h2>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></h2>

  <?php if (!empty($collection['image_url'])): ?>
    <img src="<?= htmlspecialchars($collection['image_url']) ?>" alt="תמונה" class="header-img">
  <?php endif; ?>

  <?php if (!empty($collection['description'])): ?>
    <div class="description">📝 <?= nl2br(htmlspecialchars($collection['description'])) ?></div>
  <?php endif; ?>

  <div>
    <a href="edit_collection.php?id=<?= $collection['id'] ?>" class="link-btn">✏️ ערוך</a>
    <a href="manage_collections.php?delete=<?= $collection['id'] ?>" onclick="return confirm('למחוק את האוסף?')" class="link-btn" style="background:#fdd;">🗑️ מחק</a>
    <a href="collections.php" class="link-btn">⬅ חזרה לרשימת האוספים</a>
    <a href="#" onclick="toggleDelete()" class="link-btn" style="background:#ffc;">🧹 הצג/הסתר מחיקה</a>
  </div>

  <div style="margin-top:10px;">
    גודל פוסטרים:
    <button onclick="setSize('small')">קטן</button>
    <button onclick="setSize('medium')">בינוני</button>
    <button onclick="setSize('large')">גדול</button>
 
   <button onclick="exportListCSV()" style="margin-bottom:10px;">⬇️ ייצא רשימה כ־CSV</button> </div>
  <h3>🎬 פוסטרים באוסף:</h3>
  <?php if ($poster_list): ?>
    <div class="poster-section">
      <div class="poster-grid medium">
        <?php foreach ($poster_list as $p): ?>
          <div class="poster-item medium">
            <a href="poster.php?id=<?= $p['id'] ?>">
              <?php $img = trim($p['image_url'] ?? '') ?: 'images/no-poster.png'; ?>
<img src="<?= htmlspecialchars($img) ?>" alt="Poster">

              <small><?= htmlspecialchars($p['title_en']) ?></small>
              <?php if (!empty($p['title_he'])): ?>
                <div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p['imdb_id'])): ?>
                <div class="imdb-id">
                  <a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank">
                    IMDb: <?= htmlspecialchars($p['imdb_id']) ?>
                  </a>
                </div>
              <?php endif; ?>
            </a>
            <div class="delete-box">
              <form method="post" action="remove_from_collection.php">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                <button type="submit" class="remove-btn">🗑️ הסר</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="poster-list-sidebar">
     

<h4>📃 רשימה שמית</h4>
<input type="text" id="poster-search" placeholder="🔍 חפש פוסטר...">
<ul id="poster-list">
  <?php foreach ($poster_list as $p): ?>
    <li>
      <a href="poster.php?id=<?= $p['id'] ?>" style="color:#007bff;">
        <?= htmlspecialchars($p['title_en']) ?>
      </a>
      <?php if (!empty($p['title_he'])): ?>
        <div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div>
      <?php endif; ?>
      <?php if (!empty($p['imdb_id'])): ?>
        <div class="imdb-id">
          <a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank">
            <?= htmlspecialchars($p['imdb_id']) ?>
          </a>
        </div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
      </div>
    </div>
  <?php else: ?>
    <div class="message">אין פוסטרים באוסף זה</div>
  <?php endif; ?>

  <div class="form-box">
    <h3>➕ הוספת פוסטרים לפי מזהים</h3>
    <form method="post" action="add_to_collection_batch.php">
      <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
      <label>🔗 מזהים (ID רגיל או IMDb: tt...)</label>
      <textarea name="poster_ids_raw" rows="6" placeholder="לדוגמה:\n45\ntt1375666\n89"></textarea>
      <button type="submit">📥 קשר פוסטרים</button>
    </form>
  </div>

  <?php if ($total_pages > 1): ?>
    <div style="text-align:center; margin-top:20px;">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="collection.php?id=<?= $id ?>&page=<?= $i ?>"
           style="margin:0 6px; padding:6px 10px; background:#eee; border-radius:4px; text-decoration:none; <?= $i==$page ? 'font-weight:bold;' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  // הצגת/הסתרת מחיקה
  function toggleDelete() {
    document.querySelectorAll('.delete-box').forEach(el => {
      el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    });
  }

  // שינוי גודל פוסטרים + שמירה ב-localStorage
  function setSize(size) {
    localStorage.setItem('posterSize', size);
    document.querySelectorAll('.poster-item').forEach(item => {
      item.classList.remove('small', 'medium', 'large');
      item.classList.add(size);
    });
    const grid = document.querySelector('.poster-grid');
    grid.classList.remove('small', 'medium', 'large');
    grid.classList.add(size);
  }

  // טעינה אוטומטית של גודל שנבחר קודם
  window.addEventListener('DOMContentLoaded', () => {
    const savedSize = localStorage.getItem('posterSize') || 'medium';
    setSize(savedSize);
  });

  // חיפוש חי ברשימה השמית
  const searchInput = document.getElementById('poster-search');
  const listItems = document.querySelectorAll('#poster-list li');
  searchInput.addEventListener('input', () => {
    const val = searchInput.value.toLowerCase();
    listItems.forEach(item => {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(val) ? 'list-item' : 'none';
    });
  });
  function exportListCSV() {
  const rows = [];
  document.querySelectorAll('#poster-list li').forEach(li => {
    const en = li.querySelector('a')?.innerText || '';
    const he = li.querySelector('.title-he')?.innerText || '';
    const imdb = li.querySelector('.imdb-id a')?.innerText || '';
    rows.push([en, he, imdb]);
  });

  let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
  csvContent += "English Title,Hebrew Title,IMDb ID\n";
  csvContent += rows.map(r => r.map(val => `"${val}"`).join(',')).join('\n');

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "poster_list.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

</script>

</body>
</html>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>
