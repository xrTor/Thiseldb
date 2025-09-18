<?php
include 'header.php';
require_once 'server.php';

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;

// כמות לתצוגה
$valid_limits = [20, 50, 100, 250, 'all'];
if (isset($_GET['limit'])) {
    $limit = $_GET['limit'] === 'all' ? 'all' : intval($_GET['limit']);
    if (!in_array($limit, $valid_limits, true)) {
        $limit = 100; // ברירת מחדל
    }
} else {
    $limit = 100; // ברירת מחדל
}

// עמוד נוכחי
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// סוגים
$type_res = $conn->query("SELECT id, label_he, icon, image FROM poster_types ORDER BY sort_order ASC");
$type_options = [];
if ($type_res) {
    while ($row = $type_res->fetch_assoc()) {
        $type_options[$row['id']] = $row;
    }
}

// ספירה כוללת
$count_query = "SELECT COUNT(*) as cnt FROM posters WHERE title_en IS NOT NULL AND title_en != '' ";
if ($search) {
    $count_query .= "AND title_en LIKE '%$search%' ";
}
if ($type_filter) {
    $count_query .= "AND type_id = $type_filter ";
}
$count_res = $conn->query($count_query);
$total_count = $count_res ? (int)$count_res->fetch_assoc()['cnt'] : 0;

// פוסטרים
$query = "
  SELECT id, title_en, title_he, type_id, year, imdb_id, image_url
  FROM posters
  WHERE title_en IS NOT NULL AND title_en != '' ";

if ($search) {
    $query .= "AND title_en LIKE '%$search%' ";
}
if ($type_filter) {
    $query .= "AND type_id = $type_filter ";
}
$query .= "ORDER BY id DESC";

// חישוב LIMIT ו־OFFSET
$offset = 0;
if ($limit !== 'all') {
    $offset = ($page - 1) * intval($limit);
    $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
}

