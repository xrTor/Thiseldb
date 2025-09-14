<?php
/**
 * bbcode_editor.php — Include-only BBCode Editor (RTL)
 */
?>
<style>
/* ===== סרגל כלים ===== */
.bb-editor{position:relative;}
.bb-toolbar{
  display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px;
  padding:8px;margin-bottom:10px;border:1px solid #ddd;background:#fdfdfd;border-radius:10px
}
.bb-toolbar button,.bb-toolbar select{
  height:36px;min-width:36px;border:1px solid #cfcfcf;border-radius:8px;background:#fff;color:#000;
  font:13px/1 Arial, sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 12px
}
.bb-toolbar button i{font-size:14px;color:#000!important}
.bb-toolbar button:hover,.bb-toolbar select:hover{background:#f3f3f3}
.bb-toolbar select{padding:0 12px;height:36px}
.bb-toolbar .sep{width:1px;height:22px;background:#e2e2e2;border-radius:1px;margin:0 2px}

/* --- Style for custom images in buttons --- */
.bb-toolbar button img {
  width: 28px;
  height: 28px;
  vertical-align: middle;
  object-fit: contain;
}

/* תצוגת גופן חיה */
.font-preview{
  padding:0 12px;height:36px;display:flex;align-items:center;justify-content:center;
  border:1px solid #cfcfcf;border-radius:8px;background:#fff;min-width:44px
}

/* ===== Color palette ===== */
.bb-editor .color-palette{
  position:absolute;z-index:1000;display:none;background:#fff;border:1px solid #ccc;padding:8px;min-width:360px;
  max-height:300px;overflow-y:auto;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.15)
}
.color-palette .cp-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.color-palette .cp-btn{ height:30px;padding:0 12px;border:1px solid #ccc;border-radius:8px;background:#fff;color:#000;cursor:pointer; font:13px/28px Arial }
.color-palette .cp-preview{ display:inline-block;width:74px;height:26px;line-height:26px;text-align:center;border:1px solid #ccc;border-radius:8px; font:12px Arial;color:#000;background:#fff }
.color-palette .cp-row{display:flex;gap:2px;margin:2px 0}
.color-palette .cp-sw{width:22px;height:22px;cursor:pointer;border:none;outline:0;position:relative;border-radius:4px}
.color-palette .cp-sw:hover{box-shadow:0 0 0 2px #333 inset}
.color-palette .cp-sw.selected::after{ content:"✓";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:12px/1 Arial; color:#000;background:rgba(255,255,255,.5) }
.color-palette .cp-sw.cp-custom{ width:24px;height:24px;border:1px dashed #666;border-radius:5px;margin-inline-end:4px }

/* ===== Preview ===== */
.bb-editor .bb-preview{ border:1px solid #ddd;background:#fff;padding:12px;border-radius:10px;margin-top:10px;min-height:60px;font-size:14px;color:#000 }

/* ===== עיצוב אופציות נגללות ===== */
.bb-toolbar select option[disabled]{color:#999}
.bb-toolbar .opt-h1{font-weight:bold;font-size:1.9em}
.bb-toolbar .opt-h2{font-weight:bold;font-size:1.6em}
.bb-toolbar .opt-h3{font-weight:bold;font-size:1.35em}
.bb-toolbar .opt-info{background:#eaf4ff;color:#0b4a99}
.bb-toolbar .opt-warning{background:#fff8e6;color:#7a4a00}
.bb-toolbar .opt-error{background:#ffe9e9;color:#a40000}
.bb-toolbar .opt-success{background:#eaf9ef;color:#1c6e35}
</style>

<script>
(function(){
  if (window.__bbcode_editor_inited__) return;
  window.__bbcode_editor_inited__ = true;

  const customIcons = {
    // --- עיצוב טקסט בסיסי ---
    // 'b': 'images/bbcode/bold.png',          // מודגש
    // 'i': 'images/bbcode/italic.png',          // נטוי
    // 'u': 'images/bbcode/underline.png',          // קו תחתון
    // 's': 'images/bbcode/strike.png',          // קו חוצה
    'sub': '',        // כתב תחתי
    'sup': '',        // כתב עילי
    'small': '',      // טקסט קטן
    'big': '',        // טקסט גדול
    'kbd': '',        // מקלדת
    'mark': '',       // מרקר

    // --- יישור ופסקאות ---
    'right': '',      // יישור לימין
    'center': '',     // יישור למרכז
    'left': '',       // יישור לשמאל
    'justify': '',    // יישור לשני הצדדים
    'indent': '',     // הזחה

    // --- בלוקים ותוכן מיוחד ---
    'quote': 'images/bbcode/quote.svg',      // ציטוט
    'code': 'images/bbcode/code.svg',       // קוד
    'pre': '',        // Preformatted
    'noparse': '',    // אל תפרק
    'spoiler': 'images/bbcode/spoiler.svg',    // ספוילר
    'hide': '',       // הסתר/הצג

    // --- מדיה וקישורים ---
    'url': 'images/bbcode/link.png',        // קישור
    'email': '',      // אימייל
    'img': 'images/bbcode/image.png',        // תמונה
    'youtube': 'images/bbcode/youtube.svg',    // יוטיוב
    'video': '',      // וידאו
    'audio': '',      // אודיו

    // --- צבעים ואותיות ---
    'color': 'images/bbcode/color.png',      // מזהה מיוחד: צבע טקסט
    'bg': '',         // מזהה מיוחד: צבע רקע
    'upper': '',      // אותיות גדולות
    'lower': '',      // אותיות קטנות

    // --- רשימות, טבלאות וקווים ---
    'ul': '',         // רשימה (תבליטים)
    'ol': '',         // רשימה (מספרים)
    'table': '',      // טבלה
    'hr': '',         // קו אופקי
    'br': '',         // ירידת שורה

    // --- בלוקי שפה ---
    'עברית': '',     // כפתור "א"
    'אנגלית': '',   // כפתור "A"

    // --- כלים ---
    'clear': 'images/bbcode/clear.svg',      // נקה הכל
  };

  let hiddenPicker = document.getElementById('bb_hidden_color_picker');
  if (!hiddenPicker){
    hiddenPicker = document.createElement('input');
    hiddenPicker.type = 'color';
    hiddenPicker.id   = 'bb_hidden_color_picker';
    Object.assign(hiddenPicker.style,{ position:'fixed',left:'-9999px',top:'-9999px',width:'1px',height:'1px',opacity:'0',pointerEvents:'none' });
    hiddenPicker.setAttribute('tabindex','-1');
    document.body.appendChild(hiddenPicker);
  }

  let activePaletteCtx = null;
  let chosenColor = '#000000';

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.bb-editor').forEach(editor => {
      if (editor.__toolbarBuilt) return; editor.__toolbarBuilt = true;

      const allTas = editor.querySelectorAll('.bb-textarea');
      if (allTas.length === 0) return;

      let activeTa = allTas[0];
      allTas.forEach(ta => ta.addEventListener('focus', () => activeTa = ta));

      const tb = editor.querySelector('.bb-toolbar') || document.createElement('div');
      tb.className = 'bb-toolbar';

      const size10 = Array.from({length:10},(_,i)=>{ const n=i+1; const em = 0.7 + i*0.15; return `<option value="${n}" style="font-size:${em}em">${n}</option>`; }).join('');
      const pxOpts = ['12','14','16','18','24','32','40'].map(px=>`<option value="${px}" style="font-size:${Math.min(px/14,2)}em">${px}px</option>`).join('');
      const fontList = [
        'Rubik', 'Assistant', 'Heebo', 'Arimo', 'Frank Ruhl Libre', 'David Libre',
        'Roboto', 'Open Sans', 'Lato', 'Montserrat', 
        'Merriweather', 'Playfair Display',
        'Oswald', 'Lobster',
        'Fira Code'
      ];
      
      tb.innerHTML = `
        <button type="button" data-tag="b" title="מודגש"><i class="fa-solid fa-bold"></i></button>
        <button type="button" data-tag="i" title="נטוי"><i class="fa-solid fa-italic"></i></button>
        <button type="button" data-tag="u" title="קו תחתי"><i class="fa-solid fa-underline"></i></button>
        <button type="button" data-tag="s" title="קו חוצה"><i class="fa-solid fa-strikethrough"></i></button>
        <button type="button" data-tag="sub" title="כתב תחתון"><i class="fa-solid fa-subscript"></i></button>
        <button type="button" data-tag="sup" title="כתב עילי"><i class="fa-solid fa-superscript"></i></button>
        <button type="button" data-tag="small" title="הקטן טקסט"><i class="fa-solid fa-minus"></i></button>
        <button type="button" data-tag="big" title="הגדל טקסט"><i class="fa-solid fa-plus"></i></button>
        <button type="button" data-tag="kbd" title="kbd"><i class="fa-regular fa-keyboard"></i></button>
        <button type="button" data-tag="mark" title="מרקר"><i class="fa-solid fa-highlighter"></i></button>
        <span class="sep"></span>
        <button type="button" data-tag="right" title="יישור ימין"><i class="fa-solid fa-align-right"></i></button>
        <button type="button" data-tag="center" title="מרכז"><i class="fa-solid fa-align-center"></i></button>
        <button type="button" data-tag="left" title="יישור שמאל"><i class="fa-solid fa-align-left"></i></button>
        <button type="button" data-tag="justify" title="מיושר"><i class="fa-solid fa-align-justify"></i></button>
        <button type="button" data-tag="indent" title="הזחה"><i class="fa-solid fa-indent"></i></button>
        <span class="sep"></span>
        <button type="button" data-tag="quote" title="ציטוט"><i class="fa-solid fa-quote-right"></i></button>
        <button type="button" data-tag="code" title="קוד"><i class="fa-solid fa-code"></i></button>
        <button type="button" data-tag="pre" title="Preformatted"><i class="fa-solid fa-file-prescription"></i></button>
        <button type="button" data-tag="noparse" title="אל תפרק"><i class="fa-solid fa-ban"></i></button>
        <button type="button" data-tag="spoiler" title="ספוילר"><i class="fa-solid fa-eye-slash"></i></button>
        <button type="button" data-tag="hide" title="הסתר/הצג (מתקפל)"><i class="fa-solid fa-square-caret-down"></i></button>
        <span class="sep"></span>
        <button type="button" data-tag="url" title="קישור"><i class="fa-solid fa-link"></i></button>
        <button type="button" data-tag="email" title="דוא&quot;ל"><i class="fa-solid fa-at"></i></button>
        <button type="button" data-tag="img" title="תמונה"><i class="fa-solid fa-image"></i></button>
        <button type="button" data-tag="youtube" title="YouTube"><i class="fa-brands fa-youtube"></i></button>
        <button type="button" data-tag="video" title="וידאו"><i class="fa-solid fa-video"></i></button>
        <button type="button" data-tag="audio" title="אודיו"><i class="fa-solid fa-music"></i></button>
        <span class="sep"></span>
        <button type="button" class="bb-color-btn" data-mode="text" title="צבע טקסט"><i class="fa-solid fa-palette"></i></button>
        <button type="button" class="bb-color-btn" data-mode="bg" title="צבע רקע (bg)"><i class="fa-solid fa-fill-drip"></i></button>
        <button type="button" data-action="upper" title="אותיות גדולות (UPPERCASE)"><i class="fa-solid fa-arrow-up-a-z"></i></button>
        <button type="button" data-action="lower" title="אותיות קטנות (lowercase)"><i class="fa-solid fa-arrow-down-a-z"></i></button>
        <span class="sep"></span>
        <button type="button" data-action="ul" title="רשימה תבליטים"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" data-action="ol" title="רשימה ממוספרת"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" data-action="table" title="טבלה"><i class="fa-solid fa-table"></i></button>
        <button type="button" data-action="hr" title="קו מפריד"><i class="fa-solid fa-ruler-horizontal"></i></button>
        <button type="button" data-action="br" title="שורה חדשה"><i class="fa-solid fa-turn-down"></i></button>
        <span class="sep"></span>
        <button type="button" data-tag="עברית" title="בלוק עברית" style="font-weight:bold; font-size: 1.2em;">א</button>
        <button type="button" data-tag="אנגלית" title="בלוק אנגלית" style="font-weight:bold; font-size: 1.2em; font-family: Times New Roman;">A</button>
        <span class="sep"></span>
        <select class="bb-heading" title="כותרת"><option value="">כותרת</option><option value="h1" class="opt-h1">H1</option><option value="h2" class="opt-h2">H2</option><option value="h3" class="opt-h3">H3</option><option value="h4" class="opt-h4">H4</option><option value="h5" class="opt-h5">H5</option><option value="h6" class="opt-h6">H6</option></select>
        <select class="bb-blocks" title="סגנון"><option value="">סגנון</option><option value="info" class="opt-info">מידע</option><option value="warning" class="opt-warning">אזהרה</option><option value="error" class="opt-error">שגיאה</option><option value="success" class="opt-success">הצלחה</option></select>
        <select class="bb-size" title="גודל"><option value="">גודל</option><optgroup label="סולם 1–10">${size10}</optgroup><optgroup label="פיקסלים">${pxOpts}</optgroup></select>
        <select class="bb-font" title="גופן"><option value="">גופן</option>${fontList.map(f=>`<option value="${f}" style="font-family:'${f}',sans-serif">${f}</option>`).join('')}</select>
        <span class="font-preview" title="תצוגת גופן">Aa</span>
        <button type="button" data-action="clear" title="נקה הכל"><i class="fa-solid fa-broom"></i></button>
      `;
      
      if (!editor.contains(tb)) {
          editor.prepend(tb);
      }

      for (const key in customIcons) {
          const imgPath = customIcons[key];
          if (imgPath) {
              let button;
              if (key === 'color') button = tb.querySelector('button.bb-color-btn[data-mode="text"]');
              else if (key === 'bg') button = tb.querySelector('button.bb-color-btn[data-mode="bg"]');
              else button = tb.querySelector(`button[data-tag="${key}"], button[data-action="${key}"]`);
              if (button) {
                  const title = button.getAttribute('title') || key;
                  button.innerHTML = `<img src="${imgPath}" alt="${title}">`;
              }
          }
      }

      const preview = editor.querySelector('.bb-preview');
      const updateCombinedPreview = () => {
          if (!preview) return;
          const heTa = editor.querySelector('textarea[name="description_he"]');
          const enTa = editor.querySelector('textarea[name="description_en"]');
          const textHe = heTa ? heTa.value : '';
          const textEn = enTa ? enTa.value : '';
          const combinedText = `[עברית]\n${textHe}\n[/עברית]\n\n\n[אנגלית]\n${textEn}\n[/אנגלית]`;
          fetch('preview_bbcode.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'text=' + encodeURIComponent(combinedText) })
          .then(res => res.text())
          .then(html => { preview.innerHTML = html.trim() ? html : '— ריק —'; });
      };

      const wrap = (openTag, closeTag) => {
        if (!activeTa) return;
        if (typeof openTag === 'undefined' || typeof closeTag === 'undefined') { return; }
        const start = activeTa.selectionStart;
        const end = activeTa.selectionEnd;
        const text = activeTa.value;
        const selection = text.substring(start, end);
        activeTa.value = text.substring(0, start) + openTag + selection + closeTag + text.substring(end);
        activeTa.selectionStart = start + openTag.length;
        activeTa.selectionEnd = start + openTag.length + selection.length;
        activeTa.dispatchEvent(new Event('input'));
        activeTa.focus();
      };
      
      const wrapBB=(tag,param=null)=>wrap(param?`[${tag}=${param}]`:`[${tag}]`, `[/${tag}]`);
      const transformSelection=fn=>{
        if (!activeTa || activeTa.selectionStart === activeTa.selectionEnd) return;
        const s=activeTa.selectionStart,e=activeTa.selectionEnd,sel=activeTa.value.substring(s,e); 
        const newSel = fn(sel);
        document.execCommand("insertText", false, newSel);
        activeTa.dispatchEvent(new Event('input')); 
        activeTa.focus();
      };
      const insertAtCursor = str => {
        if (!activeTa) return;
        document.execCommand("insertText", false, str);
        activeTa.dispatchEvent(new Event('input')); 
        activeTa.focus();
      };

      const palette = editor.querySelector('.color-palette') || (() => {
        const p = document.createElement('div'); p.className='color-palette'; editor.appendChild(p); return p;
      })();
      
      tb.addEventListener('click', ev => {
        const btn = ev.target.closest('button'); if (!btn) return;
        const action = btn.dataset.action, tag = btn.dataset.tag;
        
        if (action==='clear'){ allTas.forEach(ta => ta.value = ''); updateCombinedPreview(); return; }
        if (action==='upper'){ transformSelection(t=>t.toUpperCase()); return; }
        if (action==='lower'){ transformSelection(t=>t.toLowerCase()); return; }
        if (action==='hr'){ insertAtCursor('[hr]'); return; }
        if (action==='br'){ insertAtCursor('[br]'); return; }
        if (action==='ul'){ insertAtCursor('[list]\n[*]פריט 1\n[*]פריט 2\n[/list]'); return; }
        if (action==='ol'){ insertAtCursor('[list=1]\n[*]1\n[*]2\n[/list]'); return; }
        if (action==='table'){ insertAtCursor('[table]\n[tr][td]cell[/td][/tr]\n[/table]'); return; }

        if (btn.classList.contains('bb-color-btn')){
            let currentMode = btn.dataset.mode === 'bg' ? 'bg' : 'text';
            buildPalette(palette, currentMode);
            if (palette.style.display === 'block' && palette.dataset.mode === currentMode) {
                palette.style.display = 'none';
                return;
            }
            palette.dataset.mode = currentMode;
            const gap = 6;
            palette.style.visibility = 'hidden'; palette.style.display = 'block';
            requestAnimationFrame(() => {
                const btnRect = btn.getBoundingClientRect(), editorRect = editor.getBoundingClientRect();
                const palW = palette.offsetWidth;
                let top = (btnRect.top - editorRect.top) + btnRect.height + gap;
                let left = (btnRect.left - editorRect.top);
                if (left + palW > editor.clientWidth) { left = editor.clientWidth - palW - gap; }
                if (left < 0) left = gap;
                palette.style.left = left + 'px';
                palette.style.top = top + 'px';
                palette.style.visibility = 'visible';
            });
            return;
        }

        if (tag){ wrapBB(tag); }
      });
      
      const setupSelectAction = (selector, tagName = null) => {
          const select = tb.querySelector(selector);
          if (select) select.addEventListener('change', function() {
              const value = this.value;
              if (!value) return;
              const tagToUse = tagName || value;
              const param = tagName ? value : null;
              wrapBB(tagToUse, param);
              this.value = '';
          });
      };
      setupSelectAction('.bb-heading');
      setupSelectAction('.bb-blocks');
      setupSelectAction('.bb-size', 'size');
      setupSelectAction('.bb-font', 'font');
      
      function buildPalette(el, mode){
        el.innerHTML='';
        const head=document.createElement('div'); head.className='cp-head';
        const customSw=document.createElement('span'); customSw.className='cp-sw cp-custom'; customSw.title='צבע מותאם';
        const titleBtn=document.createElement('button'); titleBtn.type='button'; titleBtn.className='cp-btn'; titleBtn.textContent='בחר גוון';
        const previewBox=document.createElement('span'); previewBox.className='cp-preview'; previewBox.textContent='Aa';
        const saveBtn=document.createElement('button'); saveBtn.type='button'; saveBtn.className='cp-btn'; saveBtn.textContent='שמור צבע';
        head.appendChild(customSw); head.appendChild(titleBtn); head.appendChild(previewBox); head.appendChild(saveBtn);
        el.appendChild(head);
        chosenColor = mode==='bg' ? (localStorage.getItem('bb_last_bg_color')||'#fff59d') : (localStorage.getItem('bb_last_text_color')||'#000000');
        applyPreview(previewBox, mode, chosenColor);
        titleBtn.addEventListener('click', ()=>{ openPickerNear(previewBox, normalizeHex(chosenColor)); activePaletteCtx = {el, mode, previewBox, customSw}; });
        saveBtn.addEventListener('click', ()=> commitColor(mode, chosenColor, el));
        const colors = ['#FFFFFF','#F2F2F2','#E6E6E6','#CCCCCC','#999999','#666666','#333333','#000000','#FFEBEE','#FFCDD2','#EF9A9A','#E57373','#F44336','#D32F2F','#B71C1C','#880E4F','#F3E5F5','#E1BEE7','#CE93D8','#BA68C8','#9C27B0','#7B1FA2','#4A148C','#311B92','#E8EAF6','#C5CAE9','#9FA8DA','#7986CB','#3F51B5','#303F9F','#1A237E','#0D47A1','#E1F5FE','#B3E5FC','#81D4FA','#4FC3F7','#03A9F4','#0288D1','#01579B','#004D40','#E0F2F1','#B2DFDB','#80CBC4','#4DB6AC','#009688','#00796B','#004D40','#00251A','#F1F8E9','#DCEDC8','#C5E1A5','#AED581','#8BC34A','#689F38','#33691E','#1B5E20','#FFFDE7','#FFF9C4','#FFF59D','#FFF176','#FFEB3B','#FBC02D','#F57F17','#FF6F00','#FFF3E0','#FFE0B2','#FFCC80','#FFB74D','#FF9800','#F57C00','#E65100','#BF360C','#F0F4C3','#E6EE9C','#DCE775','#D4E157','#CDDC39','#C0CA33','#AFB42B','#9E9D24'];
        for(let i=0;i<colors.length;i+=8){ const row=document.createElement('div'); row.className='cp-row'; colors.slice(i,i+8).forEach(c=>{ const sw=document.createElement('span'); sw.className='cp-sw'; sw.style.background=c; sw.dataset.color=c; row.appendChild(sw); }); el.appendChild(row); }
        markSelectionOrCustom(chosenColor, el, customSw);
        applyPreview(previewBox, mode, chosenColor);
        el.querySelectorAll('.cp-sw:not(.cp-custom)').forEach(sw=>{
          const c=sw.dataset.color;
          sw.addEventListener('mouseenter',()=>applyPreview(previewBox,mode,c));
          sw.addEventListener('mouseleave',()=>applyPreview(previewBox,mode,chosenColor));
          sw.addEventListener('click',()=>{ chosenColor=c; markSelectionOrCustom(chosenColor, el, customSw); applyPreview(previewBox,mode,chosenColor); });
        });
        customSw.addEventListener('click', ()=>{ openPickerNear(previewBox, normalizeHex(chosenColor)); activePaletteCtx = {el, mode, previewBox, customSw}; });
      }
      hiddenPicker.addEventListener('input', ()=>{ if (!activePaletteCtx) return; chosenColor = hiddenPicker.value; applyPreview(activePaletteCtx.previewBox, activePaletteCtx.mode, chosenColor); markSelectionOrCustom(chosenColor, activePaletteCtx.el, activePaletteCtx.customSw); });
      function commitColor(mode, color, paletteEl){ if (mode==='bg'){ localStorage.setItem('bb_last_bg_color', color); wrapBB('bg', color); }else{ localStorage.setItem('bb_last_text_color', color); wrapBB('color', color); } if (paletteEl) paletteEl.style.display='none'; activePaletteCtx = null; }
      function applyPreview(box, mode, color){ if (mode==='bg'){ box.style.background=color; box.style.color='#000'; } else { box.style.color=color; box.style.background='#fff'; } }
      function markSelectionOrCustom(color, root, customSw){ root.querySelectorAll('.cp-sw').forEach(x=>x.classList.remove('selected')); const hex=normalizeHex(color); const match=[...root.querySelectorAll('.cp-sw:not(.cp-custom)')].find(x=>normalizeHex(x.dataset.color)===hex); if (match){ match.classList.add('selected'); customSw.style.background=''; customSw.style.display='none'; }else{ customSw.style.display='inline-block'; customSw.style.background=hex; customSw.classList.add('selected'); } }
      function normalizeHex(h){ h=(h||'').trim().toLowerCase(); if(/^#[0-9a-f]{3}$/.test(h)){return '#'+[h[1],h[2],h[3]].map(x=>x+x).join('');} return h; }
      function openPickerNear(anchorEl, valueHex){ if (valueHex) hiddenPicker.value = valueHex; const r = anchorEl.getBoundingClientRect(); hiddenPicker.style.left = Math.round(r.left) + 'px'; hiddenPicker.style.top = Math.round(r.bottom + 5) + 'px'; hiddenPicker.click(); }
      document.addEventListener('mousedown', e=>{ if (!palette.contains(e.target) && !e.target.closest('.bb-color-btn')) palette.style.display='none'; });

      allTas.forEach(ta => ta.addEventListener('input', updateCombinedPreview));
      updateCombinedPreview();
    });
  });
})();
</script>