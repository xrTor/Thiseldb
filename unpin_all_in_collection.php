<?php
// unpin_all_in_collection.php

// הפעלת סשן כדי לגשת למשתני הסשן
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. וידוא שהבקשה היא מסוג POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit('Error: This action requires a POST request.');
}

// 2. וידוא טוקן CSRF לאבטחה
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403); // Forbidden
    exit('Error: Invalid security token.');
}

// כלול את קובץ החיבור למסד הנתונים
require_once 'server.php';

// ודא שהתקבל מזהה אוסף (הלוגיקה הקיימת תקינה)
if (isset($_POST['collection_id'])) {
    $collection_id = intval($_POST['collection_id']);

    // הכן את שאילתת ה-UPDATE באמצעות Prepared Statement (הלוגיקה הקיימת תקינה)
    $sql = "UPDATE poster_collections SET is_pinned = 0 WHERE collection_id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $collection_id);

        if ($stmt->execute()) {
            // אם ההצלחה, הפנה את המשתמש בחזרה לדף האוסף עם הודעת הצלחה
            header("Location: collection.php?id=" . $collection_id . "&status=unpinned_all_success");
        } else {
            // אם יש שגיאה, הפנה בחזרה עם הודעת שגיאה
            header("Location: collection.php?id=" . $collection_id . "&status=error");
        }
        $stmt->close();
    } else {
        // אם הכנת השאילתה נכשלה
        header("Location: collection.php?id=" . $collection_id . "&status=prepare_error");
    }

    $conn->close();
    exit();

} else {
    // אם לא נשלח מזהה אוסף, חזור לדף הבית
    header("Location: index.php");
    exit();
}
?>