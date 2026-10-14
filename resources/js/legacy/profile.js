/**
 * Signed-in user's profile across every page.
 *
 * - Fills the topbar profile menu (<x-profile-menu>) and the sidebar user
 *   block with the real name, role, college and email.
 * - Shows the user's photo wherever an avatar appears. Photos are stored on
 *   the server (auth.php set_photo / remove_photo; user.photoUrl). A photo
 *   saved only in this browser by the earlier version is uploaded once.
 * - Opens / closes the profile menu (click, Esc, outside click).
 *
 * window.McuProfile is used by the My account page to change the photo.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var user = null;
  var listeners = [];
  var PHOTO_KEY = 'mcu-nex:photo:';
  var TEXT_KEY = 'mcu-nex:text-size';

  function store(key, value) {
    try {
      if (value == null) global.localStorage.removeItem(key);
      else global.localStorage.setItem(key, value);
      return true;
    } catch (e) {
      return false;
    }
  }

  function read(key) {
    try {
      return global.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function initials(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(function (p) { return /[A-Za-z]/.test(p); });
    if (!parts.length) return '';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function photo() {
    return user && user.photoUrl ? user.photoUrl : null;
  }

  // Ask the server to set (dataUrl) or remove (null) the photo; resolves with the updated user.
  function sendPhoto(dataUrl) {
    if (!global.McuApi) return Promise.reject(new Error('The server isn’t reachable. Try again.'));
    var body = dataUrl ? { action: 'set_photo', photo: dataUrl } : { action: 'remove_photo' };
    return global.McuApi.send('POST', 'auth.php', body).then(function (res) {
      if (res && res.user) user = res.user;
      paint();
      return user;
    });
  }

  // One-time move of a photo the earlier version kept only in this browser.
  function migrateLocalPhoto() {
    var local = read(PHOTO_KEY + user.id);
    if (!local) return;
    if (user.photoUrl) {
      store(PHOTO_KEY + user.id, null);
      return;
    }
    sendPhoto(local).then(function () { store(PHOTO_KEY + user.id, null); }, function () {});
  }

  function roleLine(u) {
    var bits = [u.roleLabel || ''];
    if (u.departmentName) bits.push(u.departmentName);
    else if (u.collegeName) bits.push(u.collegeName);
    return bits.filter(Boolean).join(' · ');
  }

  function paintAvatar(el) {
    var src = photo();
    el.textContent = '';
    if (src) {
      var img = doc.createElement('img');
      img.src = src;
      img.alt = '';
      el.appendChild(img);
    } else if (user) {
      el.textContent = initials(user.name);
    }
  }

  function paint() {
    if (!user) return;
    Array.prototype.forEach.call(doc.querySelectorAll('[data-profile-avatar], [data-app-nav-initials]'), paintAvatar);
    Array.prototype.forEach.call(doc.querySelectorAll('[data-profile-name]'), function (el) { el.textContent = user.name || ''; });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-profile-role]'), function (el) { el.textContent = roleLine(user); });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-profile-email]'), function (el) { el.textContent = user.email || ''; });
    var btn = doc.querySelector('.pm-btn');
    if (btn) btn.setAttribute('aria-label', 'Your account: ' + (user.name || ''));
    listeners.forEach(function (cb) {
      try { cb(user); } catch (e) { /* keep painting for others */ }
    });
  }

  // ----- menu -----
  var menu = doc.querySelector('[data-profile-menu]');
  var btn = menu && menu.querySelector('.pm-btn');
  var panel = menu && menu.querySelector('.pm-panel');

  function setOpen(open, focusBack) {
    if (!menu) return;
    menu.classList.toggle('is-open', open);
    panel.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    var narrow = global.matchMedia && global.matchMedia('(max-width: 640px)').matches;
    panel.style.top = open && narrow ? Math.round(btn.getBoundingClientRect().bottom + 10) + 'px' : '';
    if (open) {
      var first = panel.querySelector('.pm-item');
      if (first) first.focus();
    } else if (focusBack) {
      btn.focus();
    }
  }

  if (menu) {
    btn.addEventListener('click', function () {
      setOpen(!menu.classList.contains('is-open'), false);
    });
    menu.addEventListener('click', function (e) { e.stopPropagation(); });
    // let the notification panel and this menu close each other
    doc.addEventListener('click', function () { if (menu.classList.contains('is-open')) setOpen(false, false); });
    btn.addEventListener('click', function () {
      Array.prototype.forEach.call(doc.querySelectorAll('.topbar-notify.open'), function (n) {
        n.classList.remove('open');
        var m = n.querySelector('.notify-menu');
        if (m) m.hidden = true;
        var b = n.querySelector('.topbar-notify-btn');
        if (b) b.setAttribute('aria-expanded', 'false');
      });
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('is-open')) setOpen(false, true);
    });
  }

  // ----- public API (My account page) -----
  global.McuProfile = {
    user: function () { return user; },
    photo: photo,
    // returns a Promise; rejects with the server's message
    setPhoto: function (dataUrl) {
      if (!user) return Promise.reject(new Error('Log in again, then update your photo.'));
      return sendPhoto(dataUrl || null);
    },
    setUser: function (patch) {
      if (!user) return;
      for (var k in patch) if (Object.prototype.hasOwnProperty.call(patch, k)) user[k] = patch[k];
      paint();
    },
    textSize: function () { return read(TEXT_KEY) === 'large' ? 'large' : 'default'; },
    setTextSize: function (size) {
      store(TEXT_KEY, size === 'large' ? 'large' : null);
      doc.documentElement.classList.toggle('pref-text-large', size === 'large');
    },
    onChange: function (cb) {
      if (typeof cb !== 'function') return;
      listeners.push(cb);
      if (user) cb(user);
    },
    initials: initials,
    roleLine: roleLine,
  };

  function load() {
    if (!global.McuApi) return;
    global.McuApi.get('auth.php').then(function (res) {
      if (!res || !res.user) return;
      user = res.user;
      paint();
      migrateLocalPhoto();
    }).catch(function () {});
  }

  if (doc.readyState === 'complete') load();
  else global.addEventListener('load', load);
})(typeof window !== 'undefined' ? window : this);
