{{--
    Topbar profile button and menu, shared by every role page. The signed-in
    user's name, role, email and photo are filled in by
    resources/js/legacy/profile.js; styles are in resources/css/legacy/profile.css.
--}}
@props(['role'])
{{-- apply the "large text" preference before the page paints --}}
<script>try { if (localStorage.getItem('mcu-nex:text-size') === 'large') document.documentElement.classList.add('pref-text-large'); } catch (e) {}</script>
<div class="pm" data-profile-menu>
  <button type="button" class="pm-btn" aria-haspopup="true" aria-expanded="false" aria-controls="pm-panel" aria-label="Your account">
    <span class="pm-avatar" data-profile-avatar aria-hidden="true"></span>
    <span class="pm-name" data-profile-name></span>
    <svg class="pm-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
  </button>
  <div class="pm-panel" id="pm-panel" hidden>
    <div class="pm-head">
      <span class="pm-avatar pm-avatar--lg" data-profile-avatar aria-hidden="true"></span>
      <span class="pm-head-text">
        <span class="pm-head-name" data-profile-name></span>
        <span class="pm-head-role" data-profile-role></span>
        <span class="pm-head-email" data-profile-email></span>
      </span>
    </div>
    <a href="{{ url($role.'/account.html') }}" class="pm-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
      My account
    </a>
    <div class="pm-sep" role="separator"></div>
    <a href="{{ url('index.html') }}" class="pm-item pm-item--logout logout">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
      Log out
    </a>
  </div>
</div>
<x-legacy-js name="profile" defer />
