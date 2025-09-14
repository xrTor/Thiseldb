<?php
include 'header.php';
require_once 'server.php';

// ... (קוד עיבוד הטפסים נשאר זהה) ...
// שינוי קבוצתי
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $new_type_id = intval($_POST['bulk_type']);
    $selected = $_POST['selected_ids'] ?? [];
    $input_text = trim($_POST['bulk_mixed_list'] ?? '');
    $updated = 0;
    $not_found = [];
    if (!empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id IN ($placeholders)");
        $stmt->bind_param("i" . str_repeat('i', count($selected)), $new_type_id, ...$selected);
        $stmt->execute();
        $updated += $stmt->affected_rows;
        $stmt->close();
    }
    if ($input_text !== '') {
        $lines = preg_split('/[\s,]+/', $input_text);
        foreach ($lines as $item) {
            $item = trim($item);
            if ($item === '') continue;
            if (preg_match('/tt\d{7,}/', $item, $matches)) {
                $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE imdb_id = ?");
                $stmt->bind_param("is", $new_type_id, $matches[0]);
                if ($stmt->execute() && $stmt->affected_rows > 0) $updated++; else $not_found[] = $matches[0];
                $stmt->close();
            } elseif (is_numeric($item)) {
                $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_type_id, $item);
                if ($stmt->execute() && $stmt->affected_rows > 0) $updated++; else $not_found[] = $item;
                $stmt->close();
            } elseif (preg_match('/poster\.php\?id=(\d+)/', $item, $matches)) {
                $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_type_id, $matches[1]);
                if ($stmt->execute() && $stmt->affected_rows > 0) $updated++; else $not_found[] = $item;
                $stmt->close();
            } else { $not_found[] = $item; }
        }
    }
    if ($updated > 0) echo "<p style='color:green;'>✅ עודכנו $updated פוסטרים!</p>";
    if (!empty($not_found)) echo "<p style='color:orange;'>⚠️ לא נמצאו התאמות עבור: " . implode(', ', array_unique($not_found)) . "</p>";
}
// שינוי פרטני
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    foreach ($_POST['poster_type'] as $poster_id => $new_type_id) {
        $stmt = $conn->prepare("UPDATE posters SET type_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_type_id, $poster_id);
        $stmt->execute();
        $stmt->close();
    }
    echo "<p style='color:green;'>✅ הסוגים הפרטניים עודכנו!</p>";
}

