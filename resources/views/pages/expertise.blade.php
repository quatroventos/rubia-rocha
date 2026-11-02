@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $iconMods = ['negotiation', 'legal', 'specialized'];
  @endphp

  <section class="expertise-hero" data-cms-section="hero" aria-labelledby="expertise-title">
    <div class="expertise-hero__media" aria-hidden="true">
      @if(!empty($hero['image']))
        <img src="{{ media($hero['image']) }}" alt="{{ $hero['image_alt'] ?? '' }}" />
      @endif
    </div>
    <div class="container expertise-hero__grid">
      <div class="expertise-hero__title reveal reveal--left">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h1 id="expertise-title">{!! rich($hero['title'] ?? 'Expertise', true) !!}</h1>
      </div>
      {!! rich($hero['body'] ?? '', false, 'expertise-hero__copy reveal reveal--right') !!}
    </div>
  </section>

  <section class="expertise-cards" data-cms-section="cards" aria-label="Áreas de expertise">
    <div class="container">
      <div class="expertise-card-grid">
        @foreach($c['cards'] ?? [] as $card)
          <article class="expertise-card reveal">
            <span class="expertise-card__icon expertise-card__icon--{{ $iconMods[$loop->index] ?? 'negotiation' }}" aria-hidden="true">
              @if(!empty($card['icon']))
                <img src="{{ media($card['icon']) }}" alt="{{ $card['icon_alt'] ?? '' }}" />
              @endif
            </span>
            <h2>{!! rich($card['title'] ?? '', true) !!}</h2>
            @if(!empty($card['lead']))
              {!! rich($card['lead'], false, 'expertise-card__lead') !!}
            @endif
            @if(!empty($card['body']))
              {!! rich($card['body']) !!}
            @endif
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endsection
