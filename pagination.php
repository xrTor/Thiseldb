<?php
// This file is included by other management pages to generate pagination links.
// It expects the following variables to be set before being included:
// $total_pages, $page, $_GET

if (!isset($total_pages) || $total_pages <= 1) {
    return;
}

// Build the base URL, preserving existing query parameters like search or filters
$query_params = $_GET;
unset($query_params['page']);
$base_url = '?' . http_build_query($query_params);
$separator = empty($query_params) ? '' : '&';

$range = 3;
$links = [];

// "First" and "Previous" links
if ($page > 1) {
    $links[] = '<a href="' . $base_url . $separator . 'page=1">« ראשון</a>';
    $links[] = '<a href="' . $base_url . $separator . 'page=' . ($page - 1) . '">‹ הקודם</a>';
}

// Numbered links
for ($i = 1; $i <= $total_pages; $i++) {
    // Conditions to display a link: it's the first/last page, or it's within the range of the current page
    if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
        $active_class = ($i == $page) ? 'active' : '';
        $links[] = '<a href="' . $base_url . $separator . 'page=' . $i . '" class="' . $active_class . '">' . $i . '</a>';
    } 
    // Add an ellipsis if there's a gap
    else if (($i == $page - $range - 1) || ($i == $page + $range + 1)) {
        $links[] = '<span>...</span>';
    }
}

// "Next" and "Last" links
if ($page < $total_pages) {
    $links[] = '<a href="' . $base_url . $separator . 'page=' . ($page + 1) . '">הבא ›</a>';
    $links[] = '<a href="' . $base_url . $separator . 'page=' . $total_pages . '">אחרון »</a>';
}

// Output the final HTML
echo '<div class="pagination">' . implode(' ', $links) . '</div>';