<?php
$current = basename($_SERVER['PHP_SELF']);
echo "";
?>
<link rel="stylesheet" href="style.css">

<style>
    html { box-sizing: border-box; }
    *, *:before, *:after { box-sizing: inherit; }
    a { color: inherit; background-color: transparent; }
    
    .w3-bar { width: 100%; overflow: hidden; }
    .w3-bar .w3-bar-item { padding: 8px 16px; float: left; width: auto; border: none; display: block; outline: 0; }
    .w3-bar .w3-button { white-space: normal; }
    .w3-bar:before, .w3-bar:after { content: ""; display: table; clear: both; }
    .w3-padding { padding: 8px 16px !important; }
    .w3-button { border: none; display: inline-block; padding: 8px 16px; vertical-align: middle; overflow: hidden; text-decoration: none; color: inherit; background-color: inherit; text-align: center; cursor: pointer; white-space: nowrap; }
    .w3-black, .w3-hover-black:hover { color: #fff !important; background-color: #000 !important; }
    .w3-white, .w3-hover-white:hover { color: #000 !important; background-color: #fff !important; }
    .w3-button:hover { background-color: grey; color: white !important; }
</style>

<div class="w3-bar w3-padding" style="text-align:center;">
  <?php
  $pages = [
    'index.php' => '🪁 עמוד ראשי',
    'home.php' => '🔎 חיפוש מתקדם',
    'random.php' => '🎲 סרט רנדומלי',
    'new-movie-imdb.php' => '🎞️ סרט חדש', 
    'collections.php' => '📦 אוספים',
    'universe.php' => '🌌 ציר זמן',
    'spotlight.php' => '🎯 זרקור', 
    'top.php' => '🏆 TOP 10',
    'full-info.php' => '🧪 מסוף',
    'similar_all.php' => '🎞️ סרטים דומים',
     'stats.php' => '📈 סטטיסטיקה',
     'site-map.php' => '📚 מפת אתר',
     'contact.php' => '📩 צור קשר',
     'about.php' => '🎉 אודות',
     'about.php' => '🎉 אודות',
    'export.php' => '💾 ייצוא לCSV',
    //'movies.php' => '🎬 סרטים',
    //'series.php' => '📺 סדרות',
    // 'https://api.thiseldb.me/' => 'ניהול',
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
    'auto-add.php' => 'הוספה אוטומטית⚡',
    'toolbox.php' => 'Toolbox - פאנל ניהול⚙️',
  ];
    foreach ($pages as $file => $label) {
    $active = $current == $file ? 'active w3-black' : '';
    echo "<a href='$file' class='w3-button $active'>$label</a>";
  }
  ?>
</div>

<style>
.search-islands {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px; 
  margin: 20px auto;
  position: relative;
  max-width: 800px;
  direction: rtl; 
}

/* שדה החיפוש */
#liveSearchInput {
  padding: 12px 20px;
  font-size: 16px;
  border: 1px solid #333;
  border-radius: 3px;
  width: 300px; 
  outline: none;
  box-shadow: 0 2px 5px rgba(0,0,0,0.5);
  transition: all 0.3s;
  
  /* כפיית צבעים */
  background-color: #000000 !important;
  color: #ffffff !important;
  caret-color: #ffffff;
  -webkit-text-fill-color: #ffffff !important;
  opacity: 1 !important;
}

#liveSearchInput:-webkit-autofill,
#liveSearchInput:-webkit-autofill:hover, 
#liveSearchInput:-webkit-autofill:focus, 
#liveSearchInput:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px #000000 inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

#liveSearchInput::placeholder {
  color: #bbbbbb !important;
  -webkit-text-fill-color: #bbbbbb !important;
  opacity: 1;
}

#liveSearchInput:focus { 
  border-color: #555;
  background-color: #111111 !important;
}

