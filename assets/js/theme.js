/**
 * ApexSpend — Theme Engine (Dark / Light / System)
 * Stored preference: localStorage 'apex-theme' = 'dark' | 'light' | 'system' (default 'dark').
 * Resolved theme is written to <html data-theme="dark|light"> + <html data-theme-mode="...">.
 * Every page includes the tiny boot snippet in <head> (no-FOUC) then loads this file.
 */
(function () {
  var KEY = 'apex-theme';
  var VALID = { dark: 1, light: 1, system: 1 };

  function getMode() {
    try {
      var m = localStorage.getItem(KEY) || 'dark';
      return VALID[m] ? m : 'dark';
    } catch (e) { return 'dark'; }
  }

  function resolve(mode) {
    if (mode === 'system') {
      try {
        return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
      } catch (e) { return 'dark'; }
    }
    return mode === 'light' ? 'light' : 'dark';
  }

  function apply(mode) {
    var resolved = resolve(mode || getMode());
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-mode', mode || getMode());
    var meta = document.querySelector('meta[name="color-scheme"]');
    if (meta) meta.setAttribute('content', resolved);
    try {
      window.dispatchEvent(new CustomEvent('apex-theme-change', { detail: { mode: mode || getMode(), resolved: resolved } }));
    } catch (e) {}
    return resolved;
  }

  function set(mode) {
    if (!VALID[mode]) return getMode();
    try { localStorage.setItem(KEY, mode); } catch (e) {}
    return apply(mode);
  }

  // Follow OS changes while in System mode.
  try {
    var mq = window.matchMedia('(prefers-color-scheme: light)');
    var onChange = function () { if (getMode() === 'system') apply('system'); };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange);
  } catch (e) {}

  document.addEventListener('DOMContentLoaded', function () { apply(getMode()); });

  // ----- Built-in wiring for [data-theme-option] buttons (e.g. Profile → Preferences) -----
  // Lives here (cache-busted, loaded on every page) so the switch works even if a
  // page-specific script is stale. Painting is idempotent — safe if wired twice.
  function paintOptions(mode) {
    var resolved = document.documentElement.getAttribute('data-theme') || mode;
    var btns = document.querySelectorAll('[data-theme-option]');
    for (var i = 0; i < btns.length; i++) {
      var on = btns[i].getAttribute('data-theme-option') === mode;
      if (on) btns[i].classList.add('is-on');
      else btns[i].classList.remove('is-on');
      btns[i].setAttribute('aria-checked', on ? 'true' : 'false');
    }
    var status = document.getElementById('themeStatus');
    if (status) {
      var label = mode.charAt(0).toUpperCase() + mode.slice(1);
      status.textContent = mode === 'system'
        ? ('System · ' + resolved.charAt(0).toUpperCase() + resolved.slice(1))
        : label;
    }
  }

  function wireOptions() {
    var btns = document.querySelectorAll('[data-theme-option]');
    for (var i = 0; i < btns.length; i++) {
      if (btns[i].__apexThemeWired) continue;
      btns[i].__apexThemeWired = true;
      btns[i].addEventListener('click', function () {
        var mode = this.getAttribute('data-theme-option');
        set(mode);
        paintOptions(mode);
      });
    }
    paintOptions(getMode());
  }

  document.addEventListener('DOMContentLoaded', wireOptions);
  window.addEventListener('apex-theme-change', function (e) {
    if (e && e.detail && e.detail.mode) paintOptions(e.detail.mode);
  });

  window.ApexTheme = { get: getMode, set: set, apply: apply, resolve: resolve, KEY: KEY };
})();
