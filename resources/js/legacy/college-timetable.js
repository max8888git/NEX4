/**
 * Dean / Program Chair dashboard: their college's approved timetable (the copy the
 * VPAA published), by section, with CSV / PDF / print copies.
 * Data: GET schedule.php?college_final=1[&term=ID] (schedule-colleges.php college_final_for_viewer).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  var DAY_NAMES = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
  var root;
  var state = { data: null, program: '', find: '' };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function time12(t) {
    var m = String(t || '').match(/^(\d{1,2}):(\d{2})/);
    if (!m) return t || '';
    var h = Number(m[1]);
    return (h % 12 || 12) + ':' + m[2] + (h < 12 ? ' AM' : ' PM');
  }

  function dateText(v) {
    if (!v) return '';
    var d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? String(v) : d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function kind(c) {
    return c.component === 'lab' || c.component === 'laboratory' ? 'Lab' : 'Lecture';
  }

  // one row per meeting; classes not placed yet get one row with no day
  function rows() {
    var d = state.data;
    var q = state.find.toLowerCase();
    var out = [];
    (d.classes || []).forEach(function (c) {
      if (state.program && c.programId !== state.program) return;
      var meetings = (c.meetings || []).length ? c.meetings : [null];
      meetings.forEach(function (m) {
        var r = {
          section: c.sectionName || '',
          year: c.yearLevel || '',
          code: (c.courseCode || '') + (kind(c) === 'Lab' && !/L$/.test(c.courseCode || '') ? 'L' : ''),
          title: c.courseTitle || '',
          kind: kind(c),
          day: m ? m.day : '',
          start: m ? m.start : '',
          end: m ? m.end : '',
          room: m ? (m.roomCode || (c.requiresCampusRoom === false ? 'Off campus' : '')) : '',
          students: c.partitionStudents || c.studentCount || '',
          placed: !!m
        };
        if (q && (r.section + ' ' + r.code + ' ' + r.title + ' ' + r.room).toLowerCase().indexOf(q) < 0) return;
        out.push(r);
      });
    });
    out.sort(function (a, b) {
      return a.section.localeCompare(b.section, undefined, { numeric: true }) ||
        (a.placed === b.placed ? 0 : a.placed ? -1 : 1) ||
        DAYS.indexOf(a.day) - DAYS.indexOf(b.day) || a.start.localeCompare(b.start) || a.code.localeCompare(b.code);
    });
    return out;
  }

  function bySection(list) {
    var map = {};
    var order = [];
    list.forEach(function (r) {
      if (!map[r.section]) {
        map[r.section] = [];
        order.push(r.section);
      }
      map[r.section].push(r);
    });
    return order.map(function (s) { return { name: s, rows: map[s] }; });
  }

  function statusHtml(d) {
    if (d.publishedAt) {
      var updating = d.status !== 'published';
      return '<span class="ui-chip ui-chip--done">Approved by the VPAA · ' + esc(dateText(d.publishedAt)) + '</span>' +
        (updating ? '<p class="ct-note">The Registrar’s office is preparing an update. This is the last approved version until the VPAA approves the new one.</p>' : '');
    }
    var text = {
      pending_vpaa: 'Waiting for the VPAA’s approval',
      pending_super_admin: 'With the University Registrar',
      with_registrar: 'With the University Registrar'
    }[d.status] || 'Being prepared by the Registrar’s office';
    return '<span class="ui-chip ui-chip--waiting">' + esc(text) + '</span>';
  }

  function scope() {
    var d = state.data;
    var p = (d.programs || []).filter(function (x) { return x.id === state.program; })[0];
    return p ? p.name : (d.role === 'program_chair' && (d.programs || []).length === 1 ? d.programs[0].name : d.collegeName);
  }

  function render() {
    var d = state.data;
    var list = d.publishedAt ? rows() : [];
    var groups = bySection(list);
    var sections = {};
    var unplaced = 0;
    list.forEach(function (r) {
      sections[r.section] = true;
      if (!r.placed) unplaced++;
    });
    var termSelect = (d.terms || []).length > 1
      ? '<label class="ct-field"><span>Term</span><select class="ui-input" data-term>' + d.terms.map(function (t) {
        return '<option value="' + t.id + '"' + (d.term && d.term.id === t.id ? ' selected' : '') + '>' + esc(t.label) + '</option>';
      }).join('') + '</select></label>'
      : '';
    var programSelect = (d.programs || []).length > 1
      ? '<label class="ct-field"><span>Program</span><select class="ui-input" data-program><option value="">All programs</option>' + d.programs.map(function (p) {
        return '<option value="' + esc(p.id) + '"' + (state.program === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>';
      }).join('') + '</select></label>'
      : '';

    var html = '<div class="ct-head"><div><span class="ct-kicker">Approved timetable</span>' +
      '<h2 class="ct-title">' + esc(d.term ? d.term.label : 'No current offering yet') + '</h2>' +
      '<p class="ct-sub">' + esc(d.collegeName) + '</p></div><div class="ct-status">' + statusHtml(d) + '</div></div>';

    if (!d.publishedAt) {
      html += '<div class="ui-empty ct-empty"><h3>No approved timetable yet</h3><p>Your college’s timetable shows here, ready to check and download, once the VPAA approves it. You’ll get a notification.</p></div>';
      root.innerHTML = html + (termSelect ? '<div class="ct-bar">' + termSelect + '</div>' : '');
      return;
    }

    html += '<div class="ct-bar">' + termSelect + programSelect +
      '<label class="ct-field ct-find"><span>Search</span><input class="ui-input" type="search" data-find placeholder="Section, subject or room" value="' + esc(state.find) + '"></label>' +
      '<div class="ct-actions"><button type="button" class="ui-btn" data-csv>Download CSV</button><button type="button" class="ui-btn" data-pdf>Download PDF</button><button type="button" class="ui-btn" data-print>Print</button></div></div>' +
      '<p class="ct-count">' + Object.keys(sections).length + ' sections · ' + list.length + ' class meetings' +
      (unplaced ? ' · <strong>' + unplaced + ' not scheduled</strong>' : '') + '</p>';

    if (!groups.length) {
      html += '<div class="ui-empty ct-empty"><h3>Nothing matches</h3><p>Try another program or search.</p></div>';
    } else {
      html += groups.map(function (g, i) {
        return '<details class="ct-section"' + (i < 3 ? ' open' : '') + '><summary><strong>' + esc(g.name) + '</strong><span>' + g.rows.length + (g.rows.length === 1 ? ' meeting' : ' meetings') + '</span></summary>' +
          '<div class="ct-scroll"><table class="ct-table"><thead><tr><th>Day</th><th>Time</th><th>Code</th><th>Subject</th><th>Type</th><th>Room</th></tr></thead><tbody>' +
          g.rows.map(function (r) {
            return '<tr' + (r.placed ? '' : ' class="is-unplaced"') + '><td>' + esc(r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled') + '</td>' +
              '<td class="ct-nowrap">' + (r.placed ? esc(time12(r.start) + ' – ' + time12(r.end)) : '—') + '</td>' +
              '<td class="ct-nowrap"><strong>' + esc(r.code) + '</strong></td><td>' + esc(r.title) + '</td><td>' + esc(r.kind) + '</td><td>' + esc(r.room || '—') + '</td></tr>';
          }).join('') + '</tbody></table></div></details>';
      }).join('');
    }
    root.innerHTML = html;
  }

  // ---------------------------------------------------------------- copies

  function fileBase() {
    var d = state.data;
    return (scope() + ' timetable ' + (d.term ? d.term.label : '')).replace(/[\\/:*?"<>|·]+/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function download(name, blob) {
    var a = doc.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    doc.body.appendChild(a);
    a.click();
    setTimeout(function () {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 1000);
  }

  function csv() {
    var cell = function (v) {
      v = String(v == null ? '' : v);
      return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    };
    var lines = [['Section', 'Year level', 'Day', 'Start', 'End', 'Course Code', 'Course Title', 'Type', 'Room', 'Students'].join(',')];
    rows().forEach(function (r) {
      lines.push([r.section, r.year, r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled', time12(r.start), time12(r.end), r.code, r.title, r.kind, r.room, r.students].map(cell).join(','));
    });
    download(fileBase() + '.csv', new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  }

  function logoUrl() {
    var img = doc.querySelector('.topbar-logo img');
    return img ? img.src : new URL('/Attri/mculogo.jpg', global.location.href).href;
  }

  function subtitle() {
    var d = state.data;
    return (d.term ? d.term.label + ' · ' : '') + 'Approved by the VPAA ' + dateText(d.publishedAt);
  }

  function pdf() {
    if (!global.McuTimetablePdf) {
      notice('The PDF maker didn’t load. Refresh the page and try again.', 'bad');
      return;
    }
    var d = state.data;
    global.McuTimetablePdf.download({
      logoUrl: logoUrl(),
      collegeName: d.collegeName,
      title: scope() + ' Timetable',
      subtitle: subtitle(),
      sections: bySection(rows()).map(function (g) {
        return {
          title: g.name,
          rows: g.rows.map(function (r) {
            return [r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled', r.placed ? time12(r.start) + ' – ' + time12(r.end) : '—', r.code, r.title, r.kind, r.room || '—'];
          })
        };
      }),
      footer: 'MCU-NEX · ' + scope() + ' · printed ' + new Date().toLocaleDateString(),
      fileName: fileBase() + '.pdf'
    }).catch(function (e) { notice(e.message, 'bad'); });
  }

  function print() {
    var d = state.data;
    var body = bySection(rows()).map(function (g) {
      return '<h3>' + esc(g.name) + '</h3><table><thead><tr><th>Day</th><th>Time</th><th>Code</th><th>Subject</th><th>Type</th><th>Room</th></tr></thead><tbody>' +
        g.rows.map(function (r) {
          return '<tr><td>' + esc(r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled') + '</td><td>' + (r.placed ? esc(time12(r.start) + ' – ' + time12(r.end)) : '—') + '</td><td><b>' + esc(r.code) + '</b></td><td>' + esc(r.title) + '</td><td>' + esc(r.kind) + '</td><td>' + esc(r.room || '—') + '</td></tr>';
        }).join('') + '</tbody></table>';
    }).join('');
    var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(fileBase()) + '</title><style>' +
      '@page{size:A4;margin:14mm}body{font-family:"DM Sans",Arial,sans-serif;color:#1f1230;font-size:10.5px}' +
      'header{display:flex;gap:12px;align-items:center;border-bottom:3px solid #f9b937;padding-bottom:8px;margin-bottom:10px}' +
      'header img{width:46px;height:46px;border-radius:50%}h1{font-size:16px;margin:0;color:#2d163f}h2{font-size:13px;margin:2px 0 0;color:#5c267e;font-weight:600}' +
      '.meta{color:#6f5f86;margin-top:2px}h3{font-size:12px;margin:14px 0 4px;color:#2d163f}' +
      'table{width:100%;border-collapse:collapse;page-break-inside:avoid}th,td{border:1px solid #d9cfe6;padding:3px 5px;text-align:left;vertical-align:top}' +
      'th{background:#f3eef9;color:#4b3d5e;font-size:9.5px;text-transform:uppercase}' +
      '</style></head><body><header><img src="' + esc(logoUrl()) + '" alt=""><div><h1>' + esc(d.collegeName) + '</h1><h2>' + esc(scope()) + ' Timetable</h2>' +
      '<div class="meta">' + esc(subtitle()) + '</div></div></header>' + body + '</body></html>';
    var frame = doc.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    doc.body.appendChild(frame);
    var w = frame.contentWindow;
    w.document.open();
    w.document.write(html);
    w.document.close();
    setTimeout(function () {
      w.focus();
      w.print();
      setTimeout(function () { frame.remove(); }, 1000);
    }, 400);
  }

  function notice(text, tone) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (tone === 'bad' ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  // ---------------------------------------------------------------- load

  function load(termId) {
    root.innerHTML = '<p class="ct-loading">Loading your timetable…</p>';
    return global.McuApi.get('schedule.php?college_final=1' + (termId ? '&term=' + encodeURIComponent(termId) : '')).then(function (data) {
      state.data = data;
      if (!(data.programs || []).some(function (p) { return p.id === state.program; })) state.program = '';
      render();
    }).catch(function (e) {
      root.innerHTML = '<div class="ui-empty ct-empty"><h3>The timetable couldn’t be loaded</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  function init() {
    root = doc.getElementById('ctRoot');
    if (!root || !global.McuApi) return;
    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-csv]')) return csv();
      if (e.target.closest('[data-pdf]')) return pdf();
      if (e.target.closest('[data-print]')) return print();
    });
    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-term]')) return load(e.target.value);
      if (e.target.matches('[data-program]')) {
        state.program = e.target.value;
        render();
      }
    });
    root.addEventListener('input', function (e) {
      if (!e.target.matches('[data-find]')) return;
      state.find = e.target.value;
      var pos = e.target.selectionStart;
      render();
      var el = root.querySelector('[data-find]');
      if (el) {
        el.focus();
        el.setSelectionRange(pos, pos);
      }
    });
    load();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
