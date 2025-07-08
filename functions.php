<?php
function processValue($value, $sNotFound = '—', $sSeparator = ', ') {
    if (is_array($value)) {
        $filtered = array_filter($value, function($v) {
            return $v !== '' && $v !== null;
        });

        if (empty($filtered)) {
            return $sNotFound;
        }

        $mapped = array_map(function($v) use ($sNotFound) {
            return $v ?: $sNotFound;
        }, $value);

        return implode($sSeparator, $mapped);
    }

    if ($value === '' || $value === null) {
        return $sNotFound;
    }

    return (string)$value;
}
?>
