{{--
    Shared sidebar navigation for every role page (links per role live in
    config/navigation.php). A fixed sidebar on wide screens, a slide-in
    drawer opened by <x-app-nav-button> below 1200px; behaviour is in
    resources/js/legacy/app-nav.js, styles in resources/css/legacy/app-nav.css.
--}}
@props(['role'])
@php
    $groups = config('navigation.'.$role, []);
    // the current page relative to the role folder, e.g. "data-management/facilities"
    $current = preg_replace('#\.html$#', '', substr(request()->path(), strlen($role) + 1));
    $isActive = function (array $item) use ($current): bool {
        foreach ($item['match'] as $pattern) {
            if (str_ends_with($pattern, '/*') ? str_starts_with($current, substr($pattern, 0, -1)) : $current === $pattern) {
                return true;
            }
        }

        return false;
    };
@endphp
<div class="app-nav-backdrop" data-app-nav-backdrop hidden></div>
<nav class="app-nav no-print" id="app-nav" aria-label="Main navigation" data-role="{{ $role }}">
  <div class="app-nav-drawer-head">
    <span class="app-nav-drawer-title">Menu</span>
    <button type="button" class="app-nav-close" data-app-nav-close aria-label="Close menu">
      <x-app-nav-icon name="close" />
    </button>
  </div>

  <div class="app-nav-groups">
    @foreach ($groups as $heading => $items)
      <div class="app-nav-group">
        <div class="app-nav-heading" id="app-nav-{{ Str::slug($heading) }}">{{ $heading }}</div>
        <ul aria-labelledby="app-nav-{{ Str::slug($heading) }}">
          @foreach ($items as $item)
            @php($active = $isActive($item))
            <li>
              <a href="{{ url($role.'/'.$item['page'].'.html') }}" class="app-nav-link{{ $active ? ' is-active' : '' }}" @if ($active) aria-current="page" @endif>
                <x-app-nav-icon :name="$item['icon']" />
                <span class="app-nav-label">{{ $item['label'] }}</span>
                @isset($item['badge'])
                  <span class="app-nav-badge" data-app-nav-badge="{{ $item['badge'] }}" hidden></span>
                @endisset
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    @endforeach
  </div>

  <div class="app-nav-user">
    <span class="app-nav-avatar" data-app-nav-initials aria-hidden="true"></span>
    <span class="app-nav-user-text">
      <span class="app-nav-user-name" data-app-nav-name>Signed in</span>
      <a href="{{ url('index.html') }}" class="app-nav-logout logout">
        <x-app-nav-icon name="logout" />Log out
      </a>
    </span>
  </div>
</nav>
<x-legacy-js name="app-nav" defer />
