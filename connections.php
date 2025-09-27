<?php
include 'header.php';
require_once 'server.php';

// 1. קבלת פרמטרים מה-URL
if (!isset($_GET['label']) || empty($_GET['label'])) {
    die('שגיאה: לא נבחר סוג קשר.');
}
$label = $_GET['label'];

// 2. השאילתה מורחבת כדי לכלול גם את שנת ההפקה
$sql = "
    SELECT DISTINCT
        original_poster.id AS source_id,
        original_poster.title_en AS source_title,
        original_poster.title_he AS source_title_he,
        original_poster.imdb_id AS source_imdb_id,
        original_poster.year AS source_year,
        sequel_poster.id AS target_id,
        sequel_poster.title_en AS target_title,
        sequel_poster.title_he AS target_title_he,
        sequel_poster.imdb_id AS target_imdb_id,
        sequel_poster.year AS target_year
    FROM
        poster_connections pc
    INNER JOIN
        posters AS original_poster ON pc.related_imdb_id = original_poster.imdb_id
    LEFT JOIN
        posters AS sequel_poster ON pc.poster_id = sequel_poster.id
    WHERE
        pc.relation_label = ?
    ORDER BY
        source_title, target_title
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $label);
$stmt->execute();
$result = $stmt->get_result();

// 3. איסוף וקיבוץ התוצאות, כולל שנת ההפקה
$grouped_connections = [];
while ($row = $result->fetch_assoc()) {
    $source_id = $row['source_id'];

    if (!isset($grouped_connections[$source_id])) {
        $grouped_connections[$source_id] = [
            'title' => $row['source_title'],
            'title_he' => $row['source_title_he'],
            'imdb_id' => $row['source_imdb_id'],
            'year' => $row['source_year'],
            'linked_posters' => []
        ];
    }

    if ($row['target_id']) {
        $grouped_connections[$source_id]['linked_posters'][] = [
            'id' => $row['target_id'],
            'title' => $row['target_title'],
            'title_he' => $row['target_title_he'],
            'imdb_id' => $row['target_imdb_id'],
            'year' => $row['target_year']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🔗 קשרים מסוג: <?= htmlspecialchars($label) ?></title>
    <style>
        body { font-family: Arial; background:#f4f4f4; padding:20px; text-align:center; direction:rtl; max-width:1000px; margin:auto; }
        .box { background:#fff; padding:30px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.1); margin:30px 0; text-align:right; }
        h1 { margin-bottom: 20px; }
        table { width:100%; border-collapse:collapse; background:white; margin-top:20px; }
        th, td { padding:12px; border-bottom:1px solid #ccc; text-align:right; vertical-align: top; }
        th { background:#eee; font-weight: bold; }
        a { text-decoration: none; }
        table a { color: green !important; }
        a:hover { text-decoration: underline; }
        
        .linked-list-cell { list-style-type: none; padding: 0; margin: 0; font-weight: normal; }
        .linked-list-cell li { padding-bottom: 10px; }
        .linked-list-cell li:last-child { padding-bottom: 0; }

        .poster-meta {
            font-size: 0.85em;
            color: #555;
            font-weight: normal;
            padding-top: 4px;
        }
        /* עיצוב חדש לקישור של IMDb */
        .poster-meta a {
            color: #007bff !important; /* צבע כחול סטנדרטי לקישור */
        }
        /* --- הכלל נשמר כפי שביקשת --- */
        footer .box {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            text-align: center !important;
        }
    </style>
</head>
<body>

    <h1><br> 🔗 קשרים מסוג: "<?= htmlspecialchars($label) ?>"</h1>

    <div class="box">
        <table>
            <thead>
                <tr>
                    <th>פוסטר מקור (הסרט המקורי)</th>
                    <th>פוסטר מקושר</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($grouped_connections)): ?>
                    <?php foreach ($grouped_connections as $source_id => $data): ?>
                        <tr>
                            <td>
                                <a href="poster.php?id=<?= (int)$source_id ?>">
                                    <b><?= htmlspecialchars($data['title']) ?> (<?= htmlspecialchars($data['year']) ?>)</b>
                                </a>
                                <div class="poster-meta">
                                    <?php if (!empty($data['title_he'])): ?>
                                        <?= htmlspecialchars($data['title_he']) ?><br>
                                    <?php endif; ?>
                                    (<a href="https://www.imdb.com/title/<?= htmlspecialchars($data['imdb_id']) ?>/" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($data['imdb_id']) ?></a>)
                                </div>
                            </td>
                            <td>
                                <ul class="linked-list-cell">
                                    <?php foreach ($data['linked_posters'] as $linked_poster): ?>
                                        <li>
                                            <a href="poster.php?id=<?= (int)$linked_poster['id'] ?>">
                                                <?= htmlspecialchars($linked_poster['title'] ?? '#'.(int)$linked_poster['id']) ?> (<?= htmlspecialchars($linked_poster['year']) ?>)
                                            </a>
                                            <div class="poster-meta">
                                                <?php if (!empty($linked_poster['title_he'])): ?>
                                                    <?= htmlspecialchars($linked_poster['title_he']) ?><br>
                                                <?php endif; ?>
                                                (<a href="https://www.imdb.com/title/<?= htmlspecialchars($linked_poster['imdb_id']) ?>/" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($linked_poster['imdb_id']) ?></a>)
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">לא נמצאו קשרים התואמים לסינון.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p><a href="stats.php" style="color:#007bff;">⬅ חזרה לדף הבית</a></p>

</body>
</html>

<?php
$stmt->close();
include 'footer.php';
?>