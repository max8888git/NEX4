/**
 * Curriculums page for the registrars (roles/staff/curriculums/curriculums):
 * every curriculum in the system, read-only. Pick one from the list (grouped by
 * college and program) to see it year by year. Curriculums are made by the Deans
 * and Program Chairs (curriculum-builder) and accepted on "From the colleges";
 * here the registrars can download, print, upload a CSV, and archive or restore.
 * Data: curricula.php (the live curricula / curriculum_courses tables).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var F = global.McuCurriculumForm;
  var esc = F.esc;

  var state = { list: [], pick: '', q: '', status: 'active', pending: 0 };
  var root = null;

  function notice(text, tone) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (tone === 'bad' ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  function when(v) {
    if (!v) return '';
    var d = new Date(v);
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function fromCollege(c) {
    return /^From /.test(c.sourceFileName || '');
  }

  function versionOf(c) {
    var m = String(c.sourceFileName || '').match(/version (\d+)/i);
    return m ? Number(m[1]) : 1;
  }

  function units(c) {
    var u = 0;
    (c.courses || []).forEach(function (x) { u += (Number(x.lecture_units) || 0) + (Number(x.lab_units) || 0); });
    return Math.round(u * 100) / 100;
  }

  // the shape curriculum-form.js uses for the read-only tables and the downloads
  function asReview(c) {
    return {
      programName: c.departmentName,
      collegeName: c.collegeName,
      academicYear: c.academicYear,
      version: versionOf(c),
      status: c.status === 'archived' ? 'archived' : 'accepted',
      courses: (c.courses || []).map(F.fromSheet)
    };
  }

  function load() {
    var reviews = global.McuApi.get('curriculum-reviews.php?inbox=1').then(function (r) {
      state.pending = (r.pending || []).length;
    }).catch(function () { state.pending = 0; });
    return Promise.all([global.McuApi.get('curricula.php'), reviews]).then(function (res) {
      state.list = (res[0].curricula || []).slice().sort(function (a, b) {
        return String(a.collegeName).localeCompare(String(b.collegeName)) ||
          String(a.departmentName).localeCompare(String(b.departmentName)) ||
          String(b.academicYear).localeCompare(String(a.academicYear));
      });
    });
  }

  function shown() {
    var q = state.q.trim().toLowerCase();
    return state.list.filter(function (c) {
      if (state.status !== 'all' && c.status !== state.status) return false;
      if (!q) return true;
      var hay = (c.collegeName + ' ' + c.departmentName + ' ' + c.academicYear).toLowerCase();
      if (hay.indexOf(q) >= 0) return true;
      return (c.courses || []).some(function (x) {
        return (String(x.course_id) + ' ' + String(x.course_title)).toLowerCase().indexOf(q) >= 0;
      });
    });
  }

  function render() {
    var list = shown();
    if (!list.some(function (c) { return c.id === state.pick; })) state.pick = list[0] ? list[0].id : '';
    var counts = { active: 0, archived: 0 };
    state.list.forEach(function (c) { counts[c.status === 'archived' ? 'archived' : 'active']++; });
    var html = '';
    if (state.pending) {
      html += '<a class="cl-pending" href="review.html"><strong>' + state.pending + (state.pending === 1 ? ' curriculum is' : ' curriculums are') +
        ' waiting for your review</strong><span>Open From the colleges &rarr;</span></a>';
    }
    html += '<div class="cl-bar">' +
      '<label class="sched-find"><span class="ui-sr">Search</span>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>' +
      '<input id="clFind" type="search" placeholder="Find a program, school year, or subject" autocomplete="off" value="' + esc(state.q) + '"></label>' +
      '<div class="ui-tabs" role="tablist" aria-label="Show">' +
      [['active', 'Active', counts.active], ['archived', 'Archived', counts.archived], ['all', 'All', state.list.length]].map(function (t) {
        return '<button type="button" class="ui-tab" role="tab" data-status="' + t[0] + '" aria-selected="' + (state.status === t[0]) + '">' + t[1] + ' <span class="ui-tab-count">' + t[2] + '</span></button>';
      }).join('') + '</div>' +
      '<button type="button" class="ui-btn" id="clUpload">Upload CSV</button></div>';
    if (!list.length) {
      html += '<div class="ui-empty"><h3>' + (state.list.length ? 'No curriculums match' : 'No curriculums yet') + '</h3><p>' +
        (state.list.length ? 'Try another search or show All.' : 'Curriculums the colleges send show on From the colleges; once accepted they’re listed here.') + '</p></div>';
      root.innerHTML = html;
      bindUpload();
      return;
    }
    html += '<div class="sched-md cl-md"><div class="sched-md-list" aria-label="Curriculums">';
    var lastCollege = null;
    list.forEach(function (c) {
      if (c.collegeName !== lastCollege) {
        html += '<div class="sched-md-group">' + esc(c.collegeName) + '</div>';
        lastCollege = c.collegeName;
      }
      html += '<button type="button" class="sched-md-item' + (c.id === state.pick ? ' is-picked' : '') + '" data-pick="' + esc(c.id) + '" aria-current="' + (c.id === state.pick) + '">' +
        '<span class="sched-md-name">' + esc(c.departmentName) + '<span class="sched-md-sub">' + esc(c.academicYear) + ' · ' + (c.courses || []).length + ' subjects</span></span>' +
        (c.status === 'archived' ? '<span class="sched-tag sched-tag--muted">Archived</span>' : '<span class="sched-tag sched-tag--ok">Active</span>') + '</button>';
    });
    html += '</div><div class="sched-md-detail" id="clDetail"></div></div>';
    root.innerHTML = html;
    bindUpload();
    renderDetail();
  }

  function renderDetail() {
    var host = doc.getElementById('clDetail');
    var c = state.list.filter(function (x) { return x.id === state.pick; })[0];
    if (!host || !c) return;
    var r = asReview(c);
    var used = c.status === 'archived'
      ? '<button type="button" class="ui-btn" data-restore>Restore</button><button type="button" class="ui-btn ui-btn--danger" data-delete>Delete</button>'
      : '<button type="button" class="ui-btn" data-archive>Archive</button>';
    host.innerHTML =
      '<div class="cb-head"><div><h2>' + esc(c.departmentName) + '</h2><p>' + esc(c.collegeName) + ' · Effective ' + esc(c.academicYear) +
      ' · ' + r.courses.length + ' subjects, ' + units(c) + ' units</p>' +
      '<p class="cb-muted">' + (fromCollege(c) ? esc(c.sourceFileName.replace(/^From /, 'Made by ')) + ', accepted on From the colleges' : 'Uploaded from ' + esc(c.sourceFileName || 'a CSV file')) +
      (c.updatedAt ? ' · updated ' + esc(when(c.updatedAt)) : '') + '</p></div>' +
      '<div class="cb-head-side">' + (c.status === 'archived' ? '<span class="ui-chip ui-chip--neutral">Archived</span>' : '<span class="ui-chip ui-chip--done">Active</span>') + '</div></div>' +
      '<div class="cb-toolbar"><span class="cb-muted">Read-only. The Deans and Program Chairs make changes and send a new version.</span><span class="cb-spacer"></span>' +
      '<button type="button" class="ui-btn" data-csv>Download CSV</button><button type="button" class="ui-btn" data-pdf>Download PDF</button><button type="button" class="ui-btn" data-print>Print</button>' + used + '</div>' +
      (c.status === 'archived' ? '<p class="cl-note">Archived: it isn’t offered when making sections. Restore it to use it again.</p>' : '') +
      '<div id="clForm" class="cf"></div>';
    F.mount(doc.getElementById('clForm'), { courses: r.courses, editable: false, roomTypes: [] });
  }

  function bindUpload() {
    var btn = doc.getElementById('clUpload');
    if (btn && global.CurriculumUpload && !btn.dataset.bound) {
      btn.dataset.bound = '1';
      global.CurriculumUpload.attach(btn, {
        onSaved: function () {
          notice('Curriculum uploaded.');
          load().then(render);
        }
      });
    }
  }

  function ask(title, message, yes, danger, go) {
    var d = doc.getElementById('clAsk');
    if (!d) {
      d = doc.createElement('dialog');
      d.id = 'clAsk';
      d.className = 'ui-dialog cb-dialog';
      doc.body.appendChild(d);
      d.addEventListener('click', function (e) {
        if (e.target.closest('[data-close]') || e.target === d) d.close();
      });
    }
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + esc(title) + '</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p>' + esc(message) + '</p><p class="ui-field-error" id="clAskErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ' + (danger ? 'ui-btn--danger' : 'ui-btn--primary') + '" id="clAskGo">' + esc(yes) + '</button></div>';
    d.showModal();
    doc.getElementById('clAskGo').onclick = function () {
      var b = this;
      b.disabled = true;
      go().then(function () { d.close(); }).catch(function (e) {
        b.disabled = false;
        var err = doc.getElementById('clAskErr');
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function setStatus(c, status) {
    return global.McuApi.send('PATCH', 'curricula.php', { id: c.id, status: status }).then(function () {
      notice(status === 'archived' ? 'Archived.' : 'Restored.');
      return load().then(render);
    });
  }

  function onClick(e) {
    var t = e.target;
    var el;
    if ((el = t.closest('[data-status]'))) {
      state.status = el.getAttribute('data-status');
      return render();
    }
    if ((el = t.closest('[data-pick]'))) {
      state.pick = el.getAttribute('data-pick');
      root.querySelectorAll('[data-pick]').forEach(function (b) {
        var on = b.getAttribute('data-pick') === state.pick;
        b.classList.toggle('is-picked', on);
        b.setAttribute('aria-current', on ? 'true' : 'false');
      });
      renderDetail();
      var d = doc.getElementById('clDetail');
      if (d && global.matchMedia && global.matchMedia('(max-width: 860px)').matches) d.scrollIntoView({ block: 'start', behavior: 'smooth' });
      return;
    }
    var c = state.list.filter(function (x) { return x.id === state.pick; })[0];
    if (!c) return;
    if (t.closest('[data-csv]')) return F.csv(asReview(c));
    if (t.closest('[data-print]')) return F.print(asReview(c));
    if (t.closest('[data-pdf]')) return F.pdf(asReview(c)).catch(function (er) { notice(er.message, 'bad'); });
    if (t.closest('[data-archive]')) {
      return ask('Archive this curriculum?', c.departmentName + ' ' + c.academicYear + ' won’t be offered when making new sections. Sections that already use it keep it. You can restore it later.', 'Archive', false,
        function () { return setStatus(c, 'archived'); });
    }
    if (t.closest('[data-restore]')) {
      return ask('Restore this curriculum?', c.departmentName + ' ' + c.academicYear + ' becomes active again and is offered when making sections.', 'Restore', false,
        function () { return setStatus(c, 'active'); });
    }
    if (t.closest('[data-delete]')) {
      return ask('Delete this curriculum?', c.departmentName + ' ' + c.academicYear + ' and its subjects will be deleted for good. This can’t be undone.', 'Delete', true, function () {
        return global.McuApi.send('DELETE', 'curricula.php?id=' + encodeURIComponent(c.id)).then(function () {
          notice('Deleted.');
          return load().then(render);
        });
      });
    }
  }

  function init() {
    root = doc.getElementById('clRoot');
    if (!root || !global.McuApi) return;
    root.addEventListener('click', onClick);
    var timer = null;
    root.addEventListener('input', function (e) {
      if (e.target.id !== 'clFind') return;
      state.q = e.target.value;
      clearTimeout(timer);
      timer = setTimeout(function () {
        var pos = e.target.selectionStart;
        render();
        var f = doc.getElementById('clFind');
        if (f) {
          f.focus();
          try { f.setSelectionRange(pos, pos); } catch (er) {}
        }
      }, 200);
    });
    root.innerHTML = '<p class="cb-loading">Loading curriculums…</p>';
    var want = new URLSearchParams(global.location.search).get('open');
    // the list doesn't need the other stores; only Upload CSV does (it waits on its own)
    load()
      .then(function () {
        if (want) {
          state.pick = want;
          state.status = 'all';
        }
        render();
      })
      .catch(function (e) {
        root.innerHTML = '<div class="ui-empty"><h3>Couldn’t load curriculums</h3><p>' + esc(e.message) + '</p></div>';
      });
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
