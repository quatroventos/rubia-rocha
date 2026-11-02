@php
  $channels = $c['channels'] ?? [];
  $whatsapp = collect($channels)->firstWhere('title', 'WhatsApp') ?? ($channels[1] ?? null);
  $direct = collect($channels)->firstWhere('title', 'Atendimento direto') ?? ($channels[0] ?? null);
  $office = collect($channels)->firstWhere('title', 'Escritório') ?? ($channels[2] ?? null);
@endphp

<section class="career-form-section" aria-label="Canais de contato">
  <div class="container section-inner career-form-grid">
    <aside class="career-aside reveal reveal--left" data-cms-section="channels" aria-label="Canais de contato">
      <article class="career-aside__item">
        <span class="career-aside__marker" aria-hidden="true"></span>
        <h2>{!! rich($direct['title'] ?? 'Atendimento direto', true) !!}</h2>
        {!! rich($direct['body'] ?? '') !!}
      </article>

      <article class="career-aside__item">
        <span class="career-aside__marker" aria-hidden="true"></span>
        <h2>{!! rich($whatsapp['title'] ?? 'WhatsApp', true) !!}</h2>
        {!! rich($whatsapp['body'] ?? '') !!}
        @if(!empty($site?->whatsapp_url))
          <div class="contact-actions">
            <a class="button button--whatsapp" href="{{ $site->whatsapp_url }}" target="_blank" rel="noopener">Falar pelo WhatsApp</a>
          </div>
        @endif
      </article>

      <article class="career-aside__item">
        <span class="career-aside__marker" aria-hidden="true"></span>
        <h2>{!! rich($office['title'] ?? 'Escritório', true) !!}</h2>
        <div class="contact-info-list">
          @if(!empty($site?->address))
            <address>{!! nl2br(e($site->address)) !!}</address>
          @endif
          @if(!empty($site?->phone_href))
            <a href="{{ $site->phone_href }}">{{ $site->phone }}</a>
          @endif
          @if(!empty($site?->email))
            <a href="mailto:{{ $site->email }}">{{ $site->email }}</a>
          @endif
        </div>
      </article>
    </aside>
  </div>
</section>
