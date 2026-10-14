/**
 * Shared sidebar navigation (<x-app-nav>).
 *
 * Wide screens (>= 1200px): the sidebar is always visible, nothing to do.
 * Narrower: it's a drawer opened by the Menu button, with a backdrop,
 * Esc / backdrop / link click to close, focus moved into the drawer and
 * back to the button, and the page behind locked from scrolling.
 *
 * Also fills in the signed-in user and the pending-requests badge.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var nav = doc.getElementById('app-nav');
  if (!nav) return;

  var backdrop = doc.querySelector('[data-app-nav-backdrop]');
  var openBtn = doc.querySelector('[data-app-nav-open]');
  var closeBtn = nav.querySelector('[data-app-nav-close]');
  var wide = global.matchMedia('(min-width: 1200px)');
  var lastFocus = null;

  // Sidebar sits under the sticky header; keep its offset in sync.
  function syncHeaderHeight() {
    var header = doc.querySelector('.app-header-sticky') || doc.querySelector('.topbar');
    var h = header ? Math.round(header.getBoundingClientRect().height) : 0;
    doc.documentElement.style.setProperty('--app-header-h', h + 'px');
  }

  function isOpen() {
    return nav.classList.contains('is-open');
  }

  function focusables() {
    return Array.prototype.filter.call(nav.querySelectorAll('a[href], button'), function (el) {
      return el.offsetParent !== null;
    });
  }

  function open() {
    if (wide.matches || isOpen()) return;
    lastFocus = doc.activeElement;
    nav.classList.add('is-open');
    backdrop.hidden = false;
    // next frame so the fade-in transition runs
    global.requestAnimationFrame(function () {
      backdrop.classList.add('is-visible');
    });
    doc.documentElement.classList.add('app-nav-locked');
    if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    var current = nav.querySelector('.app-nav-link.is-active') || focusables()[0];
    if (current) current.focus();
  }

  function close(restoreFocus) {
    if (!isOpen()) return;
    nav.classList.remove('is-open');
    backdrop.classList.remove('is-visible');
    backdrop.hidden = true;
    doc.documentElement.classList.remove('app-nav-locked');
    if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
    if (restoreFocus === false) return;
    var target = lastFocus && lastFocus !== doc.body && lastFocus.focus ? lastFocus : openBtn;
    if (target) target.focus();
  }

  if (openBtn) {
    openBtn.addEventListener('click', function () {
      if (isOpen()) close();
      else open();
    });
  }
  if (closeBtn) closeBtn.addEventListener('click', function () { close(); });
  if (backdrop) backdrop.addEventListener('click', function () { close(); });

  nav.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('a[href]')) close(false);
  });

  doc.addEventListener('keydown', function (e) {
    if (!isOpen()) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    // keep Tab inside the open drawer
    if (e.key === 'Tab') {
      var items = focusables();
      if (!items.length) return;
      var first = items[0];
      var last = items[items.length - 1];
      if (e.shiftKey && doc.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && doc.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  function onWideChange() {
    if (wide.matches) close(false);
    syncHeaderHeight();
  }
  if (wide.addEventListener) wide.addEventListener('change', onWideChange);
  else if (wide.addListener) wide.addListener(onWideChange);
  global.addEventListener('resize', syncHeaderHeight);
  syncHeaderHeight();

  // Signed-in user and pending-requests badge (needs api-client.js).
  function initials(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function loadUser() {
    var api = global.McuApi;
    if (!api) return;
    api.get('auth.php').then(function (res) {
      var user = res && res.user;
      if (!user) return;
      var nameEl = nav.querySelector('[data-app-nav-name]');
      var initialsEl = nav.querySelector('[data-app-nav-initials]');
      if (nameEl) nameEl.textContent = user.name || 'Signed in';
      // profile.js owns avatars when present (it can show the user's photo)
      if (initialsEl && !global.McuProfile) initialsEl.textContent = initials(user.name);
    }).catch(function () {});

    var badge = nav.querySelector('[data-app-nav-badge="pending-requests"]');
    if (!badge) return;
    api.get('requests.php').then(function (res) {
      var n = (res.textRequests || []).filter(function (r) {
        return r.status === 'pending_admin';
      }).length;
      // the number is read as part of the link name: "Requests 4 waiting"
      badge.innerHTML = (n > 99 ? '99+' : String(n)) + '<span class="app-nav-sr"> waiting</span>';
      badge.hidden = n === 0;
    }).catch(function () {});
  }

  if (doc.readyState === 'complete') loadUser();
  else global.addEventListener('load', loadUser);
})(typeof window !== 'undefined' ? window : this);
