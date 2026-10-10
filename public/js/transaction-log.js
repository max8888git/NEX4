(function () {
  var body = document.getElementById('logBody');
  var status = document.getElementById('logStatus');
  var filter = document.getElementById('logArea');
  if (!body) return;
  var rows = [];

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatWhen(value) {
    var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!match) return String(value || '');
    var when = new Date(+match[1], +match[2] - 1, +match[3], +match[4], +match[5]);
    return when.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function visibleRows() {
    var area = filter ? filter.value : '';
    if (!area) return rows;
    if (area === 'org') {
      return rows.filter(function (row) {
        return row.area === 'college' || row.area === 'department' || row.area === 'program';
      });
    }
    return rows.filter(function (row) {
      return row.area === area;
    });
  }

  function render() {
    var list = visibleRows();
    if (!list.length) {
      body.innerHTML =
        '<tr><td class="log-empty" colspan="4">' +
        (rows.length ? 'Nothing in this filter.' : 'No transactions yet.') +
        '</td></tr>';
      return;
    }
    body.innerHTML = list
      .map(function (row) {
        var who = escapeHtml(row.actorName || 'Unknown');
        if (row.actorRoleLabel) who += ' (' + escapeHtml(row.actorRoleLabel) + ')';
        return (
          '<tr><td>' +
          escapeHtml(formatWhen(row.createdAt)) +
          '</td><td>' +
          who +
          '</td><td class="log-action">' +
          escapeHtml(row.action) +
          '</td><td>' +
          escapeHtml(row.summary) +
          '</td></tr>'
        );
      })
      .join('');
  }

  function load() {
    if (!window.McuApi) {
      if (status) status.textContent = 'The log could not be loaded.';
      return;
    }
    if (status) status.textContent = 'Loading the log…';
    window.McuApi.get('logs.php')
      .then(function (res) {
        rows = (res && res.logs) || [];
        if (status) {
          status.textContent = rows.length
            ? rows.length + (rows.length === 1 ? ' recorded action.' : ' recorded actions.')
            : '';
        }
        render();
      })
      .catch(function (err) {
        rows = [];
        if (status) status.textContent = (err && err.message) || 'The log could not be loaded.';
        body.innerHTML = '';
      });
  }

  if (filter) filter.addEventListener('change', render);
  load();
})();
