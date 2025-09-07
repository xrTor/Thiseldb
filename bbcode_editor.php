<?php
/**
 * bbcode_editor.php — Include-only BBCode Editor עם אייקוני FontAwesome
 * ודא/י שב-<head> נטען FontAwesome:
 * <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
 */
?>
<style>
/* ===== בסיס ===== */
.bb-editor{position:relative;}
.bb-toolbar{
  display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:6px;
  padding:6px;margin:8px 0;border:1px solid #ddd;background:#fdfdfd;border-radius:6px
}
.bb-toolbar button,.bb-toolbar select{
  height:34px;min-width:34px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#000;
  font:13px Arial,sans-serif;cursor:pointer;display:flex;justify-content:center;align-items:center
}
.bb-toolbar button i{font-size:14px}
.bb-toolbar button:hover,.bb-toolbar select:hover{background:#f3f3f3}
.bb-toolbar select{padding:0 6px;height:34px;width:auto}

/* ===== Color palette (scoped, prefixed with cp-) ===== */
.bb-editor .color-palette{
  position:absolute;z-index:1000;display:none;background:#fff;border:1px solid #ccc;padding:8px;min-width:360px;
  max-height:300px;overflow-y:auto;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.15)
}
.color-palette .cp-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.color-palette .cp-btn{
  height:28px;padding:0 10px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#000;cursor:pointer;
  font:13px/26px Arial,sans-serif
}
.color-palette .cp-preview{
  display:inline-block;width:70px;height:24px;line-height:24px;text-align:center;border:1px solid #ccc;border-radius:4px;
  font:12px Arial;color:#000;background:#fff
}
.color-palette .cp-row{display:flex;gap:2px;margin:2px 0}
.color-palette .cp-sw{
  width:20px;height:20px;cursor:pointer;border:none;outline:0;position:relative;border-radius:2px
}
.color-palette .cp-sw:hover{box-shadow:0 0 0 2px #333 inset}
.color-palette .cp-sw.selected::after{
  content:"✓";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:12px/1 Arial;
  color:#000;background:rgba(255,255,255,.5)
}
.color-palette .cp-sw.cp-custom{
  width:24px;height:24px;border:1px dashed #666;border-radius:3px;margin-inline-end:4px
}

/* ===== Preview ===== */
.bb-editor .bb-preview{
  border:1px solid #ddd;background:#fff;padding:10px;border-radius:4px;margin-top:10px;min-height:50px;font-size:14px;color:#000
}
.bb-preview .bb-error,.bb-preview .bb-info,.bb-preview .bb-success,.bb-preview .bb-warning{
  display:block;max-width:800px;margin:12px auto;padding:10px;border-radius:4px;font-weight:600;text-align:start
}
.bb-preview .bb-error{background:#ffe9e9;border:1px solid #f2bcbc;color:#a40000}
.bb-preview .bb-info{background:#eaf4ff;border:1px solid #b7d9ff;color:#0b4a99}
.bb-preview .bb-success{background:#eaf9ef;border:1px solid #bfe8cd;color:#1c6e35}
.bb-preview .bb-warning{background:#fff8e6;border:1px solid #ffe1a6;color:#7a4a00}

/* Spoiler / Hide / Mark */
.bb-preview .bb-spoiler{background:#000;color:#000;padding:0 4px;border-radius:3px;cursor:pointer;transition:all .15s}
.bb-preview .bb-spoiler:hover{color:#fff;background:#333}
.bb-preview details summary{cursor:pointer;font-weight:bold}
.bb-preview mark{background:#fffd75;padding:0 2px;border-radius:2px}
/* ודא שהאייקון שחור */
.bb-toolbar button i { color:#000 !important; }

/* בסיס לאייקונים המאוחדים */
.bb-toolbar .btn-case{ position:relative; }

/* תגית קטנה בפינה כדי להבדיל בין upper/lower */
.bb-toolbar .btn-case::after{
  position:absolute; right:4px; bottom:3px;
  font: 900 20px/1 Arial, sans-serif; color:#000; content:'';
}
.bb-toolbar .btn-upper::after{ content:'↑'; }
.bb-toolbar .btn-lower::after{ content:'↓'; }

</style>

<script>
(function(){
  if (window.__bbcode_editor_inited__) return;
  window.__bbcode_editor_inited__ = true;

  /* ===== hidden <input type=color> — מחוץ למסך ===== */
  let hiddenPicker = document.getElementById('bb_hidden_color_picker');
  if (!hiddenPicker){
    hiddenPicker = document.createElement('input');
    hiddenPicker.type = 'color';
    hiddenPicker.id   = 'bb_hidden_color_picker';
    Object.assign(hiddenPicker.style,{
      position:'fixed',left:'-9999px',top:'-9999px',width:'1px',height:'1px',opacity:'0',pointerEvents:'none'
    });
    hiddenPicker.setAttribute('tabindex','-1');
    hiddenPicker.setAttribute('aria-hidden','true');
    document.body.appendChild(hiddenPicker);
  }

  // אין שמירה אוטומטית — נשמור רק בלחיצה על "שמור צבע"
  let activePaletteCtx = null; // {el, mode, previewBox, customSw}
  let chosenColor = '#000000';

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.bb-editor').forEach(editor => {
      if (editor.__toolbarBuilt) return; editor.__toolbarBuilt = true;

      const ta = editor.querySelector('.bb-textarea');
      const preview = editor.querySelector('.bb-preview');
      if (!ta) return;

      /* ===== Toolbar ===== */
      const tb = editor.querySelector('.bb-toolbar') || document.createElement('div');
      tb.className = 'bb-toolbar';
      tb.innerHTML = `
        <button type="button" data-tag="b" title="מודגש"><i class="fa-solid fa-bold"></i></button>
        <button type="button" data-tag="i" title="נטוי"><i class="fa-solid fa-italic"></i></button>
        <button type="button" data-tag="u" title="קו תחתי"><i class="fa-solid fa-underline"></i></button>
        <button type="button" data-tag="s" title="קו חוצה"><i class="fa-solid fa-strikethrough"></i></button>

        <button type="button" data-tag="right" title="יישור ימין"><i class="fa-solid fa-align-right"></i></button>
        <button type="button" data-tag="center" title="מרכז"><i class="fa-solid fa-align-center"></i></button>
        <button type="button" data-tag="left" title="יישור שמאל"><i class="fa-solid fa-align-left"></i></button>
        <button type="button" data-tag="justify" title="מיושר"><i class="fa-solid fa-align-justify"></i></button>

        <button type="button" data-tag="quote" title="ציטוט"><i class="fa-solid fa-quote-right"></i></button>
        <button type="button" data-tag="code" title="קוד"><i class="fa-solid fa-code"></i></button>
        <button type="button" data-tag="spoiler" title="ספוילר"><i class="fa-solid fa-eye-slash"></i></button>
        <button type="button" data-tag="hide" title="הסתר"><i class="fa-solid fa-box-archive"></i></button>

        <button type="button" data-tag="url" title="קישור"><i class="fa-solid fa-link"></i></button>
        <button type="button" data-tag="img" title="תמונה"><i class="fa-solid fa-image"></i></button>
        <button type="button" data-tag="youtube" title="YouTube"><i class="fa-brands fa-youtube"></i></button>

        <button type="button" class="bb-color-btn" data-mode="text" title="צבע טקסט"><i class="fa-solid fa-palette"></i></button>
        <button type="button" class="bb-color-btn" data-mode="bg"   title="צבע רקע"><i class="fa-solid fa-fill-drip"></i></button>
<!-- אותיות גדולות -->
<button type="button" data-action="upper" class="btn-case btn-upper" title="אותיות גדולות">
  <i class="fa-solid fa-font"></i>
</button>

<!-- אותיות קטנות -->
<button type="button" data-action="lower" class="btn-case btn-lower" title="אותיות קטנות">
  <i class="fa-solid fa-font"></i>
</button>
<button type="button" data-tag="mark" title="הדגשה (מרקר)"><i class="fa-solid fa-highlighter"></i></button>

        <button type="button" data-tag="עברית"  style="font-weight:bold;">עברית</button>
        <button type="button" data-tag="אנגלית" style="font-weight:bold;">אנגלית</button>

        <select class="bb-heading" title="כותרת" style="font-weight:bold;">
          <option value="">כותרת</option>
          <option value="h1" style="font-size:2em;font-weight:bold;">H1 כותרת</option>
          <option value="h2" style="font-size:1.5em;font-weight:bold;">H2 כותרת</option>
          <option value="h3" style="font-size:1.17em;font-weight:bold;">H3 כותרת</option>
          <option value="h4" style="font-size:1em;font-weight:bold;">H4 כותרת</option>
          <option value="h5" style="font-size:0.83em;font-weight:bold;">H5 כותרת</option>
          <option value="h6" style="font-size:0.67em;font-weight:bold;">H6 כותרת</option>
        </select>

        <select class="bb-blocks" title="סגנון" style="font-weight:bold;">
          <option value="">סגנון</option>
          <option value="info"    style="background:#eaf4ff;color:#0b4a99;">מידע</option>
          <option value="warning" style="background:#fff8e6;color:#7a4a00;">אזהרה</option>
          <option value="error"   style="background:#ffe9e9;color:#a40000;">שגיאה</option>
          <option value="success" style="background:#eaf9ef;color:#1c6e35;">הצלחה</option>
        </select>

        <button type="button" data-action="clear" title="נקה"><i class="fa-solid fa-broom"></i></button>
      `;
      if (!editor.contains(tb)) editor.insertBefore(tb, ta);

      /* ===== Palette container ===== */
      const palette = editor.querySelector('.color-palette') || (() => {
        const p = document.createElement('div'); p.className='color-palette'; editor.appendChild(p); return p;
      })();

      let currentMode = 'text';
      const lockedColor = {
        text: localStorage.getItem('bb_last_text_color') || '#000000',
        bg:   localStorage.getItem('bb_last_bg_color')   || '#ffff00'
      };

      const updatePreview = () => { if (preview) preview.innerHTML = ta.value ? renderBB(ta.value) : '— ריק —'; };

      const wrap = (openTag, closeTag) => {
        const s=ta.selectionStart,e=ta.selectionEnd,sel=ta.value.substring(s,e);
        ta.setRangeText(openTag+sel+closeTag,s,e,'end'); ta.dispatchEvent(new Event('input')); ta.focus();
      };
      const wrapBB=(tag,param=null)=>wrap(param?`[${tag}=${param}]`:`[${tag}]`,`[/${tag}]`);
      const transformSelection=fn=>{
        const s=ta.selectionStart,e=ta.selectionEnd;if(s===e)return;
        const sel=ta.value.substring(s,e); ta.setRangeText(fn(sel),s,e,'end'); ta.dispatchEvent(new Event('input')); ta.focus();
      };

      /* ===== Toolbar interactions ===== */
      tb.addEventListener('click', ev => {
        const btn = ev.target.closest('button'); if (!btn) return;
        if (!btn.hasAttribute('type')) btn.setAttribute('type','button');
        const action = btn.dataset.action, tag = btn.dataset.tag;

        if (action==='clear'){ ta.value=''; updatePreview(); return; }
        if (action==='upper'){ transformSelection(t=>t.toUpperCase()); updatePreview(); return; }
        if (action==='lower'){ transformSelection(t=>t.toLowerCase()); updatePreview(); return; }

        if (btn.classList.contains('bb-color-btn')){
          currentMode = btn.dataset.mode==='bg' ? 'bg' : 'text';
          buildPalette(palette, currentMode);

          // toggle + מיקום שמאלי צמוד לכפתור (fallback לימין)
          if (palette.style.display==='block' && palette.dataset.mode===currentMode){
            palette.style.display='none';
            return;
          }
          palette.dataset.mode = currentMode;

          const gap=4;
          palette.style.visibility='hidden';
          palette.style.display='block';

          requestAnimationFrame(()=>{
            const br=btn.getBoundingClientRect(), er=editor.getBoundingClientRect();
            const palW=palette.offsetWidth, palH=palette.offsetHeight;

            let left=(br.left-er.left)-palW-gap;
            let top =(br.top -er.top )+editor.scrollTop;

            if (left<8) left=(br.right-er.left)+gap;              // אם אין מקום לשמאל — מעבר לימין
            if (top+palH>editor.clientHeight+editor.scrollTop){   // אם גולש תחתית — עוגן מעלה
              top=(br.bottom-er.top)+editor.scrollTop-palH;
            }
            if (top<8) top=8;

            const maxLeft=editor.clientWidth-palW-8;
            if (left>maxLeft) left=Math.max(8,maxLeft);

            palette.style.left=left+'px';
            palette.style.top =top +'px';
            palette.style.visibility='visible';
          });
          return;
        }

        if (tag){
          if (['b','i','u','s','quote','code','spoiler','hide','mark','left','center','right','justify','url','img','youtube'].includes(tag)){ wrapBB(tag); updatePreview(); return; }
          if (tag==='עברית'||tag==='אנגלית'){ wrapBB(tag); updatePreview(); return; }
        }
      });

      // רשימות נפתחות — שמירה כפי שהיה
      const headSel = tb.querySelector('.bb-heading');
      if (headSel) headSel.addEventListener('change', function(){ if(!this.value)return; wrapBB(this.value); this.value=''; updatePreview(); });
      const blockSel = tb.querySelector('.bb-blocks');
      if (blockSel) blockSel.addEventListener('change', function(){ if(!this.value)return; wrapBB(this.value); this.value=''; updatePreview(); });

      /* ===== סגירת הפלטה ===== */
      document.addEventListener('mousedown', e=>{
        if (!palette.contains(e.target) && !e.target.closest('.bb-color-btn')) palette.style.display='none';
      });
      document.addEventListener('keydown', e=>{ if(e.key==='Escape') palette.style.display='none'; });
      window.addEventListener('resize', ()=> palette.style.display='none');

      /* ===== Build Palette ===== */
      function buildPalette(el, mode){
        el.innerHTML='';

        const head=document.createElement('div'); head.className='cp-head';
        const customSw=document.createElement('span'); customSw.className='cp-sw cp-custom'; customSw.title='צבע מותאם';
        const titleBtn=document.createElement('button'); titleBtn.type='button'; titleBtn.className='cp-btn'; titleBtn.textContent='בחר גוון';
        const previewBox=document.createElement('span'); previewBox.className='cp-preview'; previewBox.textContent='Aa';
        const saveBtn=document.createElement('button'); saveBtn.type='button'; saveBtn.className='cp-btn'; saveBtn.textContent='שמור צבע';

        head.appendChild(customSw);
        head.appendChild(titleBtn);
        head.appendChild(previewBox);
        head.appendChild(saveBtn);
        el.appendChild(head);

        // צבע נוכחי (זיכרון אחרון) — רק להצגה, לא שמירה אוטומטית
        chosenColor = mode==='bg' ? (localStorage.getItem('bb_last_bg_color')||lockedColor.bg)
                                  : (localStorage.getItem('bb_last_text_color')||lockedColor.text);
        applyPreview(previewBox, mode, chosenColor);

        // פתיחת ה־color picker (ללא שמירה אוטומטית)
        titleBtn.addEventListener('click', ()=>{
          openPickerNear(previewBox, normalizeHex(chosenColor));
          activePaletteCtx = {el, mode, previewBox, customSw};
        });

        // שמירה ידנית בלבד
        saveBtn.addEventListener('click', ()=> commitColor(mode, chosenColor, el));

        // גריד צבעים
        const colors = [
          '#FFFFFF','#F2F2F2','#E6E6E6','#CCCCCC','#999999','#666666','#333333','#000000',
          '#FFEBEE','#FFCDD2','#EF9A9A','#E57373','#F44336','#D32F2F','#B71C1C','#880E4F',
          '#F3E5F5','#E1BEE7','#CE93D8','#BA68C8','#9C27B0','#7B1FA2','#4A148C','#311B92',
          '#E8EAF6','#C5CAE9','#9FA8DA','#7986CB','#3F51B5','#303F9F','#1A237E','#0D47A1',
          '#E1F5FE','#B3E5FC','#81D4FA','#4FC3F7','#03A9F4','#0288D1','#01579B','#004D40',
          '#E0F2F1','#B2DFDB','#80CBC4','#4DB6AC','#009688','#00796B','#004D40','#00251A',
          '#F1F8E9','#DCEDC8','#C5E1A5','#AED581','#8BC34A','#689F38','#33691E','#1B5E20',
          '#FFFDE7','#FFF9C4','#FFF59D','#FFF176','#FFEB3B','#FBC02D','#F57F17','#FF6F00',
          '#FFF3E0','#FFE0B2','#FFCC80','#FFB74D','#FF9800','#F57C00','#E65100','#BF360C',
          '#F0F4C3','#E6EE9C','#DCE775','#D4E157','#CDDC39','#C0CA33','#AFB42B','#9E9D24'
        ];
        for(let i=0;i<colors.length;i+=8){
          const row=document.createElement('div'); row.className='cp-row';
          colors.slice(i,i+8).forEach(c=>{
            const sw=document.createElement('span'); sw.className='cp-sw'; sw.style.background=c; sw.dataset.color=c;
            row.appendChild(sw);
          });
          el.appendChild(row);
        }

        markSelectionOrCustom(chosenColor, el, customSw);
        applyPreview(previewBox, mode, chosenColor);

        // ריבועים: hover = תצוגה, click = בחירה (ללא שמירה)
        el.querySelectorAll('.cp-sw:not(.cp-custom)').forEach(sw=>{
          const c=sw.dataset.color;
          sw.addEventListener('mouseenter',()=>applyPreview(previewBox,mode,c));
          sw.addEventListener('mouseleave',()=>applyPreview(previewBox,mode,chosenColor));
          sw.addEventListener('click',()=>{
            chosenColor=c;
            markSelectionOrCustom(chosenColor, el, customSw);
            applyPreview(previewBox,mode,chosenColor);
          });
        });

        // custom swatch → פתיחת picker
        customSw.addEventListener('click', ()=>{
          openPickerNear(previewBox, normalizeHex(chosenColor));
          activePaletteCtx = {el, mode, previewBox, customSw};
        });
      }

      /* ===== hidden color input handlers — ללא שמירה ===== */
      hiddenPicker.addEventListener('input', ()=>{
        if (!activePaletteCtx) return;
        chosenColor = hiddenPicker.value;
        applyPreview(activePaletteCtx.previewBox, activePaletteCtx.mode, chosenColor);
        markSelectionOrCustom(chosenColor, activePaletteCtx.el, activePaletteCtx.customSw);
      });
      hiddenPicker.addEventListener('change', ()=>{
        if (!activePaletteCtx) return;
        chosenColor = hiddenPicker.value;
        applyPreview(activePaletteCtx.previewBox, activePaletteCtx.mode, chosenColor);
        markSelectionOrCustom(chosenColor, activePaletteCtx.el, activePaletteCtx.customSw);
        // אין commit כאן!
      });

      /* ===== Utils ===== */
      function commitColor(mode, color, paletteEl){
        // שמירה לזיכרון רק בעת לחיצה על "שמור צבע"
        if (mode==='bg'){
          localStorage.setItem('bb_last_bg_color', color);
          wrapBB('bg', color);
        }else{
          localStorage.setItem('bb_last_text_color', color);
          wrapBB('color', color);
        }
        updatePreview();
        if (paletteEl) paletteEl.style.display='none';
        activePaletteCtx = null;
      }

      function applyPreview(box, mode, color){
        if (mode==='bg'){ box.style.background=color; box.style.color='#000'; }
        else            { box.style.color=color;    box.style.background='#fff'; }
      }
      function markSelectionOrCustom(color, root, customSw){
        root.querySelectorAll('.cp-sw').forEach(x=>x.classList.remove('selected'));
        const hex=normalizeHex(color);
        const match=[...root.querySelectorAll('.cp-sw:not(.cp-custom)')].find(x=>normalizeHex(x.dataset.color)===hex);
        if (match){
          match.classList.add('selected');
          customSw.style.background=''; customSw.style.display='none';
        }else{
          customSw.style.display='inline-block';
          customSw.style.background=hex;
          customSw.classList.add('selected');
        }
      }
      function normalizeHex(h){
        h=(h||'').trim().toLowerCase();
        if(/^#[0-9a-f]{3}$/.test(h)){return '#'+[h[1],h[2],h[3]].map(x=>x+x).join('');}
        return h;
      }

      function openPickerNear(anchorEl, valueHex){
        if (valueHex) hiddenPicker.value = valueHex;
        // העברה פיזית קרובה לאזור הכפתור כדי למנוע קפיצה במסך
        const r = anchorEl.getBoundingClientRect();
        hiddenPicker.style.left = Math.round(r.left + 8) + 'px';
        hiddenPicker.style.top  = Math.round(r.bottom + 8) + 'px';
        const sx=window.scrollX, sy=window.scrollY;
        hiddenPicker.click();
        // שחזור גלילה אם הדפדפן הזיז
        setTimeout(()=>window.scrollTo(sx, sy), 0);
      }

      /* ===== BBCode → HTML ===== */
      function renderBB(s){
        if(!s) return '';
        s=s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        s=s.replace(/\[h([1-6])\]([\s\S]*?)\[\/h\1\]/gi,(_,n,c)=>`<h${n}>${c}</h${n}>`);

        s=s.replace(/\[b\]([\s\S]*?)\[\/b\]/gi,'<strong>$1</strong>');
        s=s.replace(/\[i\]([\s\S]*?)\[\/i\]/gi,'<em>$1</em>');
        s=s.replace(/\[u\]([\s\S]*?)\[\/u\]/gi,'<u>$1</u>');
        s=s.replace(/\[s\]([\ס\S]*?)\[\/s\]/gi,'<s>$1</s>');
        s=s.replace(/\[mark\]([\s\S]*?)\[\/mark\]/gi,'<mark>$1</mark>');

        s=s.replace(/\[color=([#a-z0-9]+)\]([\s\S]*?)\[\/color\]/gi,'<span style="color:$1">$2</span>');
        s=s.replace(/\[bg=([#a-z0-9]+)\]([\s\S]*?)\[\/bg\]/gi,'<span style="background:$1">$2</span>');

        s=s.replace(/\[left\]([\s\S]*?)\[\/left\]/gi,'<div style="text-align:left">$1</div>');
        s=s.replace(/\[center\]([\s\S]*?)\[\/center\]/gi,'<div style="text-align:center">$1</div>');
        s=s.replace(/\[right\]([\s\S]*?)\[\/right\]/gi,'<div style="text-align:right">$1</div>');
        s=s.replace(/\[justify\]([\s\S]*?)\[\/justify\]/gi,'<div style="text-align:justify">$1</div>');

        s=s.replace(/\[url=([^\]]+)\]([\s\S]*?)\[\/url\]/gi,'<a href="$1" target="_blank" rel="nofollow">$2</a>');
        s=s.replace(/\[url\]([\s\S]*?)\[\/url\]/gi,'<a href="$1" target="_blank" rel="nofollow">$1</a>');
        s=s.replace(/\[img\]([\s\S]*?)\[\/img\]/gi,'<img src="$1" alt="" style="max-width:100%;">');
        s=s.replace(/\[youtube\]([\s\S]*?)\[\/youtube\]/gi,'<div class="bb-youtube">$1</div>');

        s=s.replace(/\[quote\]([\s\S]*?)\[\/quote\]/gi,'<blockquote>$1</blockquote>');
        s=s.replace(/\[code\]([\s\S]*?)\[\/code\]/gi,'<pre class="bb-code"><code>$1</code></pre>');
        s=s.replace(/\[spoiler\]([\s\S]*?)\[\/spoiler\]/gi,'<span class="bb-spoiler">$1</span>');
        s=s.replace(/\[hide\]([\s\S]*?)\[\/hide\]/gi,'<details><summary>הצג/הסתר</summary>$1</details>');

        s=s.replace(/\[info\]([\s\S]*?)\[\/info\]/gi,'<div class="bb-info">$1</div>');
        s=s.replace(/\[warning\]([\s\S]*?)\[\/warning\]/gi,'<div class="bb-warning">$1</div>');
        s=s.replace(/\[error\]([\s\S]*?)\[\/error\]/gi,'<div class="bb-error">$1</div>');
        s=s.replace(/\[success\]([\s\S]*?)\[\/success\]/gi,'<div class="bb-success">$1</div>');

        s=s.replace(/\[עברית\]([\s\S]*?)\[\/עברית\]/g,'<section dir="rtl">$1</section>');
        s=s.replace(/\[אנגלית\]([\s\S]*?)\[\/אנגלית\]/g,'<section dir="ltr">$1</section>');

        s=s.replace(/\r?\n/g,'<br>');
        return s;
      }

      ta.addEventListener('input', updatePreview);
      updatePreview();
    });
  });
})();
</script>
