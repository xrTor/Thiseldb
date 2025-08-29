<?php
/****************************************************
 * imdb.php — פרטי כותר מרוכזים (RTL, עברית)
 * BUILD: v2025-08-28-b15 "FULL VERSION: Corrected Network Logic (API-only) & Full Functions"
 ****************************************************/
set_time_limit(3000000);

mb_internal_encoding('UTF-8');
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

/* ===== Keys ===== */
$TMDB_KEY     = '931b94936ba364daf0fd91fb38ecd91e';
$RAPIDAPI_KEY = 'f5d4bd03c8msh29a2dc12893f4bfp157343jsn2b5bfcad5ae1'; // IMDb rating/votes בלבד
$TVDB_KEY     = '1c93003f-ab80-4baf-b5c4-58c7b96494a2';               // TheTVDB v4
$OMDB_KEY     = 'f7e4ae0b';                                         // OMDb — RT/MC

/* ===== מפות hardcode ===== */
$HARDCODE_TVDB = [
  // 'tt1234567' => 12345,
];

/* ===== IMDb scraper lib (אצלך) ===== */
include_once __DIR__ . '/imdb.class.php';

/* ===== קלט: מזהי tt בלבד (ללא "URL מלוכלך") ===== */
$imdbIDs = [];
if (!empty($_GET['id'])) {
  foreach (preg_split('~[,;\s]+~', $_GET['id'], -1, PREG_SPLIT_NO_EMPTY) as $tt) {
    if (preg_match('~^tt\d{6,10}$~', $tt)) $imdbIDs[] = $tt;
  }
}
if (!$imdbIDs) $imdbIDs = [];

/* ===== מגבלות תצוגה ===== */
$CAST_LIMIT = 100000;

/* ==================================================
   Utilities (guard) — כל הפונקציות, סוגריים מאוזנים
   ================================================== */
if (!defined('IMDB_UNIFIED_FUNCS')) {
define('IMDB_UNIFIED_FUNCS', 1);

if (!function_exists('extract_imdb_full_plot_from_summary_page')) {
  function extract_imdb_full_plot_from_summary_page($tt){
    $html = http_get("https://www.imdb.com/title/" . rawurlencode($tt) . "/plotsummary");
    if (!$html) return null;
    
    if (preg_match('~<ul[^>]*data-testid="plot-summaries-content"[^>]*>(.*?)</ul>~is', $html, $ul_match)) {
      if (preg_match('~<li[^>]*class="[^"]*ipc-html-content-inner-div[^"]*"[^>]*>(.*?)</li>~is', $ul_match[1], $li_match)) {
        $plot = trim(strip_tags($li_match[1]));
        if ($plot !== '' && stripos($plot, 'It looks like we don\'t have a Synopsis') === false) {
          return $plot;
        }
      }
    }
    return null;
  }
}
/* ========= Helpers ========= */
if (!function_exists('flatten_strings')) {
  function flatten_strings($v){$o=[];$st=[$v];while($st){$c=array_pop($st);if(is_array($c)){foreach($c as $x)$st[]=$x;continue;}if(is_object($c))$c=(string)$c;$t=trim((string)$c);if($t!=='')$o[]=$t;}return $o;}
}
if (!function_exists('safeHtml')) {
  function safeHtml($v){if(is_array($v)||is_object($v))return htmlspecialchars(implode(', ',flatten_strings($v)),ENT_QUOTES,'UTF-8');return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
}
if (!function_exists('safeJoin')) {
  function safeJoin($arr,$sep=', '){$vals=array_map(fn($t)=>trim((string)$t),flatten_strings($arr));$vals=array_values(array_filter($vals,fn($x)=>$x!==''));return htmlspecialchars(implode($sep,$vals),ENT_QUOTES,'UTF-8');}
}
if (!function_exists('H')) {
  function H($v){return (is_array($v)||is_object($v))?safeJoin($v):safeHtml($v);}
}
if (!function_exists('stripAllTags')) {
  function stripAllTags($v){return is_array($v)?array_map('stripAllTags',$v):trim(strip_tags((string)$v));}
}
if (!function_exists('imdb_get')) {
  function imdb_get($IMDB,$method,...$args){try{if(method_exists($IMDB,$method))return $IMDB->$method(...$args);}catch(Throwable $e){}return null;}
}
if (!function_exists('year_only')) {
  function year_only($s){$s=(string)$s;return(preg_match('~^\d{4}~',$s,$m)?$m[0]:null);}
}
if (!function_exists('first_nonempty')) {
  function first_nonempty(...$vals){foreach($vals as $v){if(is_array($v)){if(!empty($v))return $v;}else{$t=trim((string)$v);if($t!=='')return $t;}}return null;}
}
if (!function_exists('split_tokens')) {
  function split_tokens($s){return array_map('trim',preg_split('~\s*[,/;]\s*~u',(string)$s,-1,PREG_SPLIT_NO_EMPTY)?:[]);}
}
if (!function_exists('is_garbage_token')) {
  function is_garbage_token($t){$t=trim((string)$t);if($t==='')return true;if(preg_match('~^n\s*/?\s*a\.?$~i',$t))return true;if(preg_match('~^(none|unknown|-|—|–)$~i',$t))return true;if(preg_match('~^\d+(\.\d+)?$~',$t))return true;if(preg_match('~^nm\d{3,9}$~i',$t))return true;if(preg_match('~^tt\d{3,9}$~i',$t))return true;if(preg_match('~^https?://~i',$t))return true;if(preg_match('~^(?:www\.)?imdb\.com~i',$t))return true;if(mb_strlen($t,'UTF-8')<=1)return true;return false;}
}
if (!function_exists('normalize_list')) {
  function normalize_list($v){$o=[];$push=function($x)use(&$o){foreach(split_tokens($x)as$p)if(!is_garbage_token($p))$o[]=$p;};if(is_array($v)){$it=new RecursiveIteratorIterator(new RecursiveArrayIterator($v));foreach($it as $x){if(!is_array($x))$push($x);}}elseif($v!==null&&$v!==false){$push($v);} $seen=[];$u=[];foreach($o as $i){$i=preg_replace('~\bnm\d{3,9}\b~i','',$i);$i=preg_replace('~https?://\S+~i','',$i);$i=preg_replace('~\b(?:www\.)?imdb\.com\S*~i','',$i);$i=trim(preg_replace('~\s{2,}~',' ',$i));if($i==='')continue;$k=mb_strtolower(preg_replace('~\s+~u',' ',$i),'UTF-8');if(!isset($seen[$k])){$seen[$k]=1;$u[]=$i;}}return $u;}
}
if (!function_exists('merge_unique_lists')) {
  function merge_unique_lists(...$lists){$seen=[];$o=[];foreach($lists as $lst){if($lst===null||$lst===false)continue;if(!is_array($lst))$lst=[$lst];foreach($lst as $it){if($it===null)continue;if(is_array($it)){$cand=$it['name']??($it['title']??($it['value']??null));$it=$cand!==null?(string)$cand:implode(' ',flatten_strings($it));}$it=trim((string)$it);if($it===''||is_garbage_token($it))continue;$it=preg_replace('~\bnm\d{3,9}\b~i','',$it);$it=preg_replace('~https?://\S+~i','',$it);$it=preg_replace('~\b(?:www\.)?imdb\.com\S*~i','',$it);$it=trim(preg_replace('~\s{2,}~',' ',$it));if($it==='')continue;$k=mb_strtolower(preg_replace('~\s+~u',' ',$it),'UTF-8');if(!isset($seen[$k])){$seen[$k]=1;$o[]=$it;}}}return $o;}
}
if (!function_exists('clean_people_list')) {
  function clean_people_list($arr){return normalize_list($arr);}
}
if (!function_exists('soft_key')) {
  function soft_key($s){$s=mb_strtolower(trim((string)$s),'UTF-8');$s=preg_replace('~\s+~u',' ',$s);return $s;}
}

/* ========= HTTP ========= */
if (!function_exists('http_get')) {
  function http_get($url,$headers=[]){
    $ch=curl_init($url);
    $base=[
      'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Language: en-US,en;q=0.9',
      'Referer: https://www.imdb.com/',
      'Cookie: lc-main=en_US; session-id-time=2082787201l; adblk=adblk_no; optout=0'
    ];
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_FOLLOWLOCATION=>true,
      CURLOPT_TIMEOUT=>45,
      CURLOPT_ENCODING=>'',
      CURLOPT_HTTPHEADER=>array_merge($base,$headers),
    ]);
    $resp=curl_exec($ch);
    curl_close($ch);
    return $resp?:'';
  }
}
if (!function_exists('http_post_json')) {
  function http_post_json($url,$payload,$headers=[]){
    $ch=curl_init($url);
    $base=['User-Agent: Mozilla/5.0','Content-Type: application/json','Accept: application/json'];
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_FOLLOWLOCATION=>true,
      CURLOPT_TIMEOUT=>40,
      CURLOPT_ENCODING=>'',
      CURLOPT_POST=>true,
      CURLOPT_POSTFIELDS=>json_encode($payload),
      CURLOPT_HTTPHEADER=>array_merge($base,$headers),
    ]);
    $resp=curl_exec($ch);
    curl_close($ch);
    return $resp?:'';
  }
}

