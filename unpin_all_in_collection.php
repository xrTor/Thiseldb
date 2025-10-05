<?php
// unpin_all_in_collection.php

// כלול את קובץ החיבור למסד הנתונים
require_once 'server.php';

// ודא שהתקבל מזהה אוסף בשיטת POST
if (isset($_POST['collection_id'])) {
    // קבל את מזהה האוסף ואבטח אותו כמספר שלם
    $collection_id = intval($_POST['collection_id']);

    // הכן את שאילתת ה-UPDATE באמצעות Prepared Statement למניעת SQL Injection
    // השאילתה מעדכנת את כל הרשומות בטבלת poster_collections
    // וקובעת את is_pinned ל-0 (לא נעוץ) עבור האוסף הספציפי
    $sql = "UPDATE poster_collections SET is_pinned = 0 WHERE collection_id = ?";

    // הכן את ההצהרה
    if ($stmt = $conn->prepare($sql)) {
        // קשור את הפרמטר (מזהה האוסף) להצהרה
        $stmt->bind_param("i", $collection_id);

        // בצע את השאילתה
        if ($stmt->execute()) {
            // אם ההצלחה, הפנה את המשתמש בחזרה לדף האוסף עם הודעת הצלחה
            header("Location: collection.php?id=" . $collection_id . "&status=unpinned_all_success");
        } else {
            // אם יש שגיאה, הפנה בחזרה עם הודעת שגיאה
            header("Location: collection.php?id=" . $collection_id . "&status=error");
        }

        // סגור את ההצהרה
        $stmt->close();
    } else {
        // אם הכנת השאילתה נכשלה
        header("Location: collection.php?id=" . $collection_id . "&status=prepare_error");
    }

    // סגור את החיבור
    $conn->close();
    exit();

} else {
    // אם לא נשלח מזהה אוסף, פשוט חזור לדף הבית
    header("Location: index.php");
    exit();
}
?>