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
      font-weight: normal;
      cursor: pointer;
      transition: background 0.1s;
    }
    .language-cell:hover {
      background: #f0f0f0;
      border-radius: 7px;
    }
    .language-cell img {
      height: 16px;
    }
    .language-cell span {
      display: inline-block;
      min-width: 60px;
      text-align: left;
      font-weight: normal;
    }
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
      // דגל לחיץ בלבד, ללא checkbox
      echo "<td class='language-td'>
              <a class='language-cell' href='language.php?lang_code={$lang['code']}' title='{$lang['label']}'>
                <img src='{$lang['flag']}' alt='{$lang['label']}'>
                <span>{$lang['label']}</span>
              </a>
            </td>";
      $i++;
      if ($i % $columns === 0 && $i < $max_count) echo '</tr><tr>';
    }
    echo '</tr></table>';
    ?>
</div>
