<?php
include 'header.php';
require_once 'server.php';

// פונקציות עזר
function fetchOmdbData($imdb_id) {
    $api_key = 'your_omdb_api_key';
    $url = "https://www.omdbapi.com/?i={$imdb_id}&apikey={$api_key}&plot=full&r=json";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

function fetchTmdbData($imdb_id) {
    $tmdb_api_key = 'your_tmdb_api_key';
    // מקבל TMDb ID לפי IMDb ID
    $url = "https://api.themoviedb.org/3/find/{$imdb_id}?api_key={$tmdb_api_key}&language=he-IL&external_source=imdb_id";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

function fetchTvdbData($imdb_id) {
    // פיקטיבי לצורך דוגמה (כאן צריך טוקן גישה וקריאה עם Authorization)
    return [];
}

// טעינת נתונים אם נשלח טופס
$movieData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['imdb_id'])) {
    $imdb_id = $_POST['imdb_id'];

    $omdb = fetchOmdbData($imdb_id);
    $tmdb = fetchTmdbData($imdb_id);
    $tvdb = fetchTvdbData($imdb_id); // לא משמש כרגע בפועל

    $movieData['title_en'] = $omdb['Title'] ?? '';
    $movieData['title_he'] = $tmdb['movie_results'][0]['title'] ?? $tmdb['tv_results'][0]['name'] ?? '';
    $movieData['year'] = $omdb['Year'] ?? '';
    $movieData['type'] = $omdb['Type'] ?? '';
    $movieData['imdb_rating'] = $omdb['imdbRating'] ?? '';
    $movieData['genre'] = $omdb['Genre'] ?? '';
    $movieData['actors'] = $omdb['Actors'] ?? '';
    $movieData['plot_en'] = $omdb['Plot'] ?? '';
    $movieData['plot_he'] = $tmdb['movie_results'][0]['overview'] ?? $tmdb['tv_results'][0]['overview'] ?? '';
    $movieData['poster_url'] = $omdb['Poster'] ?? '';
}
?>

<h2>הוספת פוסטר חדש</h2>

<form method="post">
    <label>IMDb ID: <input type="text" name="imdb_id" required></label>
    <button type="submit">שלוף פרטים</button>
</form>

<?php if (!empty($movieData)): ?>
    <form method="post" action="save.php">
        <input type="hidden" name="imdb_id" value="<?= htmlspecialchars($_POST['imdb_id']) ?>">

        <label>כותרת באנגלית:<br><input type="text" name="title_en" value="<?= htmlspecialchars($movieData['title_en']) ?>"></label><br>
        <label>כותרת בעברית:<br><input type="text" name="title_he" value="<?= htmlspecialchars($movieData['title_he']) ?>"></label><br>
        <label>שנה:<br><input type="text" name="year" value="<?= htmlspecialchars($movieData['year']) ?>"></label><br>
        <label>סוג:<br><input type="text" name="type" value="<?= htmlspecialchars($movieData['type']) ?>"></label><br>
        <label>דירוג IMDb:<br><input type="text" name="imdb_rating" value="<?= htmlspecialchars($movieData['imdb_rating']) ?>"></label><br>
        <label>ז'אנר:<br><input type="text" name="genre" value="<?= htmlspecialchars($movieData['genre']) ?>"></label><br>
        <label>שחקנים (אנגלית בלבד):<br><input type="text" name="actors" value="<?= htmlspecialchars($movieData['actors']) ?>"></label><br>
        <label>תקציר באנגלית:<br><textarea name="plot_en"><?= htmlspecialchars($movieData['plot_en']) ?></textarea></label><br>
        <label>תקציר בעברית:<br><textarea name="plot_he"><?= htmlspecialchars($movieData['plot_he']) ?></textarea></label><br>
        <label>URL של הפוסטר:<br><input type="text" name="image_url" value="<?= htmlspecialchars($movieData['poster_url']) ?>"></label><br>

        <button type="submit">✅ שמור למסד הנתונים</button>
    </form>
<?php endif; ?>

<?php include 'footer.php'; ?>
