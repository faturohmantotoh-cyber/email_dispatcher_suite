// public/assets/js/login.js
(() => {
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  // ===== Background animasi garis minimalis (Canvas) =====
  const canvas = document.getElementById('bg-canvas');
  const ctx = canvas.getContext('2d', { alpha: true });
  let w, h, t = 0, raf;
  function resize() {
    w = canvas.width = window.innerWidth;
    h = canvas.height = window.innerHeight;
  }
  window.addEventListener('resize', resize, { passive: true });
  resize();

  const lines = 8;
  function draw(ts) {
    t += 0.004; // speed
    ctx.clearRect(0, 0, w, h);

    // gradient halus
    const g = ctx.createLinearGradient(0, 0, w, h);
    g.addColorStop(0, 'rgba(59,130,246,0.08)');   // blue-500
    g.addColorStop(1, 'rgba(96,165,250,0.06)');   // blue-400

    ctx.lineWidth = 1.6;
    ctx.strokeStyle = g;

    for (let i = 0; i < lines; i++) {
      ctx.beginPath();
      const amp = 12 + i * 3;     // amplitude
      const freq = 0.0009 + i*0.00015;
      for (let x = 0; x <= w; x += 8) {
        const y = h * 0.3 + i * 28 + Math.sin((x * freq) + t * (1.6 + i*0.2)) * amp;
        if (x === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.stroke();
    }
    raf = requestAnimationFrame(draw);
  }
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    raf = requestAnimationFrame(draw);
  }

  // ===== Toggle password visibility =====
  const togglePass = $('#togglePass');
  const pass = $('#password');
  togglePass?.addEventListener('click', () => {
    const isText = pass.type === 'text';
    pass.type = isText ? 'password' : 'text';
    togglePass.setAttribute('aria-label', isText ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
  });

  // ===== CapsLock indicator =====
  const passCaps = $('#passCaps');
  pass?.addEventListener('keydown', (e) => {
    if ('getModifierState' in e) {
      const on = e.getModifierState('CapsLock');
      passCaps.hidden = !on;
    }
  });
  pass?.addEventListener('blur', () => { passCaps.hidden = true; });

  // ===== Forgot password modal =====
  const dlg = $('#resetDialog');
  $('#forgotLink')?.addEventListener('click', (e) => {
    e.preventDefault();
    dlg?.showModal();
  });

  // ===== Theme toggle (auto / manual switching) =====
  const themeBtn = $('#themeToggle');
  let manualTheme = null;
  function applyTheme(mode) {
    // mode: 'light' | 'dark' | 'auto'
    document.documentElement.dataset.colorScheme = mode;
    localStorage.setItem('loginTheme', mode);
  }
  try {
    const saved = localStorage.getItem('loginTheme');
    if (saved) applyTheme(saved);
  } catch(_) {}

  themeBtn?.addEventListener('click', () => {
    const current = document.documentElement.dataset.colorScheme || 'auto';
    const next = current === 'auto' ? 'dark' : (current === 'dark' ? 'light' : 'auto');
    applyTheme(next);
  });

  // ===== Client-side minimal validation =====
  const form = document.querySelector('form.form');
  form?.addEventListener('submit', (e) => {
    const user = $('#username');
    const pass = $('#password');
    if (!user.value.trim() || !pass.value) {
      e.preventDefault();
      alert('Mohon isi Username dan Password.');
      user.focus();
    }
  });

  // Cleanup on unload
  window.addEventListener('beforeunload', () => {
    if (raf) cancelAnimationFrame(raf);
  });

  // ===== Animasi Pesawat Email Berulang =====
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    function createPlane() {
      const container = document.querySelector('.plane-container');
      if (!container) return;
      
      // Clone pesawat dan reset animasi
      const newPlane = container.cloneNode(true);
      newPlane.style.animation = 'none';
      newPlane.style.left = '-50px';
      newPlane.style.top = (12 + Math.random() * 10) + '%';
      document.body.appendChild(newPlane);
      
      // Trigger animasi
      setTimeout(() => {
        newPlane.style.animation = 'planeFloat 8s linear forwards';
      }, 10);
      
      // Hapus setelah animasi selesai
      setTimeout(() => {
        newPlane.remove();
      }, 8000);
    }
    
    // Jalankan pesawat setiap 3 detik
    setInterval(createPlane, 3000);
    // Jalankan yang pertama langsung
    createPlane();
  }
})();