/**
 * Requests page for the Records Officer and University Registrar.
 *
 * Lists schedule-change requests from deans (and program chair requests a
 * dean forwarded), filtered by Waiting / Done / Sent back, with search.
 * Opening one shows the full request; Mark as done and Send back record a
 * reply for the dean. Neither edits the timetable.
 *
 * Dean / Program Chair / VPAA pages keep using text-requests.js.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var list = doc.getElementById('reqList');
  if (!list) return;

  var rows = [];
  var filter = 'pending_admin';
  var query = '';
  var current = null;

  var STATUS = {
    pending_admin: { label: 'Waiting for you', chip: 'ui-chip--waiting' },
    done: { label: 'Done', chip: 'ui-chip--done' },
    sent_back: { label: 'Sent back', chip: 'ui-chip--back' },
  };

  var dialog = doc.getElementById('reqDialog');
  var replyEl = doc.getElementById('reqReply');
  var replyErr = doc.getElementById('reqReplyError');
  var actionErr = doc.getElementById('reqActionError');
  var doneBtn = doc.getElementById('reqDone');
  var backBtn = doc.getElementById('reqSendBack');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function timeAgo(iso) {
    var t = new Date(iso);
    if (isNaN(t.getTime())) return '';
    var mins = Math.floor((Date.now() - t.getTime()) / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return mins + (mins === 1 ? ' minute ago' : ' minutes ago');
    var hours = Math.floor(mins / 60);
    if (hours < 24) return hours + (hours === 1 ? ' hour ago' : ' hours ago');
    var days = Math.floor(hours / 24);
    if (days === 1) return 'yesterday';
    return days + ' days ago';
  }

  function statusOf(row) {
    return STATUS[row.status] || { label: row.statusLabel || row.status, chip: 'ui-chip--neutral' };
  }

  // What the request is about, in one line: classes first, then topics.
  function subject(row) {
    var classes = (row.classes || []).map(function (c) { return c.label; }).filter(Boolean);
    if (classes.length) return classes.length > 2 ? classes.slice(0, 2).join(', ') + ' and ' + (classes.length - 2) + ' more' : classes.join(', ');
    if (row.classLabel) return row.classLabel;
    if ((row.topicLabels || []).length) return row.topicLabels.join(', ');
    return 'Schedule change request';
  }

  function visibleRows() {
    var q = query.trim().toLowerCase();
    return rows.filter(function (r) {
      if (filter !== 'all' && r.status !== filter) return false;
      if (!q) return true;
      var hay = [r.title, r.collegeName, r.body, r.classLabel, (r.topicLabels || []).join(' '),
        (r.classes || []).map(function (c) { return c.label + ' ' + (c.meeting || ''); }).join(' ')].join(' ').toLowerCase();
      return hay.indexOf(q) !== -1;
    });
  }

  function renderCounts() {
    var counts = { pending_admin: 0, done: 0, sent_back: 0, all: rows.length };
    rows.forEach(function (r) {
      if (counts[r.status] != null) counts[r.status]++;
    });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-req-count]'), function (el) {
      el.textContent = String(counts[el.getAttribute('data-req-count')] || 0);
    });
  }

  function emptyState() {
    if (query.trim()) {
      return '<div class="ui-empty"><h3>No requests match “' + esc(query.trim()) + '”</h3><p>Try a college, a class code, or a dean’s name.</p></div>';
    }
    var copy = {
      pending_admin: ['You’re all caught up', 'No requests are waiting for you. New requests from deans will show up here.'],
      done: ['Nothing marked as done yet', 'Requests you finish will move here.'],
      sent_back: ['Nothing sent back', 'Requests you return to a dean will move here.'],
      all: ['No requests yet', 'When a dean sends a schedule-change request, it will show up here.'],
    }[filter];
    return '<div class="ui-empty"><div class="ui-empty-icon" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2" /><path d="M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z" /></svg>' +
      '</div><h3>' + esc(copy[0]) + '</h3><p>' + esc(copy[1]) + '</p></div>';
  }

  function renderList() {
    renderCounts();
    list.removeAttribute('aria-busy');
    var items = visibleRows();
    if (!items.length) {
      list.innerHTML = emptyState();
      return;
    }
    list.innerHTML = items.map(function (r) {
      var st = statusOf(r);
      var topics = (r.topicLabels || []).map(function (t) { return '<span class="ui-tag">' + esc(t) + '</span>'; }).join('');
      var preview = r.body ? '<p class="req-preview">' + esc(r.body) + '</p>' : '';
      var who = r.title + (r.collegeName ? ' · ' + r.collegeName : '');
      return '<li><button type="button" class="req-row" data-req-id="' + r.id + '">' +
        '<span class="req-row-main">' +
          '<span class="req-row-top"><span class="ui-chip ' + st.chip + '">' + esc(st.label) + '</span>' +
          '<span class="req-when" title="' + esc(r.atLabel || '') + '">' + esc(timeAgo(r.at)) + '</span></span>' +
          '<span class="req-subject">' + esc(subject(r)) + '</span>' +
          '<span class="req-who">' + esc(who) + '</span>' +
          (topics ? '<span class="req-topics">' + topics + '</span>' : '') +
          preview +
        '</span>' +
        '<span class="req-row-cta">' + (r.status === 'pending_admin' ? 'Review' : 'View') +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg></span>' +
        '</button></li>';
    }).join('');
  }

  function section(label, html) {
    return html ? '<div class="req-section"><h3 class="req-section-label">' + esc(label) + '</h3>' + html + '</div>' : '';
  }

  function renderDetail(r) {
    var st = statusOf(r);
    doc.getElementById('reqDialogTitle').textContent = subject(r);
    doc.getElementById('reqDialogMeta').innerHTML =
      '<span class="ui-chip ' + st.chip + '">' + esc(st.label) + '</span> ' +
      '<span>' + esc(r.title) + (r.collegeName ? ' · ' + esc(r.collegeName) : '') + '</span>' +
      '<span class="req-dialog-date">Sent ' + esc(r.atLabel || '') + '</span>';

    var classes = (r.classes || []).map(function (c) {
      return '<li><strong>' + esc(c.label) + '</strong>' + (c.meeting ? '<span>' + esc(c.meeting) + '</span>' : '') + '</li>';
    }).join('');
    if (!classes && r.classLabel) {
      classes = '<li><strong>' + esc(r.classLabel) + '</strong>' + (r.meetingLabel ? '<span>' + esc(r.meetingLabel) + '</span>' : '') + '</li>';
    }
    var topics = (r.topicLabels || []).map(function (t) { return '<span class="ui-tag">' + esc(t) + '</span>'; }).join(' ');
    var notes = '';
    if (r.forwardNote) notes += '<p class="req-note"><strong>Dean’s note:</strong> ' + esc(r.forwardNote) + '</p>';
    if (r.holdNote) notes += '<p class="req-note"><strong>Dean’s suggestion:</strong> ' + esc(r.holdNote) + '</p>';
    var reply = r.adminReply
      ? '<p class="req-note req-note--reply"><strong>' + (r.status === 'sent_back' ? 'Your reason for sending it back' : 'Your reply') + ':</strong> ' + esc(r.adminReply) + '</p>'
      : '';

    doc.getElementById('reqDialogContent').innerHTML =
      section('What needs to change', topics ? '<div class="req-topics">' + topics + '</div>' : '') +
      section('Classes concerned', classes ? '<ul class="req-classes">' + classes + '</ul>' : '') +
      section('Request', r.body ? '<p class="req-body">' + esc(r.body) + '</p>' : '') +
      section('Notes', notes + reply);

    var waiting = r.status === 'pending_admin';
    doc.getElementById('reqReplyBlock').hidden = !waiting;
    doneBtn.hidden = !waiting;
    backBtn.hidden = !waiting;
    doc.getElementById('reqCloseFoot').textContent = waiting ? 'Cancel' : 'Close';
    replyEl.value = '';
    setReplyError('');
    actionErr.hidden = true;
  }

  function setReplyError(msg) {
    replyErr.textContent = msg;
    replyErr.hidden = !msg;
    if (msg) replyEl.setAttribute('aria-invalid', 'true');
    else replyEl.removeAttribute('aria-invalid');
  }

  function openDetail(id) {
    current = rows.filter(function (r) { return r.id === id; })[0] || null;
    if (!current) return;
    renderDetail(current);
    dialog.showModal();
  }

  function notice(text) {
    var el = doc.createElement('div');
    el.className = 'ui-notice';
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 3500);
  }

  function act(action) {
    if (!current) return;
    var note = replyEl.value.trim();
    if (action === 'send_back' && !note) {
      setReplyError('Add a short reason so the dean knows what to change.');
      replyEl.focus();
      return;
    }
    setReplyError('');
    actionErr.hidden = true;
    doneBtn.disabled = backBtn.disabled = true;
    global.McuApi.send('POST', 'requests.php', { action: action, id: current.id, note: note })
      .then(function (res) {
        var next = res.requests || res.textRequests;
        if (next) rows = next.filter(function (r) { return STATUS[r.status]; });
        dialog.close();
        renderList();
        notice(action === 'done' ? 'Marked as done. The dean can see your reply.' : 'Sent back to the dean.');
      })
      .catch(function (err) {
        actionErr.textContent = (err && err.message) || 'That didn’t go through. Try again.';
        actionErr.hidden = false;
      })
      .then(function () {
        doneBtn.disabled = backBtn.disabled = false;
      });
  }

  function load() {
    if (!global.McuApi) {
      list.innerHTML = '<div class="ui-empty"><h3>Requests can’t load</h3><p>The server isn’t reachable. Refresh the page to try again.</p></div>';
      return;
    }
    global.McuApi.get('requests.php?actor=' + (global.location.pathname.indexOf('/super-admin/') !== -1 ? 'super_admin' : 'admin'))
      .then(function (res) {
        rows = (res.textRequests || []).filter(function (r) { return STATUS[r.status]; });
        renderList();
      })
      .catch(function () {
        list.removeAttribute('aria-busy');
        list.innerHTML = '<div class="ui-empty"><h3>Requests couldn’t load</h3><p>Refresh the page to try again.</p></div>';
      });
  }

  // Filter tabs
  var tabs = Array.prototype.slice.call(doc.querySelectorAll('[data-req-filter]'));
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      filter = tab.getAttribute('data-req-filter');
      tabs.forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
      renderList();
    });
  });

  doc.getElementById('reqSearch').addEventListener('input', function (e) {
    query = e.target.value;
    renderList();
  });

  list.addEventListener('click', function (e) {
    var row = e.target.closest && e.target.closest('[data-req-id]');
    if (row) openDetail(parseInt(row.getAttribute('data-req-id'), 10));
  });

  doneBtn.addEventListener('click', function () { act('done'); });
  backBtn.addEventListener('click', function () { act('send_back'); });
  replyEl.addEventListener('input', function () { if (replyEl.getAttribute('aria-invalid')) setReplyError(''); });
  Array.prototype.forEach.call(doc.querySelectorAll('[data-req-close]'), function (b) {
    b.addEventListener('click', function () { dialog.close(); });
  });
  dialog.addEventListener('click', function (e) {
    if (e.target === dialog) dialog.close();
  });

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', load);
  else load();
})(typeof window !== 'undefined' ? window : this);
