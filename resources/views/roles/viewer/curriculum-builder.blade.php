@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | Curriculum</title>
@endverbatim
  @fonts
  <x-legacy-css name="schedule-page" />
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-css name="ui" />
  <x-legacy-css name="schedule-colleges" />
  <x-legacy-css name="curriculum-form" />
  <x-legacy-js name="page-loading" />
  @vite(['resources/js/curriculum-pdf.js', 'resources/js/curriculum-excel.js'])
@verbatim
</head>
<body>
  <div class="app-shell">
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo"><img src="../Attri/mculogo.jpg" alt="MCU logo" /></div>
          <div>
            <div class="topbar-title-main">MCU-NEX</div>
            <div class="topbar-title-sub">Registrar Room Allocation</div>
          </div>
        </a>
      </div>
      <div class="topbar-right">
@endverbatim
@if ($role === 'viewing-program-head')
@verbatim
        <span class="chip">Program Chair</span>
@endverbatim
@elseif ($role === 'viewing-with-approval')
@verbatim
        <span class="chip">VPAA</span>
@endverbatim
@else
@verbatim
        <span class="chip">Dean</span>
@endverbatim
@endif
@verbatim
        <div class="topbar-notify">
          <button class="topbar-notify-btn" type="button">
@endverbatim
            <x-icon-bell />
@verbatim
            <span class="topbar-notify-dot"></span></button>
          <div class="notify-menu" role="menu"></div>
        </div>
@endverbatim
        <x-profile-menu :role="$role" />
@verbatim
      </div>
    </header>
    <div class="topbar-tabs" aria-label="Page navigation">
@endverbatim
      <x-app-nav-button />
@verbatim
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
    </div>
    </div>
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim
      <main class="shell cb-shell">
        <header class="ui-page-head">
          <div>
            <h1 class="ui-page-title">Curriculum</h1>
@endverbatim
@if ($role === 'viewing-program-head')
@verbatim
            <p class="ui-page-sub">Make the curriculum for each of your programs, check it, and send it to your Dean. The Dean sends it on to the Registrar.</p>
@endverbatim
@else
@verbatim
            <p class="ui-page-sub">Approve the curriculums your Program Chairs send, or make them yourself. Then send them to the Registrar; once accepted they’re used for sectioning.</p>
@endverbatim
@endif
@verbatim
          </div>
          <div class="cb-templates">
            <button type="button" class="ui-btn" id="cbTemplateXlsx" title="Excel file with set column widths and dropdowns, and example rows">Excel template</button>
            <button type="button" class="ui-btn" id="cbTemplate" title="The layout the CSV import reads, with example rows">CSV template</button>
          </div>
        </header>
        <section class="card cb-card-wrap" id="cbRoot" aria-live="polite"></section>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var el = document.getElementById('topbar-datetime');
      if (!el) return;
      function tick() {
        var d = new Date();
        el.innerHTML = '<span class="topbar-datetime-date">' + d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) +
          '</span><span class="topbar-datetime-time">' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true }) + '</span>';
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="room-types" />
  <x-legacy-js name="curriculum-store" />
  <x-legacy-js name="curriculum-form" />
  <x-legacy-js name="curriculum-builder" />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>
@endverbatim
