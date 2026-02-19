<?php
// ======= Simplified session bootstrap (CSRF fully disabled) =======
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) { @session_start(); }

require_once 'server.php'; // DB connection
require_once 'bbcode.php'; // BBCode -> HTML

/* ==========================================================
   Watchlist storage detection (DB table if exists, else session)
   ========================================================== */
function current_user_id(): int {
  return (int)($_SESSION['user_id'] ?? 1);
}

function detect_watchlist_storage(mysqli $conn): array {
  $candidates = [
    'watchlist','watch_list','poster_watchlist','watchlist_posters','watchlist_items',
    'to_watch','watch_queue','watchlater'
  ];

  foreach ($candidates as $t) {
    $tEsc = $conn->real_escape_string($t);
    $res = $conn->query("SHOW TABLES LIKE '{$tEsc}'");
    if ($res && $res->num_rows > 0) {
      $cols = [];
      $resCols = $conn->query("SHOW COLUMNS FROM `{$tEsc}`");
      if ($resCols) {
        while ($r = $resCols->fetch_assoc()) { $cols[] = $r['Field']; }
      }

      $colSet = array_flip($cols);
      $id_col = null;
      if (isset($colSet['poster_id'])) $id_col = 'poster_id';
      elseif (isset($colSet['imdb_id'])) $id_col = 'imdb_id';
      elseif (isset($colSet['imdb'])) $id_col = 'imdb';
      elseif (isset($colSet['tt'])) $id_col = 'tt';

      // If we can't identify an ID column, fall back to session
      if (!$id_col) {
        return ['mode' => 'session'];
      }

      $user_col = null;
      if (isset($colSet['user_id'])) $user_col = 'user_id';
      elseif (isset($colSet['uid'])) $user_col = 'uid';

      $ts_col = null;
      foreach (['added_at','created_at','date_added','created'] as $candTs) {
        if (isset($colSet[$candTs])) { $ts_col = $candTs; break; }
      }

      return [
        'mode' => 'table',
        'table' => $t,
        'id_col' => $id_col,
        'user_col' => $user_col,
        'ts_col' => $ts_col,
      ];
    }
  }
  return ['mode' => 'session'];
}

$watch_cfg = detect_watchlist_storage($conn);

// ===== Watchlist collection link (add/remove poster from this collection too) =====
$WATCHLIST_COLLECTION_ID = 196;



// ===============================
// Actions (pin/unpin/delete all) — POST-only
// ===============================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) {
  echo "<p>❌ אוסף לא צוין</p>";
  exit;
}

// Preserve params (remove one-off flags) for redirects
$current_params = $_GET;
unset(
  $current_params['pin_poster'], $current_params['unpin_poster'],
  $current_params['csv_success'], $current_params['inserted'], $current_params['already'],
  $current_params['pin_collection'], $current_params['unpin_collection'],
  $current_params['delete_all_content']
);
$redirect_query_string = http_build_query($current_params);

// State-changing POST only:
$has_state_action = (
    isset($_POST['action']) ||
    isset($_POST['delete_all_content']) ||
    isset($_POST['duplicate_collection'])
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $has_state_action) {
  // === CSRF validation entirely disabled by request ===

  // Handle collection duplication (clone collection + all items)
  if (isset($_POST['duplicate_collection']) && !empty($_POST['collection_id'])) {
    $src_id = (int)$_POST['collection_id'];
    if ($src_id !== $id) {
      header("Location: collection.php?" . $redirect_query_string);
      exit;
    }

    $stmtC = $conn->prepare("SELECT name, description, image_url, search_terms, is_private, is_safe, is_urgent, is_green FROM collections WHERE id = ?");
    $src = null;
    if ($stmtC) {
      $stmtC->bind_param("i", $src_id);
      $stmtC->execute();
      $resC = $stmtC->get_result();
      $src = $resC ? $resC->fetch_assoc() : null;
      $stmtC->close();
    }

    if (!$src) {
      header("Location: collection.php?" . $redirect_query_string);
      exit;
    }

    $conn->begin_transaction();
    try {
      $new_name = 'העתק של ' . (string)$src['name'];
      $desc     = (string)($src['description'] ?? '');
      $img      = (string)($src['image_url'] ?? '');
      $terms    = (string)($src['search_terms'] ?? '');
      $is_private = (int)($src['is_private'] ?? 0);
      $is_safe    = (int)($src['is_safe'] ?? 0);
      $is_urgent  = (int)($src['is_urgent'] ?? 0);
      $is_green   = (int)($src['is_green'] ?? 0);

      $stmtI = $conn->prepare("INSERT INTO collections (name, description, image_url, search_terms, is_private, is_pinned, is_safe, is_urgent, is_green, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW())");
      if (!$stmtI) { throw new Exception('insert collection failed'); }
      $stmtI->bind_param("ssssiiii", $new_name, $desc, $img, $terms, $is_private, $is_safe, $is_urgent, $is_green);
      $stmtI->execute();
      $new_id = (int)$conn->insert_id;
      $stmtI->close();

      // Copy items (preserve pin + added_at)
      $copy_sql = "INSERT INTO poster_collections (collection_id, poster_id, is_pinned, added_at)
                   SELECT ?, poster_id, is_pinned, added_at
                   FROM poster_collections WHERE collection_id = ?";
      $stmtCP = $conn->prepare($copy_sql);
      if (!$stmtCP) { throw new Exception('copy items failed'); }
      $stmtCP->bind_param("ii", $new_id, $src_id);
      $stmtCP->execute();
      $stmtCP->close();

      $conn->commit();

      header('Location: collection.php?id=' . $new_id . '&msg=duplicated');
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      header("Location: collection.php?" . $redirect_query_string . "&msg=duplicate_failed");
      exit;
    }
  }

  // Handle poster pin/unpin
  if (!empty($_POST['action']) && !empty($_POST['poster_id']) && !empty($_POST['collection_id'])) {
    $action = $_POST['action'];
    $poster_id = (int)$_POST['poster_id'];
    $collection_id = (int)$_POST['collection_id'];
    if ($collection_id === $id && in_array($action, ['pin_poster','unpin_poster','watch_add','watch_remove','watch_toggle'], true)) {
      // --- Watchlist add/remove (DB table if exists, else session) ---
      if (in_array($action, ['watch_add','watch_remove','watch_toggle'], true)) {
        $user_id = current_user_id();
        $imdb_id = trim((string)($_POST['imdb_id'] ?? ''));

        // Determine desired op
        $op = $action;
        if ($action === 'watch_toggle') {
          // lightweight check
          $is_in = false;
          if (($watch_cfg['mode'] ?? '') === 'table') {
            $table = $watch_cfg['table'];
            $id_col = $watch_cfg['id_col'];
            $user_col = $watch_cfg['user_col'] ?? null;
            $sqlChk = "SELECT 1 FROM `{$table}` WHERE `{$id_col}` = ?" . ($user_col ? " AND `{$user_col}` = ?" : "") . " LIMIT 1";
            $stmtChk = $conn->prepare($sqlChk);
            if ($stmtChk) {
              if ($id_col === 'poster_id') {
                if ($user_col) { $stmtChk->bind_param('ii', $poster_id, $user_id); }
                else { $stmtChk->bind_param('i', $poster_id); }
              } else {
                if ($user_col) { $stmtChk->bind_param('si', $imdb_id, $user_id); }
                else { $stmtChk->bind_param('s', $imdb_id); }
              }
              $stmtChk->execute();
              $r = $stmtChk->get_result();
              $is_in = ($r && $r->num_rows > 0);
              $stmtChk->close();
            }
          } else {
            $wl = $_SESSION['watchlist'] ?? [];
            $is_in = in_array($poster_id, $wl, true);
          }
          $op = $is_in ? 'watch_remove' : 'watch_add';
        }

        if (($watch_cfg['mode'] ?? '') === 'table') {
          $table = $watch_cfg['table'];
          $id_col = $watch_cfg['id_col'];
          $user_col = $watch_cfg['user_col'] ?? null;
          $ts_col = $watch_cfg['ts_col'] ?? null;

          if ($op === 'watch_add') {
            $cols = []; $vals = []; $typesW = '';
            if ($id_col === 'poster_id') { $cols[] = $id_col; $vals[] = $poster_id; $typesW .= 'i'; }
            else { $cols[] = $id_col; $vals[] = $imdb_id; $typesW .= 's'; }
            if ($user_col) { $cols[] = $user_col; $vals[] = $user_id; $typesW .= 'i'; }
            if ($ts_col) { $cols[] = $ts_col; }

            $colSql = implode(',', array_map(function($c){ return '`'.$c.'`'; }, $cols));
            $ph = [];
            for ($i=0;$i<count($vals);$i++) $ph[]='?';
            if ($ts_col) $ph[]='NOW()';
            $phSql = implode(',', $ph);

            $sqlIns = "INSERT IGNORE INTO `{$table}` ({$colSql}) VALUES ({$phSql})";
            $stmtIns = $conn->prepare($sqlIns);
            if ($stmtIns) {
              if (!empty($typesW)) { $stmtIns->bind_param($typesW, ...$vals); }
              $stmtIns->execute();
              $stmtIns->close();
            }
          } else { // watch_remove
            $sqlDel = "DELETE FROM `{$table}` WHERE `{$id_col}` = ?" . ($user_col ? " AND `{$user_col}` = ?" : "");
            $stmtDel = $conn->prepare($sqlDel);
            if ($stmtDel) {
              if ($id_col === 'poster_id') {
                if ($user_col) { $stmtDel->bind_param('ii', $poster_id, $user_id); }
                else { $stmtDel->bind_param('i', $poster_id); }
              } else {
                if ($user_col) { $stmtDel->bind_param('si', $imdb_id, $user_id); }
                else { $stmtDel->bind_param('s', $imdb_id); }
              }
              $stmtDel->execute();
              $stmtDel->close();
            }
          }
        } else {
          $wl = $_SESSION['watchlist'] ?? [];
          $wl = array_values(array_unique(array_map('intval', (array)$wl)));
          if ($op === 'watch_add') {
            if (!in_array($poster_id, $wl, true)) $wl[] = $poster_id;
          } else {
            $wl = array_values(array_diff($wl, [$poster_id]));
          }
          $_SESSION['watchlist'] = $wl;
        }

        // ---- Sync with watchlist-collection (poster_collections) ----
        if (isset($WATCHLIST_COLLECTION_ID) && (int)$WATCHLIST_COLLECTION_ID > 0) {
          $wl_cid = (int)$WATCHLIST_COLLECTION_ID;
          if ($op === 'watch_add') {
            $stmtWLC = $conn->prepare("INSERT IGNORE INTO poster_collections (collection_id, poster_id, added_at) VALUES (?, ?, NOW())");
            if ($stmtWLC) { $stmtWLC->bind_param("ii", $wl_cid, $poster_id); $stmtWLC->execute(); $stmtWLC->close(); }
          } else { // watch_remove
            $stmtWLC = $conn->prepare("DELETE FROM poster_collections WHERE collection_id = ? AND poster_id = ?");
            if ($stmtWLC) { $stmtWLC->bind_param("ii", $wl_cid, $poster_id); $stmtWLC->execute(); $stmtWLC->close(); }
          }
        }
        // ------------------------------------------------------------

        header("Location: collection.php?" . $redirect_query_string);
        exit;
      }
      // --------------------------------------------------------------

      if ($action === 'pin_poster') {
        $stmt = $conn->prepare("UPDATE poster_collections SET is_pinned = 1 WHERE collection_id = ? AND poster_id = ?");
      } else {
        $stmt = $conn->prepare("UPDATE poster_collections SET is_pinned = 0 WHERE collection_id = ? AND poster_id = ?");
      }
      $stmt->bind_param("ii", $id, $poster_id);
      $stmt->execute();
      $stmt->close();
      header("Location: collection.php?" . $redirect_query_string);
      exit;
    }
  }

  // Handle collection pin/unpin
  if (!empty($_POST['action']) && !empty($_POST['collection_id'])) {
    $action = $_POST['action'];
    $collection_to_change = (int)$_POST['collection_id'];
    if ($collection_to_change === $id && in_array($action, ['pin_collection','unpin_collection','make_private_collection','make_public_collection'], true)) {
      if ($action === 'pin_collection') {
$stmt_pin_coll = $conn->prepare("UPDATE collections SET is_pinned = 1 WHERE id = ?");
        $stmt_pin_coll->bind_param("i", $id);
        $stmt_pin_coll->execute();
        $stmt_pin_coll->close();
      } elseif ($action === 'unpin_collection') {
$stmt_unpin_coll = $conn->prepare("UPDATE collections SET is_pinned = 0 WHERE id = ?");
        $stmt_unpin_coll->bind_param("i", $id);
        $stmt_unpin_coll->execute();
        $stmt_unpin_coll->close();
      } elseif ($action === 'make_private_collection') {
        $stmt_priv_coll = $conn->prepare("UPDATE collections SET is_private = 1, is_pinned = 0 WHERE id = ?");
        $stmt_priv_coll->bind_param("i", $id);
        $stmt_priv_coll->execute();
        $stmt_priv_coll->close();
      } elseif ($action === 'make_public_collection') {
        $stmt_pub_coll = $conn->prepare("UPDATE collections SET is_private = 0 WHERE id = ?");
        $stmt_pub_coll->bind_param("i", $id);
        $stmt_pub_coll->execute();
        $stmt_pub_coll->close();
      }
      header("Location: collection.php?" . $redirect_query_string);
      exit;
    }
  }

  // Delete all content in collection
  if (isset($_POST['delete_all_content']) && $id > 0) {
    $stmt_delete = $conn->prepare("DELETE FROM poster_collections WHERE collection_id = ?");
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();
    $stmt_delete->close();
    header("Location: collection.php?id=" . $id);
    exit;
  }
}
// ===============================
// End of actions
// ===============================

