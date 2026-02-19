<?php
require_once 'server.php';
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$changes = json_decode($input, true);

if (!$changes) {
    echo json_encode(['success' => false, 'error' => 'No data']);
    exit;
}

$successCount = 0;

foreach ($changes as $id => $fields) {
    $updates = [];
    $types = "";
    $params = [];

    foreach ($fields as $col => $val) {
        // --- רשימת שדות מותרים מעודכנת ---
        $allowed = [
            'title_he', 'title_en', 'year', 'imdb_id', 'imdb_rating', 
            'trailer_url', 'runtime', 'tmdb_url',
            'overview_he', 'genres', 'image_url', 'poster_url' // השמות החדשים
        ];

        if (in_array($col, $allowed)) {
            $updates[] = "$col = ?";
            $types .= "s"; 
            $params[] = $val;
        }
    }

    if (!empty($updates)) {
        $sql = "UPDATE posters SET " . implode(", ", $updates) . " WHERE id = ?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $successCount++;
        }
    }
}

echo json_encode(['success' => true, 'updated' => $successCount]);
?>