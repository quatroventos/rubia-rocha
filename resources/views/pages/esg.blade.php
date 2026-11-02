@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $pillars = $c['pillars'] ?? [];
    $ethics = $c['ethics'] ?? [];
    $conclusion = $c['conclusion'] ?? [];
  @endphp

  <section class="esg-hero" data-cms-section="hero" aria-labelledby="esg-title">
    <div class="container section-inner esg-hero__grid">
      <div class="esg-hero__copy reveal reveal--left">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h1 id="esg-title">{!! rich($hero['title'] ?? '', true) !!}</h1>
        @if(!empty($hero['body']))
          {!! rich($hero['body']) !!}
        @endif
      </div>
      <div class="esg-hero__media reveal reveal--right">
        @if(!empty($hero['image']))
          <img src="{{ media($hero['image']) }}" alt="{{ $hero['image_alt'] ?? '' }}" />
        @endif
      </div>
    </div>
  </section>

  <section class="pillars" data-cms-section="pillars" aria-labelledby="pillars-title">
    <div class="container section-inner">
      <div class="section-heading reveal">
        <h2 id="pillars-title">{!! rich($pillars['title'] ?? '', true) !!}</h2>
        @if(!empty($pillars['subtitle']))
          {!! rich($pillars['subtitle']) !!}
        @endif
      </div>
      <div class="pillars__grid">
        @foreach($pillars['cards'] ?? [] as $card)
          <article class="pillar-card reveal">
            <span class="pillar-card__label" aria-hidden="true">{{ $card['number'] ?? str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
            {!! rich($card['body'] ?? '') !!}
            @if(!empty($card['list']))
              <ul>
                @foreach($card['list'] as $item)
                  <li>{!! rich($item, true) !!}</li>
                @endforeach
              </ul>
            @endif
          </article>
        @endforeach
      </div>
    </div>
  </section>

  @if(!empty($c['documents']['cards']))
    <section class="documents" id="documentos-esg" data-cms-section="documents" aria-labelledby="documents-title">
      <div class="container section-inner">
        <div class="section-heading reveal">
          <h2 id="documents-title">{!! rich($c['documents']['title'] ?? 'Documentos e canais institucionais', true) !!}</h2>
          @if(!empty($c['documents']['body']))
            {!! rich($c['documents']['body']) !!}
          @endif
        </div>
        <div class="documents__grid" id="politicas-institucionais">
          @foreach($c['documents']['cards'] as $doc)
            <article class="document-card {{ !empty($doc['channel']) ? 'document-card--channel' : '' }} reveal" @if(!empty($doc['id'])) id="{{ $doc['id'] }}" @endif>
              @if(!empty($doc['eyebrow']))
                <span class="document-card__eyebrow">{!! rich($doc['eyebrow'], true) !!}</span>
              @endif
              <h3>{!! rich($doc['title'] ?? '', true) !!}</h3>
              {!! rich($doc['body'] ?? '') !!}
              @if(!empty($doc['href']))
                <a class="document-card__link" href="{{ str_starts_with($doc['href'], '#') ? $doc['href'] : media($doc['href']) }}" @if(!str_starts_with($doc['href'] ?? '', '#')) target="_blank" rel="noopener" @endif>{{ $doc['link_label'] ?? 'Acessar documento' }}</a>
              @endif
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section class="ethics" id="canal-denuncias" data-cms-section="ethics" aria-labelledby="ethics-title">
    <div class="container section-inner ethics__layout">
      <div class="ethics__content reveal reveal--left">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h2 id="ethics-title">{!! rich($ethics['title'] ?? '', true) !!}</h2>
        @foreach($ethics['paragraphs'] ?? [] as $p)
          {!! rich($p, false, ($loop->last && $loop->count > 1) ? 'ethics__assurance' : null) !!}
        @endforeach
      </div>
      <div class="reveal reveal--right">
        <div class="ethics__media">
          @if(!empty($ethics['image']))
            <img src="{{ media($ethics['image']) }}" alt="{{ $ethics['image_alt'] ?? '' }}" />
          @endif
        </div>
        <div class="ethics__qr-card">
          <div class="ethics__qr-frame">
            @if(!empty($ethics['qr_image']))
              <img src="{{ media($ethics['qr_image']) }}" alt="{{ $ethics['qr_title'] ?? 'QR Code' }}" />
            @else
              <span class="ethics__qr-placeholder">QR Code do Canal de Denúncias</span>
            @endif
          </div>
          <div class="ethics__qr-copy">
            <p>
              <strong>{!! rich($ethics['qr_title'] ?? 'Acesse o canal', true) !!}</strong>
              {!! rich($ethics['qr_body'] ?? '', true) !!}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="conclusion" data-cms-section="conclusion" aria-labelledby="conclusion-title">
    <div class="container conclusion__inner reveal reveal--scale">
      <div class="eyebrow-bars" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
      </div>
      <h2 id="conclusion-title">{!! rich($conclusion['title'] ?? '', true) !!}</h2>
      @if(!empty($conclusion['body']))
        {!! rich($conclusion['body']) !!}
      @endif
    </div>
  </section>
@endsection