include 'header.php';
set_time_limit(3000000);

// Fetch collection
$stmt = $conn->prepare("SELECT * FROM collections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<p>❌ האוסף לא נמצא</p>";
  include 'footer.php';
  exit;
}
$collection = $result->fetch_assoc();
$stmt->close();

// Types for filter
$types_list = [];
$types_result = $conn->query("SELECT id, label_he FROM poster_types ORDER BY sort_order ASC");
while($row = $types_result->fetch_assoc()) $types_list[] = $row;

// Filters
$q_raw = (string)($_GET['q'] ?? '');

/**
 * Extract first tt123... IMDb id from any messy text (urls, filenames, etc.)
 */
function extract_tt_id_from_text(string $text): string {
    if ($text === '') return '';
    if (preg_match('/tt\d{5,}/i', $text, $m)) return strtolower($m[0]);
    return '';
}

/**
 * Normalize a "dirty" search string:
 * - If it contains an IMDb tt id anywhere => return the tt id
 * - Otherwise, turn separators (.,_,-) into spaces and collapse whitespace
 */
function normalize_dirty_search(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';

    $tt = extract_tt_id_from_text($raw);
    if ($tt !== '') return $tt;

    $s = str_replace(['.', '_', '-'], ' ', $raw);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim((string)$s);
}


/**
 * Split query like "Some Title 2021" into [title, year] when year is at the end.
 * Returns: ['title' => string, 'year' => string] (year empty if not found)
 */
function split_title_year(string $q): array {
    $q = trim($q);
    if ($q === '') return ['title' => '', 'year' => ''];

    // Year at end (supports: "title 2021", "title (2021)", "title [2021]")
    if (preg_match('/^(.*?)[\s\(\[\{]*((?:19|20)\d{2})[\)\]\}]*\s*$/u', $q, $m)) {
        $title = trim((string)$m[1]);
        $year  = (string)$m[2];
        if ($title !== '') return ['title' => $title, 'year' => $year];
    }

    return ['title' => $q, 'year' => ''];
}


/**
 * Handle messy release names like "scream.2.1997.2160p..." -> ['title'=>'scream 2', 'year'=>'1997']
 * Finds a year token anywhere in the string and uses ONLY the part before the year as title.
 * Falls back to split_title_year() behavior if no year found.
 */
function split_title_year_anywhere(string $q): array {
    $q = trim($q);
    if ($q === '') return ['title' => '', 'year' => ''];

    // If it's already "title 2021" (year at end), keep the simple splitter.
    $end = split_title_year($q);
    if ($end['year'] !== '') return $end;

    // Normalize separators to spaces first
    $s = str_replace(['.', '_', '-'], ' ', $q);
    $s = preg_replace('/\s+/', ' ', (string)$s);
    $s = trim((string)$s);

    // Find a standalone year token anywhere
    if (preg_match('/\b((?:19|20)\d{2})\b/u', $s, $m, PREG_OFFSET_CAPTURE)) {
        $year = (string)$m[1][0];
        $pos  = (int)$m[1][1];

        // Take only the part BEFORE the year
        $title_part = trim(substr($s, 0, $pos));

        // Remove common release junk tokens from the title part (basic safety)
        // (We keep numbers like "2" because sequels)
        $title_part = preg_replace('/\b(480p|720p|1080p|2160p|uhd|hdr|dv|dolby|bluray|brrip|webrip|web|hdtv|x264|x265|hevc|aac|dts|atmos|remux)\b/iu', ' ', (string)$title_part);
        $title_part = preg_replace('/\s+/', ' ', (string)$title_part);
        $title_part = trim((string)$title_part);

        if ($title_part !== '') return ['title' => $title_part, 'year' => $year];
        return ['title' => $s, 'year' => $year];
    }

    return ['title' => $s, 'year' => ''];
}

$q_search = normalize_dirty_search($q_raw);


// --- סינון סטטוס (לפי אוספים קבועים): נצפו=231, רשימת צפייה=196 ---
$status_raw = (string)($_GET['status'] ?? '');
$allowed_status = ['watched','watchlist','none'];
if (!in_array($status_raw, $allowed_status, true)) { $status_raw = ''; }

$filters = [
    'q' => $q_search,
    'type_id' => $_GET['type_id'] ?? '',
    'min_year' => $_GET['min_year'] ?? '',
    'max_year' => $_GET['max_year'] ?? '',
    'min_rating' => $_GET['min_rating'] ?? '',
    'status' => $status_raw
];
$sort_order = $_GET['sort'] ?? 'collection';

$where_conditions = ["pc.collection_id = ?"];
$params = [$id];
$types = "i";
$filter_query_string = '';

$join_user_tags = 'LEFT JOIN user_tags ut ON ut.poster_id = p.id';
$join_akas = '';

if (!empty($filters['q'])) {
    $keyword = $filters['q'];
    // Keep the original user input in pagination links (so the URL stays the same text the user pasted)
    $filter_query_string .= "&q=" . urlencode($q_raw);

    // If the normalized keyword is exactly a tt-id, do an exact match (fast + accurate)
    if (preg_match('/^tt\d{5,}$/i', $keyword)) {
        $where_conditions[] = "p.imdb_id = ?";
        $params[] = strtolower($keyword);
        $types .= "s";
    } else {
        $searchFields = [
            "p.title_en", "p.title_he", "p.overview_he", "p.cast", "p.genres", "p.directors",
            "p.writers", "p.producers", "p.composers", "p.cinematographers", "p.languages",
            "p.countries", "p.imdb_id", "p.year"
        ];
        $parts = split_title_year_anywhere($keyword);
        $kw_title = $parts['title'];
        $kw_year  = $parts['year'];

        $like_title = "%$kw_title%";
        $like_parts = [];
        foreach ($searchFields as $field) {
            $like_parts[] = "$field LIKE ?";
            $params[] = $like_title; $types .= "s";
        }
        $like_parts[] = "ut.genre LIKE ?"; $params[] = $like_title; $types .= "s";
        $like_parts[] = "pa.aka_title LIKE ?"; $params[] = $like_title; $types .= "s";
        $like_parts[] = "pa.aka LIKE ?"; $params[] = $like_title; $types .= "s";

        $where_conditions[] = "(" . implode(" OR ", $like_parts) . ")";

        if ($kw_year !== '') {
            $where_conditions[] = "p.year LIKE ?";
            $params[] = "%$kw_year%";
            $types .= "s";
        }

        $join_akas = ' LEFT JOIN poster_akas pa ON pa.poster_id = p.id ';
    }
}

// Extra filters
foreach ($filters as $key => $value) {
    if ($key !== 'q' && $value !== '' && $value !== null) {
        $filter_query_string .= "&$key=" . urlencode($value);
        switch ($key) {
            case 'type_id': $where_conditions[] = "p.type_id = ?"; $params[] = (int)$value; $types .= "i"; break;
            case 'min_year': $where_conditions[] = "p.year >= ?"; $params[] = (int)$value; $types .= "i"; break;
            case 'max_year': $where_conditions[] = "p.year <= ?"; $params[] = (int)$value; $types .= "i"; break;
            case 'min_rating': $where_conditions[] = "p.imdb_rating >= ?"; $params[] = (float)$value; $types .= "d"; break;
            case 'status':
                if ($value === 'watched') {
                    $where_conditions[] = "EXISTS (SELECT 1 FROM poster_collections pcw WHERE pcw.poster_id = p.id AND pcw.collection_id = 231)";
                } elseif ($value === 'watchlist') {
                    $where_conditions[] = "EXISTS (SELECT 1 FROM poster_collections pcwl WHERE pcwl.poster_id = p.id AND pcwl.collection_id = 196)";
                } elseif ($value === 'none') {
                    $where_conditions[] = "NOT EXISTS (SELECT 1 FROM poster_collections pcw WHERE pcw.poster_id = p.id AND pcw.collection_id = 231)
                                           AND NOT EXISTS (SELECT 1 FROM poster_collections pcwl WHERE pcwl.poster_id = p.id AND pcwl.collection_id = 196)";
                }
                break;
        }
    }
}

// Sorting
if ($sort_order !== 'collection') { $filter_query_string .= "&sort=" . urlencode($sort_order); }
$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$order_by_clause = 'ORDER BY pc.is_pinned DESC';
switch ($sort_order) {
    case 'collection': $order_by_clause .= ", pc.added_at DESC, p.id DESC"; break;
    case 'site_added_desc': $order_by_clause .= ", p.added_at DESC, p.id DESC"; break;
    case 'col_added_desc':  $order_by_clause .= ", pc.added_at DESC, p.id DESC"; break;
    case 'site_added_asc':  $order_by_clause .= ", p.added_at ASC, p.id ASC"; break;
    case 'col_added_asc':   $order_by_clause .= ", pc.added_at ASC, p.id ASC"; break;
    case 'year_desc':  $order_by_clause .= ", p.year DESC, p.id DESC"; break;
    case 'year_asc':   $order_by_clause .= ", p.year ASC, p.id ASC"; break;
    case 'rating_desc':$order_by_clause .= ", p.imdb_rating DESC, p.id DESC"; break;
    case 'rating_asc': $order_by_clause .= ", p.imdb_rating ASC, p.id ASC"; break;
    case 'title_he_asc':  $order_by_clause .= ", p.title_he ASC, p.id ASC"; break;
    case 'title_he_desc': $order_by_clause .= ", p.title_he DESC, p.id DESC"; break;
    case 'title_en_asc':  $order_by_clause .= ", p.title_en ASC, p.id ASC"; break;
    case 'title_en_desc': $order_by_clause .= ", p.title_en DESC, p.id DESC"; break;
    default: $order_by_clause .= ", pc.added_at DESC, p.id DESC"; break;
}

// Count
$count_sql = "SELECT COUNT(DISTINCT p.id)
              FROM posters p
              JOIN poster_collections pc ON p.id = pc.poster_id
              $join_user_tags
              $join_akas
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_posters = $count_stmt->get_result()->fetch_row()[0] ?? 0;
$count_stmt->close();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$allowed_per_page = [20, 30, 35, 40, 50, 100, 250, 500];
$per_page_request = $_GET['per_page'] ?? 250;
$per_page = 250; // Default
$show_all = false;

if ($per_page_request === 'all') {
    $show_all = true;
    $per_page = $total_posters > 0 ? $total_posters : 1;
} elseif (in_array((int)$per_page_request, $allowed_per_page)) {
    $per_page = (int)$per_page_request;
}

$offset = ($page - 1) * $per_page;
$total_pages = $show_all ? 1 : ceil(max(1, $total_posters) / max(1, $per_page));
if ($per_page_request != 250) { $filter_query_string .= "&per_page=" . urlencode($per_page_request); }

// Fetch posters
$sql_limit_clause = "LIMIT ? OFFSET ?";
if ($show_all) {
    $sql_limit_clause = ""; // No limit if showing all
}

$posters_sql = "SELECT p.*, pc.is_pinned, pc.added_at,
                       GROUP_CONCAT(DISTINCT ut.genre SEPARATOR ', ') AS user_tags_list
                FROM posters p
                JOIN poster_collections pc ON p.id = pc.poster_id
                $join_user_tags
                $join_akas
                $where_clause
                GROUP BY p.id
                $order_by_clause
                $sql_limit_clause";

$params_with_limit = $params;
$types_with_limit = $types;

if (!$show_all) {
    $params_with_limit[] = $per_page; $types_with_limit .= "i";
    $params_with_limit[] = $offset;   $types_with_limit .= "i";
}

