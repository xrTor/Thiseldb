<?php
include 'header.php';

require_once 'server.php';

// --- Data Fetching (Common for both themes) ---
function safeCount($conn, $table)
{
    $res = $conn->query("SELECT COUNT(*) as c FROM $table");
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc()['c'] : 0;
}

$stats = [
    'posters'     => safeCount($conn, 'posters'),
    'collections' => safeCount($conn, 'collections'),
    'contacts'    => safeCount($conn, 'contact_requests'),
    'votes'       => safeCount($conn, 'poster_votes'),
    'reports'     => safeCount($conn, 'poster_reports')
];

$latest_contacts = $conn->query("SELECT * FROM contact_requests ORDER BY created_at DESC LIMIT 5");
$latest_votes = $conn->query("SELECT pv.*, p.title_en FROM poster_votes pv JOIN posters p ON p.id = pv.poster_id ORDER BY pv.created_at DESC LIMIT 5");
$latest_posters = $conn->query("SELECT * FROM posters ORDER BY created_at DESC LIMIT 5");
$latest_reports = $conn->query("SELECT pr.*, po.title_en FROM poster_reports pr JOIN posters po ON po.id = pr.poster_id ORDER BY pr.created_at DESC LIMIT 5");

// --- Theme Logic ---
$theme = $_GET['theme'] ?? 'modern';
$is_modern = ($theme === 'modern');
$switch_theme_url = $is_modern ? '?theme=classic' : '?theme=modern';
$switch_theme_text = $is_modern ? 'הצג עיצוב קלאסי' : 'הצג עיצוב מודרני';

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מרכז ניהול</title>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const storedTheme = localStorage.getItem('panelTheme');
            const urlParams = new URLSearchParams(window.location.search);
            const currentTheme = urlParams.get('theme') || 'modern';

            if (storedTheme && storedTheme !== currentTheme) {
                window.location.search = `theme=${storedTheme}`;
                return;
            }
            
            const switcher = document.getElementById('theme-switcher');
            if (switcher) {
                switcher.addEventListener('click', (e) => {
                    const newTheme = e.target.dataset.theme;
                    localStorage.setItem('panelTheme', newTheme);
                });
            }
        });
    </script>
    
    <?php if ($is_modern): ?>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --primary-color: #3498db; --secondary-color: #2ecc71; --danger-color: #e74c3c;
        --background-color: #ecf0f1; --widget-background: #ffffff; --text-color: #34495e;
        --light-text-color: #7f8c8d; --border-radius: 12px; --box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        --box-shadow-hover: 0 8px 25px rgba(0,0,0,0.1);
      }
      body { font-family: 'Roboto', Arial, sans-serif; background-color: var(--background-color); color: var(--text-color); padding: 20px; margin: 0; direction: rtl; }
      h1, h2, h3, h4 { font-weight: 500; }
      .main-header { font-size: 2.5rem; font-weight: 700; text-align: center; margin-bottom: 30px; color: var(--text-color); letter-spacing: -1px; }
      .container { max-width: 1200px; margin: 0 auto; }
      .grid-layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-bottom: 40px; }
      .full-width-grid { grid-template-columns: 1fr; }
      .card { background: var(--widget-background); padding: 25px 30px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); transition: transform 0.3s ease, box-shadow 0.3s ease; border-top: 4px solid var(--primary-color); }
      .card:hover { transform: translateY(-5px); box-shadow: var(--box-shadow-hover); }
      .card-header { display: flex; align-items: center; gap: 12px; margin: -25px -30px 25px; padding: 20px 30px; font-size: 1.4rem; color: var(--text-color); background-color: #f8f9fa; border-bottom: 1px solid #e0e0e0; border-top-left-radius: var(--border-radius); border-top-right-radius: var(--border-radius); }
      .nav-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(125px, 1fr)); gap: 15px; }
      .nav-button { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px 10px; text-decoration: none; color: #fff; background: var(--btn-color, var(--primary-color)); border-radius: var(--border-radius); font-weight: 500; text-align: center; transition: transform 0.2s ease-out, box-shadow 0.2s ease-out; min-height: 90px; }
      .nav-button:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
      .nav-icon { font-size: 1.8rem; line-height: 1; }
      .nav-text { margin-top: 10px; font-size: 0.85rem; line-height: 1.2; }
      .stat-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; font-size: 1rem; border-bottom: 1px solid #f2f2f2; }
      .stat-item:last-child { border-bottom: none; }
      .stat-item span:first-child { font-weight: 500; }
      .stat-item .count { font-weight: 700; font-size: 1.1rem; background-color: var(--background-color); padding: 5px 10px; border-radius: 12px; }
      .activity-entry { background-color: #f9f9f9; padding: 15px; border-radius: var(--border-radius); margin-bottom: 12px; border-left: 4px solid var(--secondary-color); }
      .activity-entry:last-child { margin-bottom: 0; }
      .activity-entry strong { font-weight: 500; }
      .activity-entry small { color: var(--light-text-color); font-size: 0.85rem; display: block; margin-top: 8px; }
      .activity-entry a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
      .activity-entry a:hover { text-decoration: underline; }
      .reports .card, .reports .activity-entry { border-color: var(--danger-color); }
      .no-data { color: var(--light-text-color); text-align: center; padding: 20px; }
      .theme-switcher-container { text-align: left; margin-bottom: 15px; }
      .theme-switcher-container a { background: #fff; color: var(--primary-color); padding: 8px 15px; border-radius: 20px; text-decoration: none; font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.15); display: inline-block; }
    </style>

    <?php else: ?>
    <style>
      body { font-family: Arial; background:#f4f4f4; padding:40px; direction:rtl; }
      .box-grid { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:40px; }
      .stat-box, .nav-box { background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 6px rgba(0,0,0,0.1); flex:1; min-width:200px; }
      h1 { margin-bottom: 20px; }
      h2 { margin-bottom:20px; }
      .nav-box a { display:block; margin-bottom:10px; padding:10px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
      .nav-box a:hover { background:#0056b3; }
      .recent-box { margin-bottom:30px; }
      .entry { background:#fff; padding:10px; border-radius:6px; margin-bottom:10px; box-shadow:0 0 4px rgba(0,0,0,0.05); }
      .entry small { color:#888; font-size:12px; display:block; margin-top:6px; }
      .theme-switcher-container { text-align: left; margin-bottom: 20px; }
      .theme-switcher-container a { background: #007bff; color: #fff; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: 500; display: inline-block; }
    </style>
    <?php endif; ?>
</head>

<body>
<?php if ($is_modern): ?>
<div class="container">
      <div class="theme-switcher-container">
          <a id="theme-switcher" href="<?= $switch_theme_url ?>" data-theme="classic"><?= $switch_theme_text ?></a>
      </div>
      <h1 class="main-header">📋 מרכז ניהול מערכת</h1>
      
      <div class="grid-layout full-width-grid">
        <div class="card">
            <h2 class="card-header">🧭 ניווט מהיר</h2>
            <div class="nav-grid">
                <a href="manage_posters.php" class="nav-button" style="--btn-color: #27ae60;"><span class="nav-icon">🎬</span><span class="nav-text">ניהול פוסטרים</span></a>
                <a href="manage_collections.php" class="nav-button" style="--btn-color: #2980b9;"><span class="nav-icon">📦</span><span class="nav-text">ניהול אוספים</span></a>
                <a href="manage_contacts.php" class="nav-button" style="--btn-color: #f39c12;"><span class="nav-icon">📩</span><span class="nav-text">ניהול פניות</span></a>
                <a href="manage_reports.php" class="nav-button" style="--btn-color: #c0392b;"><span class="nav-icon">🚨</span><span class="nav-text">ניהול דיווחים</span></a>
                <a href="manage_trailers.php" class="nav-button" style="--btn-color: #8e44ad;"><span class="nav-icon">▶️</span><span class="nav-text">ניהול טריילרים</span></a>
                <a href="manage_genres.php" class="nav-button" style="--btn-color: #16a085;"><span class="nav-icon">🎭</span><span class="nav-text">ניהול ז'אנרים</span></a>
                <a href="manage_user_tag.php" class="nav-button" style="--btn-color: #d35400;"><span class="nav-icon">🏷️</span><span class="nav-text">ניהול תגיות</span></a>
                <a href="manage_name_genres.php" class="nav-button" style="--btn-color: #5D6D7E;"><span class="nav-icon">✍️</span><span class="nav-text">ניהול שמות ז'אנרים</span></a>
                <a href="manage_name_user_tag.php" class="nav-button" style="--btn-color: #5D6D7E;"><span class="nav-icon">✍️</span><span class="nav-text">ניהול שמות תגיות</span></a>
                <a href="manage_languages.php" class="nav-button" style="--btn-color: #34495e;"><span class="nav-icon">🌐</span><span class="nav-text">ניהול שפות</span></a>
                <a href="manage_types.php" class="nav-button" style="--btn-color: #717D7E;"><span class="nav-icon">📋</span><span class="nav-text">ניהול סוגים</span></a>
                <a href="manage_type_admin.php" class="nav-button" style="--btn-color: #717D7E;"><span class="nav-icon">🔗</span><span class="nav-text">שיוך סוגים</span></a>
                <a href="manage_titles.php" class="nav-button" style="--btn-color: #4A235A;"><span class="nav-icon">🔤</span><span class="nav-text">ניהול כותרות</span></a>
                <a href="manage_missing.php" class="nav-button" style="--btn-color: #B7950B;"><span class="nav-icon">❓</span><span class="nav-text">פוסטרים חסרים</span></a>
                <a href="manage_sync.php" class="nav-button" style="--btn-color: #7f8c8d;"><span class="nav-icon">🔄</span><span class="nav-text">סנכרון תמונות</span></a>
            </div>
        </div>
      </div>

      <div class="grid-layout">
        <div class="card">
          <h2 class="card-header">📊 סטטיסטיקות</h2>
          <div class="stat-list">
            <div class="stat-item"><span>🎬 פוסטרים</span> <span class="count"><?= $stats['posters'] ?></span></div>
            <div class="stat-item"><span>📦 אוספים</span> <span class="count"><?= $stats['collections'] ?></span></div>
            <div class="stat-item"><span>📩 פניות</span> <span class="count"><?= $stats['contacts'] ?></span></div>
            <div class="stat-item"><span>❤️/💔 הצבעות</span> <span class="count"><?= $stats['votes'] ?></span></div>
            <div class="stat-item"><span>🚨 דיווחים</span> <span class="count"><?= $stats['reports'] ?></span></div>
          </div>
        </div>
      </div>
      
      <div class="grid-layout">
        <div class="card">
            <h2 class="card-header">📩 פניות אחרונות</h2>
            <?php if ($latest_contacts && $latest_contacts->num_rows > 0): while ($row = $latest_contacts->fetch_assoc()): ?>
                <div class="activity-entry">
                    <strong><?= htmlspecialchars($row['message']) ?></strong>
                    <small><?= htmlspecialchars($row['created_at']) ?></small>
                </div>
            <?php endwhile; else: ?>
                <p class="no-data">אין פניות זמינות.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-header">🗳️ הצבעות אחרונות</h2>
            <?php if ($latest_votes && $latest_votes->num_rows > 0): while ($row = $latest_votes->fetch_assoc()): ?>
                <div class="activity-entry">
                    <strong><?= $row['vote_type'] === 'like' ? '❤️ אהבתי' : '💔 לא אהבתי' ?> על "<?= htmlspecialchars($row['title_en']) ?>"</strong>
                    <small><?= htmlspecialchars($row['created_at']) ?> | מזהה: <?= htmlspecialchars($row['visitor_token']) ?></small>
                </div>
            <?php endwhile; else: ?>
                <p class="no-data">אין הצבעות זמינות.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2 class="card-header">🆕 פוסטרים שנוספו</h2>
            <?php if ($latest_posters && $latest_posters->num_rows > 0): while ($row = $latest_posters->fetch_assoc()): ?>
                <div class="activity-entry">
                    <strong><?= htmlspecialchars($row['title_en']) ?></strong>
                    <small><?= htmlspecialchars($row['created_at']) ?> | ID: <?= $row['id'] ?></small>
                </div>
            <?php endwhile; else: ?>
                <p class="no-data">אין פוסטרים חדשים.</p>
            <?php endif; ?>
        </div>

        <div class="card reports">
            <h2 class="card-header">🚨 דיווחים אחרונים</h2>
            <?php if ($latest_reports && $latest_reports->num_rows > 0): while ($row = $latest_reports->fetch_assoc()): ?>
                <div class="activity-entry">
                    <strong><?= htmlspecialchars($row['reason'] ?? 'דיווח ללא סיבה') ?></strong>
                    <small>
                        <?= htmlspecialchars($row['created_at']) ?> |
                        מזהה מדווח: <?= htmlspecialchars($row['reporter_token'] ?? 'לא ידוע') ?> |
                        על <a href="poster.php?id=<?= $row['poster_id'] ?>" target="_blank">"<?= htmlspecialchars($row['title_en']) ?>"</a>
                    </small>
                </div>
            <?php endwhile; else: ?>
                <p class="no-data">אין דיווחים זמינים.</p>
            <?php endif; ?>
        </div>
      </div>
    </div>

<?php else: ?>
<div class="theme-switcher-container">
        <a id="theme-switcher" href="<?= $switch_theme_url ?>" data-theme="modern"><?= $switch_theme_text ?></a>
    </div>
    <h1>📋 מרכז ניהול מערכת</h1>
    
    <div class="box-grid">
      <div class="stat-box">
        <h2>📊 סטטיסטיקות</h2>
        <p>🎬 פוסטרים: <?= $stats['posters'] ?></p>
        <p>📦 אוספים: <?= $stats['collections'] ?></p>
        <p>📩 פניות צור קשר: <?= $stats['contacts'] ?></p>
        <p>❤️/💔 הצבעות: <?= $stats['votes'] ?></p>
        <p>🚨 דיווחים: <?= $stats['reports'] ?></p>
      </div>
      <div class="nav-box">
        <h2>🧭 ניווט מהיר</h2>
        <a href="manage_posters.php">ניהול פוסטרים</a><a href="manage_collections.php">ניהול אוספים</a><a href="manage_contacts.php">ניהול פניות</a><a href="manage_reports.php">ניהול דיווחים</a><a href="manage_trailers.php">ניהול טריילרים</a><a href="manage_genres.php">ניהול ז'אנרים</a><a href="manage_user_tag.php">ניהול תגיות</a><a href="manage_name_genres.php">ניהול שמות ז'אנרים</a><a href="manage_name_user_tag.php">ניהול שמות תגיות</a><a href="manage_languages.php">ניהול שפות</a><a href="manage_types.php">ניהול סוגים</a><a href="manage_type_admin.php">שיוך סוגים</a><a href="manage_titles.php">ניהול שמות כותרות</a><a href="manage_missing.php">ניהול פוסטרים חסרים</a><a href="manage_sync.php">סנכרון תמונות מהאינטרנט</a>
      </div>
    </div>
    
    <div class="recent-box">
      <h2>🕓 פעילות אחרונה</h2>
      <h3>📩 פניות אחרונות</h3>
      <?php if ($latest_contacts && $latest_contacts->num_rows > 0): while ($row = $latest_contacts->fetch_assoc()): ?><div class="entry"><strong><?= htmlspecialchars($row['message']) ?></strong><small><?= htmlspecialchars($row['created_at']) ?></small></div><?php endwhile; else: ?><p>אין פניות זמינות.</p><?php endif; ?>
      <h3>🗳️ הצבעות אחרונות</h3>
      <?php if ($latest_votes && $latest_votes->num_rows > 0): while ($row = $latest_votes->fetch_assoc()): ?><div class="entry"><strong><?= $row['vote_type'] === 'like' ? '❤️ אהבתי' : '💔 לא אהבתי' ?> על <?= htmlspecialchars($row['title_en']) ?></strong><small><?= htmlspecialchars($row['created_at']) ?> | מזהה: <?= htmlspecialchars($row['visitor_token']) ?></small></div><?php endwhile; else: ?><p>אין הצבעות זמינות.</p><?php endif; ?>
      <h3>🆕 פוסטרים שנוספו</h3>
      <?php if ($latest_posters && $latest_posters->num_rows > 0): while ($row = $latest_posters->fetch_assoc()): ?><div class="entry"><strong><?= htmlspecialchars($row['title_en']) ?></strong><small><?= htmlspecialchars($row['created_at']) ?> | ID: <?= $row['id'] ?></small></div><?php endwhile; else: ?><p>אין פוסטרים חדשים.</p><?php endif; ?>
      <h3>🚨 דיווחים אחרונים</h3>
      <?php if ($latest_reports && $latest_reports->num_rows > 0): while ($row = $latest_reports->fetch_assoc()): ?><div class="entry"><strong><?= htmlspecialchars($row['reason'] ?? 'דיווח ללא סיבה') ?></strong><br><small><?= htmlspecialchars($row['created_at']) ?> | מזהה מדווח: <?= htmlspecialchars($row['reporter_token'] ?? 'לא ידוע') ?> | על <a href="poster.php?id=<?= $row['poster_id'] ?>" target="_blank"><?= htmlspecialchars($row['title_en']) ?></a></small></div><?php endwhile; else: ?><p>אין דיווחים זמינים.</p><?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
<?php
$conn->close();
include 'footer.php';
?>