// --- הגדרות עימוד ונתונים ---
$allowed_per_page = [20, 50, 100, 250];
$per_page_request = $_GET['per_page'] ?? 50;
$total_count_result = $conn->query("SELECT COUNT(id) AS total FROM posters");
$total_rows = $total_count_result->fetch_assoc()['total'];
if ($per_page_request === 'all') { $items_per_page = $total_rows > 0 ? $total_rows : 1;
} elseif (in_array((int)$per_page_request, $allowed_per_page)) { $items_per_page = (int)$per_page_request;
} else { $items_per_page = 50; }
$total_pages = $items_per_page > 0 ? ceil($total_rows / $items_per_page) : 1;
$current_page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;
$stmt = $conn->prepare("SELECT id, title_en, title_he, image_url, imdb_id, type_id FROM posters ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

// --- שליפת סוגים כולל תמונות ---
$type_result = $conn->query("SELECT id, label_he, icon, image FROM poster_types ORDER BY sort_order ASC");
$type_options = [];
while ($type = $type_result->fetch_assoc()) {
    $type_options[$type['id']] = [
        'label' => $type['label_he'],
        'icon'  => $type['icon'],
        'image' => $type['image']
    ];
}

// --- בניית HTML של ניווט עמודים ---
ob_start();
if ($per_page_request !== 'all' && $total_pages > 1):
?>
<div class="pagination-nav">
    <?php
    $page_window = 10;
    $start_page = max(1, $current_page - floor($page_window / 2));
    $end_page = min($total_pages, $start_page + $page_window - 1);
    if ($end_page - $start_page + 1 < $page_window) $start_page = max(1, $end_page - $page_window + 1);
    $base_url = "?per_page=$items_per_page&page=";
    ?>
    <?php if ($current_page > 1): ?>
        <a href="<?= $base_url ?>1">« ראשון</a>
        <a href="<?= $base_url . ($current_page - 1) ?>">‹ הקודם</a>
    <?php endif; ?>
    <?php if ($start_page > 1): ?><span class="ellipsis">...</span><?php endif; ?>
    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <a href="<?= $base_url . $i ?>" class="<?= $current_page == $i ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($end_page < $total_pages): ?><span class="ellipsis">...</span><?php endif; ?>
    <?php if ($current_page < $total_pages): ?>
        <a href="<?= $base_url . ($current_page + 1) ?>">הבא ›</a>
        <a href="<?= $base_url . $total_pages ?>">אחרון »</a>
    <?php endif; ?>
</div>
<?php
endif;
$pagination_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="he">
<head>
    <meta charset="UTF-8">
    <title>🎬 ניהול סוגי פוסטרים</title>
    <style>
        body { font-family: Arial; direction: rtl; background: #f5f5ff; padding: 40px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #ccc; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: middle; }
        table img { width: 90px; border-radius: 4px; }
        button { padding: 8px 16px; margin-top: 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h2, h3 { margin-bottom: 25px; }
        textarea {
            resize: vertical;
            width: 100%;
            max-width: 900px;
            padding: 10px;
            font-size: 14px;
            border-radius: 4px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .pagination-controls, .pagination-nav { margin: 20px 0; text-align: center; }
        .pagination-controls span, .pagination-nav span { vertical-align: middle; }
        .pagination-controls a, .pagination-nav a { display: inline-block; padding: 8px 12px; margin: 0 4px; border: 1px solid #ddd; background: #fff; color: #007bff; text-decoration: none; border-radius: 4px; transition: background-color 0.2s; }
        .pagination-controls a:hover, .pagination-nav a:hover { background: #f0f0f0; }
        .pagination-controls a.active, .pagination-nav a.active { background-color: #007bff; color: white; border-color: #007bff; font-weight: bold; }
        .pagination-nav span.ellipsis { padding: 8px 6px; color: #777; }
        
        .visual-select-container { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px; 
            margin-top: 10px; 
            align-items: flex-start;
            justify-content: center;
            padding: 15px;
        }
        #bulk-type-selector {
            background: transparent;
            border: none;
            box-shadow: none;
        }
        .type-option {
            background-color: transparent;
            border: 3px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            width: auto; 
            min-width: 80px;
            height: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 5px;
            box-sizing: border-box;
            transition: all 0.2s ease-in-out;
            text-align: center;
        }
        .type-option:hover { transform: scale(1.05); }
        .type-option.selected {
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.5);
        }
        .type-option img {
            max-width: 100%;
            max-height: 60px;
            object-fit: contain;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
            margin-bottom: 5px;
        }
        .type-option .icon-fallback {
            font-size: 36px;
            color: #888;
            background: #f0f0f0;
            width: 100%;
            height: 60px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }
        .type-option .type-label {
            font-size: 12px;
            color: #555;
            white-space: normal; 
            word-wrap: break-word;
        }
        
        td .visual-select-container { 
            justify-content: flex-start; 
            flex-wrap: nowrap; 
            overflow-x: auto;
            padding: 5px 0;
            gap: 4px;
        }
        td .type-option {
            background-color: transparent;
            border: 2px solid transparent;
            box-shadow: none;
            border-radius: 5px;
            width: auto; 
            min-width: 50px;
            height: auto;
            min-height: 0;
            padding: 2px;
        }
        td .type-option.selected {
            border-color: #007bff;
        }
        td .type-option img { 
            max-height: 45px;
            margin-bottom: 3px;
        }
        td .type-option .icon-fallback { 
            font-size: 20px; 
            height: 30px;
            width: 30px;
            margin-bottom: 3px;
        }
        td .type-option .type-label {
            font-size: 10px;
            line-height: 1.2;
        }
    </style>
</head>
<body>

<h2>📁 ניהול סוגי פוסטרים</h2>
<form method="post">
    <h3>🔁 החלת סוג נבחר</h3>
    <label>בחר סוג:</label>
    
    <div class="visual-select-container" id="bulk-type-selector">
        <?php foreach ($type_options as $type_id => $data): ?>
            <div class="type-option" data-value="<?= $type_id ?>" title="<?= htmlspecialchars($data['label']) ?>">
                <?php if (!empty($data['image'])): ?>
                    <img src="images/types/<?= htmlspecialchars($data['image']) ?>" alt="<?= htmlspecialchars($data['label']) ?>">
                <?php else: ?>
                    <span class="icon-fallback"><?= htmlspecialchars($data['icon']) ?></span>
                <?php endif; ?>
                <span class="type-label"><?= htmlspecialchars($data['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="bulk_type" id="bulk-type-hidden-input">

    <br>
    <label for="bulk_mixed_list">הכנס מזהים (ID, IMDb, או לינקים):</label><br>
    <textarea name="bulk_mixed_list" rows="10" placeholder="
    45
    tt1375666
    https://www.imdb.com/title/tt0111161 
    poster.php?id=78"></textarea>
    <br><br>
    <button type="submit" name="bulk_update" value="1">💾 החל על מזהים / נבחרים</button>
    <button type="submit" name="update" value="1">💾 שמור שינויים פרטניים</button>
    <br><br>
    
    <div class="pagination-controls">
        <span>הצג: </span>
        <?php foreach ($allowed_per_page as $count): ?>
            <a href="?per_page=<?= $count ?>&page=1" class="<?= $per_page_request == $count ? 'active' : '' ?>"><?= $count ?></a>
        <?php endforeach; ?>
        <a href="?per_page=all&page=1" class="<?= $per_page_request == 'all' ? 'active' : '' ?>">הכל</a>
        <span style="margin: 0 15px;">|</span>
        <span>סה"כ: <strong><?= $total_rows ?></strong> פוסטרים</span>
        <span>| עמוד <strong><?= $current_page ?></strong> מתוך <strong><?= $total_pages ?></strong></span>
    </div>
    <?php echo $pagination_html; ?>

    <table>
        <thead>
        <tr>
            <th><input type="checkbox" onclick="toggle(this);"></th>
            <th>תמונה</th>
            <th>שם</th>
            <th>IMDb</th>
            <th>סוג נוכחי</th>
            <th>שינוי סוג</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
            
            <td>
                <a href="poster.php?id=<?= $row['id'] ?>" target="_blank">
                    <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="poster">
                </a>
            </td>
            
            <td>
                <a href="poster.php?id=<?= $row['id'] ?>" target="_blank" style="color: #333; text-decoration: none;">
                    <strong><?= htmlspecialchars($row['title_en']) ?></strong><br>
                    <span style="color:#555;"><?= htmlspecialchars($row['title_he']) ?></span>
                </a>
            </td>

            <td>
                <?php if (!empty($row['imdb_id'])): ?><a href="https://www.imdb.com/title/<?= htmlspecialchars($row['imdb_id']) ?>" target="_blank"><?= htmlspecialchars($row['imdb_id']) ?></a><?php else: ?>—<?php endif; ?>
            </td>
            
            <td>
                <?php if(isset($type_options[$row['type_id']])): $current_type = $type_options[$row['type_id']]; ?>
                    <?php if(!empty($current_type['image'])): ?>
                        <img src="images/types/<?= htmlspecialchars($current_type['image']) ?>" alt="" style="width:40px; height:auto; vertical-align:middle; margin-left: 5px;">
                    <?php else: ?>
                        <span style="font-size: 20px; vertical-align: middle; margin-left: 5px;"><?= htmlspecialchars($current_type['icon']) ?></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($current_type['label']) ?>
                <?php else: ?>
                    <span style="color:red;">⛔ לא מוכר</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="visual-select-container">
                    <?php foreach ($type_options as $type_id => $data): ?>
                        <div class="type-option type-option-row <?= ($row['type_id'] == $type_id) ? 'selected' : '' ?>" data-value="<?= $type_id ?>" title="<?= htmlspecialchars($data['label']) ?>">
                             <?php if (!empty($data['image'])): ?>
                                <img src="images/types/<?= htmlspecialchars($data['image']) ?>" alt="<?= htmlspecialchars($data['label']) ?>">
                            <?php else: ?>
                                <span class="icon-fallback"><?= htmlspecialchars($data['icon']) ?></span>
                            <?php endif; ?>
                            <span class="type-label"><?= htmlspecialchars($data['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <select name="poster_type[<?= $row['id'] ?>]" style="display: none;">
                    <?php foreach ($type_options as $type_id => $data): ?>
                        <option value="<?= $type_id ?>" <?= ($row['type_id'] == $type_id) ? 'selected' : '' ?>></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php echo $pagination_html; ?>

    <button type="submit" name="bulk_update" value="1">💾 החל על מזהים / נבחרים</button>
    <button type="submit" name="update" value="1">💾 שמור שינויים פרטניים</button>
</form>

<script>
// ... (ה-JavaScript נשאר זהה לחלוטין ועובד עם המבנה החדש) ...
document.addEventListener('DOMContentLoaded', function() {
    const bulkSelector = document.getElementById('bulk-type-selector');
    const bulkHiddenInput = document.getElementById('bulk-type-hidden-input');
    
    if (bulkSelector && bulkSelector.firstElementChild) {
        let hasSelected = false;
        bulkSelector.querySelectorAll('.type-option').forEach(opt => {
            if (opt.classList.contains('selected')) {
                hasSelected = true;
            }
        });
        if (!hasSelected) {
            bulkSelector.firstElementChild.classList.add('selected');
            bulkHiddenInput.value = bulkSelector.firstElementChild.getAttribute('data-value');
        }
    }

    document.body.addEventListener('click', function(e) {
        const selectedOption = e.target.closest('.type-option');
        if (!selectedOption) return;

        const container = selectedOption.closest('.visual-select-container');
        let hiddenInput;

        if (container.id === 'bulk-type-selector') {
            hiddenInput = document.getElementById('bulk-type-hidden-input');
        } else {
            // עבור שורות הטבלה, מצא את ה-select הנסתר
            const selectElement = container.nextElementSibling;
            if(selectElement && selectElement.tagName === 'SELECT') {
                 hiddenInput = selectElement;
            }
        }
        
        if (hiddenInput) {
            container.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('selected'));
            selectedOption.classList.add('selected');
            hiddenInput.value = selectedOption.getAttribute('data-value');
        }
    });
});

function toggle(source) {
    document.getElementsByName('selected_ids[]').forEach(checkbox => {
        checkbox.checked = source.checked;
    });
}
</script>

</body>
</html>
<?php include 'footer.php'; ?>