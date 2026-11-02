@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $pillars = $c['pillars'] ?? [];
    $video = $c['video'] ?? [];
    $solutions = $c['solutions'] ?? [];
    $strip = $c['strip'] ?? [];
    $management = $c['management'] ?? [];
    $excellence = $c['excellence'] ?? [];
    $coverage = $c['coverage'] ?? [];
    $cta = $c['cta'] ?? [];
    $pillarTitles = $pillars['titles'] ?? [];
    $ctaBits = preg_split('/\.\s*/', trim($cta['highlight'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
  @endphp

  <section class="hero" data-cms-section="hero" aria-label="{{ $site->name ?? 'Quatro Ventos' }}">
    <div class="hero__word-cloud" aria-hidden="true">
      @foreach($hero['word_cloud'] ?? [] as $i => $word)
        @php
          $size = $word['size'] ?? 'medium';
          $accent = !empty($word['accent']) ? ' word-cloud__word--accent' : '';
          $duration = 12 + ($i % 5);
          $delay = -(($i % 10) + 1);
          $driftX = (($i % 2) ? -1 : 1) * (8 + ($i % 6));
          $driftY = (($i % 3) === 0 ? -1 : 1) * (7 + ($i % 5));
          $rotate = (($i % 2) ? -1 : 1) * (1 + ($i % 2));
        @endphp
        <span
          class="word-cloud__word word-cloud__word--{{ $size }}{{ $accent }}"
          style="--word-x: {{ $word['x'] ?? '50%' }}; --word-y: {{ $word['y'] ?? '50%' }}; --word-duration: {{ $duration }}s; --word-delay: {{ $delay }}s; --word-drift-x: {{ $driftX }}px; --word-drift-y: {{ $driftY }}px; --word-rotate: {{ $rotate }}deg"
        >{!! rich($word['text'] ?? '', true) !!}</span>
      @endforeach
    </div>
    <div class="hero__overlay"></div>
    <div class="container hero__content">
      <h1>
        @foreach($hero['title_lines'] ?? [] as $line)
          <span class="hero-title__line">
            {!! rich($line, true) !!}
            @if(!$loop->last)
              <span class="hero-title__marker">◾</span>
            @endif
          </span>
        @endforeach
      </h1>
      @if(!empty($hero['subtitle']))
        {!! rich($hero['subtitle']) !!}
      @endif
      @if(!empty($hero['cta_label']))
        <a class="button button--outline" href="{{ $hero['cta_href'] ?? '#solutions' }}">{!! rich($hero['cta_label'], true) !!}</a>
      @endif
    </div>
  </section>

  <section class="intro-card-section" data-cms-section="pillars">
    <div class="container">
      <article class="intro-card">
        <h2>
          @if(!empty($pillars['eyebrow']))
            <span class="intro-card__eyebrow">{!! rich($pillars['eyebrow'], true) !!}</span>
          @endif
          <strong class="intro-card__pillar-title">
            @foreach($pillarTitles as $title)
              @if(!$loop->last)
                <span class="intro-card__pillar-item">
                  <span>{!! rich($title, true) !!}</span>
                  <span class="intro-card__pillar-marker" aria-hidden="true"></span>
                </span>
              @else
                <span>{!! rich($title, true) !!}</span>
              @endif
            @endforeach
          </strong>
        </h2>
        @if(!empty($pillars['intro']))
          {!! rich($pillars['intro']) !!}
        @endif
        <ul class="accent-list">
          @foreach($pillars['items'] ?? [] as $item)
            <li><strong>{!! rich($item['title'] ?? '', true) !!}:</strong> {!! rich($item['body'] ?? '', true) !!}</li>
          @endforeach
        </ul>
      </article>
    </div>
  </section>

  @if(!empty($video['src']))
    <section class="portfolio-video" data-cms-section="video" aria-labelledby="portfolio-video-title">
      <div class="container portfolio-video__grid">
        <div class="portfolio-video__copy">
          <h2 id="portfolio-video-title">{!! rich($video['title'] ?? '', true) !!}</h2>
          @if(!empty($video['body']))
            {!! rich($video['body']) !!}
          @endif
        </div>
        <div class="portfolio-video__frame" aria-label="Vídeo de portfólio do escritório">
          <video controls preload="metadata">
            <source src="{{ media($video['src']) }}" />
          </video>
        </div>
      </div>
    </section>
  @endif

  <section class="solutions" id="solutions" data-cms-section="solutions" aria-labelledby="solutions-title">
    <div class="container">
      <div class="solutions__heading">
        <h2 id="solutions-title">{!! rich($solutions['title'] ?? 'Soluções', true) !!}</h2>
        @if(!empty($solutions['subtitle']))
          {!! rich($solutions['subtitle']) !!}
        @endif
      </div>
      <div class="solutions__grid">
        <div class="solutions__visual">
          @if(!empty($solutions['image']))
            <img src="{{ media($solutions['image']) }}" alt="{{ $solutions['image_alt'] ?? '' }}" />
          @endif
        </div>
        <div class="solutions__cards" aria-label="Soluções">
          @foreach($solutions['cards'] ?? [] as $card)
            <article class="solution-card">
              <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
              {!! rich($card['body'] ?? '') !!}
            </article>
          @endforeach
        </div>
      </div>
    </div>
  </section>

  @if(!empty($strip['image']))
    <section class="image-strip" data-cms-section="strip" aria-label="Arquitetura jurídica">
      <img src="{{ media($strip['image']) }}" alt="{{ $strip['alt'] ?? '' }}" />
    </section>
  @endif

  <section class="management-section" data-cms-section="management">
    <div class="container management-section__grid">
      <div class="management-section__content">
        <h2>{!! rich($management['title'] ?? '', true) !!}</h2>
        @foreach($management['paragraphs'] ?? [] as $p)
          {!! rich($p) !!}
        @endforeach
        <span class="accent-block" aria-hidden="true"></span>
      </div>
      @if(!empty($management['image']))
        <img class="management-section__image" src="{{ media($management['image']) }}" alt="{{ $management['image_alt'] ?? '' }}" />
      @endif
    </div>
  </section>

  <section class="dark-feature" data-cms-section="excellence">
    <div class="container">
      <article class="dark-feature__card">
        <div class="dark-feature__media">
          @if(!empty($excellence['image']))
            <img src="{{ media($excellence['image']) }}" alt="{{ $excellence['image_alt'] ?? '' }}" />
          @endif
        </div>
        <div class="dark-feature__content">
          <h2>{!! rich($excellence['title'] ?? '', true) !!}</h2>
          @foreach($excellence['paragraphs'] ?? [] as $p)
            {!! rich($p) !!}
          @endforeach
        </div>
      </article>
    </div>
  </section>

  <section class="national-coverage" data-cms-section="coverage">
    <div class="container national-coverage__grid">
      @if(!empty($coverage['image']))
        <img class="national-coverage__map" src="{{ media($coverage['image']) }}" alt="{{ $coverage['image_alt'] ?? '' }}" />
      @endif
      <div class="national-coverage__content">
        <h2>{!! rich($coverage['title'] ?? '', true) !!}</h2>
        <div class="national-coverage__copy">
          @foreach($coverage['paragraphs'] ?? [] as $p)
            {!! rich($p) !!}
          @endforeach
        </div>
        <div class="national-coverage__card">
          <ul class="accent-list accent-list--compact">
            @foreach($coverage['items'] ?? [] as $item)
              <li>{!! rich($item, true) !!}</li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="final-cta" data-cms-section="cta">
    <div class="container final-cta__inner">
      <h2>
        {!! rich($cta['title'] ?? '', true) !!}<br />
        <strong>
          @foreach($ctaBits as $bit)
            <span class="final-cta__nowrap">{!! rich(rtrim($bit, '.'), true) !!}.</span>
          @endforeach
        </strong>
      </h2>
      @if(!empty($cta['button_label']))
        <a class="button button--outline" href="{{ url($cta['button_href'] ?? '/contato') }}">{!! rich($cta['button_label'], true) !!}</a>
      @endif
    </div>
  </section>
@endsection
