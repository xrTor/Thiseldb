<?php
require_once 'server.php';
require_once 'bbcode.php';

$txt = trim($_GET['txt'] ?? '');
$inTitle = !empty($_GET['title']);
$inDesc = !empty($_GET['desc']);

$where=[];
$params=[];
$types='';

if($txt!==''){
  $conds=[];
  if($inTitle){$conds[]="c.name LIKE ?"; $params[]="%$txt%"; $types.='s';}
  if($inDesc){$conds[]="c.description LIKE ?"; $params[]="%$txt%"; $types.='s';}
  if($conds){$where[]="(".implode(" OR ",$conds).")";}
}

$sql="SELECT c.*,COUNT(pc.poster_id) total_items 
      FROM collections c 
      LEFT JOIN poster_collections pc ON c.id=pc.collection_id";
if($where)$sql.=" WHERE ".implode(" AND ",$where);
$sql.=" GROUP BY c.id ORDER BY c.created_at DESC LIMIT 200";

$stmt=$conn->prepare($sql);
if($params){$stmt->bind_param($types,...$params);}
$stmt->execute();
$res=$stmt->get_result();

if($res->num_rows===0){echo "<p style='text-align:center;'>😢 לא נמצאו תוצאות</p>";}
while($c=$res->fetch_assoc()){
  $desc=trim($c['description']??'');
  $default="[עברית]\n\n[/עברית]\n\n\n[אנגלית]\n\n[/אנגלית]";
  $is_def=(trim(str_replace(["\r","\n"," "],'',$desc))===trim(str_replace(["\r","\n"," "],'',$default)));
  ?>
  <div class="collection-card <?=!empty($c['is_pinned'])?'pinned':''?>">
    <h3><a href="collection.php?id=<?=$c['id']?>"><?=!empty($c['is_pinned'])?'📌':'📁'?> <?=htmlspecialchars($c['name'])?></a></h3>
    <?php if($desc && !$is_def): ?>
      <button class="toggle-desc-btn" onclick="this.nextElementSibling.classList.toggle('open')">📝 הצג / הסתר תיאור</button>
      <div class="description collapsible"><?=bbcode_to_html($desc)?></div>
    <?php else: ?>
      <div class="description"><em>אין תיאור</em></div>
    <?php endif; ?>
    <div class="count">🎞️ <?=$c['total_items']?> פוסטרים</div>
  </div>
<?php } ?>
