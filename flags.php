<div dir="ltr" style="text-align:left;">
  <style>
    .languages-table {
      font-family: Calibri, sans-serif;
      border-collapse: collapse;
      margin: 10px auto;
      direction: ltr;
      text-align: left;
    }
    .language-td {
      padding: 6px;
      text-align: left;
      vertical-align: middle;
    }
    .language-cell {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      cursor: pointer;
      font-weight: normal;
    }
    .language-cell input[type="checkbox"] {
      transform: scale(1.2);
    }
    .language-cell img {
      height: 16px;
    }
    .language-cell span {
      flex-grow: 1;
    }
    .language-cell span {
  display: inline-block;
  min-width: 60px;
  text-align: left;
  font-weight: normal;
}
/*  ריווח שמשפיע על ADD */
/*
.language-cell {
  height: 18px !important;
  line-height: 1 !important;
}

.language-td {
  height: 20px !important;
  padding: 0 !important;
  margin: 0 !important;
}
*/
  </style>

  <table class="languages-table">
    <tr><td colspan="5"><strong>🌐 Source Languages:</strong></td></tr><tr>
    <?php
    include 'languages.php';
    $columns = 5;
    $rows_limit = 10;
    $max_count = $columns * $rows_limit;
    $i = 0;
    foreach ($languages as $lang) {
      if ($i >= $max_count) break;
      $checked = isset($_GET['languages']) && in_array($lang['code'], $_GET['languages']) ? 'checked' : '';
      echo "<td class='language-td'>
              <label class='language-cell'>
                <input type='checkbox' name='languages[]' value='{$lang['code']}' $checked>
                <img src='{$lang['flag']}' alt='{$lang['label']}' title='{$lang['label']}'>
                <span>{$lang['label']}</span>
              </label>
            </td>";
      $i++;
      if ($i % $columns === 0 && $i < $max_count) echo '</tr><tr>';
    }
    echo '</tr></table>';
    ?>
</div>
