@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $specialties = $c['specialties'] ?? [];
    $units = $c['units'] ?? [];
    $technology = $c['technology'] ?? [];
    $security = $c['security'] ?? [];
    $culture = $c['culture'] ?? [];
    $secTitle = $security['title'] ?? '';
    $secParts = str_contains($secTitle, ' e ') ? explode(' e ', $secTitle, 2) : [$secTitle, null];
  @endphp

  <section class="structure-hero" data-cms-section="hero" aria-labelledby="estrutura-title">
    <div class="structure-hero__media" aria-hidden="true">
      @if(!empty($hero['image']))
        <img src="{{ media($hero['image']) }}" alt="" />
      @endif
    </div>

    <div class="container structure-hero__grid">
      <div class="structure-hero__title reveal reveal--left">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h1 id="estrutura-title">{!! rich($hero['title'] ?? '', true) !!}</h1>
      </div>

      @if(!empty($hero['body']))
        <p class="structure-hero__copy reveal reveal--right">{!! rich($hero['body'], true) !!}</p>
      @endif
    </div>
  </section>

  @if(!empty($hero['features']))
    <section class="structure-highlights" aria-label="Características da estrutura">
      <div class="container feature-list">
        @foreach($hero['features'] as $feature)
          <article class="feature-item">
            <span class="feature-icon" aria-hidden="true">
              {!! \App\Support\SiteIcons::feature($loop->index) !!}
            </span>
            <div>
              <h3>{!! rich($feature['title'] ?? '', true) !!}</h3>
              {!! rich($feature['body'] ?? '') !!}
            </div>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  <section class="specialties" data-cms-section="specialties" aria-labelledby="especializacao-title">
    <div class="container section-inner">
      <div class="section-heading">
        <h2 id="especializacao-title">{!! rich($specialties['title'] ?? '', true) !!}</h2>
        @if(!empty($specialties['subtitle']))
          {!! rich($specialties['subtitle']) !!}
        @endif
      </div>
      <div class="specialties__grid">
        @foreach($specialties['cards'] ?? [] as $card)
          <article class="specialty-card">
            <span class="card-icon" aria-hidden="true">
              {!! \App\Support\SiteIcons::specialty($loop->index) !!}
            </span>
            <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
            {!! rich($card['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
      @if(!empty($specialties['note']))
        <p class="specialties__note">
          <span class="specialties__note-text">{!! rich($specialties['note'], true) !!}</span>
        </p>
      @endif
    </div>
  </section>

  <section class="office-locations" id="filiais" data-cms-section="units">
    <div class="container office-locations__inner">
      <div class="section-heading">
        <h2>{!! rich($units['title'] ?? 'Nossas unidades', true) !!}</h2>
        @if(!empty($units['subtitle']))
          {!! rich($units['subtitle']) !!}
        @endif
      </div>

      <div class="office-locations__dropdown" data-branches aria-label="Endereços das unidades">
        <div class="office-locations__select-field">
          <label for="branch-selector">{!! rich($units['select_label'] ?? 'Selecione a unidade', true) !!}</label>
          <select class="office-locations__select" id="branch-selector" data-branch-select>
            @foreach($branches ?? [] as $branch)
              <option value="branch-{{ $branch->slug }}" @selected($loop->first)>{{ $branch->select_label }}</option>
            @endforeach
          </select>
        </div>
        <div class="office-locations__panel">
          @foreach($branches ?? [] as $branch)
            <article
              class="branch-panel{{ $loop->first ? ' is-active' : '' }}"
              id="branch-{{ $branch->slug }}"
              role="tabpanel"
              aria-labelledby="branch-selector"
              @if(!$loop->first) hidden @endif
            >
              <span class="branch-panel__eyebrow">{{ $branch->eyebrow }}</span>
              <div class="branch-panel__grid">
                <div>
                  <h3>{{ $branch->city }} - {{ $branch->state }}</h3>
                  <address>{!! nl2br(e($branch->address)) !!}</address>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      </div>

      <div class="office-locations__mobile" aria-label="Endereços das unidades">
        @foreach($branches ?? [] as $branch)
          <details class="office-location" @if($loop->first) open @endif>
            <summary>{{ $branch->select_label }}</summary>
            <div class="office-location__body">
              <span class="office-location__eyebrow">{{ $branch->eyebrow }}</span>
              <h3>{{ $branch->city }} - {{ $branch->state }}</h3>
              <address>{!! nl2br(e($branch->address)) !!}</address>
            </div>
          </details>
        @endforeach
      </div>
    </div>
  </section>

  <section class="technology" data-cms-section="technology" aria-labelledby="technology-title">
    <div class="container section-inner">
      <div class="section-heading">
        <h2 id="technology-title">{!! rich($technology['title'] ?? '', true) !!}</h2>
        @if(!empty($technology['body']))
          {!! rich($technology['body']) !!}
        @endif
      </div>
      <div class="technology__layout">
        <div class="technology__mini-grid">
          @foreach($technology['mini_cards'] ?? [] as $card)
            <article class="tech-card">
              <span class="card-icon" aria-hidden="true">
                {!! \App\Support\SiteIcons::techMini($loop->index) !!}
              </span>
              <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
              {!! rich($card['body'] ?? '') !!}
            </article>
          @endforeach
        </div>
        <div class="technology__image">
          @if(!empty($technology['image']))
            <img src="{{ media($technology['image']) }}" alt="{{ $technology['image_alt'] ?? '' }}" />
          @endif
        </div>
      </div>
      <div class="technology__wide-grid">
        @foreach($technology['wide_cards'] ?? [] as $card)
          <article class="tech-card tech-card--wide {{ $loop->first ? 'tech-card--dark' : 'tech-card--accent' }}">
            <span class="card-icon" aria-hidden="true">
              {!! \App\Support\SiteIcons::techWide($loop->index) !!}
            </span>
            <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
            {!! rich($card['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <section class="security" data-cms-section="security" aria-labelledby="security-title">
    <div class="container section-inner">
      <div class="section-heading">
        <h2 id="security-title">
          @if(is_string($secTitle) && str_contains($secTitle, '<'))
            {!! rich($secTitle, true) !!}
          @else
            {!! rich($secParts[0] ?? '', true) !!}
            @if($secParts[1])
              <span class="security-title-break">e {!! rich($secParts[1], true) !!}</span>
            @endif
          @endif
        </h2>
        @if(!empty($security['body']))
          {!! rich($security['body']) !!}
        @endif
      </div>
      <div class="security__grid">
        @if(!empty($security['statement']))
          {!! rich($security['statement'], false, 'security__statement') !!}
        @endif
        <div class="security-list">
          @foreach($security['items'] ?? [] as $item)
            <article class="accent-bullet">
              <div>
                <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
                {!! rich($item['body'] ?? '') !!}
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </div>
  </section>

  <section class="culture" data-cms-section="culture" aria-labelledby="culture-title">
    <div class="container section-inner">
      <div class="section-heading">
        <h2 id="culture-title">{!! rich($culture['title'] ?? '', true) !!}</h2>
        @foreach($culture['paragraphs'] ?? [] as $p)
          {!! rich($p) !!}
        @endforeach
      </div>
      <div class="culture__body">
        <div class="culture__image">
          @if(!empty($culture['image']))
            <img src="{{ media($culture['image']) }}" alt="{{ $culture['image_alt'] ?? '' }}" />
          @endif
        </div>
        <div class="culture-list">
          @foreach($culture['items'] ?? [] as $item)
            <article class="accent-bullet">
              <div>
                <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
                {!! rich($item['body'] ?? '') !!}
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </div>
  </section>
@endsection
