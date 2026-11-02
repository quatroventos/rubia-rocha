@php
  $path = request()->is('/') ? '/' : '/'.trim(request()->path(), '/');
@endphp
<header class="site-header">
  <div class="container site-header__inner">
    <a class="site-header__brand" href="{{ url('/') }}" aria-label="{{ $site->name ?? 'Quatro Ventos' }}">
      @if(!empty($site?->logo_path))
        <img src="{{ media($site->logo_path) }}" alt="{{ $site->name }}" />
      @endif
    </a>

    <button class="site-menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Abrir menu">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <nav class="site-nav" id="site-nav" aria-label="Menu principal">
      @foreach($headerMenu ?? [] as $item)
        @php
          $rawHref = trim((string) $item->href);
          $isExternal = menu_href_is_external($rawHref);
          $href = menu_url($rawHref);
          $pathHref = $isExternal ? null : ($rawHref === '/' ? '/' : '/'.trim($rawHref, '/'));
          $children = collect($item->children ?? []);
          $hasChildren = $children->isNotEmpty();
          $submenuId = 'site-submenu-'.$item->id;
        @endphp
        <div class="site-nav__item{{ $hasChildren ? ' has-children' : '' }}">
          @if($hasChildren)
            <button
              class="site-nav__toggle"
              type="button"
              data-submenu-toggle
              aria-expanded="false"
              aria-controls="{{ $submenuId }}"
              @if($pathHref !== null && $path === $pathHref) aria-current="page" @endif
            >
              <span class="site-nav__toggle-label">{{ $item->label }}</span>
              <span class="site-nav__chevron" aria-hidden="true"></span>
            </button>
            <div class="site-nav__submenu" id="{{ $submenuId }}" data-submenu>
              <div class="site-nav__submenu-panel" role="list">
                @if($rawHref !== '#')
                  <a
                    class="site-nav__submenu-link site-nav__submenu-link--parent"
                    role="listitem"
                    href="{{ $href }}"
                    @if($isExternal) target="_blank" rel="noopener noreferrer" @endif
                    @if($pathHref !== null && $path === $pathHref) aria-current="page" @endif
                  >{{ $item->label }}</a>
                @endif
                @foreach($children as $child)
                  @php
                    $childRaw = trim((string) $child->href);
                    $childExternal = menu_href_is_external($childRaw);
                    $childHref = menu_url($childRaw);
                    $childPath = $childExternal ? null : ($childRaw === '/' ? '/' : '/'.trim($childRaw, '/'));
                  @endphp
                  <a
                    class="site-nav__submenu-link"
                    role="listitem"
                    href="{{ $childHref }}"
                    @if($childExternal) target="_blank" rel="noopener noreferrer" @endif
                    @if($childPath !== null && $path === $childPath) aria-current="page" @endif
                  >{{ $child->label }}</a>
                @endforeach
              </div>
            </div>
          @else
            <a
              class="site-nav__link"
              href="{{ $href }}"
              @if($isExternal) target="_blank" rel="noopener noreferrer" @endif
              @if($pathHref !== null && $path === $pathHref) aria-current="page" @endif
            >{{ $item->label }}</a>
          @endif
        </div>
      @endforeach
    </nav>
  </div>
</header>