/* ========= IMDb: HTML/JSON ========= */
if (!function_exists('imdb_title_html')) {
  function imdb_title_html($tt){ return http_get("https://www.imdb.com/title/".rawurlencode($tt)."/"); }
}
if (!function_exists('imdb_fullcredits_html')) {
  function imdb_fullcredits_html($tt){ return http_get("https://www.imdb.com/title/".rawurlencode($tt)."/fullcredits/"); }
}
if (!function_exists('imdb_fullcredits_cast_html')) {
  function imdb_fullcredits_cast_html($tt){
    foreach ([
      "https://www.imdb.com/title/$tt/fullcredits/cast/",
      "https://www.imdb.com/title/$tt/fullcredits/cast?ref_=ttfc_fc_cl",
    ] as $u){ $h=http_get($u); if($h) return $h; }
    return '';
  }
}
if (!function_exists('imdb_reference_html')) {
  function imdb_reference_html($tt){ return http_get("https://www.imdb.com/title/".rawurlencode($tt)."/reference"); }
}
if (!function_exists('imdb_plot_html')) {
  function imdb_plot_html($tt){ return http_get("https://www.imdb.com/title/".rawurlencode($tt)."/plotsummary"); }
}
if (!function_exists('imdb_jsonld')) {
  function imdb_jsonld($tt){
    $html=imdb_title_html($tt);
    if(!$html) return [];
    preg_match_all('~<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>~is',$html,$all);
    foreach($all[1]??[] as $j){
      $x=json_decode($j,true); if(!is_array($x)) continue;
      $t=(array)($x['@type']??[]);
      if(in_array('Movie',$t,true)||in_array('TVSeries',$t,true)||isset($x['name'])) return $x;
    }
    return [];
  }
}
if (!function_exists('imdb_next_data_array_from_html')) {
  function imdb_next_data_array_from_html($html){
    if(!$html) return null;
    if(preg_match('~<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>~is',$html,$m)){
      $j=json_decode($m[1],true);
      if(is_array($j)) return $j;
    }
    return null;
  }
}

/* ========= Original title (IMDb ONLY) ========= */
if (!function_exists('imdb_extract_original_title_from_titlepage')) {
  function imdb_extract_original_title_from_titlepage($tt){
    $html = imdb_title_html($tt); if(!$html) return null;
    if(preg_match('~data-testid="hero-title-block__original-title"[^>]*>(.*?)</div>~is',$html,$m)){
      $t = trim(strip_tags($m[1]));
      $t = preg_replace('~^Original\s+title:\s*~i','',$t);
      $t = html_entity_decode($t,ENT_QUOTES,'UTF-8');
      return $t !== '' ? $t : null;
    }
    if(preg_match('~data-testid="title-details-original-title"[^>]*>(.*?)</li>~is',$html,$m2)){
      if(preg_match('~<div[^>]*>(.*?)</div>~is',$m2[1],$n)){
        $t = trim(strip_tags($n[1]));
        $t = html_entity_decode($t,ENT_QUOTES,'UTF-8');
        return $t !== '' ? $t : null;
      }
    }
    return null;
  }
}
if (!function_exists('imdb_next_original_title')) {
  function imdb_next_original_title($tt){
    $j = imdb_next_data_array_from_html(imdb_title_html($tt));
    if(!$j) return null;
    $stack = [$j];
    while($stack){
      $node = array_pop($stack);
      if(is_array($node)){
        if(isset($node['originalTitleText']['text']) && trim($node['originalTitleText']['text'])!=='')
          return $node['originalTitleText']['text'];
        foreach($node as $v){ if(is_array($v)) $stack[] = $v; }
      }
    }
    return null;
  }
}
if (!function_exists('imdb_jsonld_original_title')) {
  function imdb_jsonld_original_title($tt){
    $ld = imdb_jsonld($tt);
    $alt = $ld['alternateName'] ?? null;
    return is_string($alt) && trim($alt) !== '' ? $alt : null;
  }
}
if (!function_exists('imdb_original_title_best')) {
  function imdb_original_title_best($tt,$IMDB){
    $orig = stripAllTags(imdb_get($IMDB,'getOriginalTitle')) ?: null;
    if(!$orig) $orig = imdb_extract_original_title_from_titlepage($tt);
    if(!$orig) $orig = imdb_next_original_title($tt);
    if(!$orig) $orig = imdb_jsonld_original_title($tt);
    return $orig ?: null;
  }
}

/* ========= Genres (IMDb + TVDB) ========= */
if (!function_exists('normalize_genre_name')) {
  function normalize_genre_name($g){
    $t = trim((string)$g); if($t==='') return '';
    $l = mb_strtolower($t,'UTF-8');
    static $map = [
      'sci fi'=>'Sci-fi','sci-fi'=>'sci-fi','science fiction'=>'sci-fi','science-fiction'=>'sci-fi','scifi'=>'sci-fi','action'=>'Action','adventure'=>'Adventure','animation'=>'Animation','comedy'=>'Comedy','crime'=>'Crime','documentary'=>'Documentary','drama'=>'Drama',
      'family'=>'Family','fantasy'=>'Fantasy','history'=>'History','horror'=>'Horror','music'=>'Music','mystery'=>'Mystery','romance'=>'Romance',
      'thriller'=>'Thriller','war'=>'War','western'=>'Western','reality'=>'Reality','talk show'=>'Talk Show','tv movie'=>'TV Movie'
    ];
    if(isset($map[$l])) return $map[$l];
    $t=preg_replace('~\s+~u',' ',$t);
    return mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
  }
}
if (!function_exists('imdb_extract_genres_from_title_html_no_storyline')) {
  function imdb_extract_genres_from_title_html_no_storyline($tt){
    $html=imdb_title_html($tt); if(!$html) return [];
    $names=[];
    if(preg_match('~data-testid="genres"[^>]*>(.*?)</div>~is',$html,$m)){
      if(preg_match_all('~<a[^>]*>([^<]+)</a>~i',$m[1],$mm)){
        foreach($mm[1] as $g){ $t=trim(html_entity_decode($g,ENT_QUOTES,'UTF-8')); if($t!=='') $names[]=$t; }
      }
    }
    return array_map('normalize_genre_name', normalize_list($names));
  }
}
if (!function_exists('imdb_reference_genres')) {
  function imdb_reference_genres($tt){
    $html=imdb_reference_html($tt); if(!$html) return [];
    $out=[];
    if(preg_match_all('~href="/search/title/\?genres=[^"]+"[^>]*>([^<]+)</a>~i',$html,$m)){
      foreach($m[1] as $g){ $t=trim(html_entity_decode($g,ENT_QUOTES,'UTF-8')); if($t!=='') $out[]=$t; }
    }
    return array_map('normalize_genre_name', normalize_list($out));
  }
}
if (!function_exists('extract_imdb_genres_core')) {
  function extract_imdb_genres_core($IMDB,$tt){
    $json=imdb_jsonld($tt); $gen=$json['genre']??[]; if(is_string($gen)) $gen=[$gen];
    $names_ld = array_map('normalize_genre_name', normalize_list($gen));
    $names_cls= array_map('normalize_genre_name', normalize_list(imdb_get($IMDB,'getGenre')));
    $names_htm= imdb_extract_genres_from_title_html_no_storyline($tt);
    $names_ref= imdb_reference_genres($tt);
    return merge_unique_lists($names_ld,$names_cls,$names_htm,$names_ref);
  }
}

/* ========= Languages/Countries (IMDb בלבד) ========= */
if (!function_exists('imdb_extract_languages_from_title_html')) {
  function imdb_extract_languages_from_title_html($tt){
    $html=imdb_title_html($tt); if(!$html) return [];
    $names=[];
    if(preg_match('~title-details-languages"[^>]*>(.*?)</li>~is',$html,$m)){
      if(preg_match_all('~<a[^>]*>([^<]+)</a>~i',$m[1],$mm)){
        foreach($mm[1] as $t){ $n=trim(html_entity_decode($t,ENT_QUOTES,'UTF-8')); if($n!=='') $names[]=$n; }
      }
    }
    if(preg_match('~data-testid="title-details-section"[^>]*>(.*?)</section>~is',$html,$sec)){
      if(preg_match('~Languages?\s*</span>.*?<ul[^>]*>(.*?)</ul>~is',$sec[1],$ul)){
        if(preg_match_all('~<a[^>]*>([^<]+)</a>~i',$ul[1],$mm2)){
          foreach($mm2[1] as $t){ $n=trim(html_entity_decode($t,ENT_QUOTES,'UTF-8')); if($n!=='') $names[]=$n; }
        }
      }
    }
    if(preg_match_all('~href="/search/title/\?languages=([a-z-]+)"[^>]*>([^<]+)</a>~i',$html,$mx2)){
      foreach($mx2[2] as $t){ $n=trim(html_entity_decode($t,ENT_QUOTES,'UTF-8')); if($n!=='') $names[]=$n; }
    }
    return normalize_list($names);
  }
}
if (!function_exists('imdb_technical_languages')) {
  function imdb_technical_languages($tt){
    $html=http_get("https://www.imdb.com/title/".rawurlencode($tt)."/technical"); if(!$html) return [];
    $out=[];
    if(preg_match('~<th[^>]*>\s*Languages?\s*</th>\s*<td[^>]*>(.*?)</td>~is',$html,$m)){
      if(preg_match_all('~<a[^>]*>([^<]+)</a>~i',$m[1],$mm)){
        foreach($mm[1] as $t){ $n=trim(html_entity_decode($t,ENT_QUOTES,'UTF-8')); if($n!=='') $out[]=$n; }
      } else {
        foreach (preg_split('~\s*,\s*~', strip_tags($m[1])) as $t){ $t=trim($t); if($t!=='') $out[]=$t; }
      }
    }
    return normalize_list($out);
  }
}
if (!function_exists('imdb_reference_languages')) {
  function imdb_reference_languages($tt){
    $html=imdb_reference_html($tt); if(!$html) return [];
    $out=[];
    if(preg_match_all('~<dt[^>]*>\s*Languages?\s*</dt>\s*<dd[^>]*>(.*?)</dd>~is',$html,$m)){
      foreach($m[1] as $blk){
        if(preg_match_all('~<a[^>]+>([^<]+)</a>~i',$blk,$mm)){
          foreach($mm[1] as $t){ $n=trim(html_entity_decode($t,ENT_QUOTES,'UTF-8')); if($n!=='') $out[]=$n; }
        } else {
          foreach (preg_split('~\s*,\s*~', strip_tags($blk)) as $t){ $t=trim($t); if($t!=='') $out[]=$t; }
        }
      }
    }
    return normalize_list($out);
  }
}
if (!function_exists('languages_from_imdb_only')) {
  function languages_from_imdb_only($tt, $imdbList){
    $names = merge_unique_lists(
      normalize_list($imdbList),
      imdb_extract_languages_from_title_html($tt),
      imdb_technical_languages($tt),
      imdb_reference_languages($tt)
    );
    $clean=[]; foreach ($names as $n){ $n=trim((string)$n); if($n===''||preg_match('~^[a-z]{1,2}$~i',$n)||preg_match('~^N/?A$~i',$n)) continue; $clean[]=$n; }
    return $clean;
  }
}
if (!function_exists('countries_names_only')) {
  function countries_names_only($nameList){
    $names = normalize_list($nameList); $out=[];
    foreach ($names as $n){ if(!preg_match('~^[A-Z]{2}$~',$n)) $out[]=$n; }
    $seen=[]; $uniq=[];
    foreach ($out as $x){ $k=mb_strtolower(preg_replace('~\s+~u',' ',$x),'UTF-8'); if(!isset($seen[$k])){$seen[$k]=1;$uniq[]=$x;} }
    return $uniq;
  }
}

