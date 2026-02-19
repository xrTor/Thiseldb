<?php
require_once 'server.php';
header('Content-Type: application/json; charset=utf-8');

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$term = $_GET['term'] ?? $_GET['q'] ?? '';
$term = trim($term);

if ($term === '' || mb_strlen($term, 'UTF-8') < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    SELECT id, name, description, is_pinned, is_private, updated_at
    FROM collections
    WHERE name LIKE CONCAT('%', ?, '%')
    ORDER BY is_pinned DESC, updated_at DESC
    LIMIT 20
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $term);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $id   = (int)$row['id'];
    $name = trim((string)$row['name']);
    // $desc = trim((string)$row['description']); // נשמר כתגובה אם תרצה בעתיד

    // 👇 רק שם האוסף – בלי תיאור
    $label = $name;

    $icon = '📁';
    if (!empty($row['is_pinned']))  $icon = '📌';
    if (!empty($row['is_private'])) $icon = '🔒';

    $out[] = [
        'id'    => $id,
        'name'  => $name,
        'label' => $label,
        'icon'  => $icon,
        'url'   => 'collection.php?id=' . $id,
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
