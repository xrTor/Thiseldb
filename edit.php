<?php
require_once 'server.php';
include 'header.php';

/**
 * Edit Poster — Full Page (single file)
 * ללא OMDb; TVDb רק לסדרות; תצוגת מקדימה בתחתית (ימין, מסודר);
 * chips לשדות רשימה; select אוטומטי ב-image_url/trailer_url; כפתורים ממורכזים למעלה/למטה;
 * בדיקת ייחודיות imdb_id (מלבד העצמי); רענון user_tags + poster_languages;
 * כל הקלטים LTR/left, חוץ מ-title_he ו-overview_he שהם RTL/right; ללא original_title.
 */

$poster_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($poster_id <= 0) { echo "<p>❌ מזהה פוסטר לא תקין</p>"; include 'footer.php'; exit; }

// סוגים (לזיהוי series/miniseries)
$types=[]; $resT=$conn->query("SELECT id,icon,label_he,code,sort_order,image FROM poster_types ORDER BY sort_order,id");
while($r=$resT->fetch_assoc()) $types[]=$r;
$typeCodeById=[]; foreach($types as $t) $typeCodeById[(int)$t['id']]=trim((string)$t['code']);

// ברירת מחדל
$data=[
  'type_id'=>3,'is_tv'=>0,
  'title_en'=>'','title_he'=>'','year'=>'',
  'imdb_id'=>'','tmdb_url'=>'','tvdb_url'=>'',
  'imdb_rating'=>'','imdb_votes'=>'','mc_score'=>'','mc_url'=>'','rt_score'=>'','rt_url'=>'',
  'image_url'=>'','trailer_url'=>'',
  'plot'=>'','plot_he'=>'','overview_en'=>'','overview_he'=>'',
  'directors'=>'','writers'=>'','producers'=>'','composers'=>'','cinematographers'=>'','cast'=>'',
  'genres'=>'','languages'=>'','countries'=>'','networks'=>'',
  'runtime'=>'','seasons_count'=>'','episodes_count'=>'',
  'has_subtitles'=>0,'is_dubbed'=>0,
  'user_tags'=>''
];

// טעינת פוסטר
$st=$conn->prepare("SELECT * FROM posters WHERE id=?"); $st->bind_param("i",$poster_id); $st->execute();
$rs=$st->get_result(); $cur=$rs->fetch_assoc(); $st->close();
if(!$cur){ echo "<p>❌ לא נמצא פוסטר עם מזהה זה</p>"; include 'footer.php'; exit; }
foreach($data as $k=>$v){ if(array_key_exists($k,$cur)) $data[$k]=(string)$cur[$k]; }
$data['type_id']=isset($cur['type_id'])?(int)$cur['type_id']:$data['type_id'];
$data['is_tv']=isset($cur['is_tv'])?(int)$cur['is_tv']:$data['is_tv'];

// poster_languages
$selected_flags=[]; $rf=$conn->prepare("SELECT lang_code FROM poster_languages WHERE poster_id=?");
$rf->bind_param("i",$poster_id); $rf->execute(); $rfr=$rf->get_result();
while($row=$rfr->fetch_assoc()){ $code=trim((string)$row['lang_code']); if($code!=='') $selected_flags[]=$code; }
$rf->close();

// user_tags
$tags_arr=[]; $rt=$conn->prepare("SELECT genre FROM user_tags WHERE poster_id=?"); $rt->bind_param("i",$poster_id);
$rt->execute(); $rtr=$rt->get_result(); while($row=$rtr->fetch_assoc()){ $g=trim((string)$row['genre']); if($g!=='') $tags_arr[]=$g; }
$rt->close(); $data['user_tags']=implode(', ',$tags_arr);

$message='';

