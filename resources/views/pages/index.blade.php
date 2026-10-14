@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MCU-NEX | Log in</title>
@endverbatim
  @fonts
@verbatim
  <style>
    :root {
      --purple: #5c267e;
      --gold: #f9b937;
      --white: #ffffff;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-image:
        linear-gradient(
          to bottom,
          rgba(92, 38, 126, 0) 0%,
          rgba(92, 38, 126, 0) 0%,
          rgba(92, 38, 126, 1) 50%,
          rgba(92, 38, 126, 1) 100%
        ),
        url("Attri/samplebglogin2.png");
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      padding: 1rem;
    }

    .login-card {
      background: var(--white);
      border-radius: 12px;
      padding: 2.25rem 2.5rem 2.5rem;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
      display: flex;
      flex-direction: column;
    }

    .brand-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.75rem;
    }

    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 999px;
      overflow: hidden;
      border: 2px solid var(--gold);
      flex-shrink: 0;
      background: #f3edf8;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .brand-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .brand-text-main {
      color: var(--purple);
      font-size: 1.25rem;
      font-weight: 700;
      letter-spacing: 0.04em;
    }

    .brand-text-sub {
      color: #7a6992;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.16em;
    }

    .login-card h1 {
      color: var(--purple);
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      text-align: left;
    }

    .field {
      margin-bottom: 1.25rem;
    }

    .field label {
      display: block;
      color: var(--purple);
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.375rem;
    }

    .field input {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 2px solid #e8e0ed;
      border-radius: 8px;
      font-size: 1rem;
      font-family: inherit;
      color: var(--purple);
      transition: border-color 0.2s;
    }

    .field input::placeholder {
      color: #a090a8;
    }

    .field input:focus {
      outline: none;
      border-color: var(--gold);
    }

    .forgot {
      text-align: right;
      margin-bottom: 1.5rem;
    }

    .forgot button {
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      color: var(--purple);
      font-size: 0.875rem;
      font-weight: 500;
      font-family: inherit;
      text-decoration: none;
    }

    .forgot button:hover {
      text-decoration: underline;
      color: #4a1f66;
    }

    .btn-login {
      width: 100%;
      padding: 0.875rem 1.5rem;
      background: var(--gold);
      color: var(--purple);
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: background 0.2s, transform 0.05s;
    }

    .btn-login:hover {
      background: #e5a82a;
    }

    .btn-login:active {
      transform: scale(0.99);
    }
    .btn-login:disabled {
      opacity: 0.65;
      cursor: wait;
    }
    .login-error {
      display: none;
      margin: 0 0 0.85rem;
      padding: 0.55rem 0.75rem;
      border-radius: 0.55rem;
      background: #fef2f2;
      color: #991b1b;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .login-error.is-visible { display: block; }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(40, 20, 60, 0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      z-index: 100;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s, visibility 0.2s;
    }

    .modal-overlay.is-open {
      opacity: 1;
      visibility: visible;
    }

    .modal-dialog {
      background: var(--white);
      border-radius: 12px;
      padding: 1.75rem 1.75rem 1.5rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      position: relative;
    }

    .modal-dialog h2 {
      color: var(--purple);
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .modal-dialog .field {
      margin-bottom: 1rem;
    }

    .modal-hint {
      font-size: 0.8rem;
      color: #7a6992;
      margin-bottom: 1rem;
      line-height: 1.4;
    }

    .modal-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 1.25rem;
    }


    .modal-actions .btn-primary {
      flex: 1;
      min-width: 6rem;
      padding: 0.65rem 1rem;
      border: none;
      border-radius: 8px;
      background: var(--gold);
      color: var(--purple);
      font-family: inherit;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
    }

    .modal-actions .btn-primary:hover {
      background: #e5a82a;
    }

    .modal-close {
      position: absolute;
      top: 0.75rem;
      right: 0.75rem;
      width: 2rem;
      height: 2rem;
      border: none;
      background: transparent;
      color: #7a6992;
      font-size: 1.5rem;
      line-height: 1;
      cursor: pointer;
      border-radius: 6px;
    }

    .modal-close:hover {
      background: #f3edf8;
      color: var(--purple);
    }

    .password-wrap {
      position: relative;
    }

    .password-wrap input {
      padding-right: 3rem;
    }

    /* Edge adds its own reveal eye to password fields; hide it so only ours shows. */
    .password-wrap input::-ms-reveal,
    .password-wrap input::-ms-clear {
      display: none;
    }

    .password-toggle {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      background: none;
      color: #7a6992;
      cursor: pointer;
      border-radius: 0 8px 8px 0;
    }

    .password-toggle:hover {
      color: var(--purple);
    }

    .password-toggle:focus-visible {
      outline: 2px solid var(--gold);
      outline-offset: -4px;
    }

    .password-toggle svg {
      width: 20px;
      height: 20px;
    }

    .password-toggle svg[hidden] {
      display: none;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="brand-header">
      <div class="brand-logo">
        <img src="Attri/mculogo.jpg" alt="School logo">
      </div>
      <div>
        <div class="brand-text-main">MCU-NEX</div>
        <div class="brand-text-sub">Registrar Room Allocation</div>
      </div>
    </div>
    <h1>Log in</h1>
    <p class="login-error" id="login-error" role="alert"></p>
    <form action="#" method="post" id="login-form">
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
          <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            <svg class="icon-eye-off" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.2 3.2M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18" /></svg>
          </button>
        </div>
      </div>
      <div class="forgot">
        <button type="button" id="forgot-open">Forgot password?</button>
      </div>
      <button type="submit" class="btn-login">Log in</button>
    </form>
  </div>

  <div id="forgot-modal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="forgot-title" aria-describedby="forgot-desc">
      <button type="button" class="modal-close" id="forgot-close" aria-label="Close">&times;</button>
      <h2 id="forgot-title">Forgot your password?</h2>
      <p class="modal-hint" id="forgot-desc">Passwords can’t be reset by email yet. Ask the University Registrar to set a new password for you in Account management, then log in with it.</p>
      <div class="modal-actions">
        <button type="button" class="btn-primary" id="forgot-ok">OK</button>
      </div>
    </div>
  </div>

@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
@verbatim
  <script>
    (function () {
      function normalizeEmail(s) {
        return (s || '').trim().toLowerCase();
      }

      function showLoginError(message) {
        var el = document.getElementById('login-error');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('is-visible', !!message);
      }

      var form = document.getElementById('login-form');
      var submitBtn = form.querySelector('.btn-login');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        showLoginError('');
        if (!window.McuApi) {
          showLoginError('Open this page through PHP (http://localhost:8000/) so login can reach MySQL.');
          return;
        }
        var email = normalizeEmail(document.getElementById('email').value);
        var password = document.getElementById('password').value || '';
        submitBtn.disabled = true;
        window.McuApi.send('POST', 'auth.php', { action: 'login', email: email, password: password })
          .then(function (res) {
            if (res.user && res.user.home) {
              window.location.href = res.user.home;
              return;
            }
            showLoginError('Logged in, but this role has no home page.');
          })
          .catch(function (err) {
            showLoginError(err.message || 'Invalid email or password.');
          })
          .then(function () {
            submitBtn.disabled = false;
          });
      });

      // Show / hide password.
      var password = document.getElementById('password');
      var toggle = document.getElementById('password-toggle');
      toggle.addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        // SVG elements have no .hidden property, so toggle the attribute.
        toggle.querySelector('.icon-eye').toggleAttribute('hidden', show);
        toggle.querySelector('.icon-eye-off').toggleAttribute('hidden', !show);
        password.focus();
      });

      // Email reset isn't built, so the dialog only explains who can reset it.
      var modal = document.getElementById('forgot-modal');
      var forgotOpen = document.getElementById('forgot-open');

      function openForgot() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('forgot-ok').focus(); }, 50);
      }

      function closeForgot() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        forgotOpen.focus();
      }

      forgotOpen.addEventListener('click', openForgot);
      document.getElementById('forgot-close').addEventListener('click', closeForgot);
      document.getElementById('forgot-ok').addEventListener('click', closeForgot);

      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeForgot();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeForgot();
      });
    })();
  </script>
</body>
</html>


@endverbatim