/* ========= TMDb ========= */
if (!function_exists('tmdb_find')) {
  function tmdb_find($tt,$key){
    $u="https://api.themoviedb.org/3/find/".rawurlencode($tt)."?api_key=$key&language=en-US&external_source=imdb_id";
    $j=@file_get_contents($u); return $j?(json_decode($j,true)?:[]):[];
  }
}
if (!function_exists('tmdb_movie')) {
  function tmdb_movie($id,$key){
    $j=@file_get_contents("https://api.themoviedb.org/3/movie/$id?api_key=$key&language=en-US&append_to_response=translations,images,videos,alternative_titles,external_ids");
    return $j?(json_decode($j,true)?:[]):[];
  }
}
if (!function_exists('tmdb_tv')) {
  function tmdb_tv($id,$key){
    $j=@file_get_contents("https://api.themoviedb.org/3/tv/$id?api_key=$key&language=en-US&append_to_response=translations,images,videos,alternative_titles,external_ids,content_ratings,networks");
    return $j?(json_decode($j,true)?:[]):[];
  }
}
if (!function_exists('tmdb_pick_he_title')) {
  function tmdb_pick_he_title(array $tmdb,$type){
    foreach (($tmdb['translations']['translations'] ?? []) as $t){
      if (($t['iso_639_1'] ?? '')==='he'){
        $ttl = $t['data'][ $type==='tv' ? 'name' : 'title' ] ?? '';
        if (trim($ttl)!=='') return $ttl;
      }
    }
    return null;
  }
}
if (!function_exists('tmdb_pick_he_overview')) {
  function tmdb_pick_he_overview(array $tmdb){
    foreach (($tmdb['translations']['translations'] ?? []) as $t){
      if (($t['iso_639_1'] ?? '')==='he'){
        $ov=$t['data']['overview']??''; if(trim($ov)!=='') return $ov;
      }
    }
    return $tmdb['overview'] ?? null;
  }
}
if (!function_exists('tmdb_pick_he_poster')) {
  function tmdb_pick_he_poster($type,$id,$key){
    if(!$type||!$id) return null;
    $endpoint=($type==='tv')?"https://api.themoviedb.org/3/tv/$id/images":"https://api.themoviedb.org/3/movie/$id/images";
    $j=@file_get_contents($endpoint."?api_key=$key&include_image_language=he"); if(!$j) return null;
    $data=json_decode($j,true); foreach(($data['posters']??[]) as $p){
      if(($p['iso_639_1']??null)==='he' && !empty($p['file_path'])) return 'https://image.tmdb.org/t/p/w780'.$p['file_path'];
    }
    return null;
  }
}
if (!function_exists('tmdb_pick_youtube_trailer')) {
  function tmdb_pick_youtube_trailer(array $tmdb){
    foreach(($tmdb['videos']['results']??[]) as $v){
      if(strcasecmp($v['site']??'','YouTube')===0 && preg_match('~trailer|teaser~i',$v['type']??'') && !empty($v['key'])){
        if(!empty($v['official'])) return 'https://www.youtube.com/watch?v='.$v['key'];
      }
    }
    foreach(($tmdb['videos']['results']??[]) as $v){
      if(strcasecmp($v['site']??'','YouTube')===0 && !empty($v['key'])) return 'https://www.youtube.com/watch?v='.$v['key'];
    }
    return null;
  }
}
if (!function_exists('tmdb_collect_alt_titles')) {
  function tmdb_collect_alt_titles(array $tmdb,$type){
    $out=[];
    foreach(($tmdb['translations']['translations']??[]) as $t){
      $n=$t['data'][$type==='tv'?'name':'title']??''; if(trim($n)!=='') $out[]=$n;
    }
    if($type==='movie'){
      foreach(($tmdb['alternative_titles']['titles']??[]) as $t){ $n=$t['title']??''; if(trim($n)!=='') $out[]=$n; }
    } else {
      foreach(($tmdb['alternative_titles']['results']??[]) as $t){ $n=$t['title']??($t['name']??''); if(trim($n)!=='') $out[]=$n; }
    }
    return normalize_list($out);
  }
}

/* ========= TVDB v4 (CLEAN) ========= */
if (!function_exists('tvdb_login')) {
  function tvdb_login($apikey){
    if(!$apikey) return null;
    $resp=http_post_json('https://api4.thetvdb.com/v4/login',['apikey'=>$apikey]);
    $j=$resp?json_decode($resp,true):null;
    return $j['data']['token']??null;
  }
}
if (!function_exists('tvdb_get')) {
  function tvdb_get($path,$token){
    if(!$token) return null;
    $json=http_get('https://api4.thetvdb.com/v4'.$path, ['Authorization: Bearer '.$token,'Accept: application/json']);
    return $json?json_decode($json,true):null;
  }
}
if (!function_exists('tvdb_fetch_series_core')) {
  function tvdb_fetch_series_core($id,$token){
    $j=tvdb_get('/series/'.intval($id),$token);
    if(!$j || empty($j['data'])) return null;
    return $j['data'];
  }
}
if (!function_exists('tvdb_fetch_series_extended')) {
  function tvdb_fetch_series_extended($id,$token){
    $j=tvdb_get('/series/'.intval($id).'/extended',$token);
    if(!$j || empty($j['data'])) return null;
    return $j['data'];
  }
}
if (!function_exists('tvdb_fetch_genres_api')) {
  function tvdb_fetch_genres_api($id,$apikey){
    $tok=tvdb_login($apikey); if(!$tok) return [];
    $core=tvdb_fetch_series_core($id,$tok);
    $ext =tvdb_fetch_series_extended($id,$tok);
    $names = [];
    foreach (['genres'] as $key){
      foreach ([$core,$ext] as $blk){
        if(!$blk || empty($blk[$key])) continue;
        foreach ($blk[$key] as $g){
          if(is_array($g) && isset($g['name'])) $names[]=$g['name'];
          elseif(is_string($g)) $names[]=$g;
        }
      }
    }
    $clean=[];
    foreach ($names as $n){
      $norm = normalize_genre_name($n);
      if ($norm !== '') $clean[] = $norm;
    }
    $seen=[]; $out=[];
    foreach ($clean as $x){
      $k=mb_strtolower($x,'UTF-8');
      if(!isset($seen[$k])){ $seen[$k]=1; $out[]=$x; }
    }
    return $out;
  }
}
if (!function_exists('tvdb_fetch_akas_api')) {
  function tvdb_fetch_akas_api($seriesId, $apikey){
    $tok = tvdb_login($apikey); if (!$tok) return [];
    $ext = tvdb_fetch_series_extended($seriesId, $tok);
    $names = [];
    if (!empty($ext['aliases']) && is_array($ext['aliases'])) {
        foreach ($ext['aliases'] as $a) {
            $nm = is_array($a) ? ($a['name'] ?? '') : $a;
            if (trim((string)$nm) !== '') $names[] = (string)$nm;
        }
    }
    if (!empty($ext['translations']) && is_array($ext['translations'])) {
        foreach ($ext['translations'] as $t) {
            if (is_array($t) && !empty($t['name'])) $names[] = $t['name'];
        }
    }
    return normalize_list($names);
  }
}
if (!function_exists('tvdb_fetch_networks_api')) {
  function tvdb_fetch_networks_api($seriesId,$apikey){
    $tok=tvdb_login($apikey); if(!$tok) return [];
    $ext=tvdb_fetch_series_extended($seriesId,$tok);
    $names=[];
    if(!empty($ext['networks']) && is_array($ext['networks'])){
      foreach($ext['networks'] as $n){ $nm=is_array($n)?($n['name']??''):$n; if(trim($nm)!=='') $names[]=$nm; }
    } elseif(!empty($ext['companies']) && is_array($ext['companies'])){
      foreach($ext['companies'] as $c){
        $nm=$c['name']??($c['company']['name']??'');
        $typ=strtolower(trim(($c['typeName']??($c['company']['typeName']??''))));
        if($nm && ($typ==='network' || $typ==='broadcaster')) $names[]=$nm;
      }
    }
    $names=array_map(fn($s)=>trim((string)$s),$names);
    $names=array_values(array_filter($names,fn($s)=>$s!==''));
    $seen=[];$out=[]; foreach($names as $x){ $k=mb_strtolower($x,'UTF-8'); if(!isset($seen[$k])){$seen[$k]=1;$out[]=$x;} }
    return $out;
  }
}
if (!function_exists('tvdb_fetch_slug')) {
  function tvdb_fetch_slug($seriesId,$apikey){
    $tok=tvdb_login($apikey); if(!$tok) return null;
    $j=tvdb_fetch_series_core($seriesId,$tok);
    return $j['slug']??null;
  }
}
if (!function_exists('tvdb_build_links')) {
  function tvdb_build_links($tmdb_name,$tvdb_id,$imdb_id,$HARDCODE_TVDB,$apikey){
    $id = $tvdb_id ?: ($HARDCODE_TVDB[$imdb_id] ?? null);
    $links=[];
    if($id){
      $slug = tvdb_fetch_slug($id,$apikey);
      if($slug) $links[]="https://www.thetvdb.com/series/".$slug;
      $links[]="https://www.thetvdb.com/series/".intval($id);
    } elseif($tmdb_name){
      $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$tmdb_name);
      $slug=strtolower(preg_replace('~[^a-z0-9]+~','-',$s));
      $slug=trim($slug,'-');
      if($slug) $links[]="https://www.thetvdb.com/series/".$slug;
    }
    return array_values(array_unique($links));
  }
}

