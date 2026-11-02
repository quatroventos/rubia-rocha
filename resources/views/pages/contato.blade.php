@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $channels = $c['channels'] ?? [];
    $form = $c['form'] ?? [];
    $map = $c['map'] ?? [];
    $whatsapp = collect($channels)->firstWhere('title', 'WhatsApp') ?? ($channels[1] ?? null);
    $direct = collect($channels)->firstWhere('title', 'Atendimento direto') ?? ($channels[0] ?? null);
    $office = collect($channels)->firstWhere('title', 'Escritório') ?? ($channels[2] ?? null);
  @endphp

  <section class="career-hero contact-hero" data-cms-section="hero" aria-labelledby="contact-title">
    <div class="container section-inner career-hero__grid">
      <div class="career-hero__copy reveal reveal--left">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
        <div class="section-heading">
          <h1 id="contact-title">{!! rich($hero['title'] ?? 'Contato', true) !!}</h1>
          @if(!empty($hero['body']))
            {!! rich($hero['body']) !!}
          @endif
        </div>
      </div>
    </div>
  </section>

  <section class="career-form-section" aria-labelledby="contact-form-title">
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

      <div class="career-form-card reveal reveal--right" data-cms-section="form">
        <h2 id="contact-form-title">{!! rich($form['title'] ?? '', true) !!}</h2>
        @if(!empty($form['body']))
          {!! rich($form['body']) !!}
        @endif

        @if($errors->any())
          <p class="form-feedback form-feedback--error" id="form-feedback" role="alert">
            Não foi possível enviar. Revise os campos obrigatórios e tente novamente.
          </p>
        @else
          <p class="form-feedback" id="form-feedback" role="status" hidden></p>
        @endif

        <form class="application-form" action="{{ route('contato.store') }}" method="post">
          @csrf
          <div class="form-honeypot" aria-hidden="true">
            <label for="empresa_site">Empresa</label>
            <input id="empresa_site" name="empresa_site" type="text" tabindex="-1" autocomplete="off" />
          </div>
          @include('partials.site-form-fields', [
            'formSchema' => $formSchema ?? [],
            'lgpdLabel' => $formSchema['lgpd_label'] ?? $form['lgpd_label'] ?? 'Autorizo o armazenamento dos dados informados para fins de contato e retorno, conforme a legislação aplicável de proteção de dados.',
          ])
          <button class="button" type="submit">{!! rich($formSchema['submit_label'] ?? $form['submit_label'] ?? 'Enviar', true) !!}</button>
        </form>
      </div>
    </div>
  </section>

  <section class="map-section" data-cms-section="map" aria-labelledby="map-title">
    <div class="container section-inner map-card">
      <div class="section-heading map-heading reveal">
        <h2 id="map-title">{!! rich($map['title'] ?? 'Onde estamos', true) !!}</h2>
        @if(!empty($map['body']))
          {!! rich($map['body']) !!}
        @endif
      </div>
      @if(!empty($site?->map_embed_url))
        <div class="map-frame reveal">
          <iframe
            src="{{ $site->map_embed_url }}"
            width="600"
            height="450"
            style="border: 0"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Mapa do {{ $site->name }}"
          ></iframe>
        </div>
      @endif
    </div>
  </section>
@endsection
