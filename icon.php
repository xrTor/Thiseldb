<?php
$apiKey = '931b94936ba364daf0fd91fb38ecd91e';
$uniqueNetworks = [];
$pageLimit = 3;

for ($page = 1; $page <= $pageLimit; $page++) {
    $url = "https://api.themoviedb.org/3/tv/popular?api_key=$apiKey&language=en-US&page=$page";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (!isset($data['results'])) continue;

    foreach ($data['results'] as $tvShow) {
        $tvId = $tvShow['id'];
        $detailsUrl = "https://api.themoviedb.org/3/tv/$tvId?api_key=$apiKey&language=en-US";
        $detailsResponse = file_get_contents($detailsUrl);
        $details = json_decode($detailsResponse, true);

        if (isset($details['networks'])) {
            foreach ($details['networks'] as $network) {
                $id = $network['id'];
                // ניקוי: lowercase + הסרת רווחים מכל מקום
                $cleanName = mb_strtolower(str_replace(' ', '', trim($network['name'])));
                if (!isset($uniqueNetworks[$id])) {
                    $uniqueNetworks[$id] = [
                        'name' => $cleanName,
                        'logo' => isset($network['logo_path']) ? $network['logo_path'] : null
                    ];
                }
            }
        }
    }
}

// הצגת התוצאה בטבלה
echo "<h2>רשימת רשומות ייחודיות מתוך סדרה פופולרית:</h2>";
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>שם היחידה</th><th>לוגו</th></tr>";
foreach ($uniqueNetworks as $id => $info) {
    $logoUrl = $info['logo'] ? "https://image.tmdb.org/t/p/w92{$info['logo']}" : '—';
    echo "<tr>";
    echo "<td>$id</td>";
    echo "<td>{$info['name']}</td>";
    echo "<td>";
    echo $info['logo'] ? "<img src='$logoUrl' alt='{$info['name']}' height='40'>" : "אין לוגו";
    echo "</td>";
    echo "</tr>";
}
echo "</table>";
?>