// שמירה
if($_SERVER['REQUEST_METHOD']==='POST'){
  foreach(['has_subtitles','is_dubbed'] as $cb) $data[$cb]=!empty($_POST[$cb])?1:0;
  foreach([
    'title_en','title_he','year','imdb_id','tmdb_url','tvdb_url','mc_url','rt_url','image_url','trailer_url',
    'plot','plot_he','overview_en','overview_he','directors','writers','producers','composers',
    'cinematographers','cast','genres','languages','countries','networks','user_tags'
  ] as $f){ $data[$f]=trim((string)($_POST[$f]??'')); }
  foreach(['type_id','imdb_votes','mc_score','runtime','seasons_count','episodes_count'] as $f){
    $val=$_POST[$f]??''; $data[$f]=($val===''?null:(int)$val);
  }
  $data['imdb_rating']=trim((string)($_POST['imdb_rating']??'')); $data['rt_score']=trim((string)($_POST['rt_score']??''));

  $selected_flags=(isset($_POST['lang_flags'])&&is_array($_POST['lang_flags']))?$_POST['lang_flags']:[];
  if($data['imdb_id']!==''){
    $st=$conn->prepare("SELECT id FROM posters WHERE imdb_id=? AND id!=? LIMIT 1"); $st->bind_param("si",$data['imdb_id'],$poster_id);
    $st->execute(); $st->store_result(); if($st->num_rows>0){ $message="❌ פוסטר אחר כבר קיים עם אותו IMDb ID."; } $st->close();
  }

  if(!$message){
    $typeId=(int)$data['type_id']; $code=strtolower(trim((string)($typeCodeById[$typeId]??'')));
    $data['is_tv']=in_array($code,['series','miniseries'])?1:0;

    $legacy_genre=$data['genres']; $legacy_actors=$data['cast'];
    $runtime_minutes=($data['runtime']!==null && $data['runtime']!==''?(int)$data['runtime']:null);

    $cols=[
      'type_id'=>['i',$data['type_id']],'is_tv'=>['i',$data['is_tv']],
      'title_en'=>['s',$data['title_en']],'title_he'=>['s',$data['title_he']],'year'=>['s',$data['year']],
      'imdb_id'=>['s',$data['imdb_id']],'tmdb_url'=>['s',$data['tmdb_url']],'tvdb_url'=>['s',$data['tvdb_url']],
      'imdb_rating'=>['s',$data['imdb_rating']],'imdb_votes'=>['i',($data['imdb_votes']===''||$data['imdb_votes']===null?null:(int)$data['imdb_votes'])],
      'mc_score'=>['i',($data['mc_score']===''||$data['mc_score']===null?null:(int)$data['mc_score'])],
      'mc_url'=>['s',$data['mc_url']],'rt_score'=>['s',$data['rt_score']],'rt_url'=>['s',$data['rt_url']],
      'image_url'=>['s',$data['image_url']],'trailer_url'=>['s',$data['trailer_url']],
      'plot'=>['s',$data['plot']],'plot_he'=>['s',$data['plot_he']],'overview_en'=>['s',$data['overview_en']],'overview_he'=>['s',$data['overview_he']],
      'directors'=>['s',$data['directors']],'writers'=>['s',$data['writers']],'producers'=>['s',$data['producers']],
      'composers'=>['s',$data['composers']],'cinematographers'=>['s',$data['cinematographers']],'cast'=>['s',$data['cast']],
      'genres'=>['s',$data['genres']],'genre'=>['s',$legacy_genre],'actors'=>['s',$legacy_actors],
      'languages'=>['s',$data['languages']],'countries'=>['s',$data['countries']],'networks'=>['s',$data['networks']],
      'runtime'=>['i',($data['runtime']===''||$data['runtime']===null?null:(int)$data['runtime'])],
      'runtime_minutes'=>['i',$runtime_minutes],
      'seasons_count'=>['i',($data['seasons_count']===''||$data['seasons_count']===null?0:(int)$data['seasons_count'])],
      'episodes_count'=>['i',($data['episodes_count']===''||$data['episodes_count']===null?0:(int)$data['episodes_count'])],
      'has_subtitles'=>['i',(int)$data['has_subtitles']],'is_dubbed'=>['i',(int)$data['is_dubbed']],
    ];

    $setParts=[]; $typesStr=''; $values=[];
    foreach($cols as $col=>$meta){ $setParts[]="$col=?"; $typesStr.=$meta[0]; $values[]=$meta[1]; }
    $typesStr.='i'; $values[]=$poster_id;
    $sql="UPDATE posters SET ".implode(',',$setParts)." WHERE id=?"; $stmt=$conn->prepare($sql);
    $stmt->bind_param($typesStr,...$values); $ok=$stmt->execute();
    if(!$ok){ $message="❌ שגיאה בעדכון: ".htmlspecialchars($stmt->error,ENT_QUOTES,'UTF-8'); }
    $stmt->close();

    if($ok){
      // user_tags — refresh
      $conn->query("DELETE FROM user_tags WHERE poster_id={$poster_id}");
      $tags=preg_split('~[,\n]+~u',(string)$data['user_tags']);
      $tags=array_values(array_filter(array_map('trim',$tags), function($x){ return $x!==''; }));
      foreach($tags as $tag){
        $ins=$conn->prepare("INSERT INTO user_tags (poster_id,genre) VALUES (?,?)");
        $ins->bind_param("is",$poster_id,$tag); $ins->execute(); $ins->close();
      }

      // poster_languages — refresh
      $conn->query("DELETE FROM poster_languages WHERE poster_id={$poster_id}");
      if(!empty($selected_flags)){
        $ins=$conn->prepare("INSERT INTO poster_languages (poster_id,lang_code) VALUES (?,?)");
        foreach($selected_flags as $lang){ $lc=trim((string)$lang); if($lc!==''){ $ins->bind_param("is",$poster_id,$lc); $ins->execute(); } }
        $ins->close();
      }

      // ריענון
      $st=$conn->prepare("SELECT * FROM posters WHERE id=?"); $st->bind_param("i",$poster_id); $st->execute();
      $rs=$st->get_result(); if($row=$rs->fetch_assoc()){
        foreach($data as $k=>$v) if(array_key_exists($k,$row)) $data[$k]=(string)$row[$k];
        $data['type_id']=isset($row['type_id'])?(int)$row['type_id']:$data['type_id'];
        $data['is_tv']=isset($row['is_tv'])?(int)$row['is_tv']:$data['is_tv'];
      }
      $st->close();

      $safe_url='poster.php?id='.(int)$poster_id;
      $message='✅ הפוסטר עודכן בהצלחה! — <a class="btn-link" href="'.htmlspecialchars($safe_url,ENT_QUOTES,'UTF-8').'">לצפייה בפוסטר</a>';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8"><title>✏️ עריכת פוסטר</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f2f4f6;color:#222}
  .wrap{max-width:1200px;margin:24px auto}
  .card{background:#fff;border:1px solid #e3e7ef;border-radius:12px;box-shadow:0 1px 8px rgba(0,0,0,.05)}
  .hdr{padding:14px 18px;border-bottom:1px solid #e9edf5;display:flex;align-items:center;justify-content:center;gap:18px}
  .hdr h2{margin:0;font-size:20px}
  .actions-bar{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:8px 0}
  .btn{appearance:none;border:1px solid #cfd7e6;background:#fff;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:13px}
  .btn.primary{background:linear-gradient(90deg,#53c1f8,#2274bb);color:#fff;border:none}
  .btn-link{text-decoration:underline}
  .msg-ok{background:#e9ffe7;border:1px solid #bfe8b6;color:#25674b;padding:8px 10px;border-radius:8px;margin:12px 18px}
  .msg-err{background:#ffe9e9;border:1px solid #efb3b3;color:#7a1f1f;padding:8px 10px;border-radius:8px;margin:12px 18px}
  .page-pad{padding:16px 18px}
  .section{border-top:1px dashed #e4e8f2;padding-top:16px;margin-top:16px}
  .section:first-child{border-top:0;padding-top:0;margin-top:0}
  .section h3{margin:0 0 8px;font-size:16px;color:#0e2a63}
  label{display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#314056}
  input[type="text"],input[type="number"],textarea{width:100%;padding:7px 9px;font-size:13px;border:1px solid #cfd7e6;border-radius:8px;background:#fbfdff;resize:vertical;direction:ltr;text-align:left}
  textarea{min-height:64px}
  /* חריגים: עברית RTL */
  input[name="title_he"]{direction:rtl;text-align:right}
  textarea[name="overview_he"]{direction:rtl;text-align:right}
  .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 22px}
  .full{grid-column:1/-1}
  .types{display:flex;flex-wrap:wrap;gap:10px}
  .types label{background:#eef5ff;border:1px solid #bfd7ff;border-radius:999px;padding:6px 10px;cursor:pointer;font-weight:500;display:flex;align-items:center;gap:8px}
  .types input[type="radio"]{margin-left:5px}
  .type-img{height:22px;width:auto;border-radius:6px;display:inline-block}
  .chips{margin-top:6px}
  .chip{display:inline-block;padding:2px 10px;background:#eef3fb;color:#295687;border:1px solid #d7e3f9;border-radius:999px;font-size:12px;margin:2px 2px 0 0}
  .preview-box{display:block;margin-top:8px}
  .img-prev{width:220px;height:auto;border:1px solid #e2e6ef;border-radius:8px;background:#f5f7fb;display:block}
  .trailer-frame{width:100%;max-width:640px;aspect-ratio:16/9;border:0;border-radius:8px;background:transparent}
  .series-only{display:none}.series-only.show{display:block}
  /* תצוגת מקדימה בתחתית, מסודרת וימנית */
  .preview-panel{margin:20px 0 10px;border:1px dashed #d6deee;background:#fafcff;border-radius:12px;padding:12px;text-align:right}
  .preview-panel h3{margin:0 0 10px;font-size:15px;color:#0e2a63;text-align:right}
  .pv-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 18px}
  .pv-item label{font-size:12px;color:#51607a;display:block;margin-bottom:3px}
  .pv-item .val{font-size:13px;color:#0d2a63}
  .pv-links a{margin-inline-start:8px;font-size:12px;color:#0d5dc1;text-decoration:underline}
.btn-toggle {
  display:inline-block;
  margin-top:6px;
  padding:6px 12px;
  border:1px solid #bbb;
  background:#f4f7fb;
  border-radius:6px;
  cursor:pointer;
  font-size:13px;
}
.btn-toggle:hover { background:#e3e8f2; }

</style>
<script>
  function escapeHtml(s){return (s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function ytId(url){var m=(url||'').match(/(?:youtu\.be\/|v=)([A-Za-z0-9_-]{6,})/i);return m?m[1]:'';}
  function vimeoId(url){var m=(url||'').match(/vimeo\.com\/(\d+)/i);return m?m[1]:'';}
  function makeTrailerEmbed(url){
    url=(url||'').trim();var id,html='';
    if(!url) return '<img src="images/no-trailer.png" alt="No trailer" class="trailer-frame">';
    if(id=ytId(url)) html='<iframe class="trailer-frame" loading="lazy" allowfullscreen src="https://www.youtube.com/embed/'+id+'?rel=0"></iframe>';
    else if(id=vimeoId(url)) html='<iframe class="trailer-frame" loading="lazy" allow="fullscreen; picture-in-picture" src="https://player.vimeo.com/video/'+id+'"></iframe>';
    else if(/\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) html='<video class="trailer-frame" controls src="'+url.replace(/"/g,'&quot;')+'"></video>';
    else html='<a target="_blank" rel="noopener" href="'+url.replace(/"/g,'&quot;')+'">פתח טריילר בקישור חיצוני</a>';
    return html;
  }
  function linkOrDash(url,label){url=(url||'').trim();if(!url)return'<span style="color:#888">—</span>';var s=escapeHtml(url),t=label?escapeHtml(label):s;return'<a href="'+s+'" target="_blank" rel="noopener">'+t+'</a>';}
  function buildImdbTitleUrl(imdbId){imdbId=(imdbId||'').trim();return imdbId?('https://www.imdb.com/title/'+encodeURIComponent(imdbId)+'/'):'';}

  function renderChips(fieldId){
    var input=document.getElementById(fieldId),box=document.getElementById(fieldId+'_chips'); if(!input||!box) return;
    var parts=(input.value||'').split(/,|\n/).map(function(s){return s.trim();}).filter(function(x){return x;});
    box.innerHTML=parts.map(function(p){return'<span class="chip">'+escapeHtml(p)+'</span>';}).join(' ');
  }

  function setPreview(id,html){var el=document.getElementById(id); if(el) el.innerHTML=html||'<span style="color:#888">—</span>';}

  function refreshPreview(){
    var titleEn=document.querySelector('input[name="title_en"]').value;
    var titleHe=document.querySelector('input[name="title_he"]').value;
    var year=document.querySelector('input[name="year"]').value;
    var imdbId=document.querySelector('input[name="imdb_id"]').value;
    var tmdbUrl=document.querySelector('input[name="tmdb_url"]').value;
    var tvdbInp=document.querySelector('input[name="tvdb_url"]'); var tvdbUrl=tvdbInp?tvdbInp.value:'';
    var rtUrl=document.querySelector('input[name="rt_url"]').value;
    var mcUrl=document.querySelector('input[name="mc_url"]').value;

    setPreview('pv_title_en',escapeHtml(titleEn));
    setPreview('pv_title_he',escapeHtml(titleHe));
    setPreview('pv_year',escapeHtml(year));

    var links='',imdbLink=buildImdbTitleUrl(imdbId);
    if(imdbLink) links+=linkOrDash(imdbLink,'IMDb');
    if(tmdbUrl) links+=' '+linkOrDash(tmdbUrl,'TMDb');
    if(tvdbUrl) links+=' '+linkOrDash(tvdbUrl,'TVDb');
    if(mcUrl) links+=' '+linkOrDash(mcUrl,'Metacritic');
    if(rtUrl) links+=' '+linkOrDash(rtUrl,'Rotten');
    if(!links) links='<span style="color:#888">—</span>';
    setPreview('pv_links',links);

    ['genres','cast','directors','writers','producers','composers','cinematographers','languages','countries','user_tags','networks'].forEach(function(fid){
      var inp=document.getElementById(fid),out=document.getElementById('pv_'+fid);
      if(!inp||!out) return;
      var parts=(inp.value||'').split(/,|\n/).map(function(s){return s.trim();}).filter(function(x){return x;});
      out.innerHTML=parts.length?parts.map(function(p){return'<span class="chip">'+escapeHtml(p)+'</span>';}).join(' '):'<span style="color:#888">—</span>';
    });
  }

  function isSeriesCode(code){if(!code)return false;code=(code+'').toLowerCase();return(code==='series'||code==='miniseries');}
  function currentTypeCode(){var r=document.querySelector('input[name="type_id"]:checked');return r?(r.dataset.code||''):'';}
  function applySeriesToggle(){var code=currentTypeCode(),block=document.getElementById('seriesBlock');if(!block)return; if(isSeriesCode(code)) block.classList.add('show'); else block.classList.remove('show'); refreshPreview();}

  function updateImagePreview(){var input=document.querySelector('input[name="image_url"]'),img=document.getElementById('imgPreview');if(!input||!img)return;var v=(input.value||'').trim();img.src=v||'images/no-poster.png';}
  function updateTrailerPreview(){var input=document.querySelector('input[name="trailer_url"]'),slot=document.getElementById('trailerFrameBox');if(!input||!slot)return;slot.innerHTML=makeTrailerEmbed((input.value||'').trim());}

  function wireHoverSelect(){
    ['image_url','trailer_url'].forEach(function(name){
      var el=document.querySelector('input[name="'+name+'"]'); if(!el||el.dataset.boundHover)return;
      el.addEventListener('mouseover',function(){try{this.select();}catch(e){}});
      el.addEventListener('focus',function(){try{this.select();}catch(e){}});
      el.dataset.boundHover='1';
    });
  }

  function rehydrate(){
    ['genres','cast','directors','writers','producers','composers','cinematographers','languages','countries','user_tags','networks'].forEach(function(fid){
      var el=document.getElementById(fid); renderChips(fid);
      if(el && !el.dataset.bound){ el.addEventListener('input',function(){ renderChips(fid); refreshPreview(); }); el.dataset.bound='1'; }
    });
    document.querySelectorAll('input[name="type_id"]').forEach(function(r){ if(!r.dataset.bound){ r.addEventListener('change',applySeriesToggle); r.dataset.bound='1'; } });
    applySeriesToggle();
    var imgInput=document.querySelector('input[name="image_url"]'); if(imgInput && !imgInput.dataset.bound){ imgInput.addEventListener('input',updateImagePreview); imgInput.dataset.bound='1'; }
    var trInput=document.querySelector('input[name="trailer_url"]'); if(trInput && !trInput.dataset.bound){ trInput.addEventListener('input',updateTrailerPreview); trInput.dataset.bound='1'; }
    wireHoverSelect(); updateImagePreview(); updateTrailerPreview(); refreshPreview();
  }
  document.addEventListener('DOMContentLoaded',rehydrate);
  function toggleBox(id){
  var el=document.getElementById(id);
  if(!el) return;
  if(el.style.display==='none') el.style.display='block';
  else el.style.display='none';
}

</script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="hdr"><h2>✏️ עריכת פוסטר</h2></div>

    <form method="post" id="editForm" autocomplete="off" class="page-pad">
      <!-- כפתורים עליונים -->
      <div class="actions-bar">
        <button type="submit" class="btn primary">💾 עדכן</button>
        <button type="reset" class="btn" onclick="setTimeout(rehydrate,0)">איפוס</button>
        <button type="button" class="btn" onclick="location.href='poster.php?id=<?= (int)$poster_id ?>'">⤴️ חזרה לפוסטר</button>
      </div>

      <?php if($message): ?>
        <div class="<?= (strpos($message,'✅')===0)?'msg-ok':'msg-err' ?>"><?= $message ?></div>
      <?php endif; ?>

      <!-- סוג פוסטר -->
      <div class="section">
        <h3>סוג פוסטר</h3>
        <div class="types">
          <?php foreach($types as $t):
            $img=trim((string)($t['image']??'')); $imgUrl=$img!==''?'images/types/'.htmlspecialchars($img,ENT_QUOTES,'UTF-8'):'';
          ?>
          <label class="type-option">
            <input type="radio" name="type_id" value="<?= (int)$t['id'] ?>"
                   data-code="<?= htmlspecialchars($t['code']??'',ENT_QUOTES,'UTF-8') ?>"
                   <?= ((int)$data['type_id']===(int)$t['id'])?'checked':'' ?>>
            <?php if($imgUrl): ?>
              <img src="<?= $imgUrl ?>" alt="" width="28" height="28" class="type-img"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <?php endif; ?>
            <span class="type-icon" style="display:<?= $imgUrl?'none':'inline-block' ?>;font-size:18px;">
              <?= htmlspecialchars($t['icon']??'',ENT_QUOTES,'UTF-8') ?>
            </span>
            <span><?= htmlspecialchars(($t['label_he']??''),ENT_QUOTES,'UTF-8') ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- קישורים חיצוניים וזיהוי -->
      <div class="section">
        <h3>קישורים חיצוניים וזיהוי</h3>
        <div class="grid">
          <div>
            <label>IMDb ID</label>
            <input type="text" name="imdb_id" id="imdb_id" value="<?= htmlspecialchars($data['imdb_id']) ?>">
          </div>
          <div>
            <label>TMDb URL</label>
            <input type="text" name="tmdb_url" value="<?= htmlspecialchars($data['tmdb_url']) ?>">
          </div>
        </div>
      </div>

      <!-- כותרות/זיהוי -->
      <div class="section">
        <h3>כותרות וזיהוי</h3>
        <div class="grid">
          <div>
            <label>שם באנגלית</label>
            <input type="text" name="title_en" value="<?= htmlspecialchars($data['title_en']) ?>">
          </div>
          <div>
            <label>שם בעברית</label>
            <input type="text" name="title_he" value="<?= htmlspecialchars($data['title_he']) ?>">
          </div>
          <div>
            <label>שנה</label>
            <input type="text" name="year" value="<?= htmlspecialchars($data['year']) ?>">
          </div>
          <div>
            <label>אורך (דקות)</label>
            <input type="number" name="runtime" value="<?= htmlspecialchars($data['runtime']) ?>">
          </div>
        </div>
      </div>

      <!-- דירוגים -->
      <div class="section">
        <h3>דירוגים</h3>
        <div class="grid">
          <div>
            <label>IMDb Rating</label>
            <input type="text" name="imdb_rating" value="<?= htmlspecialchars($data['imdb_rating']) ?>">
          </div>
          <div>
            <label>IMDb Votes</label>
            <input type="number" name="imdb_votes" value="<?= htmlspecialchars($data['imdb_votes']) ?>">
          </div>
          <div>
            <label>Metacritic URL</label>
            <input type="text" name="mc_url" value="<?= htmlspecialchars($data['mc_url']) ?>">
          </div>
          <div>
            <label>Metacritic (מספר)</label>
            <input type="number" name="mc_score" value="<?= htmlspecialchars($data['mc_score']) ?>">
          </div>
          <div>
            <label>Rotten Tomatoes URL</label>
            <input type="text" name="rt_url" value="<?= htmlspecialchars($data['rt_url']) ?>">
          </div>
          <div>
            <label>Rotten Tomatoes (אחוזים)</label>
            <input type="text" name="rt_score" value="<?= htmlspecialchars($data['rt_score']) ?>">
          </div>
        </div>
      </div>

      <!-- תקצירים -->
      <div class="section">
        <h3>תקציר/עלילה</h3>
        <div class="grid">
          <div class="full">
            <label>תקציר אנגלית</label>
            <textarea name="overview_en"><?= htmlspecialchars($data['overview_en']) ?></textarea>
          </div>
          <div class="full">
            <label>תקציר עברית</label>
            <textarea name="overview_he"><?= htmlspecialchars($data['overview_he']) ?></textarea>
          </div>
        </div>
      </div>

      <!-- אנשי צוות -->
      <div class="section">
        <h3>אנשי צוות</h3>
        <div class="grid">
          <div><label>במאים:</label><input type="text" name="directors" id="directors" value="<?= htmlspecialchars($data['directors']) ?>"><div id="directors_chips" class="chips"></div></div>
          <div><label>תסריטאים:</label><input type="text" name="writers" id="writers" value="<?= htmlspecialchars($data['writers']) ?>"><div id="writers_chips" class="chips"></div></div>
          <div><label>מפיקים:</label><input type="text" name="producers" id="producers" value="<?= htmlspecialchars($data['producers']) ?>"><div id="producers_chips" class="chips"></div></div>
          <div><label>מלחינים:</label><input type="text" name="composers" id="composers" value="<?= htmlspecialchars($data['composers']) ?>"><div id="composers_chips" class="chips"></div></div>
          <div><label>צלמים:</label><input type="text" name="cinematographers" id="cinematographers" value="<?= htmlspecialchars($data['cinematographers']) ?>"><div id="cinematographers_chips" class="chips"></div></div>
          <div><label>שחקנים</label><input type="text" name="cast" id="cast" value="<?= htmlspecialchars($data['cast']) ?>"><div id="cast_chips" class="chips"></div></div>
        </div>
      </div>

      <!-- רשימות -->
      <div class="section">
        <h3>רשימות (פסיקים בין ערכים)</h3>
        <div class="grid">
          <div><label>ז׳אנרים</label><input type="text" name="genres" id="genres" value="<?= htmlspecialchars($data['genres']) ?>"><div id="genres_chips" class="chips"></div></div>
          <div><label>תגיות משתמש</label><input type="text" name="user_tags" id="user_tags" value="<?= htmlspecialchars($data['user_tags']) ?>"><div id="user_tags_chips" class="chips"></div></div>
          <div><label>שפות</label><input type="text" name="languages" id="languages" value="<?= htmlspecialchars($data['languages']) ?>"><div id="languages_chips" class="chips"></div></div>
          <div><label>מדינות</label><input type="text" name="countries" id="countries" value="<?= htmlspecialchars($data['countries']) ?>"><div id="countries_chips" class="chips"></div></div>
          
        </div>
      </div>

      <!-- סדרות בלבד -->
      <?php $tcode=$typeCodeById[(int)$data['type_id']]??''; $lc=strtolower(trim((string)$tcode)); $isSeriesNow=($lc==='series'||$lc==='miniseries'); ?>
      <div class="section series-only <?= $isSeriesNow?'show':'' ?>" id="seriesBlock">
        <h3>פרטי סדרה</h3>
        <div class="grid">
          <div><label>TVDb URL</label><input type="text" name="tvdb_url" value="<?= htmlspecialchars($data['tvdb_url']) ?>"></div>
          <div>
  <label>רשתות</label>
  <input type="text" name="networks" id="networks" value="<?= htmlspecialchars($data['networks']) ?>">
  <div id="networks_chips" class="chips"></div>
</div>

          <div><label>מספר עונות</label><input type="number" name="seasons_count" value="<?= htmlspecialchars($data['seasons_count']) ?>"></div>
          <div><label>מספר פרקים</label><input type="number" name="episodes_count" value="<?= htmlspecialchars($data['episodes_count']) ?>"></div>
        </div>
      </div>

      <!-- מדיה -->
<div class="section">
  <h3>מדיה</h3>
  <div class="grid">
    <div>
      <label>תמונה (Image URL)</label>
      <input type="text" name="image_url" value="<?= htmlspecialchars($data['image_url']) ?>" onmouseover="this.select()">
      <div id="imgPrevBox" class="preview-box">
        <img id="imgPreview" class="img-prev" alt="Preview" src="<?= $data['image_url'] ? htmlspecialchars($data['image_url']) : 'images/no-poster.png' ?>">
      </div>
      <!-- כפתור חדש -->
      <button type="button" class="btn-toggle" onclick="toggleBox('imgPrevBox')">הצג/הסתר תמונה</button>
    </div>
    <div>
      <label>טריילר (YouTube/Vimeo/MP4)</label>
      <input type="text" name="trailer_url" value="<?= htmlspecialchars($data['trailer_url']) ?>" onmouseover="this.select()">
      <div id="trailerPrevBox" class="preview-box">
        <div id="trailerFrameBox">
          <?php
            $tu = trim((string)$data['trailer_url']);
            if ($tu !== '' && preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{6,})~',$tu,$m)) {
              echo '<iframe class="trailer-frame" loading="lazy" allowfullscreen src="https://www.youtube.com/embed/'.htmlspecialchars($m[1],ENT_QUOTES,'UTF-8').'"></iframe>';
            } elseif ($tu !== '' && preg_match('~vimeo\.com/(\d+)~i',$tu,$m)) {
              echo '<iframe class="trailer-frame" loading="lazy" allow="fullscreen; picture-in-picture" src="https://player.vimeo.com/video/'.htmlspecialchars($m[1],ENT_QUOTES,'UTF-8').'"></iframe>';
            } elseif ($tu !== '' && preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i',$tu)) {
              echo '<video class="trailer-frame" controls src="'.htmlspecialchars($tu,ENT_QUOTES,'UTF-8').'"></video>';
            } elseif ($tu!=='') {
              echo '<a target="_blank" rel="noopener" href="'.htmlspecialchars($tu,ENT_QUOTES,'UTF-8').'">פתח טריילר בקישור חיצוני</a>';
            } else {
              echo '<img src="images/no-trailer.png" alt="No trailer" class="trailer-frame">';
            }
          ?>
        </div>
      </div>
      <!-- כפתור חדש -->
      <button type="button" class="btn-toggle" onclick="toggleBox('trailerPrevBox')">הצג/הסתר טריילר</button>
    </div>
  </div>
</div>

      <!-- דגלים -->
      <div class="section">
        <h3>דגלים (poster_languages)</h3>
        <div class="flags-box">
          <?php
            ob_start(); include 'flags.php'; $flags_html=ob_get_clean();
            // החלפה לשם שדה אחר (להימנע מהתנגשות עם שדה הטקסט "languages")
            $flags_html = str_replace('name="languages[]"','name="lang_flags[]"',$flags_html);
            $flags_html = str_replace("name='languages[]'","name='lang_flags[]'",$flags_html);
            // סימון checked לדגלים שנשמרו
            if(!empty($selected_flags)){
              foreach($selected_flags as $lc2){
                $lc2=trim((string)$lc2); if($lc2==='') continue;
                $pat='~(<input\b[^>]*\bname=(["\'])lang_flags\[\]\2[^>]*\bvalue=(["\'])'.preg_quote($lc2,'~').'\3[^>]*)(?=>)~i';
                $flags_html=preg_replace($pat,'$1 checked',$flags_html);
              }
            }
            echo $flags_html;
          ?>
        </div>
      </div>

      <!-- שדות מוסתרים -->
      <input type="hidden" name="plot" value="<?= htmlspecialchars($data['plot']) ?>">
      <input type="hidden" name="plot_he" value="<?= htmlspecialchars($data['plot_he']) ?>">

      <!-- כפתורי שמירה תחתונים -->
      <div class="section">
        <div class="actions-bar">
          <button type="submit" class="btn primary">💾 עדכן</button>
          <button type="reset" class="btn" onclick="setTimeout(rehydrate,0)">איפוס</button>
          <button type="button" class="btn" onclick="location.href='poster.php?id=<?= (int)$poster_id ?>'">⤴️ חזרה לפוסטר</button>
        </div>
      </div>

      <!-- תצוגת מקדימה בתחתית -->
      <div class="preview-panel">
        <h3>תצוגה מקדימה</h3>
        <div class="pv-grid">
          <div class="pv-item"><label>שם באנגלית</label><div id="pv_title_en" class="val"></div></div>
          <div class="pv-item"><label>שם בעברית</label><div id="pv_title_he" class="val"></div></div>
          <div class="pv-item"><label>שנה</label><div id="pv_year" class="val"></div></div>
          <div class="pv-item"><label>קישורים</label><div id="pv_links" class="val pv-links"></div></div>

          <div class="pv-item full"><label>ז׳אנרים</label><div id="pv_genres" class="val"></div></div>
          <div class="pv-item full"><label>שחקנים</label><div id="pv_cast" class="val"></div></div>
          <div class="pv-item"><label>במאים</label><div id="pv_directors" class="val"></div></div>
          <div class="pv-item"><label>תסריטאים</label><div id="pv_writers" class="val"></div></div>
          <div class="pv-item"><label>מפיקים</label><div id="pv_producers" class="val"></div></div>
          <div class="pv-item"><label>מלחינים</label><div id="pv_composers" class="val"></div></div>
          <div class="pv-item full"><label>צלמים</label><div id="pv_cinematographers" class="val"></div></div>
          <div class="pv-item"><label>שפות</label><div id="pv_languages" class="val"></div></div>
          <div class="pv-item"><label>מדינות</label><div id="pv_countries" class="val"></div></div>
          <div class="pv-item full"><label>תגיות משתמש</label><div id="pv_user_tags" class="val"></div></div>
        </div>
      </div>

    </form>
  </div>
</div>
</body>
</html>
<?php include 'footer.php'; ?>
