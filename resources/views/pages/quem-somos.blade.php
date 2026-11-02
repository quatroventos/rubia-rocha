@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $strip = $c['strip'] ?? [];
    $culture = $c['culture'] ?? [];
    $mission = $c['mission'] ?? [];
    $vision = $c['vision'] ?? [];
  @endphp

  <section class="about-hero" data-cms-section="hero" aria-labelledby="quem-somos-title">
    <div class="container section-inner">
      <div class="about-hero__grid">
        <div class="section-heading reveal reveal--left">
          <div class="eyebrow-bars" aria-hidden="true">
            <span></span><span></span><span></span><span></span>
          </div>
          <h1 id="quem-somos-title">{!! rich($hero['title'] ?? '', true) !!}</h1>
        </div>
        <div class="about-hero__copy reveal reveal--right">
          @foreach($hero['paragraphs'] ?? [] as $p)
            {!! rich($p) !!}
          @endforeach
        </div>
      </div>
    </div>
  </section>

  @if(!empty($strip['image']))
    <section class="about-strip" data-cms-section="strip" aria-label="Ambiente jurídico">
      <div class="container section-inner">
        <div class="about-strip__image reveal reveal--scale">
          <img src="{{ media($strip['image']) }}" alt="{{ $strip['alt'] ?? '' }}" />
        </div>
      </div>
    </section>
  @endif

  <section class="culture-section" data-cms-section="culture" aria-labelledby="cultura-title">
    <div class="container section-inner">
      <div class="culture-section__grid">
        <div class="culture-section__image reveal reveal--left">
          @if(!empty($culture['image']))
            <img src="{{ media($culture['image']) }}" alt="{{ $culture['image_alt'] ?? '' }}" />
          @endif
        </div>
        <div class="culture-section__content reveal reveal--right">
          <h2 id="cultura-title">{!! rich($culture['title'] ?? '', true) !!}</h2>
          @foreach($culture['paragraphs'] ?? [] as $p)
            {!! rich($p) !!}
          @endforeach
        </div>
      </div>
    </div>
  </section>

  <section class="values-section" aria-labelledby="valores-title">
    <div class="container section-inner">
      <div class="mission-grid" data-cms-section="mission">
        <article class="mission-card reveal reveal--left">
          <div>
            <h2>{!! rich($mission['title'] ?? 'Missão', true) !!}</h2>
            {!! rich($mission['body'] ?? '') !!}
          </div>
        </article>
        <article class="mission-card reveal reveal--right">
          <div>
            <h2>{!! rich($vision['title'] ?? 'Visão', true) !!}</h2>
            {!! rich($vision['body'] ?? '') !!}
          </div>
        </article>
      </div>

      <div data-cms-section="values">
      <h2 class="values-section__title reveal" id="valores-title">{!! rich($c['values_title'] ?? 'Valores', true) !!}</h2>

      <div class="value-grid">
        @foreach($c['values'] ?? [] as $value)
          <article class="value-card reveal">
            <span class="value-card__icon" aria-hidden="true">
              {!! \App\Support\SiteIcons::value($loop->index) !!}
            </span>
            <h3>{!! rich($value['title'] ?? '', true) !!}</h3>
            {!! rich($value['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
      </div>
    </div>
  </section>

  <section class="leaders" aria-labelledby="lideranca-title">
    <div class="container section-inner">
      <h2 class="reveal" id="lideranca-title">{!! rich($c['leaders_title'] ?? 'Liderança Executiva', true) !!}</h2>
      <div class="leaders__grid">
        @foreach($team ?? [] as $member)
          <article class="leader-card reveal">
            <div class="leader-card__image">
              @if($member->photo_path)
                <img src="{{ media($member->photo_path) }}" alt="{{ $member->name }}" />
              @endif
            </div>
            <h3>{{ $member->name }}</h3>
            <p>
              @if($member->role)
                <strong>{{ $member->role }}</strong>
              @endif
            </p>
            {!! rich($member->bio) !!}
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endsection