/* ========= CAST & CREW (IMDb בלבד) ========= */
if (!function_exists('extract_block_between_headings')) {
  function extract_block_between_headings($html, array $titles){
    if(!$html) return '';
    $titleRe=implode('|', array_map(fn($t)=>preg_quote($t,'~'), $titles));
    $re='~<h[2-6][^>]*>\s*(?:Series\s+)?(?:'.$titleRe.')(?:\s*\([^)]*\))?\s*</h[2-6]>\s*(.*?)\s*(?=<h[2-6]\b|</body>|\z)~is';
    return preg_match($re,$html,$m)?$m[1]:'';
  }
}
if (!function_exists('extract_people_from_block_strict')) {
  function extract_people_from_block_strict($block){
    if(!$block) return [];
    $names=[];
    if(preg_match_all('~<a\b[^>]*href="(?:https?://(?:www\.)?imdb\.com)?/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~is',$block,$mm)){
      foreach($mm[1] as $nm){
        $n = trim(html_entity_decode($nm, ENT_QUOTES, 'UTF-8'));
        if($n!=='') $names[] = $n;
      }
    }
    return normalize_list($names);
  }
}
if (!function_exists('extract_subsection_block_by_testid')) {
  function extract_subsection_block_by_testid($html, array $keys){
    if(!$html) return '';
    $keysRe = implode('|', array_map(fn($t)=>preg_quote($t,'~'), $keys));
    if(!preg_match('~<div[^>]+data-testid\s*=\s*(?:"|=3D")sub-section-(?:'.$keysRe.')(?:"|")[^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE))
      return '';
    $start = $m[0][1];
    $rest  = substr($html, $start);
    $n1 = stripos($rest, 'data-testid="sub-section-');
    $n2 = stripos($rest, 'data-testid=3D"sub-section-');
    $next = false;
    if($n1!==false){
      $next1 = stripos($rest, 'data-testid="sub-section-', $n1+1);
      if($next1!==false) $next = $next1;
    }
    if($n2!==false){
      $next2 = stripos($rest, 'data-testid=3D"sub-section-', $n2+1);
      if($next===false || ($next2!==false && $next2<$next)) $next = $next2;
    }
    $block = ($next===false) ? $rest : substr($rest, 0, $next);
    return $block;
  }
}
if (!function_exists('extract_imdb_cast_from_cast_table')) {
  function extract_imdb_cast_from_cast_table($html){
    if(!$html) return [];
    $names=[];
    if(preg_match('~<table[^>]*class="[^"]*\bcast_list\b[^"]*"[^>]*>(.*?)</table>~is',$html,$m)){
      $tbl=$m[1];
      if(preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is',$tbl,$rows)){
        foreach($rows[1] as $row){
          if(preg_match('~<a\s+href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~is',$row,$a)){
            $nm=trim(html_entity_decode($a[1],ENT_QUOTES,'UTF-8'));
            if($nm!=='') $names[]=$nm;
          }
        }
      }
    }
    if(empty($names) && preg_match('~data-testid="fullcredits-cast"[^>]*>(.*?)</section>~is',$html,$sec)){
      if(preg_match_all('~<a\s+href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~is',$sec[1],$mm)){
        foreach($mm[1] as $nm){ $n=trim(html_entity_decode($nm,ENT_QUOTES,'UTF-8')); if($n!=='') $names[]=$n; }
      }
    }
    return normalize_list($names);
  }
}
if (!function_exists('extract_cast_from_reference_table')) {
  function extract_cast_from_reference_table($html){
    if(!$html)return[]; $names=[];
    if(preg_match('~<table[^>]*class="[^"]*\bcast\b[^"]*"[^>]*>(.*?)</table>~is',$html,$m)){
      if(preg_match_all('~<a\s+href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~is',$m[1],$mm)){
        foreach($mm[1] as $nm){ $n=trim(html_entity_decode($nm,ENT_QUOTES,'UTF-8')); if($n!=='') $names[]=$n; }
      }
    }
    return normalize_list($names);
  }
}
if (!function_exists('collect_names_from_next_data')) {
  function collect_names_from_next_data($j){
    if(!$j) return [];
    $out=[]; $stack=[ $j ];
    while($stack){
      $node=array_pop($stack);
      if(is_array($node)){
        if(isset($node['nameText']))       $out[]=$node['nameText'];
        if(isset($node['primaryName']))    $out[]=$node['primaryName'];
        if(isset($node['legacyNameText'])) $out[]=$node['legacyNameText'];
        if(isset($node['originalNameText'])) $out[]=$node['originalNameText'];
        foreach($node as $v){ if(is_array($v)) $stack[]=$v; }
      }
    }
    return normalize_list($out);
  }
}
if (!function_exists('extract_imdb_cast_from_title_block')) {
  function extract_imdb_cast_from_title_block($html){
    if(!$html) return [];
    $names = [];
    if (preg_match_all('~<a[^>]+data-testid="title-cast-item__actor"[^>]*>([^<]+)</a>~i', $html, $m)) {
      foreach ($m[1] as $nm) {
        $n = trim(html_entity_decode($nm, ENT_QUOTES, 'UTF-8'));
        if ($n !== '') $names[] = $n;
      }
    }
    if (preg_match_all('~<li[^>]*data-testid="title-cast-item[^"]*"[^>]*>(.*?)</li>~is', $html, $lis)) {
      foreach ($lis[1] as $li) {
        if (preg_match_all('~<a[^>]*href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~is', $li, $mm)) {
          foreach ($mm[1] as $nm) {
            $n = trim(html_entity_decode($nm, ENT_QUOTES, 'UTF-8'));
            if ($n !== '') $names[] = $n;
          }
        }
      }
    }
    if (empty($names) && preg_match('~<section[^>]*data-testid="title-cast"[^>]*>(.*?)</section>~is', $html, $sec)) {
      if (preg_match_all('~<li\b[^>]*>(.*?)</li>~is', $sec[1], $liAll)) {
        foreach ($liAll[1] as $li) {
          if (stripos($li, 'title-cast-item') === false) continue;
          if (preg_match_all('~<a[^>]*href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~is', $li, $mm)) {
            foreach ($mm[1] as $nm) {
              $n = trim(html_entity_decode($nm, ENT_QUOTES, 'UTF-8'));
              if ($n !== '') $names[] = $n;
            }
          }
        }
      }
    }
    return normalize_list($names);
  }
}
if (!function_exists('extract_imdb_cast_names')) {
  function extract_imdb_cast_names($IMDB,$tt){
    $names = [];
    $titleHtml = imdb_title_html($tt);
    $names = merge_unique_lists($names, extract_imdb_cast_from_title_block($titleHtml));
    $names = merge_unique_lists($names, extract_imdb_cast_from_cast_table(imdb_fullcredits_cast_html($tt)));
    return normalize_list($names);
  }
}
if (!function_exists('extract_imdb_section_people')) {
  function extract_imdb_section_people($html, array $titles){
    $blk = extract_block_between_headings($html,$titles);
    if($blk!=='') return extract_people_from_block_strict($blk);
    return [];
  }
}
/* === DIRECTORS === */
if (!function_exists('extract_imdb_directors')) {
  function extract_imdb_directors($IMDB,$tt){
    $json=imdb_jsonld($tt); $dir=$json['director']??[]; $names=[];
    if(isset($dir['name'])) $names[]=$dir['name'];
    elseif(is_array($dir)){ foreach($dir as $d){ $nm=is_array($d)?($d['name']??''):$d; if(trim($nm)!=='') $names[]=$nm; } }
    $names=normalize_list($names);
    foreach([fn()=>imdb_get($IMDB,'getDirector'), fn()=>imdb_get($IMDB,'getDirectors')] as $cb){ $n=normalize_list($cb()); if($n) $names=merge_unique_lists($names,$n); }
    $fc=imdb_fullcredits_html($tt);
    $fromFC=extract_imdb_section_people($fc,['Series Directed by','Directed by','Director']);
    if(!$fromFC){
      $blk = extract_subsection_block_by_testid($fc, ['director','directors']);
      $fromFC = extract_people_from_block_strict($blk);
    }
    return merge_unique_lists($names,$fromFC);
  }
}
/* === WRITERS === */
if (!function_exists('extract_imdb_writers')) {
  function extract_imdb_writers($IMDB,$tt){
    $fc=imdb_fullcredits_html($tt);
    $fromFC=extract_imdb_section_people($fc,['Writing Credits','Writers','Series Writing Credits']);
    if(!$fromFC){
      $blk = extract_subsection_block_by_testid($fc, ['writer','writers']);
      $fromFC = extract_people_from_block_strict($blk);
    }
    if($fromFC) return $fromFC;
    return normalize_list(imdb_get($IMDB,'getWriter'));
  }
}
/* === PRODUCERS === */
if (!function_exists('extract_imdb_producers')) {
  function extract_imdb_producers($tt){
    $fc=imdb_fullcredits_html($tt);
    $fromFC = merge_unique_lists(
      extract_imdb_section_people($fc,['Produced by','Producers','Series Produced by','Producer']),
      extract_imdb_section_people($fc,['Executive Producers','Co-producers','Associate Producers'])
    );
    if(!$fromFC){
      $blk = extract_subsection_block_by_testid($fc, ['producer','producers']);
      $fromFC = extract_people_from_block_strict($blk);
    }
    return $fromFC;
  }
}
/* === COMPOSERS === */
if (!function_exists('extract_imdb_composers')) {
  function extract_imdb_composers($IMDB,$tt){
    $fc=imdb_fullcredits_html($tt);
    $fromFC=extract_imdb_section_people($fc,['Series Music by','Music by','Original Music by','Composer','Original score by']);
    if(!$fromFC){
      $blk = extract_subsection_block_by_testid($fc, ['composer','music']);
      $fromFC = extract_people_from_block_strict($blk);
    }
    if($fromFC) return $fromFC;
    return normalize_list(imdb_get($IMDB,'getMusic'));
  }
}
/* === CINEMATOGRAPHERS === */
if (!function_exists('extract_imdb_cinematographers')) {
  function extract_imdb_cinematographers($tt){
    $fc=imdb_fullcredits_html($tt);
    $fromFC = extract_imdb_section_people($fc,['Cinematography by','Director of Photography','Photography by','Series Cinematography by']);
    if(!$fromFC){
      $blk = extract_subsection_block_by_testid($fc, ['cinematographer','cinematography','director-of-photography']);
      $fromFC = extract_people_from_block_strict($blk);
    }
    return $fromFC;
  }
}

/* === Creators (לסדרות) — לסינון קאסט בלבד === */
if (!function_exists('imdb_reference_creators_all')) {
  function imdb_reference_creators_all($tt){
    $html = imdb_reference_html($tt); if(!$html) return [];
    $out = [];
    if(preg_match_all('~<dt[^>]*>\s*(?:Series\s+)?Creators?\s*</dt>\s*((?:<dd[^>]*>.*?</dd>\s*)+)~is',$html,$blocks)){
      if(preg_match_all('~<dd[^>]*>(.*?)</dd>~is',$blocks[1][0],$dds)){
        foreach($dds[1] as $blk){
          if(preg_match_all('~<a[^>]*href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~i',$blk,$mm)){
            foreach($mm[1] as $nm){ $n=trim(html_entity_decode($nm,ENT_QUOTES,'UTF-8')); if($n!=='') $out[]=$n; }
          } else {
            foreach (preg_split('~\s*,\s*~', strip_tags($blk)) as $nm){ $nm=trim($nm); if($nm!=='') $out[]=$nm; }
          }
        }
      }
    }
    return normalize_list($out);
  }
}
if (!function_exists('imdb_fullcredits_created_by')) {
  function imdb_fullcredits_created_by($tt){
    $html = imdb_fullcredits_html($tt); if(!$html) return [];
    $blk = extract_block_between_headings($html, ['Series Writing Credits','Writing Credits']);
    if($blk==='') $blk = $html;
    $out = [];
    if(preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is',$blk,$rows)){
      foreach($rows[1] as $row){
        if(!preg_match('~\((?:created\s+by|creator)s?\)~i',$row)) continue;
        if(preg_match_all('~<a\s+href="/name/nm\d+[^"]*"[^>]*>([^<]+)</a>~i',$row,$mm)){
          foreach($mm[1] as $nm){
            $n = trim(html_entity_decode($nm,ENT_QUOTES,'UTF-8'));
            if($n!=='') $out[] = $n;
          }
        }
      }
    }
    return normalize_list($out);
  }
}
if (!function_exists('imdb_jsonld_creators')) {
  function imdb_jsonld_creators($tt){
    $ld = imdb_jsonld($tt);
    $out = [];
    $c = $ld['creator'] ?? [];
    if(isset($c['name'])) $c = [$c];
    foreach((array)$c as $it){
      if(is_array($it)){
        $n = $it['name'] ?? '';
        if($n!=='') $out[] = $n;
      } elseif(is_string($it)){
        $out[] = $it;
      }
    }
    return normalize_list($out);
  }
}
if (!function_exists('imdb_collect_creators')) {
  function imdb_collect_creators($tt){
    return normalize_list(
      merge_unique_lists(
        imdb_reference_creators_all($tt),
        imdb_fullcredits_created_by($tt),
        imdb_jsonld_creators($tt)
      )
    );
  }
}

/* === סינון קאסט לסדרות === */
if (!function_exists('filter_cast_tv_creators_only')) {
  function filter_cast_tv_creators_only($tt, $is_tv, $cast_arr, $directors = [], $writers = []) {
    $cast = normalize_list($cast_arr);
    if (!$cast) return $cast;

    $hasNoise = false;
    foreach ($cast as $nm) {
      if (preg_match('~^(?:Creators?|Created\s+by|NameText)\s*:?\s*$~iu', $nm)) { $hasNoise = true; break; }
    }

    $ban = [];
    if ($is_tv) {
      foreach (imdb_collect_creators($tt) as $nm) { $k = soft_key($nm); if ($k!=='') $ban[$k] = true; }
    }
    if ($hasNoise) {
      foreach (clean_people_list($directors) as $nm) { $k = soft_key($nm); if ($k!=='') $ban[$k] = true; }
      foreach (clean_people_list($writers)   as $nm) { $k = soft_key($nm); if ($k!=='') $ban[$k] = true; }
    }

    $out = [];
    foreach ($cast as $nm) {
      if (preg_match('~^(?:Creators?|Created\s+by|NameText)\s*:?\s*$~iu', $nm)) continue;
      $k = soft_key($nm);
      if ($k!=='' && isset($ban[$k])) continue;
      $out[] = $nm;
    }

    $seen=[]; $ded=[];
    foreach ($out as $nm) { $k = soft_key($nm); if ($k!=='' && !isset($seen[$k])) { $seen[$k]=1; $ded[]=$nm; } }
    return $ded;
  }
}

/* ========= תקציר EN — IMDb ONLY ========= */
if (!function_exists('extract_imdb_english_plot_from_title_page')) {
  function extract_imdb_english_plot_from_title_page($tt){
    $html=imdb_title_html($tt); if(!$html) return null;
    if(preg_match('~<meta\s+property="og:description"\s+content="([^"]+)"~i',$html,$m)){
      $txt=html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); $txt=trim($txt);
      if($txt!=='') return $txt;
    }
    if(preg_match('~data-testid="plot-(?:xl|l)"[^>]*>(.*?)</[^>]+>~is',$html,$m2)){
      $txt=trim(strip_tags($m2[1])); if($txt!=='') return $txt;
    }
    return null;
  }
}
if (!function_exists('extract_imdb_english_plot_from_plotpage')) {
  function extract_imdb_english_plot_from_plotpage($tt){
    $html=imdb_plot_html($tt); if(!$html) return null;
    if(preg_match('~<ul[^>]*id="plot-summaries-content"[^>]*>(.*?)</ul>~is',$html,$ul)){
      if(preg_match('~<li[^>]*>(.*?)</li>~is',$ul[1],$li)){ $t=trim(strip_tags($li[1])); if($t!=='') return $t; }
    }
    if(preg_match('~<div[^>]*id="plot-synopsis-content"[^>]*>(.*?)</div>~is',$html,$div)){
      $t=trim(strip_tags($div[1])); if($t!=='') return $t;
    }
    return null;
  }
}
if (!function_exists('extract_imdb_english_plot')) {
  function extract_imdb_english_plot($IMDB, $tt){
    $full_plot = extract_imdb_full_plot_from_summary_page($tt);
    if ($full_plot && mb_strlen($full_plot) > 150) {
        return $full_plot;
    }
    $p = imdb_get($IMDB, 'getPlot');
    $p = is_array($p) ? implode(' ', $p) : $p;
    if (trim((string)$p) !== '') return stripAllTags($p);
    $json = imdb_jsonld($tt);
    $d = $json['description'] ?? '';
    if (trim((string)$d) !== '') return $d;
    if ($full_plot) return $full_plot;
    return null;
  }
}

/* ========= AKAs ========= */
if (!function_exists('tmdb_alt_titles_for_row')) {
  function tmdb_alt_titles_for_row(array $tmdb,$type){
    return tmdb_collect_alt_titles($tmdb,$type);
  }
}
if (!function_exists('imdb_collect_akas')) {
  function imdb_collect_akas($IMDB){
    foreach (['getAkas','getAka','getAkaTitles','getAlsoKnownAs'] as $m){
      $akas=imdb_get($IMDB,$m);
      if($akas){
        if(is_array($akas)) return normalize_list($akas);
        return normalize_list(preg_split('~\R~',(string)$akas));
      }
    }
    return [];
  }
}

/* ========= IMDb Connections (ROBUST VERSION) ========= */
if (!function_exists('connections_keep_labels')) {
  function connections_keep_labels(){ return ['Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of','Alternate versions']; }
}
if (!function_exists('http_get_simple')) {
  function http_get_simple(string $url, int $timeout = 20): ?string {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => [ 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36', 'Accept-Language: en-US,en;q=0.9,he;q=0.6', 'Referer: https://www.imdb.com/', ], ]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($body === false || $code >= 400) ? null : $body;
  }
}
if (!function_exists('parse_next_data_json')) {
  function parse_next_data_json(string $html): ?array {
    if (preg_match('#<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>#si', $html, $m)) {
      $json = html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      try { $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR); } catch (Throwable $e) { $data = json_decode($json, true); }
      return is_array($data) ? $data : null;
    }
    return null;
  }
}
if (!function_exists('imdb_connections_url')) {
  function imdb_connections_url(string $id): string { return "https://www.imdb.com/title/{$id}/movieconnections/"; }
}
if (!function_exists('connections_normalize_label')) {
  function connections_normalize_label(string $s): string {
    $s = trim(preg_replace('/\s+/', ' ', $s)); $s = preg_replace('/\s*\(.*$/', '', $s);
    $map = ['Follows'=>'Follows','Followed by'=>'Followed by','Remake of'=>'Remake of','Remade as'=>'Remade as','Spin-off'=>'Spin-off','Spin-off from'=>'Spin-off from','Version of'=>'Version of','Alternate versions'=>'Alternate versions'];
    return $map[$s] ?? $s;
  }
}
if (!function_exists('connections_extract_from_next')) {
  function connections_extract_from_next(array $data, array $keep): array {
    $out = []; foreach ($keep as $k) $out[$k] = [];
    $keepSet = array_flip($keep);
    $asLabel = fn($s) => (is_string($s)&&$s!=='')?(isset($keepSet[connections_normalize_label($s)])?connections_normalize_label($s):null):null;
    $pushItem = function($cat, $node) use (&$out) {
      if (!$cat || !is_array($node)) return;
      $id=null; $title=null;
      if (!empty($node['id']) && preg_match('/^tt\d+$/', (string)$node['id'])) $id = $node['id'];
      if (!$id && !empty($node['link']) && preg_match('#/title/(tt\d+)/#', (string)$node['link'], $m)) $id = $m[1];
      if (isset($node['titleText']['text'])) $title = $node['titleText']['text'];
      if ($id) $out[$cat][] = ['id'=>$id, 'title'=>$title ?: $id];
    };
    $walk = function($node, $cur=null) use (&$walk, $asLabel, $pushItem) {
      if (is_array($node)) {
        if (isset($node['sectionTitle']['text'])) { $lbl = $asLabel($node['sectionTitle']['text']); if ($lbl) $cur = $lbl; }
        if ($cur && isset($node['titleText']['text'])) $pushItem($cur, $node);
        foreach ($node as $v) if(is_array($v)) $walk($v, $cur);
      }
    };
    $walk($data, null);
    foreach ($out as $k => $arr) { $s=[];$u=[];foreach($arr as $i){$key=$i['id'].'|'.$i['title'];if(!isset($s[$key])){$s[$key]=1;$u[]=$i;}} $out[$k]=$u; }
    return $out;
  }
}
if (!function_exists('imdb_connections_all')) {
  function imdb_connections_all(string $imdbId): array {
    $keep = connections_keep_labels();
    $out = []; foreach ($keep as $k) $out[$k] = [];
    if (!preg_match('/^tt\d{6,10}$/', $imdbId)) return $out;
    
    foreach ([imdb_connections_url($imdbId), imdb_connections_url($imdbId).'?ref_=tt_trv_cnn'] as $url) {
      $html = http_get_simple($url);
      if (!$html) continue;

      $next = parse_next_data_json($html);
      if ($next) {
        $ex = connections_extract_from_next($next, $keep);
        $has = false; foreach($ex as $a) if(!empty($a)) {$has=true; break;}
        if ($has) return $ex;
      }
      
      libxml_use_internal_errors(true);
      $dom = new DOMDocument(); @$dom->loadHTML($html); libxml_clear_errors();
      $xp = new DOMXPath($dom);
      $headers = $xp->query("//h4[contains(@class,'ipl-list-title')]");
      foreach($headers as $h) {
          $label = connections_normalize_label($h->textContent ?? '');
          if (!in_array($label, $keep, true)) continue;
          $list = $h->nextSibling;
          if ($list && $list->nodeName === 'div') {
              $items = [];
              foreach($xp->query(".//a[contains(@href,'/title/tt')]", $list) as $a) {
                  if (preg_match('#/title/(tt\d+)/#', $a->getAttribute('href'), $m)) {
                      $items[] = ['id' => $m[1], 'title' => trim($a->textContent)];
                  }
              }
              if ($items) $out[$label] = $items;
          }
      }
      $has = false; foreach($out as $a) if(!empty($a)) {$has=true; break;}
      if ($has) return $out;
    }
    return $out;
  }
}
if (!function_exists('local_search_link')) {
  function local_search_link($tt){ return 'poster.php?tt='.rawurlencode($tt); }
}

/* ========= OMDb (RT/MC) ========= */
if (!function_exists('omdb_fetch_by_imdb')) {
  function omdb_fetch_by_imdb($tt,$apikey){
    if(!$apikey) return [];
    $url='https://www.omdbapi.com/?i='.rawurlencode($tt).'&apikey='.rawurlencode($apikey).'&tomatoes=true';
    $ch=curl_init();
    curl_setopt_array($ch,[
      CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
      CURLOPT_HTTPHEADER=>['User-Agent: Mozilla/5.0','Accept: application/json']
    ]);
    $resp=curl_exec($ch); curl_close($ch);
    $j=$resp?(json_decode($resp,true)?:[]):[];
    foreach(['Metascore','tomatoURL'] as $k){ if(isset($j[$k]) && strtoupper((string)$j[$k])==='N/A') $j[$k]=null; }
    return $j;
  }
}

/* ==== Metacritic URL from IMDb ==== */
if (!function_exists('_sanitize_url')) {
  function _sanitize_url($u){
    if (!$u) return null;
    $u = html_entity_decode($u, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $u = str_replace(['https:\\/\\/', '\\/','\\u002F'], ['https://','/','/'], $u);
    $u = preg_replace('~[)"\'<>.,;]\s*$~','',$u);
    return preg_match('~^https?://~i', $u) ? $u : null;
  }
}
if (!function_exists('imdb_externalsites_html')) {
  function imdb_externalsites_html($tt){ return http_get("https://www.imdb.com/title/".rawurlencode($tt)."/externalsites"); }
}
if (!function_exists('imdb_criticreviews_html')) {
  function imdb_criticreviews_html($tt){ return http_get("https://www.imdb.com/title/".rawurlencode($tt)."/criticreviews"); }
}
if (!function_exists('mc_url_from_imdb')) {
  function mc_url_from_imdb($tt){
    libxml_use_internal_errors(true);
    $html = imdb_externalsites_html($tt);
    if ($html) {
      $dom = new DOMDocument(); $dom->loadHTML($html);
      $xp  = new DOMXPath($dom);
      $nodes = $xp->query("//a[contains(@href,'metacritic.com')]");
      $cands = [];
      foreach ($nodes as $a) {
        $u = _sanitize_url($a->getAttribute('href'));
        if ($u) $cands[$u]=1;
      }
      $list = array_keys($cands);
      if ($list) {
        foreach ($list as $u) if (preg_match('~/movie/|/tv/~i',$u)) return $u;
        return $list[0];
      }
    }
    $html = imdb_criticreviews_html($tt);
    if ($html) {
      $dom = new DOMDocument(); $dom->loadHTML($html);
      $xp  = new DOMXPath($dom);
      $nodes = $xp->query("//a[contains(@href,'metacritic.com')]");
      foreach ($nodes as $a) {
        $u = _sanitize_url($a->getAttribute('href'));
        if ($u) return $u;
      }
      if (preg_match_all('~https?://(?:www\.)?metacritic\.com/[^\s"\'<>]+~i', html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $m)) {
        foreach ($m[0] as $u){ $u=_sanitize_url($u); if($u) return $u; }
      }
    }
    foreach ([imdb_title_html($tt), imdb_reference_html($tt)] as $html) {
      if (!$html) continue;
      $scan = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $scan = str_replace(['https:\\/\\/', '\\/','\\u002F'], ['https://','/','/'], $scan);
      if (preg_match_all('~https?://(?:www\.)?metacritic\.com/[^\s"\'<>]+~i', $scan, $m)) {
        foreach ($m[0] as $u){ $u=_sanitize_url($u); if($u) return $u; }
      }
    }
    return null;
  }
}

} // <<<===== guard

/* ==================================================
   איסוף נתונים לשורה אחת
   ================================================== */
if (!function_exists('build_row')) {
  function build_row($tt,$TMDB_KEY,$RAPIDAPI_KEY){
    global $OMDB_KEY;
    $row=[
      'imdb'=>$tt,'title'=>null,'original_title'=>null,'year'=>null,'is_tv'=>false,
      'language'=>null,'country'=>null,
      'director'=>[],'writer'=>[],'producers'=>[],'music'=>[],'cinematographers'=>[],
      'imdb_runtime'=>null,'imdb_poster'=>null,
      'cast'=>[],'imdb_genres'=>[],
      'tmdb'=>[],'tmdb_type'=>null,'tmdb_id'=>null,'tmdb_akas'=>[],
      'rapidapi'=>[],
      'imdb_akas'=>[],
      'omdb'=>[],
    ];
    try{
      $IMDB=new imdb($tt);
      if(!empty($IMDB->isReady)){
        $row['title']          = stripAllTags(imdb_get($IMDB,'getTitle'));
        $row['original_title'] = imdb_original_title_best($tt,$IMDB);
        $row['year']           = (string)imdb_get($IMDB,'getYear');
        $row['is_tv']          = (bool)imdb_get($IMDB,'isSeries');
        $row['language']       = imdb_get($IMDB,'getLanguage');
        $row['country']        = imdb_get($IMDB,'getCountry');
        $row['imdb_runtime']   = imdb_get($IMDB,'getRuntime');
        $row['imdb_poster']    = imdb_get($IMDB,'getPoster');

        $row['imdb_genres']    = extract_imdb_genres_core($IMDB,$tt);
        $row['cast']           = extract_imdb_cast_names($IMDB,$tt);

        $row['director']       = extract_imdb_directors($IMDB,$tt);
        $row['writer']         = extract_imdb_writers($IMDB,$tt);
        $row['producers']      = extract_imdb_producers($tt);
        $row['music']          = extract_imdb_composers($IMDB,$tt);
        $row['cinematographers']= extract_imdb_cinematographers($tt);

        $row['imdb_akas']      = imdb_collect_akas($IMDB);

        $row['_plot_en']=extract_imdb_english_plot($IMDB,$tt);
      }
    }catch(Throwable $e){}

    $r= [];
    if(!empty($RAPIDAPI_KEY)){
      $url="https://imdb236.p.rapidapi.com/api/imdb/".urlencode($tt);
      $ch=curl_init();
      curl_setopt_array($ch,[
        CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>40,
        CURLOPT_HTTPHEADER=>["x-rapidapi-host: imdb236.p.rapidapi.com","x-rapidapi-key: ".$RAPIDAPI_KEY],
      ]);
      $resp=curl_exec($ch); curl_close($ch);
      $r = $resp?(json_decode($resp,true)?:[]):[];
    }
    if(is_array($r)) $row['rapidapi']=$r;

    $row['omdb'] = omdb_fetch_by_imdb($tt, $OMDB_KEY);

    $find=tmdb_find($tt,$TMDB_KEY);
    if(!empty($find['movie_results'][0]['id'])){
      $row['tmdb_type']='movie'; $row['tmdb_id']=$find['movie_results'][0]['id'];
      $row['tmdb']=tmdb_movie($row['tmdb_id'],$TMDB_KEY);
      $row['tmdb_akas']=tmdb_alt_titles_for_row($row['tmdb'],'movie');
      $row['is_tv']=false;
      $y=year_only($row['tmdb']['release_date']??null); if($y) $row['year']=$y;
    } elseif(!empty($find['tv_results'][0]['id'])){
      $row['tmdb_type']='tv'; $row['tmdb_id']=$find['tv_results'][0]['id'];
      $row['tmdb']=tmdb_tv($row['tmdb_id'],$TMDB_KEY);
      $row['tmdb_akas']=tmdb_alt_titles_for_row($row['tmdb'],'tv');
      $row['is_tv']=true;
      $y1=year_only($row['tmdb']['first_air_date']??null);
      $y2=year_only($row['tmdb']['last_air_date']??null);
      $row['year']=$y1?(($y2 && $y2!==$y1)?($y1.'–'.$y2):$y1):($row['year']?:null);
    }

    return $row;
  }
}

/* ==================================================
   נירמול ואיחוד לשכבת תצוגה
   ================================================== */
if (!function_exists('humanize_title_kind')) {
  function humanize_title_kind($is_tv,$rawType){
    $t=mb_strtolower((string)$rawType,'UTF-8');
    if($is_tv||in_array($t,['tvseries','tv','series'])) return 'TV Series';
    if(in_array($t,['movie','film','feature'])) return 'Movie';
    if($t==='tvepisode') return 'TV Episode';
    if(in_array($t,['tvminiseries','miniseries'])) return 'TV Mini Series';
    return $is_tv?'TV Series':'Movie';
  }
}
if (!function_exists('unify_details')) {
  function unify_details(array $row, $TMDB_KEY, $TVDB_KEY){
    global $HARDCODE_TVDB;

    $r    = $row['rapidapi'] ?? [];
    $tmdb = $row['tmdb'] ?? [];
    $omdb = $row['omdb'] ?? [];

    $english_title  = first_nonempty($row['title']);
    $original_title = first_nonempty($row['original_title']);
    $title          = first_nonempty($english_title, $row['imdb']);
    $otype          = $row['is_tv'] ? 'tv' : 'movie';
    $title_kind     = humanize_title_kind($row['is_tv'], $otype);
    $year           = $row['year'];

    $aka_pair = null;
    if ($original_title && $english_title) {
        if (mb_strtolower(trim($original_title), 'UTF-8') !== mb_strtolower(trim($english_title), 'UTF-8')) {
            $aka_pair = $original_title . ' AKA ' . $english_title;
        }
    }

    $he_title = tmdb_pick_he_title($tmdb, $row['tmdb_type']);
    
    $plot_from_rapidapi = $r['plotOutline']['text'] ?? null;
    $plot_from_scraper = $row['_plot_en'] ?? null;
    $overview_en = first_nonempty($plot_from_rapidapi, $plot_from_scraper, $tmdb['overview'] ?? null);
    
    $overview_he = tmdb_pick_he_overview($tmdb);

    $poster = tmdb_pick_he_poster($row['tmdb_type'], $row['tmdb_id'], $TMDB_KEY);
    if (!$poster) {
        if (!empty($row['imdb_poster'])) $poster = $row['imdb_poster'];
        elseif (!empty($r['primaryImage']['url'])) $poster = $r['primaryImage']['url'];
        elseif (!empty($r['image'])) $poster = $r['image'];
    }
    $trailer = tmdb_pick_youtube_trailer($tmdb);

    $genres_imdb = normalize_list($row['imdb_genres']);
    $tvdb_id = $tmdb['external_ids']['tvdb_id'] ?? ($HARDCODE_TVDB[$row['imdb']] ?? null);
    $genres_tvdb = ($row['is_tv'] && $tvdb_id) ? tvdb_fetch_genres_api($tvdb_id, $TVDB_KEY) : [];
    $genres = merge_unique_lists($genres_imdb, $genres_tvdb);

    $languages = languages_from_imdb_only($row['imdb'], $row['language']);
    $countries = countries_names_only($row['country']);
    $runtime   = first_nonempty($row['imdb_runtime']);

    $directors        = clean_people_list($row['director']);
    $writers          = clean_people_list($row['writer']);
    $producers        = clean_people_list($row['producers']);
    $composers        = clean_people_list($row['music']);
    $cinematographers = clean_people_list($row['cinematographers']);
    $cast             = filter_cast_tv_creators_only($row['imdb'], $row['is_tv'], $row['cast'], $directors, $writers);

    $tvdb_nets = ($row['is_tv'] && $tvdb_id) ? tvdb_fetch_networks_api($tvdb_id, $TVDB_KEY) : [];
    $tmdb_nets = [];
    foreach (($tmdb['networks'] ?? []) as $n) {
        $nm = is_array($n) ? ($n['name'] ?? '') : $n;
        if (trim($nm) !== '') $tmdb_nets[] = $nm;
    }
    $networks = merge_unique_lists($tvdb_nets, $tmdb_nets);
    
    $imdb_rating = $r['averageRating'] ?? null;
    $imdb_votes  = isset($r['numVotes']) ? (int)$r['numVotes'] : null;

    $tvdb_akas = ($row['is_tv'] && $tvdb_id) ? tvdb_fetch_akas_api($tvdb_id, $TVDB_KEY) : [];
    $akas_all = merge_unique_lists($row['imdb_akas'] ?? [], $row['tmdb_akas'] ?? [], $tvdb_akas);

    $tvdb_links = $row['is_tv'] ? tvdb_build_links($tmdb['name'] ?? ($tmdb['title'] ?? ''), $tvdb_id, $row['imdb'], $GLOBALS['HARDCODE_TVDB'], $TVDB_KEY) : [];
    $tvdb_url = $tvdb_links[0] ?? null;
    $num_seasons  = $row['tmdb_type'] === 'tv' ? ($tmdb['number_of_seasons'] ?? null) : null;
    $num_episodes = $row['tmdb_type'] === 'tv' ? ($tmdb['number_of_episodes'] ?? null) : null;

    $rt_score = null; $rt_url = null; $mc_score = null; $mc_url = null;
    if ($omdb) {
        if (!empty($omdb['Ratings']) && is_array($omdb['Ratings'])) {
            foreach ($omdb['Ratings'] as $rr) {
                $src = trim((string)($rr['Source'] ?? ''));
                $val = trim((string)($rr['Value'] ?? ''));
                if (strcasecmp($src, 'Rotten Tomatoes') === 0 && $val !== '') {
                    if (preg_match('~(\d+)%~', $val, $m)) $rt_score = (int)$m[1];
                }
                if (strcasecmp($src, 'Metacritic') === 0 && $val !== '') {
                    if (preg_match('~(\d+)\s*/\s*100~', $val, $m)) $mc_score = (int)$m[1];
                }
            }
        }
        if ($rt_score === null && isset($omdb['tomatoMeter']) && is_numeric($omdb['tomatoMeter'])) $rt_score = (int)$omdb['tomatoMeter'];
        if ($mc_score === null && isset($omdb['Metascore']) && is_numeric($omdb['Metascore'])) $mc_score = (int)$omdb['Metascore'];
        if (!empty($omdb['tomatoURL']) && stripos($omdb['tomatoURL'], 'rottentomatoes.com') !== false) {
            $rt_url = $omdb['tomatoURL'];
        }
        if ($mc_score !== null) {
            $mc_url = mc_url_from_imdb($row['imdb']);
        }
    }

    $tmdb_url = null;
    if (!empty($row['tmdb_id']) && !empty($row['tmdb_type'])) {
        if ($row['tmdb_type'] === 'movie') $tmdb_url = 'https://www.themoviedb.org/movie/' . $row['tmdb_id'];
        elseif ($row['tmdb_type'] === 'tv') $tmdb_url = 'https://www.themoviedb.org/tv/' . $row['tmdb_id'];
    }

    return [
      'display_title'    => ($aka_pair ?: ($title ?: $row['imdb'])),
      'title_kind'       => $title_kind,
      'he_title'         => $he_title,
      'year'             => $year,
      'overview_he'      => $overview_he,
      'overview_en'      => $overview_en,
      'poster'           => $poster,
      'trailer'          => $trailer,
      'genres'           => $genres,
      'languages'        => $languages,
      'countries'        => $countries,
      'runtime'          => $runtime,
      'directors'        => $directors,
      'writers'          => $writers,
      'producers'        => $producers,
      'composers'        => $composers,
      'cinematographers' => $cinematographers,
      'cast'             => $cast,
      'networks'         => $networks,
      'imdb_rating'      => $imdb_rating,
      'imdb_votes'       => $imdb_votes,
      'imdb_id'          => $row['imdb'],
      'is_tv'            => $row['is_tv'],
      'tvdb_url'         => $tvdb_url,
      'seasons'          => $num_seasons,
      'episodes'         => $num_episodes,
      'akas'             => $akas_all,
      'rt_score'         => $rt_score,
      'rt_url'           => $rt_url,
      'mc_score'         => $mc_score,
      'mc_url'           => $mc_url,
      'tmdb_url'         => $tmdb_url,
    ];
  }
}

/* ==================================================
   Build list
   ================================================== */
$movies=[];
foreach($imdbIDs as $tt){ if(preg_match('~^tt\d{6,10}$~',$tt)) $movies[]=build_row($tt,$TMDB_KEY,$RAPIDAPI_KEY); }
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>🎬 פרטי כותר — תצוגה מרוכזת (v2025-08-28-b15)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0f1115; --card:#151924; --muted:#8a90a2; --text:#e7ecff; --chip:#1e2433; --accent:#5b8cff; --line:#22283a; }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Hebrew",Arial;direction:rtl;background:var(--bg);color:var(--text);margin:0;padding:24px}
    .wrap{max-width:1100px;margin:0 auto}
    h2{margin:0 0 18px;font-weight:700;letter-spacing:.2px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden}
    .row{display:grid;grid-template-columns:320px 1fr;gap:0}
    .poster{padding:18px;border-inline-end:1px solid var(--line);background:linear-gradient(180deg,#161b26,#131723)}
    img.poster-img{display:block;width:100%;height:auto;border-radius:10px;border:1px solid var(--line)}
    .content{padding:20px 20px 10px}
    .title{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px}
    .title h3{margin:0;font-size:24px;line-height:1.25}
    .subtitle{color:var(--muted)}
    .chips{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
    .chip{background:var(--chip);border:1px solid var(--line);padding:6px 10px;border-radius:999px;font-size:13px}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-top:6px}
    .section{margin-top:14px;padding-top:12px;border-top:1px solid var(--line)}
    .kv{margin:0;font-size:14px}
    .label{color:var(--muted')}
    .links a,.conn-list a{color:var(--accent);text-decoration:none}
    .links a:hover,.conn-list a:hover{text-decoration:underline}
    .ratings{display:flex;flex-wrap:wrap;gap:14px}
    .pill{background:#121623;border:1px solid var(--line);border-radius:12px;padding:8px 12px;font-size:14px;display:inline-block}
    .comma-list{margin:0}
    .hidden{display:none}
    .btn-toggle{cursor:pointer;background:#121623;border:1px solid var(--line);color:var(--text);border-radius:10px;padding:6px 10px;margin-top:8px}
    .ellipsis{color:var(--muted)}
    .debug{margin:10px 0 18px;background:#101522;border:1px dashed #31406a;padding:10px;border-radius:10px;font-size:13px;white-space:pre-wrap}
  </style>
</head>
<body>
<div class="wrap">
  <h2>🎬 פרטי כותר — תצוגה מרוכזת <small style="color:#8a90a2">v2025-08-28-b15</small></h2>

  <?php foreach($movies as $movie): $u = unify_details($movie, $TMDB_KEY, $TVDB_KEY); $ttid = preg_replace('~\D~','',$u['imdb_id']); ?>

    <?php
      $connections = imdb_connections_all($u['imdb_id']);
      $conn_order  = connections_keep_labels();
      $hasConnections = false; foreach ($conn_order as $lab){ if (!empty($connections[$lab])) { $hasConnections = true; break; } }
    ?>

    <div class="card" style="margin-bottom:16px">
      <div class="row">
        <div class="poster">
          <?php if (!empty($u['poster'])): ?>
            <img class="poster-img" src="<?= H($u['poster']) ?>" alt="Poster" loading="lazy" decoding="async">
          <?php endif; ?>
        </div>
        <div class="content">
          <div class="title">
            <h3><?= H($u['display_title']) ?></h3>
            <?php if ($u['year']): ?><span class="subtitle">(<?= H($u['year']) ?>)</span><?php endif; ?>
          </div>

          <div class="chips">
            <span class="chip"><?= H($u['title_kind']) ?></span>
            <?php if (!empty($u['languages'])): ?><span class="chip"><?= safeJoin($u['languages']) ?></span><?php endif; ?>
            <?php if (!empty($u['countries'])): ?><span class="chip"><?= safeJoin($u['countries']) ?></span><?php endif; ?>
            <?php if (!empty($u['runtime'])): ?><span class="chip"><?= H($u['runtime']) ?></span><?php endif; ?>
            <?php if ($u['is_tv'] && !empty($u['seasons'])): ?><span class="chip">Seasons: <?= H($u['seasons']) ?></span><?php endif; ?>
            <?php if ($u['is_tv'] && !empty($u['episodes'])): ?><span class="chip">Episodes: <?= H($u['episodes']) ?></span><?php endif; ?>
            <?php if (!empty($u['networks'])): ?><span class="chip"><?= safeJoin($u['networks']) ?></span><?php endif; ?>
          </div>

          <?php if (!empty($u['he_title'])): ?>
            <p class="kv"><span class="label">שם בעברית:</span> <?= H($u['he_title']) ?></p>
          <?php endif; ?>

          <?php if (!empty($u['genres'])): ?>
            <p class="kv"><span class="label">ז׳אנרים (IMDb<?= $u['is_tv']?'+TVDB':'' ?>):</span> <?= safeJoin($u['genres']) ?></p>
          <?php endif; ?>

          <div class="section">
            <div class="ratings">
              <?php if ($u['imdb_rating']!==null): ?>
                <span class="pill">IMDb: <?= H($u['imdb_rating']) ?>/10<?= $u['imdb_votes']!==null ? ' • '.number_format((int)$u['imdb_votes']).' votes' : '' ?></span>
              <?php endif; ?>

              <?php if ($u['rt_score'] !== null): ?>
                <?php if (!empty($u['rt_url'])): ?>
                  <a class="pill" href="<?= H($u['rt_url']) ?>" target="_blank" rel="noopener">Rotten Tomatoes: <?= H($u['rt_score']) ?>%</a>
                <?php else: ?>
                  <span class="pill">Rotten Tomatoes: <?= H($u['rt_score']) ?>%</span>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($u['mc_score'] !== null): ?>
                <?php if (!empty($u['mc_url'])): ?>
                  <a class="pill" href="<?= H($u['mc_url']) ?>" target="_blank" rel="noopener">Metacritic: <?= H($u['mc_score']) ?>/100</a>
                <?php else: ?>
                  <span class="pill">Metacritic: <?= H($u['mc_score']) ?>/100</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="section links">
            <div class="grid">
              <?php $imdb_id_str = is_array($u['imdb_id']) ? reset($u['imdb_id']) : $u['imdb_id']; ?>
              <p class="kv"><span class="label">IMDb ID:</span> <?= H($imdb_id_str) ?> — <a href="<?= H('https://www.imdb.com/title/'.$imdb_id_str.'/') ?>" target="_blank" rel="noopener">Open</a></p>
              <?php if ($u['is_tv'] && !empty($u['tvdb_url'])): ?>
                <?php $tvdb_url_str = is_array($u['tvdb_url']) ? reset($u['tvdb_url']) : $u['tvdb_url']; ?>
                <p class="kv"><span class="label">TVDB:</span> <a href="<?= H($tvdb_url_str) ?>" target="_blank" rel="noopener"><?= H($tvdb_url_str) ?></a></p>
              <?php endif; ?>
              <?php if (!empty($u['tmdb_url'])): ?>
                <p class="kv"><span class="label">TMDb:</span> <a href="<?= H($u['tmdb_url']) ?>" target="_blank" rel="noopener"><?= H($u['tmdb_url']) ?></a></p>
              <?php endif; ?>
              <?php if (!empty($u['trailer'])): ?>
                <p class="kv"><span class="label">טריילר:</span> <a href="<?= H($u['trailer']) ?>" target="_blank" rel="noopener"><?= H($u['trailer']) ?></a></p>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($u['overview_he']) || !empty($u['overview_en'])): ?>
            <div class="section">
              <?php if (!empty($u['overview_he'])): ?>
                <p class="kv"><span class="label">תקציר:</span> <?= H($u['overview_he']) ?></p>
              <?php endif; ?>
              <?php if (!empty($u['overview_en'])): ?>
                <p class="kv"><span class="label">תקציר (EN):</span> <?= H($u['overview_en']) ?></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="section">
            <div class="grid">
              <?php if (!empty($u['directors'])): ?><p class="kv"><span class="label">Directors:</span> <?= safeJoin($u['directors']) ?></p><?php endif; ?>
              <?php if (!empty($u['writers'])): ?><p class="kv"><span class="label">Writers:</span> <?= safeJoin($u['writers']) ?></p><?php endif; ?>
              <?php if (!empty($u['producers'])): ?><p class="kv"><span class="label">Producers:</span> <?= safeJoin($u['producers']) ?></p><?php endif; ?>
              <?php if (!empty($u['composers'])): ?><p class="kv"><span class="label">Composers:</span> <?= safeJoin($u['composers']) ?></p><?php endif; ?>
              <?php if (!empty($u['cinematographers'])): ?><p class="kv"><span class="label">Cinematographers:</span> <?= safeJoin($u['cinematographers']) ?></p><?php endif; ?>
            </div>
          </div>

          <?php if (!empty($u['cast'])): ?>
            <div class="section">
              <p class="kv"><span class="label">שחקנים:</span></p>
              <?php
                $limit=(int)$CAST_LIMIT;
                $items=array_values(array_filter($u['cast'], fn($x)=>trim((string)$x)!==''));
                $first=array_slice($items,0,$limit);
                $rest =array_slice($items,$limit);
              ?>
              <p class="comma-list" dir="rtl">
                <?= safeJoin($first) ?>
                <?php if (!empty($rest)): ?>
                  , <span class="ellipsis" id="ell-<?= H($ttid) ?>">…</span>
                  <span id="<?= H($ttid) ?>" class="more hidden">, <?= safeJoin($rest) ?></span>
                <?php endif; ?>
              </p>
              <?php if (!empty($rest)): ?>
                <button class="btn-toggle" type="button" data-toggle="<?= H($ttid) ?>" data-open="false">הצג הכל</button>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($u['akas'])): ?>
            <div class="section">
              <p class="kv"><span class="label">AKAs:</span></p>
              <?php $aid='akas-'.$ttid; ?>
              <p class="comma-list" dir="rtl">
                <span class="ellipsis" id="ell-<?= H($aid) ?>">…</span>
                <span id="<?= H($aid) ?>" class="more hidden"><?= safeJoin($u['akas']) ?></span>
              </p>
              <button class="btn-toggle" type="button" data-toggle="<?= H($aid) ?>" data-open="false">הצג הכל</button>
            </div>
          <?php endif; ?>

          <?php if ($hasConnections): ?>
            <div class="section">
              <h4 style="margin:0 0 8px 0">IMDb Connections</h4>
              <?php foreach ($conn_order as $lab): $arr = $connections[$lab] ?? []; if (!$arr) continue; ?>
                <p class="kv">
                  <span class="label"><?= H($lab) ?>:</span>
                  <span class="conn-list">
                    <?php
                      $links=[];
                      foreach ($arr as $it){
                        $tid = $it['id'] ?? null;
                        $t   = $it['title'] ?? $tid;
                        if ($tid) $links[] = '<a href="'.H(local_search_link($tid)).'" target="_blank" rel="noopener">'.H($t).'</a>';
                        else      $links[] = H($t);
                      }
                      echo implode(', ', $links);
                    ?>
                  </span>
                </p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
  function toggleMore(btn){
    var id = btn.getAttribute('data-toggle');
    var more = document.getElementById(id);
    var ell = document.getElementById('ell-'+id);
    var open = btn.getAttribute('data-open') === 'true';
    if(!more) return;

    if(open){
      more.classList.add('hidden');
      if(ell) ell.classList.remove('hidden');
      btn.textContent = 'הצג הכל';
      btn.setAttribute('data-open','false');
      btn.setAttribute('aria-expanded','false');
    }else{
      more.classList.remove('hidden');
      if(ell) ell.classList.add('hidden');
      btn.textContent = 'הצג פחות';
      btn.setAttribute('data-open','true');
      btn.setAttribute('aria-expanded','true');
    }
  }
  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('.btn-toggle');
    if(btn) toggleMore(btn);
  });
  document.addEventListener('keyup', function(e){
    if(e.key !== 'Enter' && e.key !== ' ') return;
    var el = document.activeElement;
    if(el && el.classList && el.classList.contains('btn-toggle')) toggleMore(el);
  });
</script>

</body>
</html>