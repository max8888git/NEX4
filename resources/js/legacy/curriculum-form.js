/**
 * The curriculum form shared by the Dean / Program Chair page
 * (curriculum-builder.js) and the registrars' review page (curriculum-review.js):
 * year-by-year, term-by-term subject tables, the same checks the server runs
 * (legacy-api/curriculum-reviews.php curriculum_problems), totals, and the
 * CSV / print / PDF copies. A lab gets its code automatically: CCS 101 → CCS 101L.
 *
 * Course shape: { code, title, lec, lab, year, term ('1'|'2'|'summer'),
 *                 lecRoom, labRoom, prereqs: [code], elective }
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var TERMS = ['1', '2', 'summer'];
  var LEC_HOURS = 1; // hours a week per lecture unit
  var LAB_HOURS = 3; // hours a week per lab unit

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function codeKey(code) {
    return String(code || '').replace(/\s+/g, ' ').trim().toUpperCase();
  }

  function num(v) {
    var n = Number(v);
    return isFinite(n) ? n : 0;
  }

  function round2(n) {
    return Math.round(n * 100) / 100;
  }

  function labCode(c) {
    return num(c.lab) > 0 && codeKey(c.code) ? codeKey(c.code) + 'L' : '';
  }

  function termOrder(year, term) {
    return num(year) * 10 + (term === 'summer' ? 3 : term === '2' ? 2 : 1);
  }

  function ordinal(n) {
    return n === 1 ? '1st' : n === 2 ? '2nd' : n === 3 ? '3rd' : n + 'th';
  }

  function termLabel(year, term) {
    return ordinal(num(year)) + ' year · ' + (term === 'summer' ? 'Summer' : term === '2' ? '2nd semester' : '1st semester');
  }

  // "3RD YEAR STANDING" → "3rd year standing"
  function prereqLabel(p) {
    p = codeKey(p);
    return /STANDING/.test(p) ? p.toLowerCase() : p;
  }

  function standing(year) {
    return ordinal(year).toUpperCase() + ' YEAR STANDING';
  }

  function blankCourse(year, term) {
    return { code: '', title: '', lec: 3, lab: 0, year: year, term: term, lecRoom: 'Lecture', labRoom: '', prereqs: [], elective: false };
  }

  /** From the CSV reader (CurriculumStore.parse) or a live curriculum row. */
  function fromSheet(c) {
    return {
      code: codeKey(c.course_id || c.course_code || c.code),
      title: String(c.course_title || c.title || '').trim(),
      lec: num(c.lecture_units != null ? c.lecture_units : c.lec),
      lab: num(c.lab_units != null ? c.lab_units : c.lab),
      year: num(c.year_level != null ? c.year_level : c.year) || 1,
      term: String(c.semester != null ? c.semester : c.term || '1'),
      lecRoom: String(c.lecture_room_type || c.lecRoom || ''),
      labRoom: String(c.lab_room_type || c.labRoom || ''),
      prereqs: [c.prerequisite_1, c.prerequisite_2, c.prerequisite_3].concat(c.prereqs || []).map(codeKey).filter(Boolean).slice(0, 3),
      elective: !!(c.is_elective || c.elective)
    };
  }

  // ---------------------------------------------------------------- checks

  /** Same rules (and wording) as curriculum_problems() on the server. */
  function check(courses, roomTypes) {
    var out = [];
    var types = (roomTypes || []).map(function (t) { return String(t).toLowerCase(); });
    function add(level, row, message) {
      out.push({ level: level, row: row, message: message });
    }
    if (!courses.length) {
      add('error', null, 'Add at least one subject.');
      return out;
    }
    var byCode = {};
    var byTitle = {};
    var termUnits = {};
    courses.forEach(function (c, i) {
      var code = codeKey(c.code);
      var title = String(c.title || '').trim();
      var label = code || 'Row ' + (i + 1);
      if (!code) add('error', i, (title ? title : 'Row ' + (i + 1)) + ' has no course code.');
      if (!title) add('error', i, label + ' has no course title.');
      if (code) {
        if (byCode[code] != null) add('error', i, code + ' is listed twice. Each course code can be used once.');
        else byCode[code] = i;
      }
      var lec = Number(c.lec);
      var lab = Number(c.lab);
      if (!isFinite(lec) || !isFinite(lab) || lec < 0 || lab < 0 || lec > 12 || lab > 12) {
        add('error', i, label + ': lecture and lab units must be numbers from 0 to 12.');
      } else if (lec + lab <= 0) {
        add('error', i, label + ' has 0 units. Give it lecture or lab units.');
      }
      if (lec > 0 && !String(c.lecRoom || '').trim()) add('error', i, label + ' has lecture units but no lecture room type.');
      if (lab > 0 && !String(c.labRoom || '').trim()) add('error', i, label + ' has lab units but no lab room type.');
      [c.lecRoom, c.labRoom].forEach(function (t) {
        t = String(t || '').trim();
        if (t && types.length && types.indexOf(t.toLowerCase()) < 0) {
          add('error', i, label + ': room type "' + t + '" isn’t in Data management → Room types.');
        }
      });
      var year = num(c.year);
      if (year < 1 || year > 8 || TERMS.indexOf(String(c.term)) < 0) {
        add('error', i, label + ' needs a year level (1–8) and a term (1st, 2nd, or summer).');
      } else {
        var k = termOrder(year, String(c.term));
        termUnits[k] = (termUnits[k] || 0) + (lec || 0) + (lab || 0);
      }
      if (title) {
        var tk = title.toLowerCase();
        (byTitle[tk] = byTitle[tk] || []).push(code);
      }
    });
    Object.keys(byCode).forEach(function (code) {
      var m = code.match(/^(.*\d)\s*L$/);
      if (m && byCode[codeKey(m[1])] != null) {
        add('error', byCode[code], code + ' is the lab of ' + codeKey(m[1]) + '. Put the lab units on ' + codeKey(m[1]) +
          ' instead; the lab gets the “L” code automatically.');
      }
    });
    courses.forEach(function (c, i) {
      var code = codeKey(c.code) || 'Row ' + (i + 1);
      var here = termOrder(c.year, String(c.term));
      (c.prereqs || []).slice(0, 3).forEach(function (p) {
        p = codeKey(p);
        if (!p || notACourse(p)) return;
        var pi = byCode[p];
        if (pi == null) {
          var m = p.match(/^(.*\d)\s*L$/);
          if (m) pi = byCode[codeKey(m[1])];
        }
        if (pi == null) {
          add('error', i, code + ': prerequisite ' + p + ' isn’t in this curriculum.');
          return;
        }
        if (termOrder(courses[pi].year, String(courses[pi].term)) >= here) {
          add('error', i, code + ': prerequisite ' + p + ' must be in an earlier term.');
        }
      });
    });
    Object.keys(byTitle).forEach(function (t) {
      var codes = byTitle[t].filter(function (v, idx, a) { return v && a.indexOf(v) === idx; });
      if (codes.length > 1) add('warning', null, 'Same title under different codes: ' + codes.join(', ') + '.');
    });
    Object.keys(termUnits).forEach(function (k) {
      var u = round2(termUnits[k]);
      if (u > 30) {
        var n = Number(k);
        add('warning', null, termLabel(Math.floor(n / 10), ['', '1', '2', 'summer'][n % 10]).replace(' · ', ', ') + ' has ' + u + ' units, which is a heavy load.');
      }
    });
    return out;
  }

  // prerequisites that aren't subjects: "3rd year standing", "None", or a "**" placeholder
  function notACourse(p) {
    return /STANDING/.test(p) || /^(\*+|-+|NONE|N\/A)$/.test(p);
  }

  function errorsIn(problems) {
    return problems.filter(function (p) { return p.level === 'error'; });
  }

  // ---------------------------------------------------------------- grouping

  function groups(courses, extraTerms) {
    var map = {};
    var maxYear = 1;
    courses.forEach(function (c, i) {
      var y = num(c.year) || 1;
      var t = TERMS.indexOf(String(c.term)) >= 0 ? String(c.term) : '1';
      maxYear = Math.max(maxYear, y);
      (map[y + '|' + t] = map[y + '|' + t] || []).push(i);
    });
    (extraTerms || []).forEach(function (k) {
      maxYear = Math.max(maxYear, num(k.split('|')[0]));
      map[k] = map[k] || [];
    });
    var out = [];
    for (var y = 1; y <= maxYear; y++) {
      var terms = [];
      ['1', '2', 'summer'].forEach(function (t) {
        if (t === 'summer' && !map[y + '|summer']) return;
        terms.push({ year: y, term: t, rows: map[y + '|' + t] || [] });
      });
      out.push({ year: y, terms: terms });
    }
    return out;
  }

  function totals(courses, rows) {
    var lec = 0;
    var lab = 0;
    rows.forEach(function (i) {
      lec += num(courses[i].lec);
      lab += num(courses[i].lab);
    });
    return { lec: round2(lec), lab: round2(lab), units: round2(lec + lab), hours: round2(lec * LEC_HOURS + lab * LAB_HOURS) };
  }

  function grandTotals(courses) {
    return totals(courses, courses.map(function (c, i) { return i; }));
  }

  // ---------------------------------------------------------------- render

  function roomSelect(value, roomTypes, field, i, disabled) {
    var opts = ['<option value="">—</option>'];
    var found = false;
    (roomTypes || []).forEach(function (t) {
      if (t === value) found = true;
      opts.push('<option' + (t === value ? ' selected' : '') + '>' + esc(t) + '</option>');
    });
    if (value && !found) opts.push('<option selected>' + esc(value) + '</option>');
    return '<select class="cf-in cf-room" data-field="' + field + '" data-row="' + i + '"' + (disabled ? ' disabled' : '') + ' aria-label="' + (field === 'lecRoom' ? 'Lecture' : 'Lab') + ' room type">' + opts.join('') + '</select>';
  }

  function rowNoteHtml(c, opts) {
    var note = (opts.notes || {})[codeKey(c.code)];
    return note ? '<div class="cf-rownote"><strong>Note:</strong> ' + esc(note) + '</div>' : '';
  }

  function rowHtml(c, i, opts, rowProblems) {
    var bad = rowProblems[i] && rowProblems[i].some(function (p) { return p.level === 'error'; });
    var mark = (opts.marks || {})[codeKey(c.code)];
    var cls = [bad ? 'is-bad' : '', mark ? 'is-' + mark : '', (opts.notes || {})[codeKey(c.code)] ? 'has-note' : ''].filter(Boolean).join(' ');
    if (!opts.editable) {
      return '<tr data-view-row="' + i + '"' + (cls ? ' class="' + cls + '"' : '') + '>' +
        '<td class="cf-code">' + esc(codeKey(c.code)) + (labCode(c) ? '<span class="cf-labcode">Lab: ' + esc(labCode(c)) + '</span>' : '') +
        (mark ? '<span class="cf-mark cf-mark--' + mark + '">' + (mark === 'new' ? 'New' : 'Changed') + '</span>' : '') + '</td>' +
        '<td>' + esc(c.title) + (c.elective ? ' <span class="cf-tag">Elective</span>' : '') + rowNoteHtml(c, opts) + '</td>' +
        '<td class="cf-num">' + num(c.lec) + '</td><td class="cf-num">' + num(c.lab) + '</td>' +
        '<td class="cf-num">' + round2(num(c.lec) + num(c.lab)) + '</td>' +
        '<td>' + esc(c.lecRoom || '—') + (num(c.lab) > 0 ? ' / ' + esc(c.labRoom || '—') : '') + '</td>' +
        '<td>' + esc((c.prereqs || []).map(prereqLabel).join(', ') || '—') + '</td>' +
        (opts.noting ? '<td class="cf-act"><button type="button" class="cf-notebtn" data-note="' + i + '">' + ((opts.notes || {})[codeKey(c.code)] ? 'Edit note' : 'Note') + '</button></td>' : '') +
        '</tr>';
    }
    return '<tr data-row="' + i + '"' + (bad ? ' class="is-bad"' : '') + '>' +
      '<td class="cf-code"><input class="cf-in" data-field="code" data-row="' + i + '" value="' + esc(c.code) + '" placeholder="CCS 101" aria-label="Course code">' +
      '<span class="cf-labcode" data-labcode="' + i + '">' + (labCode(c) ? 'Lab: ' + esc(labCode(c)) : '') + '</span></td>' +
      '<td><input class="cf-in" data-field="title" data-row="' + i + '" value="' + esc(c.title) + '" placeholder="Descriptive title" aria-label="Course title">' +
      '<label class="cf-elective"><input type="checkbox" data-field="elective" data-row="' + i + '"' + (c.elective ? ' checked' : '') + '> Elective</label>' + rowNoteHtml(c, opts) + '</td>' +
      '<td class="cf-num"><input class="cf-in cf-n" type="number" min="0" max="12" step="0.5" data-field="lec" data-row="' + i + '" value="' + num(c.lec) + '" aria-label="Lecture units"></td>' +
      '<td class="cf-num"><input class="cf-in cf-n" type="number" min="0" max="12" step="0.5" data-field="lab" data-row="' + i + '" value="' + num(c.lab) + '" aria-label="Lab units"></td>' +
      '<td class="cf-num" data-units="' + i + '">' + round2(num(c.lec) + num(c.lab)) + '</td>' +
      '<td class="cf-rooms">' + roomSelect(c.lecRoom, opts.roomTypes, 'lecRoom', i) + roomSelect(c.labRoom, opts.roomTypes, 'labRoom', i, num(c.lab) <= 0) + '</td>' +
      '<td class="cf-pre" data-pre="' + i + '">' + prereqCellHtml(i, opts.all) + '</td>' +
      '<td class="cf-act"><button type="button" class="cf-x" data-remove="' + i + '" aria-label="Remove ' + esc(codeKey(c.code) || 'this subject') + '" title="Remove">&times;</button></td></tr>';
  }

  /**
   * What a subject can require: "—" (none), a year standing up to its own year,
   * or a subject from an earlier term (earlier years, and the earlier semester of the same year).
   */
  function prereqOptionsHtml(i, courses) {
    var c = courses[i];
    var year = num(c.year) || 1;
    var here = termOrder(year, String(c.term));
    var chosen = (c.prereqs || []).map(codeKey);
    var html = '<option value="">' + (chosen.length ? '+ Add another' : '— None') + '</option>';
    if (year >= 2) {
      html += '<optgroup label="Year standing">';
      for (var y = 2; y <= year; y++) {
        if (chosen.indexOf(standing(y)) < 0) html += '<option value="' + esc(standing(y)) + '">' + ordinal(y) + ' year standing</option>';
      }
      html += '</optgroup>';
    }
    var byTerm = {};
    courses.forEach(function (x, j) {
      var code = codeKey(x.code);
      var k = termOrder(x.year, String(x.term));
      if (j === i || !code || k >= here || chosen.indexOf(code) >= 0) return;
      (byTerm[k] = byTerm[k] || []).push(x);
    });
    Object.keys(byTerm).map(Number).sort(function (a, b) { return a - b; }).forEach(function (k) {
      html += '<optgroup label="' + esc(termLabel(Math.floor(k / 10), ['', '1', '2', 'summer'][k % 10])) + '">' +
        byTerm[k].map(function (x) {
          var t = String(x.title || '').trim();
          return '<option value="' + esc(codeKey(x.code)) + '">' + esc(codeKey(x.code) + (t ? ' – ' + (t.length > 40 ? t.slice(0, 38) + '…' : t) : '')) + '</option>';
        }).join('') + '</optgroup>';
    });
    return html;
  }

  function prereqCellHtml(i, courses) {
    var c = courses[i];
    var list = c.prereqs || [];
    return list.map(function (p, k) {
      return '<span class="cf-pchip">' + esc(prereqLabel(p)) + '<button type="button" data-pre-remove="' + i + '|' + k + '" aria-label="Remove prerequisite ' + esc(prereqLabel(p)) + '">&times;</button></span>';
    }).join('') +
      (list.length < 3 ? '<select class="cf-in cf-pre-add" data-field="prereqAdd" data-row="' + i + '" aria-label="Add a prerequisite">' + prereqOptionsHtml(i, courses) + '</select>' : '');
  }

  function removedHtml(removed, year, term) {
    var list = (removed || []).filter(function (c) { return num(c.year) === year && String(c.term) === term; });
    return list.length ? '<p class="cf-removed">Removed since it was sent back: ' + list.map(function (c) { return '<s>' + esc(codeKey(c.code) + ' ' + c.title) + '</s>'; }).join(', ') + '</p>' : '';
  }

  /**
   * Draw the form into `host`. opts: { courses, editable, roomTypes, problems (optional), onChange(courses) }
   * Returns an object with refresh() for totals/checks after outside changes.
   */
  function mount(host, opts) {
    var state = {
      courses: opts.courses.map(function (c) {
        var copy = Object.assign({}, c);
        // "None", "-", "**" from older files mean no prerequisite
        copy.prereqs = (c.prereqs || []).map(codeKey).filter(function (p) { return p && !/^(\*+|-+|NONE|N\/A)$/.test(p); });
        return copy;
      }),
      extra: []
    };
    opts.all = state.courses;
    // the reviewer's notes on single subjects (code → note), sent with a send-back
    opts.notes = Object.assign({}, opts.rowNotes || {});

    function rowProblems(problems) {
      var map = {};
      problems.forEach(function (p) {
        if (p.row != null) (map[p.row] = map[p.row] || []).push(p);
      });
      return map;
    }

    function draw() {
      var problems = check(state.courses, opts.roomTypes);
      var rp = rowProblems(problems);
      var head = '<thead><tr><th>Code</th><th>Descriptive title</th><th class="cf-num">Lec</th><th class="cf-num">Lab</th><th class="cf-num">Units</th>' +
        '<th>Room type' + (opts.editable ? ' (lecture / lab)' : '') + '</th><th>Prerequisites</th>' + (opts.editable || opts.noting ? '<th></th>' : '') + '</tr></thead>';
      var html = '';
      groups(state.courses, state.extra).forEach(function (y) {
        html += '<section class="cf-year"><h3 class="cf-year-title">' + ordinal(y.year) + ' year</h3>';
        y.terms.forEach(function (t) {
          var tot = totals(state.courses, t.rows);
          html += '<div class="cf-term" data-term="' + y.year + '|' + t.term + '"><div class="cf-term-head"><h4>' + esc(termLabel(y.year, t.term).split(' · ')[1]) + '</h4>' +
            '<span class="cf-term-tot" data-tot="' + y.year + '|' + t.term + '">' + tot.units + ' units · ' + tot.hours + ' hrs/week</span></div>' +
            '<div class="cf-scroll"><table class="cf-table">' + head + '<tbody>' +
            (t.rows.length ? t.rows.map(function (i) { return rowHtml(state.courses[i], i, opts, rp); }).join('')
              : '<tr><td colspan="' + (opts.editable || opts.noting ? 8 : 7) + '" class="cf-empty">No subjects in this term.</td></tr>') +
            '</tbody></table></div>' + removedHtml(opts.removed, y.year, t.term) +
            (opts.editable ? '<button type="button" class="cf-add" data-add="' + y.year + '|' + t.term + '">+ Add subject</button>' : '') + '</div>';
        });
        if (opts.editable && !y.terms.some(function (t) { return t.term === 'summer'; })) {
          html += '<button type="button" class="cf-add cf-add--quiet" data-add-summer="' + y.year + '">+ Add a summer term</button>';
        }
        html += '</section>';
      });
      if (opts.editable) html += '<button type="button" class="cf-add cf-add--year" data-add-year>+ Add a year level</button>';
      var g = grandTotals(state.courses);
      html += '<p class="cf-grand">Whole curriculum: <strong>' + state.courses.length + ' subjects</strong> · ' + g.lec + ' lecture + ' + g.lab + ' lab = <strong>' + g.units + ' units</strong></p>';
      host.innerHTML = html;
      if (opts.onProblems) opts.onProblems(problems);
    }

    function refreshPre(i) {
      var cell = host.querySelector('[data-pre="' + i + '"]');
      if (cell) cell.innerHTML = prereqCellHtml(i, state.courses);
    }

    function update(i, field, el) {
      var c = state.courses[i];
      if (!c) return;
      if (field === 'elective') c.elective = el.checked;
      else if (field === 'prereqAdd') {
        var pick = codeKey(el.value);
        c.prereqs = c.prereqs || [];
        if (!pick || c.prereqs.indexOf(pick) >= 0 || c.prereqs.length >= 3) return;
        c.prereqs.push(pick);
        refreshPre(i);
        var next = host.querySelector('select[data-field="prereqAdd"][data-row="' + i + '"]');
        if (next) next.focus();
      } else if (field === 'lec' || field === 'lab') c[field] = el.value === '' ? 0 : Number(el.value);
      else if (field === 'code') {
        var before = codeKey(c.code);
        c.code = el.value.toUpperCase();
        var after = codeKey(c.code);
        // keep prerequisites pointing at this subject when its code is retyped
        var shared = state.courses.some(function (x, j) { return j !== i && codeKey(x.code) === before; });
        if (before && after && before !== after && !shared) {
          state.courses.forEach(function (x, j) {
            var k = (x.prereqs || []).indexOf(before);
            if (k >= 0) {
              x.prereqs[k] = after;
              refreshPre(j);
            }
          });
        }
      } else c[field] = el.value;
      // light updates so typing keeps focus
      var lc = host.querySelector('[data-labcode="' + i + '"]');
      if (lc) lc.textContent = labCode(c) ? 'Lab: ' + labCode(c) : '';
      var u = host.querySelector('[data-units="' + i + '"]');
      if (u) u.textContent = round2(num(c.lec) + num(c.lab));
      if (field === 'lab') {
        var labSel = host.querySelector('select[data-field="labRoom"][data-row="' + i + '"]');
        if (labSel) {
          labSel.disabled = num(c.lab) <= 0;
          if (num(c.lab) > 0 && !c.labRoom) {
            var guess = (opts.roomTypes || []).filter(function (t) { return /lab/i.test(t); })[0];
            if (guess) { c.labRoom = guess; labSel.value = guess; }
          }
        }
      }
      var key = num(c.year) + '|' + c.term;
      var tot = host.querySelector('[data-tot="' + key + '"]');
      if (tot) {
        var rows = [];
        state.courses.forEach(function (x, j) { if (num(x.year) + '|' + x.term === key) rows.push(j); });
        var t = totals(state.courses, rows);
        tot.textContent = t.units + ' units · ' + t.hours + ' hrs/week';
      }
      changed();
    }

    function changed() {
      var problems = check(state.courses, opts.roomTypes);
      var rp = rowProblems(problems);
      host.querySelectorAll('tr[data-row]').forEach(function (tr) {
        var r = Number(tr.getAttribute('data-row'));
        tr.classList.toggle('is-bad', !!(rp[r] && rp[r].some(function (p) { return p.level === 'error'; })));
      });
      if (opts.onProblems) opts.onProblems(problems);
      if (opts.onChange) opts.onChange(state.courses);
    }

    if (opts.editable) {
      host.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.matches('[data-field]')) return;
        update(Number(el.getAttribute('data-row')), el.getAttribute('data-field'), el);
      });
      host.addEventListener('change', function (e) {
        var el = e.target;
        if (el.matches('select[data-field], input[type="checkbox"][data-field]')) {
          update(Number(el.getAttribute('data-row')), el.getAttribute('data-field'), el);
        }
      });
      // option lists are rebuilt just before a dropdown opens (codes may have changed)
      var freshen = function (e) {
        var sel = e.target;
        if (!sel.matches || !sel.matches('select[data-field="prereqAdd"]')) return;
        sel.innerHTML = prereqOptionsHtml(Number(sel.getAttribute('data-row')), state.courses);
      };
      host.addEventListener('mousedown', freshen);
      host.addEventListener('focusin', freshen);
      host.addEventListener('click', function (e) {
        var t = e.target;
        var pr = t.closest('[data-pre-remove]');
        if (pr) {
          var at = pr.getAttribute('data-pre-remove').split('|');
          var row = Number(at[0]);
          state.courses[row].prereqs.splice(Number(at[1]), 1);
          refreshPre(row);
          changed();
          return;
        }
        var add = t.closest('[data-add]');
        if (add) {
          var p = add.getAttribute('data-add').split('|');
          state.courses.push(blankCourse(Number(p[0]), p[1]));
          draw();
          var inputs = host.querySelectorAll('input[data-field="code"]');
          var last = Array.prototype.filter.call(inputs, function (inp) { return Number(inp.getAttribute('data-row')) === state.courses.length - 1; })[0];
          if (last) last.focus();
          if (opts.onChange) opts.onChange(state.courses);
          return;
        }
        var rm = t.closest('[data-remove]');
        if (rm) {
          state.courses.splice(Number(rm.getAttribute('data-remove')), 1);
          draw();
          if (opts.onChange) opts.onChange(state.courses);
          return;
        }
        var sum = t.closest('[data-add-summer]');
        if (sum) {
          state.extra.push(sum.getAttribute('data-add-summer') + '|summer');
          draw();
          return;
        }
        if (t.closest('[data-add-year]')) {
          var max = 1;
          state.courses.forEach(function (c) { max = Math.max(max, num(c.year)); });
          state.extra.forEach(function (k) { max = Math.max(max, num(k.split('|')[0])); });
          state.extra.push((max + 1) + '|1', (max + 1) + '|2');
          draw();
        }
      });
    }
    if (opts.noting) {
      host.addEventListener('click', function (e) {
        var b = e.target.closest('[data-note]');
        if (!b) return;
        var c = state.courses[Number(b.getAttribute('data-note'))];
        if (!c) return;
        noteDialog(c, opts.notes[codeKey(c.code)] || '', function (text) {
          if (text) opts.notes[codeKey(c.code)] = text;
          else delete opts.notes[codeKey(c.code)];
          draw();
          if (opts.onNotes) opts.onNotes(opts.notes);
        });
      });
    }
    draw();
    return {
      courses: function () { return state.courses; },
      notes: function () { return opts.notes; },
      // add subjects copied from another version; one with the same code is replaced
      merge: function (list) {
        var added = 0;
        var replaced = 0;
        list.forEach(function (src) {
          var copy = Object.assign({}, src, { prereqs: (src.prereqs || []).slice(0, 3) });
          var at = -1;
          state.courses.forEach(function (c, i) { if (codeKey(c.code) === codeKey(copy.code)) at = i; });
          if (at >= 0) {
            state.courses[at] = copy;
            replaced++;
          } else {
            state.courses.push(copy);
            added++;
          }
        });
        draw();
        if (opts.onChange) opts.onChange(state.courses);
        return { added: added, replaced: replaced };
      },
      focusRow: function (i) {
        var el = host.querySelector('[data-field="code"][data-row="' + i + '"]') || host.querySelector('tr[data-row="' + i + '"]');
        if (el && el.scrollIntoView) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        if (el && el.focus) el.focus();
      },
      redraw: draw
    };
  }

  function noteDialog(c, current, done) {
    var d = doc.getElementById('cfNoteDialog');
    if (!d) {
      d = doc.createElement('dialog');
      d.id = 'cfNoteDialog';
      d.className = 'ui-dialog cb-dialog';
      doc.body.appendChild(d);
    }
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Note on ' + esc(codeKey(c.code)) + '</h2><button type="button" class="ui-icon-btn" data-x aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p class="cs-dialog-msg">' + esc(c.title) + '</p><label class="ui-field-label" for="cfNoteText">What should change in this subject?</label>' +
      '<textarea class="ui-textarea" id="cfNoteText" maxlength="500">' + esc(current) + '</textarea>' +
      '<p class="ui-field-hint">It’s sent with your send-back and shows on this subject’s row.</p></div>' +
      '<div class="ui-dialog-foot">' + (current ? '<button type="button" class="ui-btn ui-btn--danger cb-push" data-remove-note>Remove note</button>' : '') +
      '<button type="button" class="ui-btn" data-x>Cancel</button><button type="button" class="ui-btn ui-btn--primary" data-keep-note>Save note</button></div>';
    d.onclick = function (e) {
      if (e.target.closest('[data-x]') || e.target === d) return d.close();
      if (e.target.closest('[data-remove-note]')) {
        d.close();
        return done('');
      }
      if (e.target.closest('[data-keep-note]')) {
        var text = doc.getElementById('cfNoteText').value.trim();
        d.close();
        done(text);
      }
    };
    d.showModal();
    doc.getElementById('cfNoteText').focus();
  }

  var FIELD_NAMES = { title: 'Title', lec: 'Lecture units', lab: 'Lab units', term: 'Term', lecRoom: 'Lecture room', labRoom: 'Lab room', prereqs: 'Prerequisites', elective: 'Elective' };

  function fieldText(c, f) {
    if (f === 'term') return termLabel(c.year, String(c.term));
    if (f === 'prereqs') return (c.prereqs || []).map(prereqLabel).join(', ') || 'none';
    if (f === 'elective') return c.elective ? 'yes' : 'no';
    if (f === 'lec' || f === 'lab') return String(num(c[f]));
    if (f === 'labRoom' && num(c.lab) <= 0) return '—';
    if (f === 'lecRoom' && num(c.lec) <= 0) return '—';
    return String(c[f] || '—');
  }

  /** What's different from `base` to `now`, by course code. */
  function diff(base, now) {
    var b = {};
    (base || []).forEach(function (c) { if (codeKey(c.code)) b[codeKey(c.code)] = c; });
    var seen = {};
    var out = { added: [], removed: [], changed: [], same: 0 };
    (now || []).forEach(function (c) {
      var code = codeKey(c.code);
      if (!code) return;
      seen[code] = true;
      var old = b[code];
      if (!old) return out.added.push(c);
      var changes = [];
      Object.keys(FIELD_NAMES).forEach(function (f) {
        var x = fieldText(old, f);
        var y = fieldText(c, f);
        if (x !== y) changes.push({ field: FIELD_NAMES[f], from: x, to: y });
      });
      if (changes.length) out.changed.push({ code: code, title: c.title, course: c, changes: changes });
      else out.same++;
    });
    Object.keys(b).forEach(function (code) { if (!seen[code]) out.removed.push(b[code]); });
    return out;
  }

  /** code → 'new' | 'changed', for highlighting rows. */
  function diffMarks(d) {
    var m = {};
    d.added.forEach(function (c) { m[codeKey(c.code)] = 'new'; });
    d.changed.forEach(function (c) { m[c.code] = 'changed'; });
    return m;
  }

  /** The differences as a list. names: { base: 'Version 1', now: 'your draft' } */
  function diffHtml(d, names) {
    if (!d.added.length && !d.removed.length && !d.changed.length) {
      return '<p class="cf-diff-same">No differences. Both have the same ' + d.same + ' subjects.</p>';
    }
    var line = function (c) { return '<li><strong>' + esc(codeKey(c.code)) + '</strong> ' + esc(c.title) + ' <span class="cb-muted">' + esc(termLabel(c.year, String(c.term))) + '</span></li>'; };
    return '<div class="cf-diff">' +
      (d.added.length ? '<section><h4 class="cf-diff-add">Only in ' + esc(names.now) + ' (' + d.added.length + ')</h4><ul>' + d.added.map(line).join('') + '</ul></section>' : '') +
      (d.removed.length ? '<section><h4 class="cf-diff-del">Only in ' + esc(names.base) + ' (' + d.removed.length + ')</h4><ul>' + d.removed.map(line).join('') + '</ul></section>' : '') +
      (d.changed.length ? '<section><h4 class="cf-diff-chg">Different (' + d.changed.length + ')</h4><ul>' + d.changed.map(function (x) {
        return '<li><strong>' + esc(x.code) + '</strong> ' + esc(x.title) + '<ul class="cf-diff-fields">' + x.changes.map(function (ch) {
          return '<li>' + esc(ch.field) + ': <span class="cf-diff-to">' + esc(ch.to) + '</span> in ' + esc(names.now) + ' · <span class="cf-diff-from">' + esc(ch.from) + '</span> in ' + esc(names.base) + '</li>';
        }).join('') + '</ul></li>';
      }).join('') + '</ul></section>' : '') +
      (d.same ? '<p class="cb-muted">' + d.same + ' other subjects are the same.</p>' : '') + '</div>';
  }

  function dialogEl(id) {
    var d = doc.getElementById(id);
    if (!d) {
      d = doc.createElement('dialog');
      d.id = id;
      d.className = 'ui-dialog cb-dialog cf-wide';
      doc.body.appendChild(d);
    }
    return d;
  }

  function choiceSelect(id, choices, pick) {
    return '<select class="ui-input" id="' + id + '">' + choices.map(function (c) {
      return '<option value="' + esc(c.id) + '"' + (c.id === pick ? ' selected' : '') + '>' + esc(c.label) + '</option>';
    }).join('') + '</select>';
  }

  /**
   * Compare this version with another one.
   * opts: { title, nowLabel, courses (this version's), choices: [{ id, label }], pick, load(id) → Promise<courses> }
   */
  function compareDialog(opts) {
    var d = dialogEl('cfCompare');
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + esc(opts.title || 'Compare versions') + '</h2><button type="button" class="ui-icon-btn" data-x aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><label class="ui-field-label" for="cfCompareWith">Compare ' + esc(opts.nowLabel) + ' with</label>' + choiceSelect('cfCompareWith', opts.choices, opts.pick) +
      '<div id="cfCompareOut" class="cf-compare-out"><p class="cb-muted">Loading…</p></div></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-x>Close</button></div>';
    d.onclick = function (e) { if (e.target.closest('[data-x]') || e.target === d) d.close(); };
    var sel = doc.getElementById('cfCompareWith');
    var show = function () {
      var out = doc.getElementById('cfCompareOut');
      var choice = opts.choices.filter(function (c) { return c.id === sel.value; })[0];
      out.innerHTML = '<p class="cb-muted">Loading…</p>';
      opts.load(sel.value).then(function (courses) {
        out.innerHTML = diffHtml(diff(courses, opts.courses), { base: choice ? choice.short || choice.label : 'the other version', now: opts.nowLabel });
      }).catch(function (e) { out.innerHTML = '<p class="ui-field-error">' + esc(e.message) + '</p>'; });
    };
    sel.onchange = show;
    d.showModal();
    show();
  }

  /**
   * Pick subjects from another version to add here (same code = replaced).
   * opts: { courses (this version's), choices, pick, load(id) → Promise<courses>, onAdd(list) }
   */
  function copyDialog(opts) {
    var d = dialogEl('cfCopy');
    var source = [];
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Copy subjects from another version</h2><button type="button" class="ui-icon-btn" data-x aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><label class="ui-field-label" for="cfCopyFrom">Copy from</label>' + choiceSelect('cfCopyFrom', opts.choices, opts.pick) +
      '<p class="ui-field-hint">Tick whole terms or single subjects. A subject with the same code here is replaced by the copy.</p>' +
      '<div id="cfCopyList" class="cf-copy-list"><p class="cb-muted">Loading…</p></div><p class="ui-field-error" id="cfCopyErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-x>Cancel</button><button type="button" class="ui-btn ui-btn--primary" data-copy-go>Copy selected</button></div>';
    var here = {};
    (opts.courses || []).forEach(function (c) { here[codeKey(c.code)] = true; });
    var list = doc.getElementById('cfCopyList');
    var show = function () {
      list.innerHTML = '<p class="cb-muted">Loading…</p>';
      opts.load(doc.getElementById('cfCopyFrom').value).then(function (courses) {
        source = courses || [];
        var groups = {};
        var order = [];
        sorted(source).forEach(function (c) {
          var k = num(c.year) + '|' + c.term;
          if (!groups[k]) { groups[k] = []; order.push(k); }
          groups[k].push(c);
        });
        list.innerHTML = order.length ? order.map(function (k) {
          var p = k.split('|');
          return '<fieldset class="cf-copy-term"><legend><label><input type="checkbox" data-copy-term="' + k + '"> <strong>' + esc(termLabel(Number(p[0]), p[1])) + '</strong></label></legend>' +
            groups[k].map(function (c) {
              return '<label class="cf-copy-row"><input type="checkbox" data-copy="' + source.indexOf(c) + '" data-term-of="' + k + '"> <strong>' + esc(codeKey(c.code)) + '</strong> ' + esc(c.title) +
                (here[codeKey(c.code)] ? ' <span class="cf-tag">replaces yours</span>' : '') + '</label>';
            }).join('') + '</fieldset>';
        }).join('') : '<p class="cb-muted">That version has no subjects.</p>';
      }).catch(function (e) { list.innerHTML = '<p class="ui-field-error">' + esc(e.message) + '</p>'; });
    };
    d.onchange = function (e) {
      if (e.target.id === 'cfCopyFrom') return show();
      var term = e.target.getAttribute('data-copy-term');
      if (term) {
        list.querySelectorAll('[data-term-of="' + term + '"]').forEach(function (b) { b.checked = e.target.checked; });
      }
    };
    d.onclick = function (e) {
      if (e.target.closest('[data-x]') || e.target === d) return d.close();
      if (e.target.closest('[data-copy-go]')) {
        var picked = Array.prototype.filter.call(list.querySelectorAll('[data-copy]'), function (b) { return b.checked; })
          .map(function (b) { return source[Number(b.getAttribute('data-copy'))]; });
        if (!picked.length) {
          var err = doc.getElementById('cfCopyErr');
          err.textContent = 'Tick at least one subject.';
          err.hidden = false;
          return;
        }
        d.close();
        opts.onAdd(picked);
      }
    };
    d.showModal();
    show();
  }

  function dateText(v) {
    var d = new Date(String(v || '').replace(' ', 'T'));
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  /** "N changes since <who> sent it back", the differences, and how each subject note was handled. */
  function changesHtml(d, ret, who) {
    var count = d.added.length + d.changed.length + d.removed.length;
    var notes = Object.keys(ret.notes || {});
    return '<details class="cb-changes"' + (count ? ' open' : '') + '><summary><strong>' +
      (count ? count + (count === 1 ? ' change' : ' changes') : 'No changes') + (who ? ' since ' + who + ' sent it back' : ' since it was sent back') + (ret.at ? ' on ' + esc(dateText(ret.at)) : '') + '</strong>' +
      (count ? ' · ' + [d.added.length ? d.added.length + ' added' : '', d.changed.length ? d.changed.length + ' changed' : '', d.removed.length ? d.removed.length + ' removed' : ''].filter(Boolean).join(', ') : '') +
      '</summary>' + (count ? diffHtml(d, { base: 'the version sent back', now: 'this version' }) : '') +
      (notes.length ? '<div class="cb-oldnotes"><strong>Notes on single subjects then:</strong><ul>' + notes.map(function (code) {
        return '<li><strong>' + esc(code) + '</strong> ' + esc(ret.notes[code]) + (d.changed.some(function (x) { return x.code === code; }) ? ' <span class="ui-chip ui-chip--done">changed</span>' : ' <span class="ui-chip ui-chip--neutral">not changed</span>') + '</li>';
      }).join('') + '</ul></div>' : '') + '</details>';
  }

  /** Checklist (errors first). */
  // reviewer: the registrars' wording (nothing to "send")
  function checklistHtml(problems, reviewer) {
    var errs = errorsIn(problems);
    var warns = problems.filter(function (p) { return p.level === 'warning'; });
    if (!problems.length) {
      return '<div class="cf-check is-ok"><strong>' + (reviewer ? 'No problems found.' : 'Ready to send.') + '</strong>' +
        (reviewer ? ' The system’s checks all pass; still look it over for mistakes it can’t catch.' : ' No problems found.') + '</div>';
    }
    return '<div class="cf-check' + (errs.length ? ' is-bad' : ' is-warn') + '"><strong>' +
      (errs.length
        ? errs.length + (errs.length === 1 ? ' problem' : ' problems') + (reviewer ? ' found. Send it back so the college can fix ' + (errs.length === 1 ? 'it' : 'them') : ' to fix before sending')
        : (reviewer ? 'No problems, ' : 'Ready to send, with ') + warns.length + (warns.length === 1 ? ' note' : ' notes')) +
      '</strong><ul>' + errs.concat(warns).map(function (p) {
        return '<li class="cf-check-' + p.level + '">' + (p.row != null ? '<button type="button" class="cf-goto" data-goto="' + p.row + '">' + esc(p.message) + '</button>' : esc(p.message)) + '</li>';
      }).join('') + '</ul></div>';
  }

  // ---------------------------------------------------------------- copies

  function sorted(courses) {
    return courses
      .map(function (c, i) { return { c: c, i: i }; })
      .sort(function (a, b) { return termOrder(a.c.year, a.c.term) - termOrder(b.c.year, b.c.term) || a.i - b.i; })
      .map(function (x) { return x.c; });
  }

  function fileName(review, ext) {
    return (review.programName + ' curriculum ' + review.academicYear + ' v' + review.version).replace(/[\\/:*?"<>|]+/g, ' ') + '.' + ext;
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

  var CSV_HEAD = ['Course Code', 'Course Title', 'Lecture Units', 'Lab Units', 'Total Units', 'Total Hours/Week', 'Total Hours/Sem',
    'Year Level', 'Semester', 'Lecture Room Type', 'Lab Room Type', 'Prerequisite 1', 'Prerequisite 2', 'Prerequisite 3', 'Elective'];

  function csvCell(v) {
    v = String(v == null ? '' : v);
    return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
  }

  function csvLine(c) {
    return sheetRow(c).map(csvCell).join(',');
  }

  // one subject as spreadsheet cells (CSV and Excel)
  function sheetRow(c) {
    var weeks = c.term === 'summer' ? 6 : 18;
    var perWeek = round2(((num(c.lec) * LEC_HOURS + num(c.lab) * LAB_HOURS) * 18) / weeks);
    var pre = (c.prereqs || []).map(function (x) { return prereqLabel(x).replace(/\b(year|standing)\b/g, function (w) { return w[0].toUpperCase() + w.slice(1); }); });
    return [codeKey(c.code), c.title, num(c.lec), num(c.lab), round2(num(c.lec) + num(c.lab)), perWeek, round2(perWeek * weeks),
      num(c.year), c.term === 'summer' ? 'Summer' : c.term, num(c.lec) > 0 ? c.lecRoom : '', num(c.lab) > 0 ? c.labRoom : '',
      pre[0] || '', pre[1] || '', pre[2] || '', c.elective ? 'Yes' : ''];
  }

  function csvBlob(lines) {
    return new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
  }

  // one block per term: a blank line, a heading ("1st Year - 2nd Semester"), then its subjects.
  // The reader skips the headings (and fills Year Level / Semester from them when a row leaves those blank).
  // the sheet as rows of cells: headings row, then per term a blank row, a heading row ({ heading }) and its subjects
  function sheetRows(courses) {
    var rows = [CSV_HEAD.slice()];
    var last = null;
    sorted(courses).forEach(function (c) {
      var key = num(c.year) + '|' + c.term;
      if (key !== last) {
        rows.push([]);
        rows.push({ heading: ordinal(num(c.year)) + ' Year - ' + (c.term === 'summer' ? 'Summer' : c.term === '2' ? '2nd Semester' : '1st Semester') });
        last = key;
      }
      rows.push(sheetRow(c));
    });
    return rows;
  }

  function csvLines(courses) {
    return sheetRows(courses).map(function (r) { return r.heading ? csvCell(r.heading) : r.map(csvCell).join(','); });
  }

  /** Same columns as the curriculum upload reads (CurriculumStore FIELDS), so it can be uploaded again. */
  function csv(review) {
    download(fileName(review, 'csv'), csvBlob(csvLines(review.courses)));
  }

  /** A filled-in example in the layout the CSV import reads; Deans and Program Chairs start from it. */
  function csvTemplate(roomTypes) {
    download('Curriculum CSV template.csv', csvBlob(csvLines(templateSample(roomTypes))));
  }

  /** Same template as Excel: set column widths, frozen headings, dropdowns (resources/js/curriculum-excel.js). */
  function excelTemplate(roomTypes) {
    if (!global.McuCurriculumExcel) return Promise.reject(new Error('The Excel maker didn’t load. Refresh the page and try again.'));
    return global.McuCurriculumExcel.write({ rows: sheetRows(templateSample(roomTypes)), roomTypes: roomTypes || [], fileName: 'Curriculum template.xlsx' });
  }

  function templateSample(roomTypes) {
    var types = roomTypes || [];
    var lab = types.filter(function (t) { return /lab/i.test(t); })[0] || 'Computer Laboratory';
    var lec = types.filter(function (t) { return /^lecture/i.test(t); })[0] || 'Lecture';
    var sample = [
      { code: 'CCS 101', title: 'Introduction to Computing', lec: 2, lab: 1, year: 1, term: '1', lecRoom: lec, labRoom: lab, prereqs: [] },
      { code: 'GEC 001', title: 'Purposive Communication', lec: 3, lab: 0, year: 1, term: '1', lecRoom: lec, labRoom: '', prereqs: [] },
      { code: 'CCS 102', title: 'Computer Programming 1', lec: 2, lab: 1, year: 1, term: '2', lecRoom: lec, labRoom: lab, prereqs: ['CCS 101'] },
      { code: 'CCS 201', title: 'Data Structures and Algorithms', lec: 2, lab: 1, year: 2, term: '1', lecRoom: lec, labRoom: lab, prereqs: ['CCS 102'] },
      { code: 'CCS 301', title: 'Software Engineering', lec: 3, lab: 0, year: 3, term: '1', lecRoom: lec, labRoom: '', prereqs: ['CCS 201', standing(3)] },
      { code: 'FRE 001', title: 'Free Elective 1', lec: 3, lab: 0, year: 3, term: '2', lecRoom: lec, labRoom: '', prereqs: [], elective: true },
      { code: 'CCS 399', title: 'Practicum (300 hours)', lec: 3, lab: 0, year: 3, term: 'summer', lecRoom: lec, labRoom: '', prereqs: [standing(3)] }
    ];
    return sample;
  }

  /** Rows with something typed in but no course code (a draft can't be saved like that). */
  function rowsWithoutCode(courses) {
    var out = [];
    courses.forEach(function (c, i) {
      if (!codeKey(c.code) && !isBlankRow(c)) out.push(i);
    });
    return out;
  }

  /** Rows nobody typed into ("+ Add subject" and left alone). */
  function isBlankRow(c) {
    return !codeKey(c.code) && !String(c.title || '').trim() && !(c.prereqs || []).length;
  }

  function approvalLines(review) {
    var out = [];
    if (review.createdBy) out.push(['Prepared by', review.createdBy, '']);
    if (review.deanBy) out.push(['Approved by the Dean', review.deanBy, review.deanAt || '']);
    if (review.registrarBy && review.status === 'accepted') out.push(['Accepted by the Registrar', review.registrarBy, review.registrarAt || '']);
    return out;
  }

  function statusText(status) {
    return {
      draft: 'Draft', pending_dean: 'Waiting for the Dean', dean_approved: 'Approved by the Dean',
      pending_registrar: 'Waiting for the Registrar', accepted: 'Accepted · active', superseded: 'Replaced by a newer version', archived: 'Archived'
    }[status] || status;
  }

  // the page's own logo (pages sit at different folder depths)
  function logoUrl() {
    var img = doc.querySelector('.topbar-logo img');
    return img ? img.src : new URL('/Attri/mculogo.jpg', global.location.href).href;
  }

  /** Print layout in a hidden frame. */
  function print(review) {
    var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(fileName(review, 'pdf').replace(/\.pdf$/, '')) + '</title><style>' +
      '@page{size:A4;margin:14mm}body{font-family:"DM Sans",Arial,sans-serif;color:#1f1230;font-size:10.5px}' +
      'header{display:flex;gap:12px;align-items:center;border-bottom:3px solid #f9b937;padding-bottom:8px;margin-bottom:10px}' +
      'header img{width:46px;height:46px;border-radius:50%}h1{font-size:16px;margin:0;color:#2d163f}h2{font-size:13px;margin:2px 0 0;color:#5c267e;font-weight:600}' +
      '.meta{color:#6f5f86;margin-top:2px}h3{font-size:12px;margin:14px 0 4px;color:#2d163f}' +
      'table{width:100%;border-collapse:collapse;margin-bottom:4px;page-break-inside:avoid}th,td{border:1px solid #d9cfe6;padding:3px 5px;text-align:left;vertical-align:top}' +
      'th{background:#f3eef9;color:#4b3d5e;font-size:9.5px;text-transform:uppercase}td.n,th.n{text-align:center;width:34px}' +
      'tfoot td{font-weight:700;background:#faf8fc}.lab{display:block;color:#6f5f86;font-size:9px}' +
      '.sign{display:flex;gap:24px;margin-top:22px}.sign div{flex:1;border-top:1px solid #1f1230;padding-top:4px}.foot{margin-top:14px;color:#6f5f86;font-size:9px}' +
      '</style></head><body>' + documentHtml(review) + '</body></html>';
    var frame = doc.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    doc.body.appendChild(frame);
    var w = frame.contentWindow;
    w.document.open();
    w.document.write(html);
    w.document.close();
    var go = function () {
      w.focus();
      w.print();
      setTimeout(function () { frame.remove(); }, 2000);
    };
    var img = w.document.querySelector('img');
    if (img && !img.complete) img.onload = img.onerror = go;
    else setTimeout(go, 50);
  }

  function documentHtml(review) {
    var logo = logoUrl();
    var html = '<header><img src="' + esc(logo) + '" alt=""><div><h1>' + esc(review.collegeName) + '</h1><h2>' + esc(review.programName) +
      ' Curriculum</h2><div class="meta">Effective school year ' + esc(review.academicYear) + ' · Version ' + review.version + ' · ' + esc(statusText(review.status)) + '</div></div></header>';
    groups(review.courses, []).forEach(function (y) {
      y.terms.forEach(function (t) {
        if (!t.rows.length) return;
        var tot = totals(review.courses, t.rows);
        html += '<h3>' + esc(termLabel(y.year, t.term)) + '</h3><table><thead><tr><th>Code</th><th>Descriptive title</th><th class="n">Lec</th><th class="n">Lab</th><th class="n">Units</th><th>Prerequisite</th></tr></thead><tbody>' +
          t.rows.map(function (i) {
            var c = review.courses[i];
            return '<tr><td>' + esc(codeKey(c.code)) + (labCode(c) ? '<span class="lab">Lab: ' + esc(labCode(c)) + '</span>' : '') + '</td><td>' + esc(c.title) + (c.elective ? ' (elective)' : '') +
              '</td><td class="n">' + num(c.lec) + '</td><td class="n">' + num(c.lab) + '</td><td class="n">' + round2(num(c.lec) + num(c.lab)) + '</td><td>' + esc((c.prereqs || []).join(', ') || 'None') + '</td></tr>';
          }).join('') + '</tbody><tfoot><tr><td colspan="2">Total</td><td class="n">' + tot.lec + '</td><td class="n">' + tot.lab + '</td><td class="n">' + tot.units + '</td><td></td></tr></tfoot></table>';
      });
    });
    var g = grandTotals(review.courses);
    html += '<p><strong>Total: ' + review.courses.length + ' subjects, ' + g.units + ' units</strong></p>';
    var signs = approvalLines(review);
    if (signs.length) {
      html += '<div class="sign">' + signs.map(function (s) {
        return '<div><strong>' + esc(s[1]) + '</strong><br>' + esc(s[0]) + (s[2] ? '<br>' + esc(String(s[2]).slice(0, 10)) : '') + '</div>';
      }).join('') + '</div>';
    }
    html += '<p class="foot">Made in MCU-NEX on ' + esc(new Date().toLocaleDateString()) + '.</p>';
    return html;
  }

  /** Real PDF file (resources/js/curriculum-pdf.js, jsPDF). */
  function pdf(review) {
    if (!global.McuCurriculumPdf) {
      return Promise.reject(new Error('The PDF maker is still loading. Try again in a moment.'));
    }
    var rows = [];
    groups(review.courses, []).forEach(function (y) {
      y.terms.forEach(function (t) {
        if (!t.rows.length) return;
        rows.push({
          title: termLabel(y.year, t.term),
          courses: t.rows.map(function (i) {
            var c = review.courses[i];
            return {
              code: codeKey(c.code), labCode: labCode(c), title: c.title + (c.elective ? ' (elective)' : ''),
              lec: num(c.lec), lab: num(c.lab), units: round2(num(c.lec) + num(c.lab)), prereqs: (c.prereqs || []).join(', ') || 'None'
            };
          }),
          totals: totals(review.courses, t.rows)
        });
      });
    });
    var g = grandTotals(review.courses);
    return global.McuCurriculumPdf.download({
      fileName: fileName(review, 'pdf'),
      logoUrl: logoUrl(),
      collegeName: review.collegeName,
      programName: review.programName,
      subtitle: 'Effective school year ' + review.academicYear + ' · Version ' + review.version + ' · ' + statusText(review.status),
      terms: rows,
      totalLine: 'Total: ' + review.courses.length + ' subjects, ' + g.units + ' units',
      signatures: approvalLines(review).map(function (s) { return { name: s[1], role: s[0], date: s[2] ? String(s[2]).slice(0, 10) : '' }; }),
      footer: 'Made in MCU-NEX on ' + new Date().toLocaleDateString()
    });
  }

  global.McuCurriculumForm = {
    TERMS: TERMS,
    check: check,
    errorsIn: errorsIn,
    mount: mount,
    checklistHtml: checklistHtml,
    fromSheet: fromSheet,
    csvTemplate: csvTemplate,
    csvLines: csvLines,
    sheetRows: sheetRows,
    excelTemplate: excelTemplate,
    CSV_HEAD: CSV_HEAD,
    compareDialog: compareDialog,
    changesHtml: changesHtml,
    copyDialog: copyDialog,
    diff: diff,
    diffMarks: diffMarks,
    diffHtml: diffHtml,
    sorted: sorted,
    rowsWithoutCode: rowsWithoutCode,
    isBlankRow: isBlankRow,
    prereqLabel: prereqLabel,
    labCode: labCode,
    termLabel: termLabel,
    statusText: statusText,
    csv: csv,
    print: print,
    pdf: pdf,
    documentHtml: documentHtml,
    esc: esc
  };
})(window);
