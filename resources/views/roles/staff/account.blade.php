@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | My account</title>
@endverbatim
  @fonts
@verbatim
  <style>
    :root {
      --purple: #5c267e;
      --purple-soft: #f6f1fc;
      --gold: #f9b937;
      --purple-dark: #2d163f;
      --bg: #f5f5f9;
      --text-main: #1f1230;
      --text-muted: #7a6992;
      --border-soft: #e3d9f0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text-main);
      display: flex;
      flex-direction: column;
    }

    .app-shell {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .topbar {
      background: var(--purple);
      color: #fff;
      padding: 0.5rem 1.5rem 0.4rem;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
      position: sticky;
      top: 0;
      z-index: 20;
    }

    .topbar-main {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.25rem;
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .nav-toggle {
      display: inline-flex;
      flex-direction: column;
      justify-content: center;
      gap: 3px;
      cursor: pointer;
      padding: 0.25rem 0.4rem;
      border-radius: 0.35rem;
      border: 1px solid rgba(255, 255, 255, 0.4);
      background: transparent;
    }

    .nav-toggle:hover {
      background: rgba(0, 0, 0, 0.2);
    }

    .nav-toggle-line {
      width: 18px;
      height: 2px;
      border-radius: 999px;
      background: #fff;
    }

    .topbar-logo {
      width: 32px;
      height: 32px;
      border-radius: 999px;
      overflow: hidden;
      border: 2px solid var(--gold);
      background: #f3edf8;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .topbar-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .topbar-brand-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: inherit;
      cursor: pointer;
    }
    .topbar-brand-link:hover {
      opacity: 0.92;
    }

    .topbar-title-main {
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .topbar-title-sub {
      font-size: 0.75rem;
      color: #e2d4ff;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 0.875rem;
    }

    .chip {
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

    .topbar-notify {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .topbar-notify-btn {
      position: relative;
      width: 30px;
      height: 30px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.4);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      background: rgba(0, 0, 0, 0.16);
      color: #fff;
      font-size: 0.9rem;
    }

    .topbar-notify-dot {
      position: absolute;
      top: 6px;
      right: 6px;
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: var(--gold);
      box-shadow: 0 0 0 3px rgba(249, 185, 55, 0.35);
    }

    .topbar-user {
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
    }

    .avatar {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      background: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--purple);
    }

    .user-menu {
      position: absolute;
      top: 130%;
      right: 0;
      background: #fff;
      color: var(--text-main);
      min-width: 160px;
      border-radius: 0.6rem;
      border: 1px solid var(--border-soft);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16);
      padding: 0.35rem 0;
      font-size: 0.85rem;
      display: none;
      z-index: 10;
    }

    .user-menu a {
      display: block;
      padding: 0.45rem 0.9rem;
      text-decoration: none;
      color: inherit;
      white-space: nowrap;
    }

    .user-menu a:hover {
      background: var(--purple-soft);
      color: var(--purple);
    }

    .user-menu a.logout {
      color: #e05454;
    }

    .topbar-user.open .user-menu {
      display: block;
    }

    .tooltip {
      position: relative;
    }

    .tooltip::after {
      content: attr(data-tooltip);
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      background: #fff;
      color: var(--text-main);
      padding: 0.55rem 0.9rem;
      border-radius: 999px;
      box-shadow: 0 14px 26px rgba(0, 0, 0, 0.22);
      opacity: 0;
      transform: translateY(-4px);
      transition: opacity 0.15s ease, transform 0.15s ease;
      pointer-events: none;
      white-space: nowrap;
      z-index: 50;
    }

    .tooltip:hover::after {
      opacity: 1;
      transform: translateY(0);
    }

    .tooltip.open::after,
    .tooltip.open:hover::after {
      opacity: 0;
      transform: translateY(-4px);
    }

    .tooltip-left::after {
      left: 0;
      right: auto;
    }

    .topbar-tabs {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.4rem 1.5rem;
      font-size: 0.8rem;
      overflow-x: visible;
      overflow-y: visible;
      background: var(--purple-dark);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
    }
    .topbar-tab {
      text-decoration: none;
      color: #e2d4ff;
      padding-bottom: 0.3rem;
      border-bottom: 2px solid transparent;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      white-space: nowrap;
    }
    .topbar-tab:hover {
      border-bottom-color: rgba(255, 255, 255, 0.6);
      color: #fff;
    }
    .topbar-tab.active {
      border-bottom-color: var(--gold);
      color: #fff;
      font-weight: 600;
    }

    .layout-row {
      display: flex;
      flex: 1;
      min-height: 0;
    }

    .nav-bar {
      background: var(--purple-dark);
      color: #fff;
      padding: 1.5rem 1rem;
      width: 210px;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      position: fixed;
      top: 88px;
      left: 0;
      height: calc(100vh - 88px);
      transform: translateX(-100%);
      transition: transform 0.25s ease;
      z-index: 15;
    }

    .nav-bar.nav-bar-open {
      transform: translateX(0);
    }

    .nav-section-title {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: #c9b8f3;
      margin-bottom: 0.25rem;
    }

    .nav-link {
      display: block;
      padding: 0.45rem 0.75rem;
      border-radius: 0.6rem;
      font-size: 0.85rem;
      color: #e8ddff;
      text-decoration: none;
    }

    .nav-link:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
    }

    .nav-link.active {
      background: #fff;
      color: var(--purple-dark);
      font-weight: 600;
    }

    .shell { max-width: 840px; margin: 2rem auto; padding: 0 1.25rem; flex: 1; }

    @media (max-width: 768px) {
      .nav-bar {
        width: 75%;
        max-width: 260px;
      }
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.25rem;
    }

    .page-title {
      font-size: 1.35rem;
      font-weight: 600;
    }

    .page-sub {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
    }

    .link-back {
      font-size: 0.8rem;
      color: var(--purple);
      text-decoration: none;
      padding: 0.3rem 0.7rem;
      border-radius: 999px;
      background: var(--purple-soft);
      border: 1px solid var(--border-soft);
    }

    .card {
      background: #fff;
      border-radius: 0.9rem;
      border: 1px solid var(--border-soft);
      padding: 1.25rem 1.4rem 1.4rem;
      box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
    }

    form {
      display: grid;
      gap: 0.9rem;
      margin-top: 0.5rem;
    }

    label {
      font-size: 0.85rem;
      font-weight: 500;
      display: block;
      margin-bottom: 0.25rem;
    }

    input {
      width: 100%;
      padding: 0.55rem 0.8rem;
      border-radius: 0.6rem;
      border: 1px solid var(--border-soft);
      font-family: inherit;
      font-size: 0.9rem;
    }

    .hint {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
    }

    .actions {
      margin-top: 0.8rem;
      display: flex;
      gap: 0.5rem;
    }

    .btn-primary {
      border: 0;
      border-radius: 999px;
      padding: 0.55rem 1.5rem;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      background: var(--gold);
      color: var(--purple);
      box-shadow: 0 12px 24px rgba(249, 185, 55, 0.4);
    }

    .btn-secondary {
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: #fff;
      padding: 0.55rem 1.15rem;
      font-size: 0.85rem;
      cursor: pointer;
      color: var(--text-muted);
    }
      .topbar-tabs .topbar-datetime {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
      gap: 0.05rem;
      font-size: 0.72rem;
      color: #c9b8f3;
      line-height: 1.2;
      text-align: right;
      font-variant-numeric: tabular-nums;
      letter-spacing: 0.02em;
      flex-shrink: 0;
      margin-left: auto;
      order: 99;
      padding-left: 1rem;
      border-left: 1px solid rgba(255, 255, 255, 0.12);
    }
    .topbar-tabs .topbar-datetime .topbar-datetime-date { opacity: 0.95; }
    .topbar-tabs .topbar-datetime .topbar-datetime-time {
      font-size: 0.78rem;
      font-weight: 600;
      color: #fff;
    }
</style>
@endverbatim
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-css name="ui" />
  <x-legacy-css name="my-account" />
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
  <div class="app-shell">
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-main">
        <div class="topbar-left">
@endverbatim
          <a href="{{ str_starts_with($role, 'viewing') ? 'dashboard.html' : 'home-dashboard.html' }}" class="topbar-brand-link" aria-label="Go to dashboard">
@verbatim
            <div class="topbar-logo">
              <img src="../Attri/mculogo.jpg" alt="MCU logo" />
            </div>
            <div>
              <div class="topbar-title-main">MCU-NEX</div>
              <div class="topbar-title-sub">Registrar Room Allocation</div>
            </div>
          </a>
        </div>
        <div class="topbar-right">
@endverbatim
          <span class="chip">{{ ['super-admin' => 'University Registrar', 'admin' => 'Records Officer', 'viewing' => 'Dean', 'viewing-program-head' => 'Program Chair', 'viewing-with-approval' => 'VPAA'][$role] ?? '' }}</span>
@verbatim
          <div class="topbar-notify" aria-label="Notifications">
            <button class="topbar-notify-btn" type="button">
@endverbatim
              <x-icon-bell />
@verbatim
              <span class="topbar-notify-dot" aria-hidden="true"></span>
            </button>
          </div>
@endverbatim
          <x-profile-menu :role="$role" />
@verbatim
        </div>
      </div>
    </header>
    <div class="topbar-tabs" aria-label="Page navigation">
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
@endverbatim
      <x-app-nav-button />
@verbatim
    </div>

    </div>
@endverbatim
@if ($role === 'super-admin')
@else
@verbatim

@endverbatim
@endif
@verbatim
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim

      <main class="ma-page" id="maPage">
        <header class="ui-page-head">
          <div>
            <h1 class="ui-page-title">My account</h1>
            <p class="ui-page-sub">Your profile photo, details, password, and display preferences.</p>
          </div>
        </header>

        <section class="ui-card ma-profile" aria-label="Profile">
          <span class="pm-avatar" data-profile-avatar aria-hidden="true"></span>
          <div>
            <h2 class="ma-profile-name" data-profile-name></h2>
            <p class="ma-profile-role" data-profile-role></p>
            <p class="ma-profile-email" data-profile-email></p>
            <div class="ma-photo-actions">
              <label class="ui-btn ma-file">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v12H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
                Upload photo
                <input type="file" id="maPhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="maPhotoHint maPhotoError">
              </label>
              <button type="button" class="ui-btn ui-btn--danger" id="maRemovePhoto" hidden>Remove photo</button>
            </div>
            <p class="ui-field-hint" id="maPhotoHint">JPG, PNG, or WebP, up to 8 MB. It’s cropped to a square and shows wherever you log in.</p>
            <p class="ui-field-error" id="maPhotoError" role="alert" hidden></p>
          </div>
        </section>

        <section class="ui-card" aria-labelledby="maDetailsTitle">
          <div class="ma-card-head">
            <h2 class="ma-card-title" id="maDetailsTitle">Personal details</h2>
@endverbatim
@if ($role === 'super-admin')
            <button type="button" class="ui-btn" id="maEditDetails">Edit</button>
@endif
@verbatim
          </div>
          <dl class="ma-details" id="maDetailsView">
            <div><dt>Name</dt><dd id="maName">—</dd></div>
            <div><dt>Email</dt><dd id="maEmail">—</dd></div>
            <div><dt>Role</dt><dd id="maRole">—</dd></div>
            <div><dt>College</dt><dd id="maCollege">—</dd></div>
            <div id="maDeptRow" hidden><dt>Department</dt><dd id="maDept">—</dd></div>
          </dl>
@endverbatim
@if ($role === 'super-admin')
@verbatim
          <form class="ma-form" id="maDetailsForm" novalidate hidden>
            <div class="ma-grid-2">
              <div>
                <label class="ui-field-label" for="maEditName">Full name</label>
                <input class="ui-input" id="maEditName" autocomplete="name" aria-describedby="maEditNameError">
                <p class="ui-field-error" id="maEditNameError" hidden></p>
              </div>
              <div>
                <label class="ui-field-label" for="maEditEmail">Email</label>
                <input class="ui-input" id="maEditEmail" type="email" autocomplete="email" aria-describedby="maEditEmailError">
                <p class="ui-field-error" id="maEditEmailError" hidden></p>
              </div>
            </div>
            <div>
              <label class="ui-field-label" for="maEditConfirm">Current password</label>
              <div class="ma-pw">
                <input class="ui-input" id="maEditConfirm" type="password" autocomplete="current-password" aria-describedby="maEditConfirmHint maEditConfirmError">
                <button type="button" class="ma-reveal" data-ma-reveal="maEditConfirm" aria-pressed="false" aria-label="Show password">Show</button>
              </div>
              <p class="ui-field-hint" id="maEditConfirmHint">For security, confirm it’s you before saving.</p>
              <p class="ui-field-error" id="maEditConfirmError" hidden></p>
            </div>
            <p class="ma-error" id="maDetailsError" role="alert" hidden></p>
            <div class="ma-actions">
              <button type="button" class="ui-btn" id="maCancelDetails">Cancel</button>
              <button type="submit" class="ui-btn ui-btn--primary" id="maSaveDetails">Save details</button>
            </div>
          </form>
@endverbatim
@else
          <p class="ma-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></svg><span>Need to change your name, email, or role? Ask {{ $role === 'super-admin' ? '' : (in_array($role, ['viewing', 'viewing-program-head'], true) ? 'your Records Officer or the University Registrar' : 'the University Registrar') }} to update your account.</span></p>
@endif
@verbatim
        </section>

        <section class="ui-card" aria-labelledby="maPasswordTitle">
          <div class="ma-card-head">
            <div>
              <h2 class="ma-card-title" id="maPasswordTitle">Password</h2>
              <p class="ma-card-sub">Use at least 5 characters, and don’t reuse a password from another site. You’ll use the new one the next time you log in.</p>
            </div>
          </div>
          <form class="ma-form" id="maPasswordForm" novalidate>
            <div>
              <label class="ui-field-label" for="maPwCurrent">Current password</label>
              <div class="ma-pw">
                <input class="ui-input" id="maPwCurrent" type="password" autocomplete="current-password" aria-describedby="maPwCurrentError">
                <button type="button" class="ma-reveal" data-ma-reveal="maPwCurrent" aria-pressed="false" aria-label="Show password">Show</button>
              </div>
              <p class="ui-field-error" id="maPwCurrentError" hidden></p>
            </div>
            <div class="ma-grid-2">
              <div>
                <label class="ui-field-label" for="maPwNew">New password</label>
                <div class="ma-pw">
                  <input class="ui-input" id="maPwNew" type="password" autocomplete="new-password" aria-describedby="maPwNewError">
                  <button type="button" class="ma-reveal" data-ma-reveal="maPwNew" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
                <p class="ui-field-error" id="maPwNewError" hidden></p>
              </div>
              <div>
                <label class="ui-field-label" for="maPwConfirm">Confirm new password</label>
                <div class="ma-pw">
                  <input class="ui-input" id="maPwConfirm" type="password" autocomplete="new-password" aria-describedby="maPwConfirmError">
                  <button type="button" class="ma-reveal" data-ma-reveal="maPwConfirm" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
                <p class="ui-field-error" id="maPwConfirmError" hidden></p>
              </div>
            </div>
            <p class="ma-error" id="maPasswordError" role="alert" hidden></p>
            <div class="ma-actions">
              <button type="submit" class="ui-btn ui-btn--primary" id="maSavePassword">Change password</button>
            </div>
          </form>
        </section>

        <section class="ui-card" aria-labelledby="maDisplayTitle">
          <div class="ma-card-head">
            <div>
              <h2 class="ma-card-title" id="maDisplayTitle">Display</h2>
              <p class="ma-card-sub">Applies to this computer.</p>
            </div>
          </div>
          <fieldset style="border:0;padding:0;margin:0">
            <legend class="ui-field-label">Text size</legend>
            <div class="ma-choices">
              <label class="ma-choice">
                <input type="radio" name="maTextSize" value="default">
                <span class="ma-choice-title">Default</span>
                <span class="ma-choice-sample" style="font-size:0.9rem">Room 402 · Tue 1:00–3:00 PM</span>
              </label>
              <label class="ma-choice">
                <input type="radio" name="maTextSize" value="large">
                <span class="ma-choice-title">Larger text</span>
                <span class="ma-choice-sample" style="font-size:1.02rem">Room 402 · Tue 1:00–3:00 PM</span>
              </label>
            </div>
          </fieldset>
        </section>

        <section class="ui-card ma-signout" aria-labelledby="maSignoutTitle">
          <div>
            <h2 class="ma-card-title" id="maSignoutTitle">Log out</h2>
            <p class="ma-card-sub">Log out of MCU-NEX on this computer, especially if others use it.</p>
          </div>
@endverbatim
          <a href="{{ url('index.html') }}" class="ui-btn ui-btn--danger logout">Log out</a>
@verbatim
        </section>
      </main>
    </div>
  </div>
  <script>
    (function () {
      const navToggle = document.querySelector('.nav-toggle');
      const navBar = document.querySelector('.nav-bar');
      const user = document.querySelector('.topbar-user');
      const userMenu = document.querySelector('.user-menu');

      if (navToggle && navBar) {
        navToggle.addEventListener('click', function () {
          navBar.classList.toggle('nav-bar-open');
          navToggle.classList.toggle('open', navBar.classList.contains('nav-bar-open'));
          navToggle.setAttribute('aria-expanded', navBar.classList.contains('nav-bar-open') ? 'true' : 'false');
        });
      }

      if (user && userMenu) {
        user.addEventListener('click', function (event) {
          event.stopPropagation();
          user.classList.toggle('open');
          user.setAttribute('aria-expanded', user.classList.contains('open') ? 'true' : 'false');
        });

        userMenu.addEventListener('click', function (event) {
          event.stopPropagation();
        });

        document.addEventListener('click', function () {
          user.classList.remove('open');
          user.setAttribute('aria-expanded', 'false');
        });
      }
    })();
  </script>
  <script>
    (function () {
      var el = document.getElementById('topbar-datetime');
      if (!el) return;
      function tick() {
        var d = new Date();
        var opts = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
        var tOpts = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
        el.innerHTML = '<span class="topbar-datetime-date">' + d.toLocaleDateString(undefined, opts) +
          '</span><span class="topbar-datetime-time">' + d.toLocaleTimeString(undefined, tOpts) + '</span>';
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
@endverbatim
    <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="my-account" defer />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>


@endverbatim
