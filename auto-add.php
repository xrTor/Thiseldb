<?php
include 'header.php';
require_once 'server.php';
require_once 'imdb.class.php';

set_time_limit(3000000);

function safe($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$genre_he = [
    'Action' => 'אקשן',
    'Adventure' => 'הרפתקאות',
    'Animation' => 'אנימציה',
    'Biography' => 'ביוגרפיה',
    'Comedy' => 'קומדיה',
    'Crime' => 'פשע',
    'Documentary' => 'תיעודי',
    'Drama' => 'דרמה',
    'Family' => 'משפחה',
    'Fantasy' => 'פנטזיה',
    'History' => 'היסטוריה',
    'Horror' => 'אימה',
    'Music' => 'מוזיקה',
    'Musical' => 'מיוזיקל',
    'Mystery' => 'מיסתורין',
    'News' => 'חדשות',
    'Reality-TV' => 'ריאליטי',
    'Romance' => 'רומנטיקה',
    'Sci-Fi' => 'מדע בדיוני',
    'Short' => 'קצר',
    'Sport' => 'ספורט',
    'Talk-Show' => 'תוכנית אירוח',
    'Thriller' => 'מותחן',
    'War' => 'מלחמה',
    'Western' => 'מערבון'
];

$report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imdb_ids'])) {
    $ids = preg_split('/[\n\r]+/', $_POST['imdb_ids']);

    foreach ($ids as $raw) {
        $raw = trim($raw);
        if ($raw === '') continue;

        if (preg_match('/tt\d+/', $raw, $matches)) {
            $imdb_id = $matches[0];
        } elseif (preg_match('/(?:title\/)?(tt\d+)/', $raw, $matches)) {
            $imdb_id = $matches[1];
        } else {
            $report[] = "<div class='alert alert-danger'>❌ IMDb ID לא תקין: $raw</div>";
            continue;
        }

        $omdb_url = "https://www.omdbapi.com/?apikey=$omdb_key&i=$imdb_id&plot=full";
        $omdb = @json_decode(file_get_contents($omdb_url), true);
        if (!$omdb || $omdb['Response'] == 'False') {
            $report[] = "<div class='alert alert-danger'>❌ לא ניתן למצוא מידע OMDb עבור $imdb_id</div>";
            continue;
        }

        $imdb_rating = is_numeric($omdb['imdbRating']) ? floatval($omdb['imdbRating']) : null;
        $title_en = $omdb['Title'] ?? '';
        $year = $omdb['Year'] ?? '';
        $plot = $omdb['Plot'] ?? '';
        $lang_code = substr($omdb['Language'] ?? '', 0, 2);
        $poster = $omdb['Poster'] ?? '';
        $genre = $omdb['Genre'] ?? '';
        $genre_array = array_map('trim', explode(',', $genre));
        $genre_he_str = implode(', ', array_map(function($g) use ($genre_he) {
            return $genre_he[$g] ?? $g;
        }, $genre_array));
        $imdb_link = "https://www.imdb.com/title/$imdb_id";

        $tmdb_url = "https://api.themoviedb.org/3/find/$imdb_id?api_key=$tmdb_key&external_source=imdb_id";
        $tmdb_data = @json_decode(file_get_contents($tmdb_url), true);
        $type = '';
        $tmdb_id = null;

        if (!empty($tmdb_data['movie_results'])) {
            $type = 'movie';
            $tmdb_id = $tmdb_data['movie_results'][0]['id'];
        } elseif (!empty($tmdb_data['tv_results'])) {
            $type = 'series';
            $tmdb_id = $tmdb_data['tv_results'][0]['id'];
        }

        if (!$tmdb_id) {
            $report[] = "<div class='alert alert-danger'>❌ לא נמצא TMDb ID עבור $imdb_id</div>";
            continue;
        }

        $tmdb_en = @json_decode(file_get_contents("https://api.themoviedb.org/3/$type/$tmdb_id?api_key=$tmdb_key&language=en"), true);
        $tmdb_he = @json_decode(file_get_contents("https://api.themoviedb.org/3/$type/$tmdb_id?api_key=$tmdb_key&language=he"), true);
        $tmdb_credits = @json_decode(file_get_contents("https://api.themoviedb.org/3/$type/$tmdb_id/credits?api_key=$tmdb_key&language=en"), true);

        $title_he = $tmdb_he['name'] ?? $tmdb_he['title'] ?? '';
        $plot_he = $tmdb_he['overview'] ?? '';
        $actors = implode(', ', array_map(fn($a) => $a['name'], array_slice($tmdb_credits['cast'] ?? [], 0, 10)));

        $directors = implode(', ', array_column(array_filter($tmdb_credits['crew'] ?? [], fn($c) => $c['job'] === 'Director'), 'name'));
        $writers = implode(', ', array_column(array_filter($tmdb_credits['crew'] ?? [], fn($c) => $c['job'] === 'Writer'), 'name'));
        $producers = implode(', ', array_column(array_filter($tmdb_credits['crew'] ?? [], fn($c) => $c['job'] === 'Producer'), 'name'));
        $cinematographers = implode(', ', array_column(array_filter($tmdb_credits['crew'] ?? [], fn($c) => $c['job'] === 'Director of Photography'), 'name'));
        $composers = implode(', ', array_column(array_filter($tmdb_credits['crew'] ?? [], fn($c) => $c['job'] === 'Original Music Composer'), 'name'));

        $network = $tmdb_en['networks'][0]['name'] ?? '';
        $network_logo = isset($tmdb_en['networks'][0]['logo_path']) ? 'https://image.tmdb.org/t/p/w200' . $tmdb_en['networks'][0]['logo_path'] : '';
        $seasons_count = $tmdb_en['number_of_seasons'] ?? 0;
        $episodes_count = $tmdb_en['number_of_episodes'] ?? 0;

        $start_year = substr($tmdb_en['first_air_date'] ?? '', 0, 4);
        $end_year = substr($tmdb_en['last_air_date'] ?? '', 0, 4);
        if ($type === 'series') {
            if ($start_year && $end_year && $start_year != $end_year) {
                $year = "$start_year–$end_year";
            } elseif ($start_year) {
                $year = $start_year;
            }
        }
        $type_id = ($type === 'series') ? 2 : 1;

        $stmt = $conn->prepare("INSERT INTO posters (
            title_en, title_he, year, plot, plot_he,
            imdb_id, imdb_link, imdb_rating, genre, lang_code,
            tvdb_id, youtube_trailer, image_url, type_id, network,
            network_logo, seasons_count, episodes_count, metacritic_score, rt_score,
            metacritic_link, rt_link, collection_name, pending, created_at,
            directors, writers, producers, cinematographers, composers,
            runtime, languages, countries, tmdb_collection_id, has_subtitles, is_dubbed
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(),
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )");

        $stmt->bind_param(
            'sssssssdsssssssiiissssssssssssiiiii',
            $title_en,
            $title_he,
            $year,
            $plot,
            $plot_he,
            $imdb_id,
            $imdb_link,
            $imdb_rating,
            $genre_he_str,
            $lang_code,
            $tvdb_id,
            $omdb['Trailer'] ?? '',
            $poster,
            $type_id,
            $network,
            $network_logo,
            $seasons_count,
            $episodes_count,
            $omdb['Metascore'] ?? '',
            $omdb['Ratings'][1]['Value'] ?? '',
            $omdb['MetacriticLink'] ?? '',
            $omdb['RottenTomatoesLink'] ?? '',
            $omdb['CollectionName'] ?? '',
            0,
            $directors,
            $writers,
            $producers,
            $cinematographers,
            $composers,
            isset($omdb['Runtime']) ? (int)filter_var($omdb['Runtime'], FILTER_SANITIZE_NUMBER_INT) : null,
            $omdb['Language'] ?? '',
            $omdb['Country'] ?? '',
            $tmdb_en['belongs_to_collection']['id'] ?? null,
            isset($omdb['Subtitles']) ? 1 : 0,
            isset($omdb['Dubbed']) ? 1 : 0
        );

        if ($stmt->execute()) {
            $report[] = "<div class='alert alert-success'>✅ נוסף בהצלחה: <strong>$title_en</strong> ($year)</div>
                <ul>
                    <li><strong>IMDb ID:</strong> $imdb_id</li>
                    <li><strong>IMDb Link:</strong> <a href='$imdb_link' target='_blank'>$imdb_link</a></li>
                    <li><strong>Year:</strong> $year</li>
                    <li><strong>Rating:</strong> $imdb_rating</li>
                    <li><strong>Genre:</strong> $genre_he_str</li>
                    <li><strong>Language:</strong> $lang_code</li>
                    <li><strong>Actors:</strong> $actors</li>
                    <li><strong>Directors:</strong> $directors</li>
                    <li><strong>Seasons:</strong> $seasons_count</li>
                    <li><strong>Episodes:</strong> $episodes_count</li>
                </ul>";
        } else {
            $report[] = "<div class='alert alert-danger'>❌ שגיאה בהוספה למסד עבור $title_en: {$stmt->error}</div>";
        }

        $stmt->close();
    }
}
?>

<div class="container mt-4">
    <h2>הוספת פוסטרים מ־IMDb</h2>
    <form method="post">
        <div class="form-group">
            <label>הכנס קודי IMDb (tt...), כל אחד בשורה נפרדת:</label>
            <textarea name="imdb_ids" class="form-control" rows="10" dir="ltr"><?php echo isset($_POST['imdb_ids']) ? htmlspecialchars($_POST['imdb_ids']) : ''; ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">הוסף</button>
    </form>

    <div class="mt-4">
        <?php foreach ($report as $r) echo $r; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