$posters = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>📁 ניהול פוסטרים</title>
    <style>
        body { font-family:Arial; background:#f9f9f9; padding:10px; text-align:center; }
        h1 { margin-bottom:10px; }
        form { margin-bottom:10px; }
        input[type="text"] { padding:8px; width:250px; font-size:14px; }

        select { padding:6px; font-size:14px; margin-right:10px; }

        .type-buttons { 
            margin: 15px 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            gap: 15px; /* מרווח בין הבלוקים */
        }
        .type-button {
            display: flex;
            flex-direction: column; /* הצבת תוכן בעמודה */
            align-items: center; /* יישור תוכן למרכז */
            text-align: center;
            padding: 8px; /* הגדלת ריווח פנימי */
            margin: 0;
            background: transparent; /* רקע שקוף */
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            border: none; /* הסרת המסגרת */
            font-size: 14px;
            transition: transform 0.2s;
        }
        .type-button:hover {
            transform: scale(1.05); /* אפקט ריחוף קל */
        }
        .type-button.active {
            color: #007bff;
        }
        .type-button.active img, .type-button.active .icon {
            border: 2px solid #007bff; /* מסגרת כחולה לפריט פעיל */
            border-radius: 5px;
        }

        .type-button img {
            width: 60px;
            height: 60px; /* תמונה מרובעת */
            background-color: transparent;
        }
        .type-button .icon {
            font-size: 60px; /* גודל אייקון שווה לתמונה */
            width: 60px;
            height: 60px;
            line-height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .type-button span {
            margin-top: 5px; /* מרווח בין התמונה לשם */
            display: block;
            text-align: center;
        }
        .type-buttons .type-button.all-types {
            /* עיצוב מיוחד לכפתור "כל הסוגים" */
            font-weight: bold;
        }

        /* שינוי CSS עבור תא הסוג בטבלה */
        .type-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            justify-content: center;
            height: 100%;
        }
        .table-type-icon {
            height: 40px;
            width: 40px;
            background-color: transparent;
        }
        .type-label {
            display: block;
            font-size: 12px;
            margin-top: 4px;
        }


        table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 0 8px rgba(0,0,0,0.1); }
        th, td { padding:10px; border-bottom:1px solid #ccc; text-align:right; vertical-align:middle; }
        th { background:#eee; }
        tr:hover td { background:#f7f7f7; }

        .poster-title { text-align:right; line-height:1.6; }
        .poster-title .he { color:#777; font-size:13px; display:block; }

        .poster-img { width:50px; height:auto; border-radius:4px; }

        a.action { margin:0 8px; color:#007bff; text-decoration:none; }
        a.action:hover { text-decoration:underline; }

        .pagination { margin:20px 0; }
        .pagination a, .pagination span {
            display:inline-block;
            padding:6px 12px;
            margin:0 2px;
            border:1px solid #ccc;
            border-radius:4px;
            text-decoration:none;
            color:#333;
            background:#fff;
            min-width:34px;
        }
        .pagination a.active {
            background:#007bff;
            color:#fff;
            border-color:#007bff;
            font-weight:bold;
        }
        .pagination span.disabled {
            background:#eee;
            color:#aaa;
            border-color:#ddd;
            cursor:default;
        }
    </style>
</head>
<body>

<h1>📁 ניהול פוסטרים</h1>

<form method="get">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 חפש פוסטר...">
    <select name="limit" onchange="this.form.submit()">
        <?php foreach ($valid_limits as $opt): ?>
            <option value="<?= $opt ?>" <?= ($limit == $opt ? 'selected' : '') ?>>
                <?= $opt === 'all' ? 'הכול' : $opt ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">חיפוש</button>
    <?php if ($search || $type_filter || $limit != 100 || $page > 1): ?>
        <a href="manage_posters.php" style="margin-right:10px;">🔄 איפוס</a>
    <?php endif; ?>
</form>

<div class="type-buttons">
    <a href="?<?= http_build_query(['search' => $search, 'type' => 0, 'limit' => $limit]) ?>" class="type-button all-types <?= $type_filter === 0 ? 'active' : '' ?>">
        <div class="icon">📦</div>
        <span>כל הסוגים</span>
    </a>
    <?php foreach ($type_options as $id => $data): ?>
        <a href="?<?= http_build_query(['search' => $search, 'type' => $id, 'limit' => $limit]) ?>" class="type-button <?= $type_filter === $id ? 'active' : '' ?>">
            <?php if (!empty($data['image'])): ?>
                <img src="images/types/<?= htmlspecialchars($data['image']) ?>" alt="<?= htmlspecialchars($data['label_he']) ?>">
            <?php elseif (!empty($data['icon'])): ?>
                <div class="icon"><?= htmlspecialchars($data['icon']) ?></div>
            <?php endif; ?>
            <span><?= htmlspecialchars($data['label_he']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<p style="margin:20px 0; color:#555;">נמצאו <?= $total_count ?> פוסטרים</p>

<table>
    <tr>
        <th>ID</th>
        <th>תמונה</th>
        <th>שם</th>
        <th>IMDb</th>
        <th>סוג</th>
        <th>שנה</th>
        <th>פעולות</th>
    </tr>
    <?php while ($row = $posters->fetch_assoc()): ?>
        <tr>
            <td><a href="poster.php?id=<?= $row['id'] ?>"><strong><?= $row['id'] ?></strong></a></td>
            <td>
                <?php if (!empty($row['image_url'])): ?>
                    <a href="poster.php?id=<?= $row['id'] ?>"><img src="<?= htmlspecialchars($row['image_url']) ?>" alt="poster" class="poster-img"></a>
                <?php else: ?> — <?php endif; ?>
            </td>
            <td>
                <div class="poster-title">
                    <strong><a href="poster.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['title_en']) ?></a></strong>
                    <span class="he"><?= htmlspecialchars($row['title_he']) ?></span>
                </div>
            </td>
            <td>
                <?php if (!empty($row['imdb_id'])): ?>
                    <a href="https://www.imdb.com/title/<?= htmlspecialchars($row['imdb_id']) ?>" target="_blank"><?= htmlspecialchars($row['imdb_id']) ?></a>
                <?php else: ?> — <?php endif; ?>
            </td>
            <td>
                <div class="type-cell">
                    <?php
                    $type_data = $type_options[$row['type_id']] ?? null;
                    if ($type_data) {
                        if (!empty($type_data['image'])) {
                            echo '<img src="images/types/' . htmlspecialchars($type_data['image']) . '" alt="' . htmlspecialchars($type_data['label_he']) . '" class="table-type-icon">';
                        } elseif (!empty($type_data['icon'])) {
                            echo '<div class="icon">' . htmlspecialchars($type_data['icon']) . '</div>';
                        }
                        echo '<span class="type-label">' . htmlspecialchars($type_data['label_he']) . '</span>';
                    } else {
                        echo '<span style="color:red;">⛔ לא מוכר</span>';
                    }
                    ?>
                </div>
            </td>
            <td><?= $row['year'] ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id'] ?>" class="action">✏️ עריכה</a>
                <a href="delete.php?id=<?= $row['id'] ?>" class="action" onclick="return confirm('האם למחוק פוסטר זה?')">🗑️ מחיקה</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

<?php if ($limit !== 'all'): ?>
    <div class="pagination">
        <?php
        $total_pages = ceil($total_count / $limit);
        if ($total_pages > 1) {
            $start = max(1, $page - 4);
            $end   = min($total_pages, $start + 9);
            if ($end - $start < 9) $start = max(1, $end - 9);

            // ראשון
            if ($page > 1) {
                echo '<a href="?' . http_build_query(['search'=>$search,'type'=>$type_filter,'limit'=>$limit,'page'=>1]) . '">⟪ ראשון</a>';
            } else {
                echo '<span class="disabled">⟪ ראשון</span>';
            }

            // קודם
            if ($page > 1) {
                echo '<a href="?' . http_build_query(['search'=>$search,'type'=>$type_filter,'limit'=>$limit,'page'=>$page-1]) . '">⟨ קודם</a>';
            } else {
                echo '<span class="disabled">⟨ קודם</span>';
            }

            // עמודים
            for ($i = $start; $i <= $end; $i++) {
                $url = '?' . http_build_query(['search'=>$search,'type'=>$type_filter,'limit'=>$limit,'page'=>$i]);
                echo '<a href="'.$url.'" class="'.($i==$page?'active':'').'">'.$i.'</a>';
            }

            // הבא
            if ($page < $total_pages) {
                echo '<a href="?' . http_build_query(['search'=>$search,'type'=>$type_filter,'limit'=>$limit,'page'=>$page+1]) . '">הבא ⟩</a>';
            } else {
                echo '<span class="disabled">הבא ⟩</span>';
            }

            // אחרון
            if ($page < $total_pages) {
                echo '<a href="?' . http_build_query(['search'=>$search,'type'=>$type_filter,'limit'=>$limit,'page'=>$total_pages]) . '">אחרון ⟫</a>';
            } else {
                echo '<span class="disabled">אחרון ⟫</span>';
            }
        }
        ?>
    </div>
<?php endif; ?>

<p style="margin-top:30px;"><a href="add.php">➕ הוסף פוסטר חדש</a></p>

<?php include 'footer.php'; ?>
</body>
</html>