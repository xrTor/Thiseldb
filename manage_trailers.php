<?php
include 'header.php';
require_once 'server.php';
$conn->set_charset("utf8");

// שליפת פוסטרים
$resAll = $conn->query("SELECT id, title_en, title_he, youtube_trailer FROM posters ORDER BY id DESC");

$postersYes = [];
$postersNo  = [];

function extractYoutubeId($url) {
  parse_str(parse_url($url, PHP_URL_QUERY), $vars);
  return $vars['v'] ?? '';
}

while ($row = $resAll->fetch_assoc()) {
  if (!empty($row['youtube_trailer'])) {
    $row['ytId'] = extractYoutubeId($row['youtube_trailer']);
    $postersYes[] = $row;
  } else {
    $postersNo[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="he">
<head>
  <meta charset="UTF-8">
  <title>ניהול טריילרים</title>
  <style>
    body { font-family: Arial, sans-serif; direction: rtl; margin: 20px; background-color: white; }
    h2, h3 { text-align:center; margin-top:30px; }
    table { width:100%; border-collapse: collapse; margin-top:20px; }
    th, td { border:1px solid #ccc; padding:8px; text-align:center; vertical-align:middle; }
    iframe { border-radius:4px; }
    .yt-preview { width:220px; height:120px; border:1px solid #ccc; border-radius:4px; object-fit:cover; }
    .yt-broken { width:220px; height:120px; border:1px solid red; border-radius:4px; line-height:120px; text-align:center; color:red; background:#fee; }
    .btn { padding:6px 12px; border:none; border-radius:4px; cursor:pointer; }
    .btn-preview { background:#007bff; color:#fff; margin-top:5px; }
    .btn-preview:hover { background:#0056b3; }
    .update-btn { background:#28a745; color:#fff; margin-top:5px; }
    .delete-btn { background:#dc3545; color:#fff; margin-top:5px; }
    .toggle-btn { background:#007BFF; color:white; border:none; border-radius:4px; padding:5px 10px; cursor:pointer; margin-right:10px; }
    .toggle-btn:hover { background:#0056b3; }
    .hidden { display:none; }
    .edit-form { display:flex; flex-direction:column; gap:6px; align-items:center; }
    .edit-form input[type=url]{ width:95%; padding:8px; font-size:14px; }
  </style>
</head>
<body>

<h2>🎬 ניהול טריילרים לפי פוסטר</h2>

<!-- 🟢 עם טריילר -->
<h3>
  🟢 פוסטרים עם טריילר (<?= count($postersYes) ?>)
  <button class="toggle-btn" data-target="yes">הצג</button>
</h3>
<div id="table-yes" class="hidden">
<table>
<tr><th>ID</th><th>שם הפוסטר</th><th>טריילר</th><th>פעולות</th></tr>
<?php foreach ($postersYes as $row): ?>
<tr>
  <td><?= $row['id'] ?></td>
  <td>
    <a href="poster.php?id=<?= $row['id'] ?>"><b><?= htmlspecialchars($row['title_en']) ?></b></a><br>
    <small><?= htmlspecialchars($row['title_he']) ?></small>
  </td>
  <td>
    <div class="yt-box" data-yt="<?= $row['ytId'] ?>" data-url="<?= htmlspecialchars($row['youtube_trailer']) ?>">
      <img src="https://img.youtube.com/vi/<?= $row['ytId'] ?>/0.jpg"
           class="yt-preview check-thumb"
           data-id="<?= $row['id'] ?>"
           data-url="<?= htmlspecialchars($row['youtube_trailer']) ?>">
      <br><button type="button" class="btn btn-preview">הצג תצוגה מקדימה</button>
    </div>
  </td>
  <td>
    <form action="update_trailer.php" method="POST">
      <input type="hidden" name="poster_id" value="<?= $row['id'] ?>">
      <input type="url" name="youtube_trailer" placeholder="https://www.youtube.com/watch?v=..." required style="width:95%; padding:8px;">
      <button type="submit" class="btn update-btn">עדכן</button>
    </form>
    <form action="delete_trailer.php" method="POST">
      <input type="hidden" name="poster_id" value="<?= $row['id'] ?>">
      <button type="submit" class="btn delete-btn">🗑️ הסר</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<!-- 🔴 בלי טריילר -->
<h3>
  🔴 פוסטרים ללא טריילר (<?= count($postersNo) ?>)
  <button class="toggle-btn" data-target="no">הצג</button>
</h3>
<div id="table-no" class="hidden">
<table>
<tr><th>ID</th><th>שם הפוסטר</th><th>טריילר</th><th>עדכון</th></tr>
<?php foreach ($postersNo as $row): ?>
<tr>
  <td><?= $row['id'] ?></td>
  <td>
    <a href="poster.php?id=<?= $row['id'] ?>"><b><?= htmlspecialchars($row['title_en']) ?></b></a><br>
    <small><?= htmlspecialchars($row['title_he']) ?></small>
  </td>
  <td><span style="color:gray;">אין טריילר</span></td>
  <td>
    <form action="update_trailer.php" method="POST">
      <input type="hidden" name="poster_id" value="<?= $row['id'] ?>">
      <input type="url" name="youtube_trailer" placeholder="https://www.youtube.com/watch?v=..." required style="width:95%; padding:8px;">
      <button type="submit" class="btn update-btn">עדכן</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<!-- ❌ שבורים -->
<h3>
  ❌ פוסטרים עם טריילר שבור (<span id="broken-count">0</span>)
  <button class="toggle-btn" data-target="broken">הצג</button>
  <button id="recheck-broken" type="button" class="btn" style="background:#ff9800;">🔄 בדוק שוב</button>
</h3>
<div id="table-broken" class="hidden">
  <table id="broken-table">
    <tr><th>ID</th><th>שם הפוסטר</th><th>סטטוס</th><th>קישור YouTube</th><th>עריכה</th></tr>
  </table>
  <p style="text-align:center; margin-top:10px;">סה״כ שבורים: <span id="broken-total">0</span></p>
</div>

<script>
// בדיקת תקינות thumbnail אמיתית
async function checkImage(img){
  try {
    const resp = await fetch(img.src, { method: "HEAD" });
    return resp.ok;
  } catch(e){
    return false;
  }
}

async function runCheck(){
  const brokenTable = document.getElementById("broken-table");
  brokenTable.innerHTML = "<tr><th>ID</th><th>שם הפוסטר</th><th>טריילר</th><th>קישור YouTube</th><th>עריכה</th></tr>";
  document.getElementById("broken-count").textContent = "0";
  document.getElementById("broken-total").textContent = "0";

  const imgs = document.querySelectorAll(".yt-box img");
  let brokenCount = 0;

  for(const img of imgs){
    const ok = await checkImage(img);
    if(!ok){
      const tr = img.closest("tr");
      const id   = tr.querySelector("td:first-child")?.innerText || "";
      const linkCell = tr.querySelector("td:nth-child(2)")?.innerHTML || "";
      const url  = img.dataset.url || "";

      const brokenRow = document.createElement("tr");
      brokenRow.innerHTML = `
        <td>${id}</td>
        <td>${linkCell}</td>
        <td><div class="yt-broken">❌ טריילר שבור</div></td>
        <td>${url}</td>
        <td>
          <form action="update_trailer.php" method="POST" class="edit-form" style="margin-bottom:8px;">
            <input type="hidden" name="poster_id" value="${id}">
            <input type="url" name="youtube_trailer" placeholder="YouTube חדש..." required style="width:95%; padding:8px; font-size:14px;">
            <button type="submit" class="btn update-btn">עדכן</button>
          </form>
          <form action="delete_trailer.php" method="POST" class="edit-form">
            <input type="hidden" name="poster_id" value="${id}">
            <button type="submit" class="btn delete-btn">🗑️ הסר</button>
          </form>
        </td>
      `;
      brokenTable.appendChild(brokenRow);

      img.style.opacity = "0.3";
      brokenCount++;
    } else {
      img.style.opacity = "1";
    }
  }

  document.getElementById("broken-count").textContent = brokenCount;
  document.getElementById("broken-total").textContent = brokenCount;
}

document.addEventListener("click", function(e){
  // הצג/הסתר טבלאות
  if(e.target.classList.contains("toggle-btn")){
    const target = e.target.dataset.target;
    if(!target) return;
    const table = document.getElementById("table-"+target);
    table.classList.toggle("hidden");
    e.target.textContent = table.classList.contains("hidden") ? "הצג" : "הסתר";
  }

  // תצוגה מקדימה לכל טריילר בנפרד
  if(e.target.classList.contains("btn-preview")){
    const box = e.target.closest(".yt-box");
    const yt = box.dataset.yt;
    if(yt){
      box.innerHTML = `<iframe width="220" height="120" src="https://www.youtube.com/embed/${yt}" frameborder="0" allowfullscreen></iframe>`;
    }
  }
});

// כפתור בדוק שוב + הרצה אוטומטית
document.addEventListener("DOMContentLoaded", function(){
  runCheck(); // ירוץ אוטומטית עם טעינת הדף
  const btn = document.getElementById("recheck-broken");
  if(btn){
    btn.addEventListener("click", function(){
      runCheck();
    });
  }
});
</script>

</body>
</html>

<?php include 'footer.php'; ?>