/* כפתור */
.btn-island {
  border: none;
  border-radius: 3px;
  cursor: pointer;
  background-color: #000 !important;
  color: #fff !important;
  font-size: 16px;
  padding: 12px 25px;
  white-space: nowrap;
  transition: transform 0.2s;
  box-shadow: 0 2px 5px rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-island:hover { background-color: #333 !important; transform: scale(1.05); }

/* עוטף יחסי */
.input-wrapper {
    position: relative;
    display: inline-block;
}

/* תוצאות צפות */
#liveSearchResults {
  position: absolute;
  top: 115%;
  right: 0;
  left: 0;
  width: 100%;
  background-color: #111;
  border: 1px solid #333;
  border-radius: 8px;
  z-index: 9999;
  display: none;
  box-shadow: 0 8px 16px rgba(0,0,0,0.9);
  text-align: right;
  overflow: hidden;
}

.search-result-item {
  display: flex;
  gap: 10px;
  padding: 10px;
  border-bottom: 1px solid #222;
  text-decoration: none;
  align-items: center;
  color: #ffffff !important; 
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover { background-color: #222; }

.search-result-item img {
    width: 35px; height: 50px; object-fit: cover; background: #000; border-radius: 3px;
}
</style>

<div class="search-islands">
  <form method="get" action="search.php" style="display: contents;" autocomplete="off">
    <div class="input-wrapper">
        <input type="text" name="q" id="liveSearchInput" placeholder="🔎 הקלד שם סרט..." autocomplete="off">
        <div id="liveSearchResults"></div>
    </div>
    <button type="submit" class="btn-island">🔍 חפש</button>
  </form>
</div>

<script>
    const searchInput = document.getElementById('liveSearchInput');
    const resultsDiv = document.getElementById('liveSearchResults');
    let searchTimeout;

    async function runLiveSearch() {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            resultsDiv.innerHTML = '';
            resultsDiv.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`search_handler.php?q=${encodeURIComponent(query)}`);
            const movies = await response.json();

            resultsDiv.innerHTML = '';

            if (movies.length > 0) {
                resultsDiv.style.display = 'block';
                movies.forEach(movie => {
                    const a = document.createElement('a');
                    a.href = `poster.php?id=${movie.id}`;
                    a.className = 'search-result-item';
                    
                    // הכנת ה-HTML של הדירוג
                    const ratingHtml = movie.rating > 0 ? `⭐ ${movie.rating}` : '';
                    
                    // הוספת השנה בצהוב לאותה שורה
                    const yearHtml = movie.year ? `🗓 ${movie.year}` : '';

                    // >>> [שינוי 1]: הוספת Runtime
                    const runtimeHtml = movie.runtime ? ` | ⏱ ${movie.runtime}` : '';
                    
                    // מפריד: אם יש גם דירוג וגם שנה, נוסיף נקודה ביניהם
                    const separator = (movie.rating > 0 && movie.year) ? ' • ' : '';

                    a.innerHTML = `
                        <img src="${movie.poster}">
                        <div>
                            <div style="font-weight:bold; font-size:14px; color: #ffffff !important;">${movie.title}</div>
                            <div style="font-size:12px; color:#bbbbbb !important;">${movie.sub || ''}</div>
                            
                            <div style="font-size:11px; margin-top:2px; color: #f5c518 !important;">
                                ${ratingHtml}${separator}${yearHtml}${runtimeHtml} </div>
                        </div>
                    `;
                    resultsDiv.appendChild(a);
                });
                
                const allLink = document.createElement('a');
                allLink.href = `search.php?q=${encodeURIComponent(query)}`;
                allLink.className = 'search-result-item';
                allLink.style.justifyContent = 'center';
                allLink.style.color = '#4aa3df !important'; 
                allLink.innerHTML = 'לכל התוצאות >>';
                resultsDiv.appendChild(allLink);

            } else {
                resultsDiv.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
        }
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(runLiveSearch, 300);
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
</script>