$stmt = $conn->prepare($posters_sql);
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$res = $stmt->get_result();
$poster_list = [];
while ($row = $res->fetch_assoc()) $poster_list[] = $row;
$stmt->close();

// --- קוד חדש: איסוף מזהי IMDB עבור כפתור ההעתקה ---
$current_page_imdb_ids = [];
foreach ($poster_list as $p) {
    if (!empty($p['imdb_id'])) {
        $current_page_imdb_ids[] = $p['imdb_id'];
    }
}
// --------------------------------------------------

// --- Watchlist status for current page posters (for add/remove button) ---
$watchlisted = []; // [poster_id => true]
if (!empty($poster_list)) {
  if (($watch_cfg['mode'] ?? '') === 'table') {
    $table = $watch_cfg['table'];
    $id_col = $watch_cfg['id_col'];
    $user_col = $watch_cfg['user_col'] ?? null;
    $user_id = current_user_id();

    // Collect IDs for query
    $ids = [];
    if ($id_col === 'poster_id') {
      foreach ($poster_list as $pp) { $ids[] = (int)$pp['id']; }
    } else {
      foreach ($poster_list as $pp) { $ids[] = (string)($pp['imdb_id'] ?? ''); }
      $ids = array_values(array_filter(array_map('trim', $ids)));
    }

    if (!empty($ids)) {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $sqlW = "SELECT `$id_col` AS pid FROM `$table` WHERE `$id_col` IN ($placeholders)";
      $typesW = str_repeat(($id_col === 'poster_id') ? 'i' : 's', count($ids));
      $paramsW = $ids;

      if ($user_col) {
        $sqlW .= " AND `$user_col` = ?";
        $typesW .= "i";
        $paramsW[] = $user_id;
      }

      $stmtW = $conn->prepare($sqlW);
      if ($stmtW) {
        $stmtW->bind_param($typesW, ...$paramsW);
        $stmtW->execute();
        $rW = $stmtW->get_result();
        $present = [];
        while ($r = $rW->fetch_assoc()) { $present[(string)$r['pid']] = true; }
        $stmtW->close();

        foreach ($poster_list as $pp) {
          $key = ($id_col === 'poster_id') ? (string)(int)$pp['id'] : (string)($pp['imdb_id'] ?? '');
          if ($key !== '' && !empty($present[$key])) $watchlisted[(int)$pp['id']] = true;
        }
      }
    }
  } else {
    $wl = $_SESSION['watchlist'] ?? [];
    $wl = array_values(array_unique(array_map('intval', (array)$wl)));
    foreach ($wl as $pid) { $watchlisted[(int)$pid] = true; }
  }
}


$watchlisted = []; // source of truth: poster_collections (collection 196)

// --- Mark posters that are already in the "watchlist collection" (collection_id=196) as ON ---
$watch_collection_id = 196;
if (!empty($poster_list)) {
  $ids196 = array_map(fn($x)=> (int)$x['id'], $poster_list);
  $ids196 = array_values(array_unique(array_filter($ids196)));
  if (!empty($ids196)) {
    $ph196 = implode(',', array_fill(0, count($ids196), '?'));
    $types196 = str_repeat('i', count($ids196));
    $sql196 = "SELECT poster_id FROM poster_collections WHERE collection_id = ? AND poster_id IN ($ph196)";
    $stmt196 = $conn->prepare($sql196);
    if ($stmt196) {
      $types196 = 'i' . $types196;
      $params196 = array_merge([(int)$watch_collection_id], $ids196);
      $stmt196->bind_param($types196, ...$params196);
      $stmt196->execute();
      $r196 = $stmt196->get_result();
      if ($r196) {
        while ($row196 = $r196->fetch_assoc()) {
          $watchlisted[(int)$row196['poster_id']] = true;
        }
      }
      $stmt196->close();
    }
  }
}

// --- Mark posters that are already in the "watched collection" (collection_id=231) as ON ---
$watched = []; // [poster_id => true]
$watched_collection_id = 231;
if (!empty($poster_list)) {
  $ids231 = array_map(fn($x)=> (int)$x['id'], $poster_list);
  $ids231 = array_values(array_unique(array_filter($ids231)));
  if (!empty($ids231)) {
    $ph231 = implode(',', array_fill(0, count($ids231), '?'));
    $types231 = str_repeat('i', count($ids231));
    $sql231 = "SELECT poster_id FROM poster_collections WHERE collection_id = ? AND poster_id IN ($ph231)";
    $stmt231 = $conn->prepare($sql231);
    if ($stmt231) {
      $types231 = 'i' . $types231;
      $params231 = array_merge([(int)$watched_collection_id], $ids231);
      $stmt231->bind_param($types231, ...$params231);
      $stmt231->execute();
      $r231 = $stmt231->get_result();
      if ($r231) {
        while ($row231 = $r231->fetch_assoc()) {
          $watched[(int)$row231['poster_id']] = true;
        }
      }
      $stmt231->close();
    }
  }
}

// -----------------------------------------------------------------------

// --------------------------------------------------

// Full list (sidebar)
$all_posters_for_list = [];
$sql_all = "SELECT p.*
            FROM poster_collections pc
            JOIN posters p ON p.id = pc.poster_id
            WHERE pc.collection_id = ?
            ORDER BY pc.added_at ASC";
$stmt_all = $conn->prepare($sql_all);
$stmt_all->bind_param("i", $id);
$stmt_all->execute();
$res_all = $stmt_all->get_result();
while ($row = $res_all->fetch_assoc()) $all_posters_for_list[] = $row;
$stmt_all->close();

