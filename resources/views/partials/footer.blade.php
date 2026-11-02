<footer class="site-footer">
  <div class="container site-footer__inner">
    <div class="site-footer__brand">
      @if(!empty($site?->logo_path))
        <img src="{{ media($site->logo_path) }}" alt="{{ $site->name }}" />
      @endif
      <div class="site-footer__social" aria-label="Redes sociais">
        <a href="{{ $site?->instagram_url ?: '#' }}" aria-label="Instagram" @if(!empty($site?->instagram_url)) target="_blank" rel="noopener" @endif>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="4" y="4" width="16" height="16" rx="4"></rect>
            <circle cx="12" cy="12" r="4"></circle>
            <circle cx="17" cy="7" r="1"></circle>
          </svg>
        </a>
        <a href="{{ $site?->linkedin_url ?: '#' }}" aria-label="LinkedIn" @if(!empty($site?->linkedin_url)) target="_blank" rel="noopener" @endif>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 9h4v11H5z"></path>
            <path d="M5 4h4v3H5z"></path>
            <path d="M11 9h4v2c.7-1.3 1.9-2.2 3.8-2.2 2.7 0 4.2 1.8 4.2 5V20h-4v-5.6c0-1.3-.5-2.2-1.8-2.2-1.4 0-2.2 1-2.2 2.5V20h-4z"></path>
          </svg>
        </a>
        <a href="{{ $site?->facebook_url ?: '#' }}" aria-label="Facebook" @if(!empty($site?->facebook_url)) target="_blank" rel="noopener" @endif>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M13.5 21v-8h2.7l.4-3h-3.1V8.2c0-.9.3-1.5 1.6-1.5h1.7V4.1C16.5 4.1 15.4 4 14.3 4 11.8 4 10 5.5 10 8v2H7.5v3H10v8z"></path>
          </svg>
        </a>
      </div>
    </div>

    <div class="site-footer__compliance" aria-label="Documentos institucionais">
      <a href="{{ url('/esg#politicas-institucionais') }}">Políticas institucionais</a>
      <a href="{{ url('/esg#codigo-etica') }}">Código de Ética e Conduta</a>
      <a href="{{ url('/esg#canal-denuncias') }}">Canal de Denúncias</a>
      <a href="{{ url('/esg#politica-privacidade') }}">Política de Privacidade</a>
    </div>

    <nav class="site-footer__nav" aria-label="Menu do rodapé">
      @foreach([($footerMenu1 ?? collect()), ($footerMenu2 ?? collect())] as $group)
        <div class="site-footer__nav-group">
          @foreach($group as $item)
            @php $children = collect($item->children ?? []); @endphp
            <div class="site-footer__nav-item">
              @php
                $itemExternal = menu_href_is_external($item->href);
              @endphp
              <a
                href="{{ menu_url($item->href) }}"
                @if($itemExternal) target="_blank" rel="noopener noreferrer" @endif
              >{{ $item->label }}</a>
              @if($children->isNotEmpty())
                <div class="site-footer__submenu">
                  @foreach($children as $child)
                    @php $childExternal = menu_href_is_external($child->href); @endphp
                    <a
                      href="{{ menu_url($child->href) }}"
                      @if($childExternal) target="_blank" rel="noopener noreferrer" @endif
                    >{{ $child->label }}</a>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
        </div>
      @endforeach
    </nav>

    <div class="site-footer__certifications" aria-label="Certificações">
      @foreach($certs ?? [] as $cert)
        @if(($cert->type ?? 'badge') === 'badge' && $cert->image_path)
          <div class="site-footer__badge" aria-label="{{ $cert->alt ?: $cert->label }}">
            <img src="{{ media($cert->image_path) }}" alt="{{ $cert->alt ?: $cert->label }}" />
          </div>
        @else
          <div class="site-footer__certification-space" aria-label="{{ $cert->alt ?: $cert->label }}"></div>
        @endif
      @endforeach
    </div>
  </div>
</footer>
