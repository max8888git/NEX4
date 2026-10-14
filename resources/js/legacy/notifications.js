/**
 * Topbar notifications for the signed-in user only.
 * Loads from the API so each role sees the interactions they were part of.
 *
 * The bell shows the unread count; the panel lists the newest first with
 * unread ones marked, "Mark all as read" at the top, and relative times.
 * Opening an item marks it read before going to its page.
 */
(function (global) {
  'use strict';

  var STYLE_ID = 'mcu-nex-notify-styles-v3';
  var POLL_MS = 45000;

  function siteRoot() {
    if (global.McuApi && global.McuApi.base) {
      return String(global.McuApi.base).replace(/api\/?$/, '');
    }
    try {
      return new URL('../', global.location.href).href.replace(
        /\/(admin|super-admin|viewing|viewing-with-approval|viewing-program-head)\/?$/,
        '/'
      );
    } catch (e) {
      return '../';
    }
  }

  function toHref(path) {
    var rel = String(path || '').replace(/^\/+/, '');
    if (!rel) return '#';
    return siteRoot() + rel;
  }

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var css = [
      '.topbar-notify{position:relative;z-index:25;}',
      '.topbar-notify .topbar-notify-btn{position:relative;}',
      '.topbar-notify .topbar-notify-dot{position:absolute;top:-5px;right:-6px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:#e05454!important;box-shadow:0 0 0 2px #5c267e!important;color:#fff;font:700 0.66rem/18px "DM Sans",system-ui,sans-serif;text-align:center;opacity:0;transform:scale(0);transition:opacity .15s ease,transform .15s ease;width:auto!important;}',
      '.topbar-notify.has-unread .topbar-notify-dot{opacity:1;transform:scale(1);}',
      '.topbar-notify .notify-menu{display:none!important;position:absolute;top:calc(100% + 10px);right:-8px;width:min(380px,calc(100vw - 1rem));max-height:min(520px,75vh);padding:0!important;background:#fff;color:#1f1230;border:1px solid #e3d9f0;border-radius:0.9rem;box-shadow:0 18px 44px rgba(20,10,30,.22);z-index:120;flex-direction:column;overflow:hidden;text-align:left;}',
      '.topbar-notify.open .notify-menu{display:flex!important;}',
      '.topbar-notify .notify-menu[hidden]{display:none!important;}',
      '.topbar-notify .nm-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem 1rem .7rem;border-bottom:1px solid #eee7f5;}',
      '.topbar-notify .nm-title{font-size:.98rem;font-weight:700;color:#2d163f;}',
      '.topbar-notify .nm-count{margin-left:.4rem;font-size:.78rem;font-weight:600;color:#7a6992;}',
      '.topbar-notify .nm-mark{border:0;background:none;padding:.25rem .35rem;border-radius:.4rem;color:#5c267e;font:600 .8rem/1.2 inherit;cursor:pointer;}',
      '.topbar-notify .nm-mark:hover{background:#f6f1fc;}',
      '.topbar-notify .nm-mark[disabled]{color:#b5a8c6;cursor:default;background:none;}',
      '.topbar-notify .nm-list{list-style:none;margin:0;padding:0;overflow-y:auto;overscroll-behavior:contain;}',
      '.topbar-notify .nm-item{display:flex;gap:.7rem;padding:.75rem 1rem;border-bottom:1px solid #f2ecf8;text-decoration:none;color:#1f1230;}',
      '.topbar-notify .nm-item:hover{background:#faf7fd;}',
      '.topbar-notify .nm-item:focus-visible{outline:3px solid #f9b937;outline-offset:-3px;}',
      '.topbar-notify .nm-dot{flex-shrink:0;width:9px;height:9px;margin-top:.4rem;border-radius:50%;background:transparent;}',
      '.topbar-notify .nm-item.is-unread{background:#fbf8ff;}',
      '.topbar-notify .nm-item.is-unread .nm-dot{background:#5c267e;}',
      '.topbar-notify .nm-text{min-width:0;flex:1;}',
      '.topbar-notify .nm-item-title{display:block;font-size:.88rem;font-weight:600;line-height:1.35;color:#5b4d6e;}',
      '.topbar-notify .nm-item.is-unread .nm-item-title{font-weight:700;color:#2d163f;}',
      '.topbar-notify .nm-item-body{display:block;margin-top:.15rem;font-size:.82rem;line-height:1.4;color:#6f5f86;}',
      '.topbar-notify .nm-item-meta{display:block;margin-top:.3rem;font-size:.74rem;color:#9a8ab0;}',
      '.topbar-notify .nm-empty{padding:2rem 1.25rem;text-align:center;color:#6f5f86;font-size:.88rem;}',
      '.topbar-notify .nm-empty strong{display:block;margin-bottom:.25rem;font-size:.95rem;color:#2d163f;}',
      // phones: full-width panel just under the bell (top set in setOpen)
      '@media (max-width:640px){.topbar-notify .notify-menu{position:fixed;left:.5rem;right:.5rem;width:auto;max-height:calc(100vh - 6rem);}}'
    ].join('');
    var s = document.createElement('style');
    s.id = STYLE_ID;
    s.textContent = css;
    document.head.appendChild(s);
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function timeAgo(value) {
    var t = new Date(value);
    if (isNaN(t.getTime())) return '';
    var mins = Math.floor((Date.now() - t.getTime()) / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return mins + (mins === 1 ? ' minute ago' : ' minutes ago');
    var hours = Math.floor(mins / 60);
    if (hours < 24) return hours + (hours === 1 ? ' hour ago' : ' hours ago');
    var days = Math.floor(hours / 24);
    if (days === 1) return 'Yesterday';
    if (days < 7) return days + ' days ago';
    return t.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function buildMenuHTML(items, unread) {
    var html =
      '<div class="nm-head"><div><span class="nm-title" id="nm-title">Notifications</span>' +
      (unread ? '<span class="nm-count">' + unread + ' unread</span>' : '') +
      '</div><button type="button" class="nm-mark" data-notify-mark-all' + (unread ? '' : ' disabled') +
      '>Mark all as read</button></div>';
    if (!items.length) {
      return html + '<div class="nm-empty"><strong>No notifications yet</strong>Updates about requests and timetables will show up here.</div>';
    }
    html += '<ul class="nm-list" aria-labelledby="nm-title">';
    items.forEach(function (item) {
      var when = timeAgo(item.at) || item.atLabel || '';
      var meta = [item.badge, when].filter(Boolean).join(' · ');
      html +=
        '<li><a class="nm-item' + (item.isRead ? '' : ' is-unread') + '" href="' + escapeHtml(toHref(item.href)) +
        '" data-nid="' + String(item.id) + '"' + (item.atLabel ? ' title="' + escapeHtml(item.atLabel) + '"' : '') + '>' +
        '<span class="nm-dot" aria-hidden="true"></span><span class="nm-text">' +
        (item.isRead ? '' : '<span class="ui-sr app-nav-sr">Unread: </span>') +
        '<span class="nm-item-title">' + escapeHtml(item.title) + '</span>' +
        '<span class="nm-item-body">' + escapeHtml(item.body) + '</span>' +
        (meta ? '<span class="nm-item-meta">' + escapeHtml(meta) + '</span>' : '') +
        '</span></a></li>';
    });
    return html + '</ul>';
  }

  var openRoot = null;

  function setOpen(root, open, restoreFocus) {
    var btn = root.querySelector('.topbar-notify-btn');
    var menu = root.querySelector('.notify-menu');
    root.classList.toggle('open', open);
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (menu) {
      menu.hidden = !open;
      menu.setAttribute('aria-hidden', open ? 'false' : 'true');
      var narrow = global.matchMedia && global.matchMedia('(max-width: 640px)').matches;
      menu.style.top = open && narrow && btn ? Math.round(btn.getBoundingClientRect().bottom + 10) + 'px' : '';
    }
    openRoot = open ? root : openRoot === root ? null : openRoot;
    if (!open && restoreFocus && btn) btn.focus();
  }

  function closeAllNotify() {
    document.querySelectorAll('.topbar-notify.open').forEach(function (r) {
      setOpen(r, false, false);
    });
  }

  function mountNotify(root) {
    var btn = root.querySelector('.topbar-notify-btn');
    var dot = root.querySelector('.topbar-notify-dot');
    var menu = root.querySelector('.notify-menu');
    if (!menu) {
      menu = document.createElement('div');
      menu.className = 'notify-menu';
      root.appendChild(menu);
    }
    // a panel of links, not an ARIA menu (role=menu needs arrow-key handling)
    menu.removeAttribute('role');
    menu.setAttribute('role', 'region');
    menu.setAttribute('aria-label', 'Notifications');
    if (!menu.id) menu.id = 'notify-panel';

    function applyState(res) {
      var items = (res && res.notifications) || [];
      var unread = (res && res.unreadCount) || 0;
      menu.innerHTML = buildMenuHTML(items, unread);
      root.classList.toggle('has-unread', unread > 0);
      if (dot) dot.textContent = unread > 9 ? '9+' : unread ? String(unread) : '';
      if (btn) btn.setAttribute('aria-label', unread ? 'Notifications, ' + unread + ' unread' : 'Notifications');
    }

    function load() {
      if (!global.McuApi) {
        applyState({ notifications: [], unreadCount: 0 });
        return Promise.resolve();
      }
      return global.McuApi.get('notifications.php')
        .then(applyState)
        .catch(function () {
          applyState({ notifications: [], unreadCount: 0 });
        });
    }

    menu.addEventListener('click', function (e) {
      var markAll = e.target.closest && e.target.closest('[data-notify-mark-all]');
      if (markAll) {
        e.preventDefault();
        if (!global.McuApi || markAll.disabled) return;
        markAll.disabled = true;
        global.McuApi.send('POST', 'notifications.php', { action: 'mark_all' })
          .then(applyState)
          .catch(function () { markAll.disabled = false; });
        return;
      }
      var link = e.target.closest && e.target.closest('a[data-nid]');
      if (!link) return;
      var id = parseInt(link.getAttribute('data-nid') || '0', 10);
      var unread = link.classList.contains('is-unread');
      closeAllNotify();
      // ctrl/cmd/middle click open a new tab: let the browser handle it
      if (!unread || !id || !global.McuApi || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) {
        if (unread && id && global.McuApi) global.McuApi.send('POST', 'notifications.php', { action: 'mark_read', id: id }).catch(function () {});
        return;
      }
      // mark it read before leaving, or the page change can cut the request off
      e.preventDefault();
      var go = function () { global.location.href = link.href; };
      var timer = setTimeout(go, 800);
      global.McuApi.send('POST', 'notifications.php', { action: 'mark_read', id: id })
        .catch(function () {})
        .then(function () { clearTimeout(timer); go(); });
    });

    root.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    load();
    setOpen(root, false, false);

    if (btn) {
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-controls', menu.id);
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !root.classList.contains('open');
        closeAllNotify();
        document.querySelectorAll('.topbar-user').forEach(function (user) {
          user.classList.remove('open');
          user.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('[data-profile-menu].is-open').forEach(function (pm) {
          pm.classList.remove('is-open');
          var panel = pm.querySelector('.pm-panel');
          if (panel) panel.hidden = true;
          var b = pm.querySelector('.pm-btn');
          if (b) b.setAttribute('aria-expanded', 'false');
        });
        if (!open) return;
        setOpen(root, true, false);
        load().then(function () {
          var first = menu.querySelector('.nm-item');
          if (first && root.classList.contains('open')) first.focus();
        });
      });
    }

    setInterval(function () {
      if (document.hidden || root.classList.contains('open')) return;
      load();
    }, POLL_MS);
  }

  function init() {
    injectStyles();
    document.addEventListener('click', closeAllNotify);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && openRoot) setOpen(openRoot, false, true);
    });
    document.querySelectorAll('.topbar-notify').forEach(mountNotify);
    document.querySelectorAll('.topbar-user').forEach(function (user) {
      user.addEventListener('click', closeAllNotify);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : this);