// Helper to generate search links to HOME.PHP for hover card
function generate_home_search_link($param, $value) {
    return 'home.php?' . http_build_query([$param => $value]);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <link rel="stylesheet" href="bbcode.css">
  <meta charset="UTF-8">
  <title>📦 אוסף: <?= htmlspecialchars($collection['name']) ?></title>
  <style>
    body { font-family:Arial, sans-serif; background:#f9f9f9; padding:10px; direction:rtl; }
    .container { max-width:1300px; margin:auto; background:white; padding:20px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1); }
    .header-img { max-width:100%; border-radius:1px; margin-top:10px; max-width:420px;width:auto; height:auto;}
    .description { margin-top:10px; color:#444; }
    .poster-section { display:flex; flex-direction:row-reverse; gap:14px; margin-top:20px; align-items:flex-start; flex-wrap:nowrap !important; }

    /* ממורכז */
    .poster-grid { flex:1 1 0; min-width:0; display:flex; flex-wrap:wrap; justify-content: center; }

    .poster-grid.small { gap:2px; } .poster-grid.medium { gap:8px; } .poster-grid.large { gap:12px; }
    /* חדש: ענק */
    .poster-grid.giant { gap:16px; }

    .poster-item { text-align:center; position:relative; padding-bottom: 5px; }
    .poster-item.pinned { background-color: #fffff0; border-radius: 4px; border: 1px solid #ffd700; }
    .poster-item.small { width:100px; margin-bottom:0 !important; } .poster-item.medium { width:160px; } .poster-item.large { width:220px; }
    /* חדש: ענק */
    .poster-item.giant { width:300px; }

    .poster-item img { width:100%; aspect-ratio:2/3; object-fit:cover; border-radius:1px; margin:0; box-shadow:0 0 4px rgba(0,0,0,0.1); }
    .poster-item.small small, .poster-item.small .title-he { font-size:10px; line-height:1; margin-top:1px !important; margin-bottom:0 !important; }
    .poster-item .year { font-size:11px; color:#444; margin-top:2px; }
    .title-he { font-size:12px; color:#555; }
    .imdb-rating a {  color:#d18b00; font-size: 12px; text-decoration: none; }
    .remove-btn { background:pink; color:black; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin:0; }
.admin-actions-row{margin-top:6px; display:none; align-items:center; justify-content:center; gap:6px; flex-wrap:nowrap;}
body.admin-mode.show-pin-controls .admin-actions-row{display:flex;}
body.admin-mode.show-delete-controls .admin-actions-row{display:flex;}

.admin-actions-row .pin-box, .admin-actions-row .delete-box{margin-top:0;}

.watch-btn { background:#e7f5ff; color:#0b3d91; border:1px solid #a5d8ff; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin:0;}
.watch-btn.on { background:#d3f9d8; color:#1b5e20; border-color:#b2f2bb; }
    .coll-arrow{display:inline-flex;align-items:center;justify-content:center;margin:0;padding:0 8px 0 0;background:transparent;border:0;font-size:18px;line-height:1;text-decoration:none;color:#111;}
    .coll-arrow:hover{opacity:.75;}
.watch-box{margin-top:6px;display:flex;align-items:center;justify-content:center;gap:0;flex-wrap:nowrap;}

.watch-box form{margin:0; display:inline-flex;}
.watch-box .watch-btn{border-radius:0;}
.watch-box > form:first-child .watch-btn{border-radius:4px;}
.pin-btn { background:#e0ffad; color:black; border:none; padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; margin:0; display:inline-flex; align-items:center; gap:4px; }
    .poster-list-sidebar h4 { margin-top:0; font-size:16px; }
    .poster-list-sidebar input { width:100%; padding:8px 14px; border:1px solid #bbb; border-radius:8px; margin-bottom:10px; background:#fafcff; font-size:15px; box-shadow:0 1px 6px #0001; outline:none; transition:.17s; direction: rtl; }
    .poster-list-sidebar ol { list-style-type:decimal !important; direction: rtl; padding-right: 8px; margin: 0; list-style-position: inside !important; }
    .poster-list-sidebar li { margin-bottom: 8px; line-height: 1.3; text-align: right; }
    .form-box textarea { width: 100%; font-size: 15px; padding: 8px; border-radius: 7px; border: 1px solid #bbb; background: #fafcff; margin-bottom: 10px; resize: vertical; min-height: 65px; }
    .form-box button { width: 100%; font-size: 16px; padding: 8px 0; border-radius: 7px; border: none; background: #007bff; color: #fff; margin-top: 6px; cursor: pointer; transition: background 0.2s; }
    .main-search-box { background: #fff; border: 1.5px solid #bbb; border-radius: 11px; padding: 10px 18px; width: 270px; font-size: 16px; box-shadow: 0 2px 10px #e1e6eb50; outline: none; transition: border .2s, box-shadow .2s; margin-left: 0; margin-right: 0; color: #1d1d1d; }
    .main-search-btn { padding: 8px 20px; border-radius: 7px; border: none; background: #1576cc; color: #fff; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center;}
    .sidebar-wrapper { flex:none !important; width:305px; min-width:250px; max-width:320px; display: flex; flex-direction: column; gap: 20px; align-self: flex-start; }
    .filter-panel { background: #fdfdfd; border: 1px solid #eee; padding: 15px; border-radius: 6px; box-shadow: 0 0 4px rgba(0,0,0,0.05); }
    .filter-panel h4 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd; }

    .filter-panel form { display: flex; flex-wrap: wrap; gap: 0 15px; }
    .filter-group { margin-bottom: 15px; flex-grow: 1; flex-basis: 45%; min-width: 200px; }
    .filter-group label { display: block; margin-bottom: 5px; color: #333; font-weight: bold; font-size: 14px; }
    .filter-group input, .filter-group select { width: 100%; padding: 8px; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .filter-submit-btn { width: 100%; padding: 10px; font-size: 1.1em; font-weight: bold; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; flex-basis: 100%; }
    .filter-submit-btn:hover { background-color: #0056b3; }

    .poster-list-sidebar { background:#f8f8f8; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.05); font-size:14px; color:#333; height:fit-content; text-align:right; padding:12px 0px; }
    .poster-section.sidebar-hidden .sidebar-wrapper { display: none; }

    @media (max-width:1000px) { .container { padding: 8px 2px; } .poster-section { flex-direction:column; gap:0; } .sidebar-wrapper { width:100%; max-width:100%; margin-bottom:16px; } }
    .poster-list-sidebar .year, .poster-list-sidebar .imdb-id { font-size: 10px; color: #555; }

    .controls-bar { display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap: 20px; margin-bottom:15px; padding: 0 5px; }
    .sort-box { display:flex; align-items:center; gap:8px; }
    .sort-box label { font-weight:bold; font-size:15px; }
    .sort-box select { padding: 5px 8px; border-radius: 5px; border: 1px solid #ccc; font-size:15px; }

    /* ===== סינון סטטוס: נצפו / רשימת צפייה / לא נצפו ===== */
    .status-filters { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; align-items:center; }
    .status-btn{
      text-decoration:none;
      padding: 6px 12px;
      border-radius: 6px;
      border: 1px solid #ced4da;
      background: #e9ecef;
      color: #000;
      font-size: 14px;
      line-height: 1.2;
      white-space: nowrap;
      cursor: pointer;
    }
    .status-btn:hover{ background:#dfe3e7; }
    .status-btn.active{
      background: deepskyblue;
      border-color: deepskyblue;
      color: #fff;
      font-weight: bold;
    }

    .controls-wrapper { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 20px 0; display: flex; flex-direction: column; gap: 15px; }
    .action-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .action-bar strong, .action-bar span { font-size: 14px; color: #495057; margin-left: 5px; }
    .action-bar form { margin: 0; }
    .action-bar .action-btn, .action-bar button { text-decoration: none; padding: 6px 12px; border-radius: 5px; border: 1px solid #ced4da; background-color: deepskyblue; color: white; border-color: #00BFFF; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; white-space: nowrap; font-family: inherit; }
    .action-bar .action-btn:hover, .action-bar button:hover { background-color: #00a8e6; border-color: #00a8e6; }
    .action-bar .action-btn.active, .action-bar button.active { background-color: #007bff; color: white; border-color: #007bff; z-index: 2; }
    .action-bar .btn-group { display: inline-flex; }
    .btn-group .action-btn, .btn-group button { border-radius: 0; margin-left: -1px; }
    .btn-group .action-btn:first-child, .btn-group button:first-child { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
    .btn-group .action-btn:last-child, .btn-group button:last-child { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }

    .desc-shared-wrap { text-align: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
    .desc-shared-wrap img { max-width: 100%; height: auto; border-radius: 4px; }
    .desc2-wrap{width:100%; text-align:center; margin:14px 0;}
    .desc2-table{display:inline-table; border-collapse:separate; border-spacing:24px 0; width:auto; max-width:1100px;}
    .desc2-td{vertical-align:top; padding:0 20px; max-width:520px;}
    .desc2-td:first-child { border-right: 2px solid black; }
    .desc2-col{ text-align:justify; text-justify:inter-word; unicode-bidi:plaintext; line-height:1.45; font-size:15px; }
    .desc2-col.en{ direction:ltr; text-align-last:left; }
    .desc2-col.he{ direction:rtl; text-align-last:right; }
    @media (max-width: 860px){ .desc2-table{ display:block; max-width:95%; margin:0 auto; border-spacing:0; } .desc2-td{ display:block; width:auto; max-width:none; margin:0 0 12px 0; } }

    .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
    .modal-content { background-color: #fefefe; padding: 20px; border: 1px solid #888; border-radius: 8px; width: 90%; max-width: 500px; position: relative; }
    .modal-close-btn { color: #aaa; float: left; font-size: 28px; font-weight: bold; position: absolute; top: 5px; left: 15px; cursor: pointer; }
    .modal-close-btn:hover, .modal-close-btn:focus { color: black; }

    .hover-card { position: absolute; top: 0; left: 105%; width: 420px; background: #fff; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 20; padding: 12px; text-align: right; direction: rtl; font-size: 14px; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out; }
    .hover-card a { color: #1576cc; text-decoration: none; } .hover-card a:hover { text-decoration: underline; } .hover-card h4 { margin: 0 0 8px 0; font-size: 1.2em; color: #111; } .hover-card .meta-item { margin-bottom: 6px; } .hover-card .meta-item strong { color: #555; } .hover-card .overview { font-size: 0.9em; line-height: 1.4; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
    .trailer-placeholder { position: relative; cursor: pointer; background-color: #000; border-radius: 4px; overflow: hidden; } .trailer-placeholder img { width: 100%; aspect-ratio: 16/9; border: 0; display: block; opacity: 0.8; transition: opacity 0.2s; } .trailer-placeholder:hover img { opacity: 1; } .trailer-placeholder .play-button { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 68px; height: 48px; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 48"><path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="%23f00"></path><path d="M 45,24 27,14 27,34" fill="%23fff"></path></svg>'); background-repeat: no-repeat; background-position: center; background-size: contain; border: none; transition: transform 0.2s; pointer-events: none; }
    .trailer-wrap iframe { width: 100%; aspect-ratio: 16/9; border: 0; }

    .pagination { text-align: center; margin-top: 25px; padding: 10px 0; direction: rtl; }
    .pagination a, .pagination span.current { margin: 0 4px; padding: 8px 14px; border: 1px solid #ddd; background: #fdfdfd; color: #007bff; border-radius: 4px; text-decoration: none; display: inline-block; font-size: 14px; transition: background-color 0.2s, border-color 0.2s; }
    .pagination a:hover { background: #f0f0f0; border-color: #ccc; }
    .pagination span.current { background: #007bff; color: white; border-color: #007bff; cursor: default; }

    /* ===== Admin visibility rules (FIX) ===== */

/* ברירת מחדל: לא להציג */
#manage-controls { display: none; }
.pin-box, .delete-box, .watch-box { display: none; }

/* רק במצב ניהול מציגים את אזור הניהול */
body.admin-mode #manage-controls { display: block !important; }

/* מציגים לפי טוגלים */
body.admin-mode.show-pin-controls    .pin-box    { display: flex !important; }
body.admin-mode.show-delete-controls .delete-box { display: flex !important; }
body.admin-mode.show-watch-controls  .watch-box  { display: flex !important; }

/* יישור */
.pin-box, .delete-box, .watch-box{
  margin-top:6px;
  align-items:center;
  justify-content:center;
  gap:0;
  flex-wrap:nowrap;
}

/* === מצב תמונות בלבד (body.images-only-mode) === */
    body.images-only-mode .poster-item small,
body.images-only-mode .poster-item .title-he,
body.images-only-mode .poster-item .year,
body.images-only-mode .poster-item .imdb-rating{
  display:none !important;
}


    body.images-only-mode .poster-item { padding-bottom: 0; background: none; border: none; }
    body.images-only-mode .poster-item img { box-shadow: 0 0 4px rgba(0,0,0,0.15); }
    body.images-only-mode #toggleImagesOnlyBtn { background-color: #007bff !important; border-color: #007bff !important; color: #fff !important; }

    /* === מצב בלי כרטיס ריחוף (body.no-hover-mode) === */
    body.no-hover-mode .poster-item .hover-card { display: none !important; }
    body.no-hover-mode #toggleHoverBtn { background-color: #007bff !important; border-color: #007bff !important; color: #fff !important; }

    /* === אינדיקטור נעיצה קבוע בצד שמאל למעלה === */
    .pin-indicator{
        position:absolute;
        top:4px;
        left:4px;
        right:auto;
        background: silver;
        color:#000;
        font-size:13px;
        font-weight:bold;
        line-height:1;
        padding:3px 6px;
        border-radius:4px;
        box-shadow:0 2px 4px rgba(0,0,0,.3);
        z-index:30;
        pointer-events:none;
    }
    /* === מצב ללא רווחים בין תמונות (כשהתצוגה תמונות בלבד) === */
    body.no-gaps-mode .poster-grid { gap: 0 !important; }
    body.no-gaps-mode .poster-item { margin: 0 !important; padding-bottom: 0 !important; }

   /* הכפתור תמיד מוצג */
#toggleGapsBtn { display: inline-flex; }

    /* מבטל את ה-baseline והרווח התחתון של תמונות */
.poster-item img { 
  display: block;          /* במקום inline */
}

/* ביטול כל גובה שורה/פונט שעשוי להשאיר רווח זעיר */
body.images-only-mode .poster-item {
  line-height: 0;
  font-size: 0;
}

/* לוודא שאין רווחי גריד/פלקס כלל */
body.images-only-mode .poster-grid {
  gap: 0 !important;
}
body.images-only-mode .poster-item img { box-shadow: none; }
/* FIX: לאפשר הצגת רווחים כש'תמונות בלבד' פעיל אבל 'ללא רווחים' כבוי */
body.images-only-mode:not(.no-gaps-mode) .poster-grid { gap: inherit !important; }

  /* ===== Top "חיפוש אוסף" bar (sticky) ===== */
    .top-collection-search{
      position: static;
      
      
      background: rgba(245,245,245,0.92);
      backdrop-filter: blur(6px);
      border-bottom: 1px solid #d9d9d9;
      padding: 14px 12px;
      margin: -10px -10px 15px -10px;
    }
    .top-collection-search .tcs-inner{
      display:flex;
      flex-direction: row-reverse;
      align-items:center;
      justify-content:center;
      gap:14px;
      flex-wrap: wrap;
    }

    /* שורה שמחזיקה את שדה החיפוש + כפתור יחד (כדי שלא יתפצלו) */
    .top-collection-search .tcs-row{
display:flex;
  flex-direction: row-reverse;
  align-items:center;
  justify-content: flex-start;
  gap: 12px;
  flex-wrap: nowrap;
}
    
    .tcs-btn{ order: 1; }
    .tcs-input-wrap{ order: 2; }
.top-collection-search .tcs-input-wrap{
position:relative;
  min-width: 420px;
  flex: 1 1 520px;
  max-width: 720px;
}
    .top-collection-search input[type="text"]{
width: 100%;
      font-size: 18px;
      padding: 12px 14px;
      border: 1px solid #cfcfcf;
      border-radius: 12px;
      outline: none;
      background: #fff;
  min-width: 420px;
}
    .top-collection-search input[type="text"]:focus{
      border-color:#9bbcff;
      box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
    }
    .top-collection-search .tcs-btn{
font-size:16px;
      padding: 11px 16px;
      border-radius: 12px;
      border: 0;
      cursor: pointer;
      background: #0d6efd;
      color: #fff;
      line-height: 1;
      white-space: nowrap;
}
    .top-collection-search .tcs-btn:hover{ background:#0b5ed7; }
    .top-collection-search .tcs-check{
      font-size: 15px;
      user-select:none;
      white-space: nowrap;
    }
    .top-collection-search .tcs-check input{ transform: scale(1.15); margin- }

    .collections-suggestions{

  position:absolute;
  
   
  background:#fff;
  border:1px solid #cfcfcf;
  border-radius:12px;
  max-height:260px;
  overflow-y:auto;
  box-shadow:0 10px 24px rgba(0,0,0,0.12);
  
  display:none;
  direction: rtl;
  text-align: right;
}
    .suggestion-item{

  padding:10px 12px;
  cursor:pointer;
  font-size:15px;
  display:flex;
  align-items:center;
  gap:10px;
  justify-content: flex-start;
  direction: rtl;
  text-align: right;
}
    .suggestion-item:hover{ background:#f2f6ff; }
    .suggestion-icon{ width:22px; text-align:center; }




</style>
</head>
<body>
<form id="collections-nav-form" class="top-collection-search" method="get" action="collections.php">
  <div class="tcs-inner">
    <div class="tcs-row">
      <button type="submit" class="tcs-btn">חיפוש</button>

      <div class="tcs-checks">
        <label class="tcs-check"><input type="checkbox" name="search_title" value="1" checked> בכותרת</label>
        <label class="tcs-check"><input type="checkbox" name="search_desc" value="1" checked> בתיאור</label>
      </div>

      <div class="tcs-input-wrap">
        <input type="text" name="txt" id="collection-search-input" autocomplete="off" placeholder="חיפוש אוסף...">
        <div id="collection-suggestions" class="collections-suggestions"></div>
      </div>
    </div>
  </div>
</form>
<br>
<?php if (isset($_GET['csv_success'])): ?>
  <div id="csv-success-message" style="background:#f6fff6; border:1px solid #cde8cd; padding:12px; border-radius:8px; margin-bottom:15px; text-align:center;">
    ✅ הועלה ועובד בהצלחה.<br>
    נוספו לאוסף: <strong><?= (int)($_GET['inserted'] ?? 0) ?></strong>, כבר היו: <strong><?= (int)($_GET['already'] ?? 0) ?></strong>.
  </div>
<?php endif; ?>
<div class="container">
  <h2>📂 אוסף: <?= htmlspecialchars($collection['name']) ?></h2>
  <?php if (!empty($collection['image_url'])): ?>
    <img src="<?= htmlspecialchars($collection['image_url']) ?>" alt="תמונה" class="header-img">
  <?php endif; ?>

  <?php if (!empty($collection['description'])): ?>
    <?php
      $raw = (string)($collection['description'] ?? '');
      $shared_raw = ''; $right_he_raw = ''; $left_en_raw  = '';
      $tags_detected = (strpos($raw, '[משותף]') !== false || strpos($raw, '[עברית]') !== false || strpos($raw, '[אנגלית]') !== false);
      if ($tags_detected) {
        if (preg_match('~\[משותף\](.*?)\[/משותף\]~is', $raw, $mShared)) { $shared_raw = trim($mShared[1]); }
        if (preg_match('~\[עברית\](.*?)\[/עברית\]~is', $raw, $mHe)) { $right_he_raw = trim($mHe[1]); }
        if (preg_match('~\[אנגלית\](.*?)\[/אנגלית\]~is', $raw, $mEn)) { $left_en_raw = trim($mEn[1]); }
      } else {
        $rawN = str_replace("\r\n", "\n", trim($raw)); $rawN = preg_replace("/\n{4,}/", "\n\n\n", $rawN);
        $parts = preg_split("/\n{3,}/", $rawN, 2); $p0 = trim($parts[0] ?? ''); $p1 = trim($parts[1] ?? '');
        $hasHeb = static function(string $t): bool { return (bool)preg_match('/\p{Hebrew}/u', $t); };
        if (count($parts) >= 2) { $right_he_raw = $p0; $left_en_raw  = $p1;
        } else { if ($p0 !== '') { if ($hasHeb($p0)) { $right_he_raw = $p0; } else { $left_en_raw = $p0; } } }
      }
      $desc_shared_html = trim(bbcode_to_html($shared_raw)); $desc_he_html = trim(bbcode_to_html($right_he_raw)); $desc_en_html = trim(bbcode_to_html($left_en_raw));
      $has_shared = !empty($desc_shared_html); $has_he = !empty($desc_he_html); $has_en = !empty($desc_en_html);
    ?>
    <?php if ($has_shared): ?><div class="desc-shared-wrap bbcode"><?= $desc_shared_html ?></div><?php endif; ?>
    <?php if ($has_he || $has_en): ?><div class="desc2-wrap"><table class="desc2-table" role="presentation" dir="ltr"><tr><td class="desc2-td"><div class="desc2-col en bbcode"><?= $desc_en_html ?></div></td><td class="desc2-td"><div class="desc2-col he bbcode"><?= $desc_he_html ?></div></td></tr></table></div><?php endif; ?>
  <?php endif; ?>


  <div id="csvUploadResult" style="max-width:900px;margin:12px auto 0;display:none;background:#f0f8ff;border:1px solid #cce5ff;padding:12px;border-radius:8px;"></div>

  <div class="controls-wrapper">
    <div id="manage-controls">
        <div class="action-bar">
            <strong>ניהול ועריכה:</strong>
            <form id="csvUploadForm" action="collection_upload_csv_api.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="collection_id" value="<?= $id ?>">
                <input type="file" name="ids_file" id="ids_file" accept=".csv,.txt,text/csv,text/plain" style="display:none" onchange="uploadCsvToCollection(this.form)" multiple>
                <button type="button" class="action-btn" onclick="document.getElementById('ids_file').click()" style="background-color: #6f42c1; color: #fff; border-color: #6f42c1;">⬆️ העלאת CSV/TXT                <input type="hidden" name="mode" value="file">
</button>
            </form>
            <button type="button" onclick="openBatchAddModal()" class="action-btn" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">➕ הוספה מרובה</button>

            <button type="button" onclick="openBatchRemoveModal()" class="action-btn" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">➖ מחיקה מרובה</button>
            <a href="edit_collection.php?id=<?= $collection['id'] ?>" class="action-btn">✏️ ערוך</a>
            <a href="manage_collection_posters.php?id=<?= $collection['id'] ?>" class="action-btn" style="background-color: #ffc107; border-color: #ffc107; color: #212529;">✏️ נהל אוסף</a>
            <form method="post" action="collection.php?<?= $redirect_query_string ?>" style="display:inline;" autocomplete="off">
              <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
              <button type="submit" name="duplicate_collection" value="1" class="action-btn" style="background:#6f42c1;color:#fff;border:0;">🧬 שכפל אוסף</button>
            </form>
            <?php if (!empty($collection['is_pinned'])): ?>
              <form method="post" action="collection.php?<?= $redirect_query_string ?>" style="display:inline;">
                <input type="hidden" name="action" value="unpin_collection">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <button type="submit" class="action-btn" style="background:#fff3cd; color:#856404; border-color: #ffeeba;">📌 הסר נעיצת אוסף</button>
              </form>
            <?php else: ?>
              <form method="post" action="collection.php?<?= $redirect_query_string ?>" style="display:inline;">
                <input type="hidden" name="action" value="pin_collection">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <button type="submit" class="action-btn" style="background:#d1e7dd; color:#0f5132; border-color: #badbcc;">📌 נעיצת אוסף</button>
              </form>
            <?php endif; ?>

            <?php if (!empty($collection['is_private'])): ?>
              <form method="post" action="collection.php?<?= $redirect_query_string ?>" style="display:inline;">
                <input type="hidden" name="action" value="make_public_collection">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <button type="submit" class="action-btn" style="...dd; color:#084298; border-color:#b6d4fe;">🔓 הפוך לציבורי</button>
              </form>
            <?php else: ?>
              <form method="post" action="collection.php?<?= $redirect_query_string ?>" style="display:inline;">
                <input type="hidden" name="action" value="make_private_collection">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <button type="submit" class="action-btn" style="...dd; color:#842029; border-color:#f5c2c7;">🔒 הפוך לפרטי</button>
              </form>
            <?php endif; ?>

               <form method="post" action="collection.php?id=<?= $id ?>" style="display:inline;" autocomplete="off">
              <input type="hidden" name="collection_id" value="<?= $id ?>">
              <input type="hidden" name="delete_all_content" value="1">
              <button type="submit" class="action-btn" style="background:#f9e7cf; color:#856404; border-color: #f7ddb2;">🗑️ מחק את כל התוכן</button>
            </form>
            
           <form method="post" action="collections.php" style="display:inline;">
              <input type="hidden" name="delete_collection" value="<?= $collection['id'] ?>">
              <button type="submit" onclick="return confirm('למחוק את האוסף?')">🗑️ מחק אוסף</button>
            </form>

         

            <a href="#" id="togglePinBtn" onclick="togglePin(); return false;" class="action-btn">📌 הצג נעיצה</a>
            <a href="#" id="toggleDeleteBtn" onclick="toggleDelete(); return false;" class="action-btn">🧹 הצג מחיקה</a>

            <a href="#" id="toggleWatchBtn" onclick="toggleWatch(); return false;" class="action-btn">⏳ הצג הוספה</a>
<form method="post" action="unpin_all_in_collection.php" method="post" onsubmit="return confirm('האם אתה בטוח שברצונך להסיר נעיצה מכל הפוסטרים באוסף זה?');" style="display: inline-block; margin-left: 10px;">
                <input type="hidden" name="collection_id" value="<?php echo htmlspecialchars($id); ?>">
                <button type="submit" class="action-btn" style="background-color: #dc3545; border-color: #dc3545; color: white;">הסר נעיצה מהכל</button>
            </form>

        </div>
    </div>

    <div class="action-bar">
        <strong>תצוגה וכלים:</strong>
        <button type="button" id="toggleManageBtn" class="action-btn admin-toggle" onclick="toggleManageMode()">🔑 מצב ניהול</button>
        <button type="button" id="toggleListBtn" onclick="toggleNameList()" class="action-btn" style="background: #e7f5ff; color: #1971c2; border-color: #a5d8ff;">📄 הצג רשימה שמית</button>
        <a href="collection_csv.php?id=<?= $id ?>" target="_blank" class="action-btn" style="background:#2a964a; color:#fff; border-color: #2a964a;">⬇️ ייצא כ־CSV</a>
        <a href="report.php?collection_id=<?= (int)$id ?>"
   class="action-btn"
   style="background:#fff5f5; color:#c92a2a; border-color:#ffa8a8;">
   🚩 דווח על האוסף
</a>

        
        <button type="button" onclick="copyPtpLinks()" class="action-btn" style="background: #f7eeba; color: #5c4d00; border-color: #e8db86;">📋 העתק IMDB</button>
            <button type="button" id="toggleImagesOnlyBtn" onclick="toggleImagesOnly()" class="action-btn" style="background:#fff3cd; color:#856404; border-color:#ffeeba;">🖼 מצב תמונות בלבד</button>
            <button type="button" id="toggleHoverBtn" onclick="toggleHoverCard()" class="action-btn" style="background:#f8f9fa; color:#343a40; border-color:#ced4da;">🪄 ביטול כרטיס ריחוף</button>
            <button type="button" id="toggleGapsBtn" onclick="toggleGaps()" class="action-btn" style="background:#f8f9fa; color:#343a40; border-color:#ced4da;"> 🔲 הסתר רווחים</button>

        
        <a href="universe.php?collection_id=<?= $collection['id'] ?>" class="action-btn" style="background:#d1ecf1;color:#0c5460; border-color: #bee5eb;">🌌 הצג בציר זמן</a>

        <a href="collections.php" class="action-btn" style="margin-right: auto;">⬅ חזרה לרשימת האוספים</a>
    
    <div class="action-bar" style="justify-content:center; gap:8px; flex-wrap:wrap;">
    <strong>פתח בכל אתר:</strong>
    <button type="button" onclick="openSiteLinks('filelist')" class="action-btn" style="background:#e2e6ea; color:#495057; border-color:#dae0e5;">Filelist</button>
    <button type="button" onclick="openSiteLinks('ipt')" class="action-btn" style="background:#e2e6ea; color:#495057; border-color:#dae0e5;">IPT</button>
    <button type="button" onclick="openSiteLinks('fuzer')" class="action-btn" style="background:#e2e6ea; color:#495057; border-color:#dae0e5;">Fuzer</button>
    <button type="button" onclick="openSiteLinks('hebits')" class="action-btn" style="background:#e2e6ea; color:#495057; border-color:#dae0e5;">Hebits</button>
    <button type="button" onclick="openSiteLinks('ptp')" class="action-btn" style="background:#e2e6ea; color:#495057; border-color:#dae0e5;">PTP</button>
    <button type="button" onclick="openSiteLinks('imdb')" class="action-btn" style="background:#e2e6ea; color:#495057; border-color:#dae0e5;">IMDb</button>
</div>
</div>

    <div class="action-bar">
      <span>כמות לעמוד:</span>
      <div class="btn-group">
        <?php
        $per_page_params = $_GET; unset($per_page_params['page']);
        $per_page_options = [20, 30, 35, 40, 50, 100, 250, 500, 'all'];
        foreach ($per_page_options as $option) {
            $per_page_params['per_page'] = $option;
            $current_per_page_val = $show_all ? 'all' : $per_page;
            $active_class = ($current_per_page_val == $option) ? 'active' : '';
            $label = ($option === 'all') ? 'הכל' : $option;
            echo '<a href="collection.php?' . http_build_query($per_page_params) . '" class="action-btn ' . $active_class . '">' . $label . '</a>';
        }
        ?>
      </div>

      <span style="margin-right: 15px;">גודל תצוגה:</span>
      <div class="btn-group">
        <button type="button" id="size-small" class="action-btn" onclick="setSize('small')">קטן</button>
        <button type="button" id="size-medium" class="action-btn" onclick="setSize('medium')">בינוני</button>
        <button type="button" id="size-large" class="action-btn" onclick="setSize('large')">גדול</button>
        <!-- חדש: מצב ענק -->
        <button type="button" id="size-giant" class="action-btn" onclick="setSize('giant')">ענק</button>
      </div>
    </div>
  </div>

  <form method="get" action="collection.php" style="margin:12px 0 15px 0; display:flex; gap:10px; align-items:center; justify-content:center;">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="text" name="q" value="<?= htmlspecialchars($q_raw) ?>" class="main-search-box" placeholder="🔎 חפש (אפשר להדביק טקסט מלוכלך / לינק IMDb) בתוך האוסף">
    <button type="submit" class="main-search-btn">חפש באוסף</button>
    <?php if (!empty($filters['q']) || !empty($filters['type_id']) || !empty($filters['min_year']) || !empty($filters['max_year']) || !empty($filters['min_rating'])): ?>
        <a href="collection.php?id=<?= $id ?>&sort=<?= urlencode($sort_order) ?>" class="main-search-btn" style="background-color: #6c757d;">איפוס</a>
    <?php endif; ?>
  </form>

  <div class="filter-panel" style="margin: 0 auto 20px auto; max-width: 50%; min-width: 500px;">
    <h4>✔️ סנן פוסטרים</h4>
    <form method="get">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="q" value="<?= htmlspecialchars($q_raw) ?>">
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">

      <div class="filter-group">
        <label for="type_id">סוג</label>
        <select name="type_id" id="type_id" onchange="this.form.submit()">
          <option value="">הכל</option>
          <?php foreach ($types_list as $t): ?>
            <option value="<?= $t['id'] ?>" <?= ($filters['type_id'] == $t['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['label_he']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="min_rating">דירוג IMDb מינימלי</label>
        <input type="number" name="min_rating" id="min_rating" step="0.1" min="1" max="10" placeholder="7.5" value="<?= htmlspecialchars($filters['min_rating']) ?>">
      </div>

      <div class="filter-group">
        <label for="min_year">משנה</label>
        <input type="number" name="min_year" id="min_year" placeholder="1980" value="<?= htmlspecialchars($filters['min_year']) ?>">
      </div>

      <div class="filter-group">
        <label for="max_year">עד שנה</label>
        <input type="number" name="max_year" id="max_year" placeholder="2025" value="<?= htmlspecialchars($filters['max_year']) ?>">
      </div>

      <button type="submit" class="filter-submit-btn">סנן</button>
      <?php if (!empty(array_filter(array_slice($filters, 1)))): ?>
        <a href="collection.php?id=<?= $id ?>&q=<?= urlencode($filters['q']) ?>&sort=<?= urlencode($sort_order) ?>" style="display: block; text-align: center; margin-top: 10px;">נקה סינון</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="controls-bar">
    <h3>🎬 פוסטרים באוסף: (<?= $total_posters ?> תוצאות)</h3>
    <?php
      $__sf_base = $_GET;
      $__sf_base['id'] = $id;
      unset($__sf_base['page']);
    ?>
    <div class="status-filters">
      <a class="status-btn <?= (($filters['status'] ?? '') === 'watched') ? 'active' : '' ?>"
         href="collection.php?<?= htmlspecialchars(http_build_query(array_merge($__sf_base, ['status' => 'watched']))) ?>">👁️ נצפו</a>

      <a class="status-btn <?= (($filters['status'] ?? '') === 'watchlist') ? 'active' : '' ?>"
         href="collection.php?<?= htmlspecialchars(http_build_query(array_merge($__sf_base, ['status' => 'watchlist']))) ?>">⏳ ברשימת הצפייה</a>

      <a class="status-btn <?= (($filters['status'] ?? '') === 'none') ? 'active' : '' ?>"
         href="collection.php?<?= htmlspecialchars(http_build_query(array_merge($__sf_base, ['status' => 'none']))) ?>">🚫 לא נצפו / לא ברשימה</a>
    </div>

    <div class="sort-box">
      <form method="get" id="sortForm">
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php foreach ($filters as $key => $value): if ($value !== '' && $value !== null): ?>
          <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
        <?php endif; endforeach; ?>
        <?php if (isset($_GET['per_page'])): ?>
          <input type="hidden" name="per_page" value="<?= htmlspecialchars($_GET['per_page']) ?>">
        <?php endif; ?>
        <label for="sort">מיון לפי:</label>
        <select name="sort" id="sort" onchange="document.getElementById('sortForm').submit()">
          <option value="collection" <?= ($sort_order == 'collection') ? 'selected' : '' ?>>📚 לפי סדר האוסף (ברירת מחדל)</option>
          <option value="site_added_desc" <?= ($sort_order == 'site_added_desc') ? 'selected' : '' ?>>⏱ נוספו לאתר: מהחדש לישן</option>
          <option value="site_added_asc"  <?= ($sort_order == 'site_added_asc') ? 'selected' : '' ?>>⏱ נוספו לאתר: מהישן לחדש</option>
          <option value="col_added_desc" <?= ($sort_order == 'col_added_desc') ? 'selected' : '' ?>>📥 נוספו לאוסף: מהחדש לישן</option>
          <option value="col_added_asc"  <?= ($sort_order == 'col_added_asc') ? 'selected' : '' ?>>📤 נוספו לאוסף: מהישן לחדש</option>
          <option value="year_desc"  <?= ($sort_order == 'year_desc') ? 'selected' : '' ?>>📅 שנה ↓</option>
          <option value="year_asc"   <?= ($sort_order == 'year_asc') ? 'selected' : '' ?>>📅 שנה ↑</option>
          <option value="rating_desc" <?= ($sort_order == 'rating_desc') ? 'selected' : '' ?>>⭐ דירוג ↓</option>
          <option value="rating_asc"  <?= ($sort_order == 'rating_asc') ? 'selected' : '' ?>>⭐ דירוג ↑</option>
          <option value="title_he_asc" <?= ($sort_order == 'title_he_asc') ? 'selected' : '' ?>>🔤 א-ת (עברית)</option>
          <option value="title_he_desc"  <?= ($sort_order == 'title_he_desc') ? 'selected' : '' ?>>🔤 ת-א (עברית)</option>
          <option value="title_en_asc" <?= ($sort_order == 'title_en_asc') ? 'selected' : '' ?>>🔠 A-Z (English)</option>
          <option value="title_en_desc" <?= ($sort_order == 'title_en_desc') ? 'selected' : '' ?>>🔠 Z-A (English)</option>
        </select>
      </form>
    </div>
  </div>
<?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php
        $range = 5; $base_url = "collection.php?id=$id" . $filter_query_string;
        if ($page > 1) { echo "<a href='{$base_url}&page=1'>ראשון</a>"; }
        if ($page > 1) { $prev_page = $page - 1; echo "<a href='{$base_url}&page={$prev_page}'>הקודם</a>"; }
        $start_range = max(1, $page - $range); $end_range = min($total_pages, $page + $range);
        if ($start_range > 1) { echo "<span>...</span>"; }
        for ($i = $start_range; $i <= $end_range; $i++) {
          if ($i == $page) { echo "<span class='current'>$i</span>"; }
          else { echo "<a href='{$base_url}&page={$i}'>$i</a>"; }
        }
        if ($end_range < $total_pages) { echo "<span>...</span>"; }
        if ($page < $total_pages) { $next_page = $page + 1; echo "<a href='{$base_url}&page={$next_page}'>הבא</a>"; }
        if ($page < $total_pages) { echo "<a href='{$base_url}&page={$total_pages}'>אחרון</a>"; }
      ?>
    </div>
  <?php endif; ?>
  <div class="poster-section">
    <div class="poster-grid medium">
      <?php if ($poster_list): ?>
        <?php $base_link_params = http_build_query(array_merge($_GET, ['id' => $id])); ?>
        <?php foreach ($poster_list as $p): ?>
          <?php $img = trim($p['image_url'] ?? ''); if ($img === '') $img = 'images/no-poster.png'; ?>
          <div class="poster-item medium <?= !empty($p['is_pinned']) ? 'pinned' : '' ?>">

            <?php if (!empty($p['is_pinned'])): ?>
              <div class="pin-indicator">📌</div>
            <?php endif; ?>

            <a href="poster.php?id=<?= $p['id'] ?>">
              <img src="<?= htmlspecialchars($img) ?>" alt="Poster">
              <?php if (!empty($p['title_en'])): ?><small><?= htmlspecialchars($p['title_en']) ?></small><?php endif; ?>
              <?php if (!empty($p['title_he'])): ?><div class="title-he"><?= htmlspecialchars($p['title_he']) ?></div><?php endif; ?>
              <?php if (!empty($p['year'])): ?> <div class="year"><?= htmlspecialchars($p['year']) ?></div> <?php endif; ?>
              <?php if (!empty($p['imdb_rating']) && !empty($p['imdb_id'])): ?>
                <div class="imdb-rating"><a href="https://www.imdb.com/title/<?= htmlspecialchars($p['imdb_id']) ?>" target="_blank" rel="noopener">⭐ <?= htmlspecialchars($p['imdb_rating']) ?>/10</a></div>
              <?php endif; ?>
            </a>

            <div class="watch-box">
              <?php $in_watch = !empty($watchlisted[(int)$p['id']]); ?>
              <?php if ($in_watch): ?>
                <form method="post" action="collection.php?<?= $base_link_params ?>" style="display:inline;" autocomplete="off">
                  <input type="hidden" name="action" value="watch_remove">
                  <input type="hidden" name="collection_id" value="<?= $id ?>">
                  <input type="hidden" name="poster_id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="imdb_id" value="<?= htmlspecialchars((string)($p['imdb_id'] ?? '')) ?>">
                  <button type="submit" class="watch-btn on js-watch-toggle" data-poster-id="<?= (int)$p['id'] ?>" data-collection-id="196" title="הסר מרשימת צפייה">✅ הסר מרשימת צפייה</button>
                </form>
              <?php else: ?>
                <form method="post" action="collection.php?<?= $base_link_params ?>" style="display:inline;" autocomplete="off">
                  <input type="hidden" name="action" value="watch_add">
                  <input type="hidden" name="collection_id" value="<?= $id ?>">
                  <input type="hidden" name="poster_id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="imdb_id" value="<?= htmlspecialchars((string)($p['imdb_id'] ?? '')) ?>">
                  <button type="submit" class="watch-btn js-watch-toggle" data-poster-id="<?= (int)$p['id'] ?>" data-collection-id="196" title="הוסף לרשימת צפייה">⏳ הוסף לרשימת צפייה</button>
                </form>
              <?php endif; ?>
            <a class="coll-arrow" href="collection.php?id=196" title="פתח את האוסף" target="_blank">↗</a>
            </div>

            <div class="hover-card">
              <h4><a href="poster.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['title_he'] ?: $p['title_en']) ?><?php if (!empty($p['title_he']) && !empty($p['title_en'])): ?> | <?= htmlspecialchars($p['title_en']) ?><?php endif; ?></a></h4>
              <?php if (!empty($p['year'])): ?><div class="meta-item"><strong>שנה:</strong> <a href="<?= generate_home_search_link('year', $p['year']) ?>"><?= htmlspecialchars($p['year']) ?></a></div><?php endif; ?>
              <?php if (!empty($p['genres'])): ?>
                <div class="meta-item"><strong>ז'אנרים:</strong>
                  <?php $genresArr = array_values(array_filter(array_map('trim', explode(',', $p['genres'])))); ?>
                  <?php foreach ($genresArr as $idx => $g): ?>
                    <a href="<?= generate_home_search_link('genre', $g) ?>"><?= htmlspecialchars($g) ?></a><?php if ($idx < count($genresArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if(!empty($p['user_tags_list'])): ?>
                <div class="meta-item"><strong>תגיות:</strong>
                  <?php $tagsArr = array_values(array_filter(array_map('trim', explode(',', $p['user_tags_list'])))); ?>
                  <?php foreach ($tagsArr as $idx => $tag): ?>
                    <a href="<?= generate_home_search_link('user_tag', $tag) ?>"><?= htmlspecialchars($tag) ?></a><?php if ($idx < count($tagsArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($p['countries'])): ?>
                <div class="meta-item"><strong>מדינות:</strong>
                  <?php $countriesArr = array_values(array_filter(array_map('trim', explode(',', $p['countries'])))); ?>
                  <?php foreach ($countriesArr as $idx => $c): ?>
                    <a href="<?= generate_home_search_link('country', $c) ?>"><?= htmlspecialchars($c) ?></a><?php if ($idx < count($countriesArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($p['languages'])): ?>
                <div class="meta-item"><strong>שפות:</strong>
                  <?php $langsArr = array_values(array_filter(array_map('trim', explode(',', $p['languages'])))); ?>
                  <?php foreach ($langsArr as $idx => $l): ?>
                    <a href="<?= generate_home_search_link('lang_code', $l) ?>"><?= htmlspecialchars($l) ?></a><?php if ($idx < count($langsArr)-1): ?>, <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if(!empty($p['overview_he'])): ?>
                <div class="overview"><?= nl2br(htmlspecialchars(mb_substr($p['overview_he'], 0, 500) . (mb_strlen($p['overview_he']) > 500 ? '...' : ''))) ?></div>
              <?php endif; ?>

              <div class="trailer-wrap">
                <?php
                if (!empty($p['trailer_url']) && (strpos($p['trailer_url'], 'youtube.com') !== false || strpos($p['trailer_url'], 'youtu.be') !== false)) {
                  if (preg_match('~(?:v=|youtu\.be/|/embed/)([A-Za-z0-9_-]{11})~', $p['trailer_url'], $m)) {
                    $youtube_id = $m[1];
                    $thumbnail_url = 'https://i.ytimg.com/vi/' . htmlspecialchars($youtube_id) . '/hqdefault.jpg';
                    echo '<div class="trailer-placeholder" data-youtube-id="' . htmlspecialchars($youtube_id) . '"><img loading="lazy" src="' . $thumbnail_url . '" alt="Trailer Thumbnail"><div class="play-button"></div></div>';
                  }
                }
                ?>
              </div>
            </div>

            <div class="admin-actions-row">
              <div class="pin-box">
              <?php if (!empty($p['is_pinned'])): ?>
                <form method="post" action="collection.php?<?= $base_link_params ?>" style="display:inline;">
                  <input type="hidden" name="action" value="unpin_poster">
                  <input type="hidden" name="collection_id" value="<?= $id ?>">
                  <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="pin-btn">📌 הסר נעיצה</button>
                </form>
              <?php else: ?>
                <form method="post" action="collection.php?<?= $base_link_params ?>" style="display:inline;">
                  <input type="hidden" name="action" value="pin_poster">
                  <input type="hidden" name="collection_id" value="<?= $id ?>">
                  <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="pin-btn">📌 נעיצה</button>
                </form>
              <?php endif; ?>
            </div>

            <div class="delete-box">
              <form method="post" action="remove_from_collection.php">
                <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                <input type="hidden" name="poster_id" value="<?= $p['id'] ?>">
                <button type="submit" class="remove-btn">🗑️ מחק</button>
              </form>
            </div>
            </div>

          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>לא נמצאו פוסטרים באוסף זה התואמים לסינון.</p>
      <?php endif; ?>
    </div>

    <aside class="sidebar-wrapper">
      <div class="poster-list-sidebar" id="name-list-sidebar">
        <h4>📃 רשימה שמית</h4>
        <div style="position:relative;"><input type="text" id="poster-search" placeholder="חפש פוסטר..."></div>
        <ol id="poster-list">
          <?php foreach ($all_posters_for_list as $i => $p2): ?>
            <li>
              <a href="poster.php?id=<?= $p2['id'] ?>" style="color:#1576cc;"><?= htmlspecialchars($p2['title_en'] ?: $p2['title_he']) ?></a>
              <?php if (!empty($p2['title_he']) && !empty($p2['title_en'])): ?>
                <div class="title-he"><?= htmlspecialchars($p2['title_he']) ?> | <?= htmlspecialchars($p2['title_en']) ?></div>
              <?php elseif (!empty($p2['title_he'])): ?>
                <div class="title-he"><?= htmlspecialchars($p2['title_he']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p2['year'])): ?><div class="year">שנה: <a href="<?= generate_home_search_link('year', $p2['year']) ?>"><?= htmlspecialchars($p2['year']) ?></a></div><?php endif; ?>
              <?php if (!empty($p2['imdb_id'])): ?><div class="imdb-id"><a href="https://www.imdb.com/title/<?= htmlspecialchars($p2['imdb_id']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($p2['imdb_id']) ?></a></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </aside>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php
        $range = 5; $base_url = "collection.php?id=$id" . $filter_query_string;
        if ($page > 1) { echo "<a href='{$base_url}&page=1'>ראשון</a>"; }
        if ($page > 1) { $prev_page = $page - 1; echo "<a href='{$base_url}&page={$prev_page}'>הקודם</a>"; }
        $start_range = max(1, $page - $range); $end_range = min($total_pages, $page + $range);
        if ($start_range > 1) { echo "<span>...</span>"; }
        for ($i = $start_range; $i <= $end_range; $i++) {
          if ($i == $page) { echo "<span class='current'>$i</span>"; }
          else { echo "<a href='{$base_url}&page={$i}'>$i</a>"; }
        }
        if ($end_range < $total_pages) { echo "<span>...</span>"; }
        if ($page < $total_pages) { $next_page = $page + 1; echo "<a href='{$base_url}&page={$next_page}'>הבא</a>"; }
        if ($page < $total_pages) { echo "<a href='{$base_url}&page={$total_pages}'>אחרון</a>"; }
      ?>
    </div>
  <?php endif; ?>

  <div class="form-box">
    <h3>➕ הוספת פוסטרים לפי מזהים</h3>
    <form method="post" action="add_to_collection_batch.php" class="batch-add-form" autocomplete="off">
      <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
      <label>🔗 מזהים (ID רגיל או IMDb: tt...)</label>
      <textarea name="poster_ids_raw" rows="6" placeholder="לדוגמה:&#10;45&#10;tt1375666&#10;89"></textarea>
      <button type="submit">📥 קשר פוסטרים</button>
    </form>
  </div>
</div>

<div id="batchAddModal" class="modal-overlay" onclick="closeBatchAddModal(event)">
  <div class="modal-content">
    <span class="modal-close-btn" onclick="closeBatchAddModal({target: this, currentTarget: this})">&times;</span>
    <div class="form-box">
      <h3>➕ הוספת פוסטרים לפי מזהים</h3>
      <form method="post" action="add_to_collection_batch.php" class="batch-add-form" autocomplete="off">
        <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
        <label>🔗 מזהים (ID רגיל או IMDb: tt...)</label>
        <textarea name="poster_ids_raw" rows="6" placeholder="לדוגמה:&#10;45&#10;tt1375666&#10;89"></textarea>
        <button type="submit">📥 קשר פוסטרים</button>
      </form>
    </div>
  </div>
</div>

<div id="batchRemoveModal" class="modal-overlay" onclick="closeBatchRemoveModal(event)">
  <div class="modal-content">
    <span class="modal-close-btn" onclick="closeBatchRemoveModal({target: this, currentTarget: this})">&times;</span>
    <div class="form-box">
      <h3>➖ מחיקת פוסטרים לפי מזהים</h3>
      <form method="post" action="remove_from_collection_batch.php" class="batch-remove-form" autocomplete="off" onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את כל הפוסטרים הללו מהאוסף? פעולה זו אינה הפיכה.');">
        <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
        <label>🔗 מזהים (ID רגיל או IMDb: tt...)</label>
        <textarea name="poster_ids_raw" rows="6" placeholder="לדוגמה:&#10;45&#10;tt1375666&#10;89"></textarea>
        <button type="submit" style="background-color: #dc3545;">🗑️ מחק פוסטרים</button>
      </form>
    </div>
  </div>
</div>
<script>
  function deleteAllContent(collectionId) {
    if (confirm('האם אתה בטוח שברצונך למחוק את כל הפוסטרים מאוסף זה? הפעולה אינה הפיכה.')) {
      const f = document.createElement('form');
      f.method = 'POST';
      f.action = 'collection.php?id=' + collectionId;
      const i1 = document.createElement('input'); i1.type='hidden'; i1.name='delete_all_content'; i1.value='1';
      const i2 = document.createElement('input'); i2.type='hidden'; i2.name='collection_id'; i2.value=collectionId;
      f.appendChild(i1); f.appendChild(i2);
      document.body.appendChild(f); f.submit();
    }
  }

  function setSize(size) {
    localStorage.setItem('posterSize', size);
    document.querySelectorAll('.poster-item, .poster-grid').forEach(item => {
      item.classList.remove('small', 'medium', 'large', 'giant'); // ← נוספה giant
      item.classList.add(size);
    });
    document.querySelectorAll('#size-small, #size-medium, #size-large, #size-giant').forEach(btn => btn.classList.remove('active')); // ← נוספה giant
    const btn = document.getElementById('size-'+size);
    if (btn) btn.classList.add('active');
  }

  function toggleNameList() {
    const posterSection = document.querySelector('.poster-section');
    const button = document.getElementById('toggleListBtn');
    if (posterSection && button) {
      const isHidden = posterSection.classList.toggle('sidebar-hidden');
      button.innerHTML = isHidden ? '📄 הצג רשימה שמית' : '📄 הסתר רשימה שמית';
      localStorage.setItem('isNameListHidden', isHidden ? 'true' : 'false');
    }
  }

  // מצב "תמונות בלבד"
  function toggleImagesOnly() {
    const body = document.body;
    const btn  = document.getElementById('toggleImagesOnlyBtn');
    const gapsBtn = document.getElementById('toggleGapsBtn');
    const isOn = body.classList.toggle('images-only-mode');
    localStorage.setItem('imagesOnlyMode', isOn ? '1' : '0');

    if (btn){
      btn.innerHTML = isOn ? '🖼 החזר טקסט' : '🖼 מצב תמונות בלבד';
      btn.classList.toggle('active', isOn);
    }
   // הכפתור תמיד גלוי
if (gapsBtn) {
  gapsBtn.style.display = 'inline-flex';
}

  }

  // מצב "כרטיס ריחוף"
  function toggleHoverCard() {
    const body = document.body;
    const btn  = document.getElementById('toggleHoverBtn');
    const isOn = body.classList.toggle('no-hover-mode');
    localStorage.setItem('noHoverMode', isOn ? '1' : '0');
    if (btn){
      btn.innerHTML = isOn ? '🪄 הפעל ריחוף' : '🪄 ביטול ריחוף';
      btn.classList.toggle('active', isOn);
    }
  }

  // מצב "ללא רווחים" (מופעל רק יחד עם תמונות בלבד)
  function toggleGaps() {
    const body = document.body;
    const btn  = document.getElementById('toggleGapsBtn');
    const isOn = body.classList.toggle('no-gaps-mode');
    localStorage.setItem('noGapsMode', isOn ? '1' : '0');
    if (btn) {
      btn.innerHTML = isOn ? '🔳 הצג רווחים' : '🔲 הסתר רווחים';
      btn.classList.toggle('active', isOn);
    }
  }

  const batchAddModal = document.getElementById('batchAddModal');
  function openBatchAddModal() { batchAddModal.style.display = 'flex'; }
  function closeBatchAddModal(event) { if (event.target === event.currentTarget) { batchAddModal.style.display = 'none'; } }

  // START: פונקציות חדשות למודל מחיקה מרובה
  const batchRemoveModal = document.getElementById('batchRemoveModal');
  function openBatchRemoveModal() { batchRemoveModal.style.display = 'flex'; }
  function closeBatchRemoveModal(event) { if (event.target === event.currentTarget) { batchRemoveModal.style.display = 'none'; } }
  // END: פונקציות חדשות

  function toggleManageMode(){
    const body = document.body;
    const isOn = body.classList.toggle('admin-mode');
    localStorage.setItem('adminMode', isOn ? '1' : '0');
    const btn = document.getElementById('toggleManageBtn');
    if (btn){
      btn.innerHTML = isOn ? '🚪 יציאה ממצב ניהול' : '🔑 מצב ניהול';
      btn.classList.toggle('active', isOn);
    }
  }

  function toggleControls(controlType) {
    const body = document.body;
    const buttonId = `toggle${controlType}Btn`;
    const className = `show-${controlType.toLowerCase()}-controls`;
    const showText = controlType === 'Pin' ? '📌 הצג נעיצה' : '🧹 הצג מחיקה';
    const hideText = controlType === 'Pin' ? '📌 הסתר נעיצה' : '🧹 הסתר מחיקה';
    const button = document.getElementById(buttonId);

    const isVisible = body.classList.toggle(className);
    localStorage.setItem(`is${controlType}Visible`, isVisible ? '1' : '0');

    if (button) {
      button.classList.toggle('active', isVisible);
      button.innerHTML = isVisible ? hideText : showText;
    }
  }
  function toggleDelete() { toggleControls('Delete'); }
  function togglePin() { toggleControls('Pin'); }


  function toggleWatch(){
    const body = document.body;
    const button = document.getElementById('toggleWatchBtn');
    const className = 'show-watch-controls';
    const isVisible = body.classList.toggle(className);
    localStorage.setItem('isWatchVisible', isVisible ? '1' : '0');
    if (button) {
      button.classList.toggle('active', isVisible);
      button.innerHTML = isVisible ? '⏳ הסתר הוספה' : '⏳ הצג הוספה';
    }
  }
  document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    // מצב ניהול
    const isAdminMode = localStorage.getItem('adminMode') === '1';
    if (isAdminMode) body.classList.add('admin-mode');
    const manageBtn = document.getElementById('toggleManageBtn');
    if (manageBtn) {
      manageBtn.innerHTML = isAdminMode ? '🚪 יציאה ממצב ניהול' : '🔑 מצב ניהול';
      manageBtn.classList.toggle('active', isAdminMode);
    }

    // הצגת כפתורי נעיצה/מחיקה
    const isPinVisible = localStorage.getItem('isPinVisible') === '1';
    const pinBtn = document.getElementById('togglePinBtn');
    if (isPinVisible) {
      body.classList.add('show-pin-controls');
      if (pinBtn) { pinBtn.classList.add('active'); pinBtn.innerHTML = '📌 הסתר נעיצה'; }
    } else if (pinBtn) { pinBtn.innerHTML = '📌 הצג נעיצה'; }

    const isDeleteVisible = localStorage.getItem('isDeleteVisible') === '1';
    const deleteBtn = document.getElementById('toggleDeleteBtn');
    if (isDeleteVisible) {
      body.classList.add('show-delete-controls');
      if (deleteBtn) { deleteBtn.classList.add('active'); deleteBtn.innerHTML = '🧹 הסתר מחיקה'; }
    } else if (deleteBtn) { deleteBtn.innerHTML = '🧹 הצג מחיקה'; }

    
    const isWatchVisible = localStorage.getItem('isWatchVisible') === '1';
    const watchBtn = document.getElementById('toggleWatchBtn');
    if (isWatchVisible) {
      body.classList.add('show-watch-controls');
      if (watchBtn) { watchBtn.classList.add('active'); watchBtn.innerHTML = '⏳ הסתר הוספה'; }
    } else if (watchBtn) { watchBtn.innerHTML = '⏳ הצג הוספה'; }

// גודל פוסטרים
    setSize(localStorage.getItem('posterSize') || 'medium');

    // רשימה שמית
    const posterSectionOnLoad = document.querySelector('.poster-section');
    const toggleListBtn = document.getElementById('toggleListBtn');
    if (localStorage.getItem('isNameListHidden') === 'true') {
        if(posterSectionOnLoad) posterSectionOnLoad.classList.add('sidebar-hidden');
        if(toggleListBtn) toggleListBtn.innerHTML = '📄 הצג רשימה שמית';
    } else {
        if(posterSectionOnLoad) posterSectionOnLoad.classList.remove('sidebar-hidden');
        if(toggleListBtn) toggleListBtn.innerHTML = '📄 הסתר רשימה שמית';
    }

    // תמונות בלבד
    const imagesOnlyStored = localStorage.getItem('imagesOnlyMode') === '1';
    const imgOnlyBtn = document.getElementById('toggleImagesOnlyBtn');
    if (imagesOnlyStored) {
        body.classList.add('images-only-mode');
        if (imgOnlyBtn) { imgOnlyBtn.innerHTML = '🖼 החזר טקסט'; imgOnlyBtn.classList.add('active'); }
    } else {
        if (imgOnlyBtn) { imgOnlyBtn.innerHTML = '🖼 מצב תמונות בלבד'; imgOnlyBtn.classList.remove('active'); }
    }

    // בלי ריחוף
    const noHoverStored = localStorage.getItem('noHoverMode') === '1';
    const hoverBtn = document.getElementById('toggleHoverBtn');
    if (noHoverStored) {
        body.classList.add('no-hover-mode');
        if (hoverBtn) { hoverBtn.innerHTML = '🪄 הפעל ריחוף'; hoverBtn.classList.add('active'); }
    } else {
        if (hoverBtn) { hoverBtn.innerHTML = '🪄 ביטול ריחוף'; hoverBtn.classList.remove('active'); }
    }

    // היגיון ה-HOVER CARD
    const HIDE_DELAY = 100;
    document.querySelectorAll('.poster-item').forEach(item => {
        const card = item.querySelector('.hover-card');
        if (!card) return;
        let hideTimeout;

        const showCard = () => {
            if (document.body.classList.contains('no-hover-mode')) return;
            clearTimeout(hideTimeout);
            const rect = item.getBoundingClientRect();
            card.style.left = 'auto';
            card.style.right = '105%';
            if (rect.left < card.offsetWidth + 20) {
                card.style.right = 'auto';
                card.style.left = '105%';
            }
            card.style.opacity = '1';
            card.style.visibility = 'visible';
            card.style.pointerEvents = 'auto';
        };

        const hideCard = () => {
            hideTimeout = setTimeout(() => {
                card.style.opacity = '0';
                card.style.visibility = 'hidden';
                card.style.pointerEvents = 'none';
            }, HIDE_DELAY);
        };

        item.addEventListener('mouseenter', showCard);
        item.addEventListener('mouseleave', hideCard);
        card.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
        card.addEventListener('mouseleave', hideCard);
    });

    // פתיחת טריילר מיוטיוב
    document.body.addEventListener('click', function(event) {
        const placeholder = event.target.closest('.trailer-placeholder');
        if (!placeholder) return;
        const youtubeId = placeholder.dataset.youtubeId;
        if (!youtubeId) return;
        const iframe = document.createElement('iframe');
        iframe.setAttribute('src', `https://www.youtube.com/embed/${youtubeId}?autoplay=1&rel=0`);
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('title', 'Trailer');
        const wrapper = placeholder.parentElement;
        if (wrapper && wrapper.classList.contains('trailer-wrap')) {
            wrapper.innerHTML = '';
            wrapper.appendChild(iframe);
        }
    });

    // סינון רשימה שמית
    const searchInput = document.getElementById('poster-search');
    if (searchInput) {
      const listItems = document.querySelectorAll('#poster-list li');
      searchInput.addEventListener('input', () => {
        const val = searchInput.value.toLowerCase();
        listItems.forEach(item => {
          item.style.display = item.textContent.toLowerCase().includes(val) ? 'list-item' : 'none';
        });
      });
    }

    // START: ניקוי לינקים של batch add/remove
    document.querySelectorAll('.batch-add-form, .batch-remove-form').forEach(form => {
      form.addEventListener('submit', function() {
        const textarea = this.querySelector('textarea[name="poster_ids_raw"]');
        if (textarea) {
          // מנקה לינקים מלאים של poster.php?id=1234 והופך אותם למספר 1234 בלבד
          textarea.value = textarea.value.replace(/https?:\/\/[^\s\/]+\/.*?poster\.php\?id=(\d+)/g, '$1');
        }
      });
    });
    // END: ניקוי לינקים

    // שחזור מצב "ללא רווחים" אם היה פעיל
    const noGapsStored = localStorage.getItem('noGapsMode') === '1';
    const gapsBtn = document.getElementById('toggleGapsBtn');
    if (noGapsStored) {
      document.body.classList.add('no-gaps-mode');
      if (gapsBtn) { gapsBtn.classList.add('active'); gapsBtn.innerHTML = '🔳 הצג רווחים'; }
    }
  });
</script>

<script>
function uploadCsvToCollection(form){
  try{
    if(!form) return;
    var cid = form.querySelector('input[name="collection_id"]');
    if(cid && (!cid.value || cid.value === "0")){
      var m = (location.search.match(/[?&]id=(\d+)/) || [])[1];
      if(m) cid.value = m;
    }
    var fileInput = form.querySelector('input[type="file"][name="ids_file"]');
    if(!fileInput || !fileInput.files || fileInput.files.length===0) return;
    var mode = form.querySelector('input[name="mode"]');
    if(!mode){
      mode = document.createElement('input');
      mode.type='hidden'; mode.name='mode'; mode.value='file';
      form.appendChild(mode);
    } else { mode.value='file'; }
    form.submit();
  }catch(e){ if (window.console && console.error) console.error('CSV upload error:', e); }
}

// פונקציה להעתקת קישורי PTP
function copyPtpLinks() {
    // קבלת המערך מ-PHP
    var ids = <?php echo json_encode(($current_page_imdb_ids ?? []), JSON_UNESCAPED_SLASHES); ?>;
    
    if (!ids || ids.length === 0) {
        alert('לא נמצאו מזהי IMDB בעמוד זה להעתקה.');
        return;
    }

    var textToCopy = "";
    var count = 0;

    ids.forEach(function(id) {
        if (id) {
            // בדיקה אם המזהה כבר כולל tt או לא
            // הפורמט המבוקש: searchstr=tt* (כלומר חייב להתחיל ב-tt)
            var cleanId = id.trim();
            if (!cleanId.startsWith('tt')) {
                cleanId = 'tt' + cleanId;
            }
            
            textToCopy += "https://www.imdb.com/title/" + cleanId + "\n";
            count++;
        }
    });

    if (count === 0) {
        alert('לא נמצאו מזהים תקינים.');
        return;
    }

    // העתקה ללוח
    navigator.clipboard.writeText(textToCopy).then(function() {
        alert('✅ הועתקו ' + count + ' קישורים ללוח!');
    }).catch(function(err) {
        console.error('Async: Could not copy text: ', err);
        alert('שגיאה בהעתקה');
    });
}
// פונקציה לפתיחת כל הקישורים בחלונות חדשים (לפי אתר)
function openSiteLinks(siteKey) {
    var ids = <?php echo json_encode(($current_page_imdb_ids ?? []), JSON_UNESCAPED_SLASHES); ?>;

    if (!ids || ids.length === 0) {
        alert('לא נמצאו מזהי IMDB בעמוד זה.');
        return;
    }

    var bases = {
        filelist: "http://filelist.io/browse.php?cat=0&searchin=0&sort=3&search=",
        ipt:      "http://www.iptorrents.com/t?o=size;q=",
        fuzer:    "https://www.fuzer.xyz/browse.php?ref_=basic&query=",
        hebits:   "https://hebits.net/torrents.php?searchstr=",
        ptp:      "https://passthepopcorn.me/torrents.php?searchstr=",
        imdb:     "https://www.imdb.com/title/"
    };

    var base = bases[siteKey];
    if (!base) {
        alert('אתר לא נתמך.');
        return;
    }

    // סינון ובניית רשימת כתובות תקינה
    var urlsToOpen = [];
    ids.forEach(function(id) {
        if (!id) return;

        // תומך גם במזהה נקי וגם בטקסט/לינק שמכיל tt...
        var raw = String(id).trim();
        if (!raw) return;

        var mm = raw.match(/tt\d{5,}/i);
        if (!mm) return;

        var cleanId = mm[0].toLowerCase();

        if (siteKey === 'imdb') {
            urlsToOpen.push(base + cleanId + '/');
        } else {
            urlsToOpen.push(base + encodeURIComponent(cleanId));
        }
    });
if (urlsToOpen.length === 0) {
        alert('לא נמצאו מזהים תקינים.');
        return;
    }

    var siteLabel = ({
        filelist: 'Filelist',
        ipt: 'IPT',
        fuzer: 'Fuzer',
        hebits: 'Hebits',
        ptp: 'PTP',
        imdb: 'IMDb'
    })[siteKey] || 'האתר';
    urlsToOpen.forEach(function(url) {
        window.open(url, '_blank');
    });
}

// תאימות אחורה (אם משהו עדיין קורא לפונקציה הישנה)
function openPtpLinks() {
    openSiteLinks('ptp');
}

</script>

<script>
(function(){
  var input = document.getElementById('collection-search-input');
  var box   = document.getElementById('collection-suggestions');
  if (!input || !box) return;

  var timer = null;

  function hide(){
    box.style.display = 'none';
    box.innerHTML = '';
  }

  function render(items){
    if (!items || !items.length) { hide(); return; }
    box.innerHTML = '';
    items.forEach(function(item){
      var div = document.createElement('div');
      div.className = 'suggestion-item';
      div.dataset.url = item.url || '';
      div.dataset.name = item.name || item.label || '';
      var ic = document.createElement('span');
      ic.className = 'suggestion-icon';
      ic.textContent = item.icon || '📁';
      var tx = document.createElement('span');
      tx.textContent = item.label || item.name || '';
      div.appendChild(ic);
      div.appendChild(tx);

      div.addEventListener('click', function(){
        if (div.dataset.url) window.location.href = div.dataset.url;
      });

      box.appendChild(div);
    });
    box.style.display = 'block';
  }

  input.addEventListener('input', function(){
    var q = input.value.trim();
    if (q.length < 2) { hide(); return; }
    if (timer) clearTimeout(timer);
    timer = setTimeout(function(){
      fetch('collections_search_handler.php?term=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(render)
        .catch(function(){ hide(); });
    }, 200);
  });

  document.addEventListener('click', function(e){
    if (e.target !== input && !box.contains(e.target)) hide();
  });
})();

// ===============================
// AJAX toggle for Watchlist button inside collection page
// Uses toggle_collection_api.php (collection_id=196)
// ===============================
(function(){
  function setBtn(btn, inCol){
    if (!btn) return;
    if (inCol){
      btn.classList.add('on');
      btn.title = 'הסר מרשימת צפייה';
      btn.textContent = '✅ הסר מרשימת צפייה';
    } else {
      btn.classList.remove('on');
      btn.title = 'הוסף לרשימת צפייה';
      btn.textContent = '⏳ הוסף לרשימת צפייה';
    }
    btn.disabled = false;
  }

  function postToggle(posterId, collectionId){
    var fd = new FormData();
    fd.append('poster_id', String(posterId));
    fd.append('collection_id', String(collectionId));
    return fetch('toggle_collection_api.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(function(r){ return r.json(); });
  }

  document.addEventListener('submit', function(e){
    var form = e.target;
    if (!form) return;
    var btn = form.querySelector('.js-watch-toggle');
    if (!btn) return;

    e.preventDefault();

    var posterId = parseInt(btn.getAttribute('data-poster-id') || '0', 10);
    var collectionId = parseInt(btn.getAttribute('data-collection-id') || '0', 10);
    if (!posterId || !collectionId) return;

    btn.disabled = true;

    postToggle(posterId, collectionId).then(function(j){
      if (!j || !j.ok) { btn.disabled = false; return; }
      setBtn(btn, !!j.in_collection);
    }).catch(function(){
      btn.disabled = false;
    });
  }, true);
})();

</script>

</body>
</html>
<?php include 'footer.php'; ?>