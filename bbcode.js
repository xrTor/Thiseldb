// bbcode.js
(function () {
  // Guard: אם ניסו לטעון פעמיים, אל תירשם שוב
  if (window.__BB_INIT__) return;
  window.__BB_INIT__ = true;

  document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('descBox');
    const preview  = document.getElementById('previewBox');

    // === עדכון תצוגה חיה ===
    function updatePreview() {
      if (!textarea || !preview) return;
      const text = textarea.value;
      fetch('preview_bbcode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'text=' + encodeURIComponent(text)
      })
      .then(r => r.text())
      .then(html => { preview.innerHTML = html || '— ריק —'; })
      .catch(() => { preview.innerHTML = '— שגיאה —'; });
    }

    // === כפתורי ה־Toolbar ===
    document.querySelectorAll('.bb-toolbar button').forEach(btn => {
      btn.addEventListener('click', function() {
        const ta = (this.closest('.bb-editor') || document).querySelector('.bb-textarea') || textarea;
        if (!ta) return;

        // נקה
        if (this.dataset.action === 'clear') {
          ta.value = '';
          updatePreview();
          return;
        }

        const tag = this.dataset.tag;
        if (!tag) return;

        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        const sel   = ta.value.substring(start, end);

        let open = `[${tag}]`;
        let close = `[/${tag}]`;

        // חריגים
        if (tag === 'url')     open = `[url=]`;
        if (tag === 'img')     open = `[img]`;
        if (tag === 'youtube') open = `[youtube]`;

        ta.setRangeText(open + sel + close, start, end, 'end');
        ta.focus();
        updatePreview();
      });
    });

    // === בחירת צבע ===
    document.querySelectorAll('.bb-toolbar .bb-color').forEach(sel => {
      sel.addEventListener('change', function() {
        const color = this.value;
        if (!color) return;
        const ta = (this.closest('.bb-editor') || document).querySelector('.bb-textarea') || textarea;
        if (!ta) return;

        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        const sel   = ta.value.substring(start, end);

        ta.setRangeText(`[color=${color}]` + sel + `[/color]`, start, end, 'end');
        this.value = '';
        updatePreview();
      });
    });

    // === בחירת גודל ===
    document.querySelectorAll('.bb-toolbar .bb-size').forEach(sel => {
      sel.addEventListener('change', function() {
        const size = this.value;
        if (!size) return;
        const ta = (this.closest('.bb-editor') || document).querySelector('.bb-textarea') || textarea;
        if (!ta) return;

        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        const sel   = ta.value.substring(start, end);

        ta.setRangeText(`[size=${size}]` + sel + `[/size]`, start, end, 'end');
        this.value = '';
        updatePreview();
      });
    });

    // === חיבור הקלדה ל־Preview ===
    if (textarea) {
      textarea.addEventListener('input', updatePreview);
      updatePreview(); // רענון ראשוני
    }
  });
})();
