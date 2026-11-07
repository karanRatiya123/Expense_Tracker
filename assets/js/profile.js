// profile.js — minimal client niceties for the profile page.
// Forms already POST server-side; this only adds light validation + UX.

(function () {
  const pwForm = document.getElementById('pwForm');
  if (pwForm) {
    pwForm.addEventListener('submit', (e) => {
      const n = pwForm.querySelector('#new_password').value;
      const c = pwForm.querySelector('#confirm_password').value;
      if (n.length < 6) { e.preventDefault(); alert('New password must be at least 6 characters.'); return; }
      if (n !== c)      { e.preventDefault(); alert('Passwords do not match.'); return; }
    });
  }

  const infoForm = document.getElementById('infoForm');
  if (infoForm) {
    infoForm.addEventListener('submit', (e) => {
      const name = infoForm.querySelector('#name').value.trim();
      const email = infoForm.querySelector('#email').value.trim();
      if (name.length < 2) { e.preventDefault(); alert('Name is too short.'); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { e.preventDefault(); alert('That email looks off.'); return; }
    });
  }

  // ----- Theme switch (Dark / Light / System) -----
  // Persists via window.ApexTheme (theme.js, localStorage) so it applies site-wide instantly.
  function paintThemeButtons(mode) {
    const btns = document.querySelectorAll('[data-theme-option]');
    btns.forEach((b) => {
      const on = b.getAttribute('data-theme-option') === mode;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    const status = document.getElementById('themeStatus');
    if (status) {
      const resolved = document.documentElement.getAttribute('data-theme') || mode;
      const label = mode.charAt(0).toUpperCase() + mode.slice(1);
      status.textContent = mode === 'system' ? ('System · ' + resolved.charAt(0).toUpperCase() + resolved.slice(1)) : label;
    }
  }

  function currentMode() {
    if (window.ApexTheme) return window.ApexTheme.get();
    try { return localStorage.getItem('apex-theme') || 'dark'; } catch (e) { return 'dark'; }
  }

  document.querySelectorAll('[data-theme-option]').forEach((btn) => {
    if (btn.__apexThemeWired) return; // already wired by theme.js — skip duplicate
    btn.__apexThemeWired = true;
    btn.addEventListener('click', () => {
      const mode = btn.getAttribute('data-theme-option');
      if (window.ApexTheme) window.ApexTheme.set(mode);
      else {
        try { localStorage.setItem('apex-theme', mode); } catch (e) {}
        const resolved = mode === 'system'
          ? (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
          : mode;
        document.documentElement.setAttribute('data-theme', resolved);
      }
      paintThemeButtons(mode);
    });
  });

  window.addEventListener('apex-theme-change', (e) => {
    if (e && e.detail && e.detail.mode) paintThemeButtons(e.detail.mode);
  });

  paintThemeButtons(currentMode());

  // ----- Email notifications toggle -----
  function profileToast(message, type) {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }
    const toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'info');
    const icon = document.createElement('i');
    icon.className = 'fa-solid ' + (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check');
    icon.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.textContent = message;
    toast.append(icon, text);
    stack.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(6px)';
      setTimeout(() => toast.remove(), 250);
    }, 3200);
  }

  const emailToggle = document.getElementById('emailToggle');
  if (emailToggle) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta ? meta.getAttribute('content') : '';

    emailToggle.addEventListener('change', () => {
      const enabled = emailToggle.checked;
      const previous = !enabled;
      emailToggle.disabled = true;

      const fd = new FormData();
      fd.append('enabled', enabled ? '1' : '0');
      fd.append('_token', token);

      fetch('../api/api_toggle_email.php', {
        method: 'POST',
        body: fd,
        headers: {
          'X-CSRF-Token': token,
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then((res) => res.json().catch(() => ({ success: false, error: 'Unexpected server response' })))
        .then((data) => {
          if (!data.success) throw new Error(data.error || 'Could not update preference');
          profileToast(enabled ? 'Email notifications turned on' : 'Email notifications turned off', 'success');
        })
        .catch((err) => {
          emailToggle.checked = previous;
          profileToast(err.message || 'Could not update preference', 'error');
        })
        .finally(() => {
          emailToggle.disabled = false;
        });
    });
  }
})();
