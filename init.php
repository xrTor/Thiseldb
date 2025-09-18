<?php
// This file should be included at the VERY TOP of your pages.

// --- Database Connection (assuming you have a conn.php or similar) ---
require_once 'server.php'; // שנה לשם קובץ החיבור שלך

// --- Visitor Logic ---
if (isset($conn) && $conn instanceof mysqli) {
    // Total page views counter
    $conn->query("UPDATE visitors SET count = count + 1 WHERE id = 1");

    // Unique visitors counter (based on cookie)
    if (!isset($_COOKIE['visitor_counted'])) {
        $conn->query("UPDATE unique_visitors SET count = count + 1 WHERE id = 1");
        // Set cookie for 1 year
        setcookie('visitor_counted', 'yes', time() + (86400 * 365), "/");
    }
}

// --- Fetch Stats for Display Later ---
// Fetch total posters
$total_query = $conn->query("SELECT COUNT(*) AS total FROM posters");
$total_posters = $total_query->fetch_assoc()['total'] ?? 0;

// Fetch collections
$collections_query = $conn->query("SELECT COUNT(*) as c FROM collections");
$total_collections = $collections_query->fetch_assoc()['c'] ?? 0;

// Fetch movies
$movies_query = $conn->query("SELECT COUNT(*) AS c FROM posters p JOIN poster_types pt ON pt.id = p.type_id WHERE pt.code = 'movie'");
$total_movies = $movies_query->fetch_assoc()['c'] ?? 0;

// Fetch series
$series_query = $conn->query("SELECT COUNT(*) AS c FROM posters p JOIN poster_types pt ON pt.id = p.type_id WHERE pt.code = 'series'");
$total_series = $series_query->fetch_assoc()['c'] ?? 0;

// Fetch total views
$views_query = $conn->query("SELECT count FROM visitors WHERE id = 1");
$total_views = $views_query->fetch_assoc()['count'] ?? 0;

// Fetch unique visitors
$unique_visitors_query = $conn->query("SELECT count FROM unique_visitors WHERE id = 1");
$unique_visitors = $unique_visitors_query->fetch_assoc()['count'] ?? 0;

// Note: The database connection ($conn) is left open on purpose
// so it can be used by the rest of the page. It will be closed in the footer.
?>