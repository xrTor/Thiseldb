if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // קליטת משתנים
    $title_en    = $_POST['title_en'] ?? '';
    $title_he    = $_POST['title_he'] ?? '';
    $year        = $_POST['year'] ?? '';
    $imdb_rating = $_POST['imdb_rating'] ?? '';
    $imdb_link   = $_POST['imdb_link'] ?? '';
    $image_url   = $_POST['image_url'] ?? '';
    $plot        = $_POST['plot'] ?? '';
    $type        = $_POST['type'] ?? 'movie';
    $tvdb_id     = $_POST['tvdb_id'] ?? '';

    // שמירה למסד
    $stmt = $conn->prepare("INSERT INTO posters 
    (title_en, title_he, year, imdb_rating, imdb_link, image_url, plot, type, tvdb_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssss", $title_en, $title_he, $year, $imdb_rating, $imdb_link, $image_url, $plot, $type, $tvdb_id);
    $stmt->execute();
    $poster_id = $conn->insert_id;
    $stmt->close();

    // שמירת תגיות
    if (!empty($_POST['categories'])) {
        foreach ($_POST['categories'] as $cat_id) {
            $stmt = $conn->prepare("INSERT INTO poster_categories (poster_id, category_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $poster_id, $cat_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<p style='color:green; text-align:center;'>✅ פוסטר נוסף בהצלחה!</p>";
}
