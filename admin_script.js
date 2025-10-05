document.addEventListener('DOMContentLoaded', function() {
  const body = document.body;
  const btn = document.getElementById('toggle-admin');
  
  // בטעינת כל עמוד, בודק מה המצב השמור בזיכרון הדפדפן
  if (localStorage.getItem('adminMode') === '1') {
    body.classList.add('admin-mode');
    if (btn) btn.textContent = '🚪 יציאה ממצב ניהול';
  }

  // מוסיף אירוע לחיצה לכפתור (אם הוא קיים בעמוד)
  if (btn) {
    btn.addEventListener('click', function() {
      body.classList.toggle('admin-mode');
      const isAdminActive = body.classList.contains('admin-mode');
      
      // שומר את הבחירה בזיכרון הדפדפן (1=פעיל, 0=כבוי)
      localStorage.setItem('adminMode', isAdminActive ? '1' : '0');
      
      // משנה את הטקסט בכפתור
      btn.textContent = isAdminActive ? '🚪 יציאה ממצב ניהול' : '🔑 מצב ניהול';
    });
  }
});