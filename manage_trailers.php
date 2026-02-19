<?php
include 'header.php';
require_once 'server.php';
$conn->set_charset("utf8");

// שליפת פוסטרים - שימוש בעמודה המקורית trailer_url
$resAll = $conn->query("SELECT id, title_en, title_he, trailer_url, imdb_id FROM posters ORDER BY id DESC");

$postersYes = [];
$postersNo  = [];

// חילוץ מזהה יוטיוב
function extractYoutubeId($url) {
  // תמיכה בפורמטים שונים של קישורי יוטיוב
  parse_str(parse_url($url, PHP_URL_QUERY), $vars);
  return $vars['v'] ?? '';
}

while ($row = $resAll->fetch_assoc()) {
  $row['id']          = (int)$row['id'];
  $row['title_en']    = $row['title_en'] ?? '';
  $row['title_he']    = $row['title_he'] ?? '';
  $row['trailer_url'] = $row['trailer_url'] ?? ''; // שימוש בעמודה המקורית
  $row['imdb_id']     = $row['imdb_id'] ?? '';

  // בדיקה אם קיים תוכן בעמודה
  if (!empty($row['trailer_url'])) {
    $row['ytId'] = extractYoutubeId($row['trailer_url']);
    $postersYes[] = $row;
  } else {
    $row['ytId'] = '';
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
    body {
      font-family: Arial, sans-serif;
      direction: rtl;
      margin: 20px;
      background-color: white;
    }
    h2, h3 {
      text-align:center;
      margin-top:30px;
    }
    table {
      width:100%;
      border-collapse: collapse;
      margin-top:20px;
      background-color: white;
    }
    th, td {
      border:1px solid #ccc;
      padding:8px;
      text-align:center;
      vertical-align:middle;
    }
    iframe { border-radius:4px; }
    .yt-preview {
      width:220px;
      height:120px;
      border:1px solid #ccc;
      border-radius:4px;
      object-fit:cover;
    }
    .yt-broken {
      width:220px;
      height:120px;
      border:1px solid red;
      border-radius:4px;
      line-height:120px;
      text-align:center;
      color:red;
      background:#fee;
    }
    .btn {
      padding:6px 12px;
      border:none;
      border-radius:4px;
      cursor:pointer;
    }
    .btn-preview {
      background:#007bff;
      color:#fff;
      margin-top:5px;
    }
    .btn-preview:hover { background:#0056b3; }
    .update-btn {
      background:#28a745;
      color:#fff;
      margin-top:5px;
    }
    .delete-btn {
      background:#dc3545;
      color:#fff;
      margin-top:5px;
    }
    .toggle-btn {
      background:#007BFF;
      color:white;
      border:none;
      border-radius:4px;
      padding:5px 10px;
      cursor:pointer;
      margin-right:10px;
    }
    .toggle-btn:hover { background:#0056b3; }
    .hidden { display:none; }
    .edit-form {
      display:flex;
      flex-direction:column;
      gap:6px;
      align-items:center;
    }
    .edit-form input[type=url], .edit-form input[type=text] {
      width:95%;
      padding:8px;
      font-size:14px;
    }

    .yt-search-link a {
      display:inline-block;
      text-decoration:none;
    }
    .yt-search-link img {
      width:120px;
      height:auto;
      border-radius:4px;
      border:1px solid #ccc;
      background:#fff;
      padding:2px;
    }
    .yt-search-link img:hover {
      filter:brightness(0.9);
    }

    .imdb-id-cell a {
      font-family: Consolas, monospace;
      font-size:13px;
      color:#333;
      text-decoration:none;
      background:#f5f5f5;
      border:1px solid #ccc;
      border-radius:4px;
      padding:2px 6px;
      display:inline-block;
      min-width:70px;
    }
    .imdb-id-cell a:hover {
      background:#eaeaea;
    }

    .pager-bar {
      display:flex;
      justify-content:center;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      margin-top:15px;
      margin-bottom:10px;
      font-size:14px;
    }
    .pager-btn {
      background:#f5f5f5;
      border:1px solid #ccc;
      border-radius:4px;
      padding:4px 8px;
      cursor:pointer;
    }
    .pager-btn[disabled] {
      opacity:0.4;
      cursor:default;
    }
    .count-info {
      font-weight:bold;
    }
  </style>
</head>
<body>

<h2>🎬 ניהול טריילרים לפי פוסטר</h2>

<h3>
  🟢 פוסטרים עם טריילר (<span id="yes-count"><?= count($postersYes) ?></span>)
  <button class="toggle-btn" data-target="yes">הצג</button>
</h3>

<div id="table-yes" class="hidden">
  <div class="pager-bar" id="yes-pager-top">
    <button class="pager-btn" data-yes-nav="first">⏮ ראשון</button>
    <button class="pager-btn" data-yes-nav="prev">◀ קודם</button>
    <span class="count-info">
      עמוד <span id="yes-page-now">1</span> מתוך <span id="yes-page-max">1</span>
    </span>
    <button class="pager-btn" data-yes-nav="next">הבא ▶</button>
    <button class="pager-btn" data-yes-nav="last">אחרון ⏭</button>
  </div>

  <table>
    <tr>
      <th>ID</th>
      <th>שם הפוסטר</th>
      <th>טריילר</th>
      <th>פעולות</th>
      <th>יוטיוב</th>
      <th>IMDb</th>
    </tr>
    <tbody id="yes-tbody"></tbody>
  </table>

  <div class="pager-bar" id="yes-pager-bottom">
    <button class="pager-btn" data-yes-nav="first">⏮ ראשון</button>
    <button class="pager-btn" data-yes-nav="prev">◀ קודם</button>
    <span class="count-info">
      עמוד <span id="yes-page-now-bottom">1</span> מתוך <span id="yes-page-max-bottom">1</span>
    </span>
    <button class="pager-btn" data-yes-nav="next">הבא ▶</button>
    <button class="pager-btn" data-yes-nav="last">אחרון ⏭</button>
  </div>
</div>

<h3>
  🔴 פוסטרים ללא טריילר (<span id="no-count"><?= count($postersNo) ?></span>)
  <button class="toggle-btn" data-target="no">הצג</button>
</h3>

<div id="table-no" class="hidden">
  <div class="pager-bar" id="no-pager-top">
    <button class="pager-btn" data-no-nav="first">⏮ ראשון</button>
    <button class="pager-btn" data-no-nav="prev">◀ קודם</button>
    <span class="count-info">
      עמוד <span id="no-page-now">1</span> מתוך <span id="no-page-max">1</span>
    </span>
    <button class="pager-btn" data-no-nav="next">הבא ▶</button>
    <button class="pager-btn" data-no-nav="last">אחרון ⏭</button>
  </div>

  <table>
    <tr>
      <th>ID</th>
      <th>שם הפוסטר</th>
      <th>טריילר</th>
      <th>עדכון</th>
      <th>יוטיוב</th>
      <th>IMDb</th>
    </tr>
    <tbody id="no-tbody"></tbody>
  </table>

  <div class="pager-bar" id="no-pager-bottom">
    <button class="pager-btn" data-no-nav="first">⏮ ראשון</button>
    <button class="pager-btn" data-no-nav="prev">◀ קודם</button>
    <span class="count-info">
      עמוד <span id="no-page-now-bottom">1</span> מתוך <span id="no-page-max-bottom">1</span>
    </span>
    <button class="pager-btn" data-no-nav="next">הבא ▶</button>
    <button class="pager-btn" data-no-nav="last">אחרון ⏭</button>
  </div>
</div>

<h3>
  ❌ פוסטרים עם טריילר שבור (<span id="broken-count">0</span>)
  <button class="toggle-btn" data-target="broken">הצג</button>
  <button id="recheck-broken" type="button" class="btn" style="background:#ff9800;">🔄 בדוק שוב</button>
</h3>
<div id="table-broken" class="hidden">
  <table id="broken-table">
    <tr>
      <th>ID</th>
      <th>שם הפוסטר</th>
      <th>סטטוס</th>
      <th>קישור YouTube</th>
      <th>עריכה</th>
      <th>יוטיוב</th>
      <th>IMDb</th>
    </tr>
  </table>
  <p style="text-align:center; margin-top:10px;">סה״כ שבורים: <span id="broken-total">0</span></p>
</div>

<script>
/* ====== DATA מה-PHP ====== */
const postersYes = <?= json_encode($postersYes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const postersNo  = <?= json_encode($postersNo,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

/* ====== הגדרות עימוד ====== */
const PAGE_SIZE = 250;
let yesPage = 1;
let noPage  = 1;

function maxPage(list) {
  return Math.max(1, Math.ceil(list.length / PAGE_SIZE));
}

/* ====== פונקציות עזר ====== */
function esc(s){
  if (s === null || s === undefined) return '';
  return String(s)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

function buildImdb(cleanImdbRaw){
  let cleanImdb = (cleanImdbRaw || '').trim();
  if (cleanImdb !== '' && !cleanImdb.startsWith('tt')) {
    cleanImdb = 'tt' + cleanImdb;
  }
  if (cleanImdb === '') {
    return { html: '<span style="color:#999;">—</span>' };
  }
  const imdbUrl = "https://www.imdb.com/title/" + encodeURIComponent(cleanImdb) + "/";
  return {
    cleanImdb,
    imdbUrl,
    html: `<a href="${imdbUrl}" target="_blank" rel="noopener noreferrer">${esc(cleanImdb)}</a>`
  };
}

/* ====== בניית שורת טבלה עבור YES (עם טריילר) ====== */
function rowHtmlYes(row){
  const ytId    = row.ytId || '';
  const yurl    = row.trailer_url || ''; // שימוש בשם העמודה המתוקן
  const titleEn = row.title_en || '';
  const titleHe = row.title_he || '';
  const ytSearchURL = "https://www.youtube.com/results?search_query=" +
                      encodeURIComponent(titleEn + " trailer");

  const imdbData = buildImdb(row.imdb_id);

  return `
    <tr
      data-title-en="${esc(titleEn)}"
      data-imdb-id="${esc(row.imdb_id || '')}"
    >
      <td>${esc(row.id)}</td>

      <td>
        <a href="poster.php?id=${encodeURIComponent(row.id)}"><b>${esc(titleEn)}</b></a><br>
        <small>${esc(titleHe)}</small>
      </td>

      <td>
        <div class="yt-box"
             data-yt="${esc(ytId)}"
             data-url="${esc(yurl)}">
          <img src="https://img.youtube.com/vi/${esc(ytId)}/0.jpg"
               class="yt-preview check-thumb"
               data-id="${esc(row.id)}"
               data-url="${esc(yurl)}">
          <br>
          <button type="button" class="btn btn-preview">הצג תצוגה מקדימה</button>
        </div>
      </td>

      <td>
        <form action="update_trailer.php" method="POST">
          <input type="hidden" name="poster_id" value="${esc(row.id)}">
          <input type="url" name="trailer_url" placeholder="https://www.youtube.com/watch?v=..." required style="width:95%; padding:8px;" value="${esc(yurl)}">
          <button type="submit" class="btn update-btn">עדכן</button>
        </form>
        <form action="delete_trailer.php" method="POST">
          <input type="hidden" name="poster_id" value="${esc(row.id)}">
          <button type="submit" class="btn delete-btn">🗑️ הסר</button>
        </form>
      </td>

      <td class="yt-search-link">
        <a href="${ytSearchURL}" target="_blank" rel="noopener noreferrer" title="חפש ביוטיוב">
          <img src="images/youtube.png" alt="YouTube">
        </a>
      </td>

      <td class="imdb-id-cell">
        ${imdbData.html}
      </td>
    </tr>
  `;
}

/* ====== בניית שורת טבלה עבור NO (בלי טריילר) ====== */
function rowHtmlNo(row){
  const titleEn = row.title_en || '';
  const titleHe = row.title_he || '';
  const ytSearchURL = "https://www.youtube.com/results?search_query=" +
                      encodeURIComponent(titleEn + " trailer");

  const imdbData = buildImdb(row.imdb_id);

  return `
    <tr
      data-title-en="${esc(titleEn)}"
      data-imdb-id="${esc(row.imdb_id || '')}"
    >
      <td>${esc(row.id)}</td>

      <td>
        <a href="poster.php?id=${encodeURIComponent(row.id)}"><b>${esc(titleEn)}</b></a><br>
        <small>${esc(titleHe)}</small>
      </td>

      <td><span style="color:gray;">אין טריילר</span></td>

      <td>
        <form action="update_trailer.php" method="POST">
          <input type="hidden" name="poster_id" value="${esc(row.id)}">
          <input type="url" name="trailer_url" placeholder="https://www.youtube.com/watch?v=..." required style="width:95%; padding:8px;">
          <button type="submit" class="btn update-btn">עדכן</button>
        </form>
      </td>

      <td class="yt-search-link">
        <a href="${ytSearchURL}" target="_blank" rel="noopener noreferrer" title="חפש ביוטיוב">
          <img src="images/youtube.png" alt="YouTube">
        </a>
      </td>

      <td class="imdb-id-cell">
        ${imdbData.html}
      </td>
    </tr>
  `;
}

/* ====== רינדור טבלאות YES/NO עם עימוד ====== */
function renderYes(){
  const tbody = document.getElementById('yes-tbody');
  if (!tbody) return;

  const totalPages = maxPage(postersYes);
  if (yesPage > totalPages) yesPage = totalPages;
  if (yesPage < 1) yesPage = 1;

  const start = (yesPage-1)*PAGE_SIZE;
  const slice = postersYes.slice(start, start+PAGE_SIZE);

  let html = '';
  for (const row of slice){
    html += rowHtmlYes(row);
  }
  tbody.innerHTML = html;

  document.getElementById('yes-page-now').textContent         = yesPage;
  document.getElementById('yes-page-max').textContent         = totalPages;
  document.getElementById('yes-page-now-bottom').textContent  = yesPage;
  document.getElementById('yes-page-max-bottom').textContent  = totalPages;
}

function renderNo(){
  const tbody = document.getElementById('no-tbody');
  if (!tbody) return;

  const totalPages = maxPage(postersNo);
  if (noPage > totalPages) noPage = totalPages;
  if (noPage < 1) noPage = 1;

  const start = (noPage-1)*PAGE_SIZE;
  const slice = postersNo.slice(start, start+PAGE_SIZE);

  let html = '';
  for (const row of slice){
    html += rowHtmlNo(row);
  }
  tbody.innerHTML = html;

  document.getElementById('no-page-now').textContent        = noPage;
  document.getElementById('no-page-max').textContent        = totalPages;
  document.getElementById('no-page-now-bottom').textContent = noPage;
  document.getElementById('no-page-max-bottom').textContent = totalPages;
}

/* ====== ניווט בעימוד ====== */
function navYes(action){
  const totalPages = maxPage(postersYes);
  if (action === 'first') yesPage = 1;
  else if (action === 'prev') yesPage = Math.max(1, yesPage-1);
  else if (action === 'next') yesPage = Math.min(totalPages, yesPage+1);
  else if (action === 'last') yesPage = totalPages;
  renderYes();
}

function navNo(action){
  const totalPages = maxPage(postersNo);
  if (action === 'first') noPage = 1;
  else if (action === 'prev') noPage = Math.max(1, noPage-1);
  else if (action === 'next') noPage = Math.min(totalPages, noPage+1);
  else if (action === 'last') noPage = totalPages;
  renderNo();
}

/* ====== בדיקת שבורים על כל הטריילרים ====== */
async function checkThumbUrl(ytId){
  if (!ytId) return false;
  const thumbUrl = "https://img.youtube.com/vi/" + encodeURIComponent(ytId) + "/0.jpg";
  try {
    const resp = await fetch(thumbUrl, { method:"HEAD" });
    return resp.ok;
  } catch(e){
    return false;
  }
}

async function runCheckBrokenAll(){
  const brokenTable = document.getElementById("broken-table");
  if (!brokenTable) return;

  brokenTable.innerHTML = `
    <tr>
      <th>ID</th>
      <th>שם הפוסטר</th>
      <th>סטטוס</th>
      <th>קישור YouTube</th>
      <th>עריכה</th>
      <th>יוטיוב</th>
      <th>IMDb</th>
    </tr>
  `;
  document.getElementById("broken-count").textContent = "0";
  document.getElementById("broken-total").textContent = "0";

  let brokenCount = 0;

  // עובר על כל הפוסטרים עם טריילר
  for (const row of postersYes){
    const ytId    = (row.ytId || "").trim();
    const yurl    = (row.trailer_url || "").trim(); // שימוש בעמודה המתוקנת
    const titleEn = row.title_en || "";
    const titleHe = row.title_he || "";
    const imdbRaw = row.imdb_id || "";

    const ok = await checkThumbUrl(ytId);
    if(!ok){
      const ytSearchURL = "https://www.youtube.com/results?search_query=" +
                          encodeURIComponent(titleEn + " trailer");

      let cleanImdb = imdbRaw.trim();
      if (cleanImdb !== "" && !cleanImdb.startsWith("tt")) {
        cleanImdb = "tt" + cleanImdb;
      }
      const imdbUrl = cleanImdb
        ? "https://www.imdb.com/title/" + encodeURIComponent(cleanImdb) + "/"
        : "";

      const brokenRow = document.createElement("tr");
      brokenRow.innerHTML = `
        <td>${esc(row.id)}</td>
        <td>
          <a href="poster.php?id=${encodeURIComponent(row.id)}"><b>${esc(titleEn)}</b></a><br>
          <small>${esc(titleHe)}</small>
        </td>
        <td><div class="yt-broken">❌ טריילר שבור</div></td>
        <td>${esc(yurl)}</td>
        <td>
          <form action="update_trailer.php" method="POST" class="edit-form" style="margin-bottom:8px;">
            <input type="hidden" name="poster_id" value="${esc(row.id)}">
            <input type="url" name="trailer_url" placeholder="YouTube חדש..." required style="width:95%; padding:8px; font-size:14px;">
            <button type="submit" class="btn update-btn">עדכן</button>
          </form>
          <form action="delete_trailer.php" method="POST" class="edit-form">
            <input type="hidden" name="poster_id" value="${esc(row.id)}">
            <button type="submit" class="btn delete-btn">🗑️ הסר</button>
          </form>
        </td>
        <td class="yt-search-link">
          <a href="${ytSearchURL}" target="_blank" rel="noopener noreferrer" title="חפש ביוטיוב">
            <img src="images/youtube.png" alt="YouTube">
          </a>
        </td>
        <td class="imdb-id-cell">
          ${
            imdbUrl !== ""
              ? `<a href="${imdbUrl}" target="_blank" rel="noopener noreferrer">${esc(cleanImdb)}</a>`
              : `<span style="color:#999;">—</span>`
          }
        </td>
      `;
      brokenTable.appendChild(brokenRow);

      brokenCount++;
    }
  }

  document.getElementById("broken-count").textContent = brokenCount;
  document.getElementById("broken-total").textContent = brokenCount;
}

/* ====== האזנות ====== */
document.addEventListener("click", function(e){
  // כפתורי הצג/הסתר בלוקים
  if(e.target.classList.contains("toggle-btn")){
    const target = e.target.dataset.target;
    if(!target) return;
    const wrap = document.getElementById("table-"+target);
    if (!wrap) return;

    wrap.classList.toggle("hidden");
    e.target.textContent = wrap.classList.contains("hidden") ? "הצג" : "הסתר";

    if (target === 'yes' && !wrap.classList.contains('hidden')) {
      renderYes();
    }
    if (target === 'no' && !wrap.classList.contains('hidden')) {
      renderNo();
    }
    if (target === 'broken' && !wrap.classList.contains('hidden')) {
      // פתיחת בלוק "שבורים" תפעיל סריקה על כל הרשומות
      runCheckBrokenAll();
    }
  }

  // ניווט עימוד ל-YES
  if (e.target.matches("[data-yes-nav]")){
    const action = e.target.getAttribute("data-yes-nav");
    navYes(action);
  }

  // ניווט עימוד ל-NO
  if (e.target.matches("[data-no-nav]")){
    const action = e.target.getAttribute("data-no-nav");
    navNo(action);
  }

  // תצוגה מקדימה לטריילר (iframe)
  if(e.target.classList.contains("btn-preview")){
    const box = e.target.closest(".yt-box");
    const yt  = box.dataset.yt;
    if(yt){
      box.innerHTML =
        `<iframe width="220" height="120" src="https://www.youtube.com/embed/${yt}" frameborder="0" allowfullscreen></iframe>`;
    }
  }
});

// כפתור "בדוק שוב" לשבורים -> סורק שוב את כל הטריילרים
document.addEventListener("DOMContentLoaded", function(){
  const btn = document.getElementById("recheck-broken");
  if(btn){
    btn.addEventListener("click", function(){
      runCheckBrokenAll();
    });
  }
});
</script>

</body>
</html>
<?php include 'footer.php'; ?>