<?php
// alias.php

// מיפויים לפי שם השדה כפי שהוא נשלח מהטופס ב-bar.php
$ALIASES = [
  // חיפוש חופשי
  'search' => [
    'ארה"ב'  => 'USA',
    'ארה״ב'  => 'USA',
  ],

  // מדינות (שדה הטופס: country)
  'country' => [
    'קנדה'        => 'Canada',
    'ארצות הברית' => 'USA',
    'ארה״ב'       => 'USA',
    'usa'         => 'United States',
    'צרפת'        => 'France',
    'יפן'         => 'Japan',
    'ישראל'       => 'Israel',
    'אוסטרליה'       => 'Australia',
    'הודו'         => 'India',
  ],

  // שפות (שדה הטופס: lang_code)
  'lang_code' => [
    'עברית'  => 'Hebrew',
    'אנגלית' => 'English',
    'יפנית'  => 'Japanese',
    'צרפתית' => 'French',
    'שוודית' => 'Swedish',

    
  ],

  // ז׳אנרים (שדה הטופס: genre)
  'genre' => [
    'אקשן'   => 'Action',
    'דרמה'   => 'Drama',
    'קומדיה' => 'Comedy',
    'מותחן'  => 'Thriller',
  ],

  // רשתות/פלטפורמות (שדה הטופס: network)
  'network' => [
    'נטפליקס' => 'Netflix',
    'אמזון'   => 'Amazon',
    'הולו'    => 'Hulu',
  ],

  // 🔹 תגיות משתמש (שדה הטופס: user_tag)
  // הוסף כאן מיפויים כרצונך – הדוגמאות להמחשה בלבד
  'user_tag' => [
    'דוקו'        => 'Documentary',
    'דוקומנטרי'   => 'Documentary',
    'ישראלי'      => 'Israeli',
    'קלאסי'       => 'Classic',
    'חובה'        => 'Must See',
    'ילדים'       => 'Kids',
    'פסטיבל'      => 'Festival',
    'אימה'        => 'Horror',
    'מד"ב'        => 'Sci-Fi',
    'מד״ב'        => 'Sci-Fi',
    'ספורט'       => 'Sports',
    'רימייק'      => 'Remake',
  ],
];

/**
 * ממיר ערך של שדה לפי ALIASES.
 * תומך ברשימה מופרדת בפסיקים, בשלילה (!) ורגישויות שונות לאותיות (case-insensitive).
 * דוג׳: "ישראל,!קנדה" => "Israel,!Canada"
 */
function applyAliases(string $field, string $value, array $ALIASES): string {
  if ($value === '' || empty($ALIASES[$field])) return $value;

  $map = $ALIASES[$field];

  // מיפוי לאותיות קטנות כדי לאפשר חיפוש ללא תלות בקייס
  $map_ci = [];
  foreach ($map as $k => $v) {
    $map_ci[mb_strtolower($k, 'UTF-8')] = $v;
  }

  // פירוק לפריטים לפי פסיקים (תמיכה גם ברווחים)
  $parts = preg_split('/\s*,\s*/u', $value);
  $out   = [];

  foreach ($parts as $p) {
    if ($p === '') continue;
    $neg = false;
    if ($p[0] === '!') { $neg = true; $p = substr($p, 1); }

    $key = mb_strtolower($p, 'UTF-8');
    $normalized = $map_ci[$key] ?? $p; // אם אין מיפוי – נשאר המקור
    $out[] = $neg ? '!'.$normalized : $normalized;
  }

  return implode(',', $out);
}
