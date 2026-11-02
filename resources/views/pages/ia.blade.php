@extends('layouts.site')

@section('content')
  @php
    $hero = $c['hero'] ?? [];
    $purpose = $c['purpose'] ?? [];
    $principles = $c['principles'] ?? [];
    $goals = $c['goals'] ?? [];
  @endphp

  <section class="ai-hero" data-cms-section="hero" aria-labelledby="ia-title">
    <div class="ai-hero__media" aria-hidden="true">
      @if(!empty($hero['image']))
        <img src="{{ media($hero['image']) }}" alt="{{ $hero['image_alt'] ?? '' }}" />
      @endif
    </div>
    <div class="container ai-hero__content">
      <div class="ai-hero__title reveal reveal--left">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h1 id="ia-title">{!! rich($hero['title'] ?? '', true) !!}</h1>
      </div>
      {!! rich($hero['body'] ?? '', false, 'ai-hero__copy reveal reveal--right') !!}
    </div>
  </section>

  <section class="purpose" data-cms-section="purpose" aria-labelledby="purpose-title">
    <div class="container section-inner">
      <div class="purpose__heading reveal reveal--left">
        <h2 id="purpose-title">{!! rich($purpose['title'] ?? '', true) !!}</h2>
        @if(!empty($purpose['body']))
          {!! rich($purpose['body']) !!}
        @endif
      </div>
      <div class="purpose__body">
        <div class="purpose-list reveal reveal--left">
          @foreach($purpose['cards'] ?? [] as $card)
            <article class="purpose-card">
              <span class="icon-box" aria-hidden="true">
                {!! \App\Support\SiteIcons::purpose($loop->index) !!}
              </span>
              <div>
                <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
                {!! rich($card['body'] ?? '') !!}
              </div>
            </article>
          @endforeach
        </div>
        <div class="image-frame reveal reveal--right">
          @if(!empty($purpose['image']))
            <img src="{{ media($purpose['image']) }}" alt="{{ $purpose['image_alt'] ?? '' }}" />
          @endif
        </div>
      </div>
    </div>
  </section>

  <section class="principles" data-cms-section="principles" aria-labelledby="principles-title">
    <div class="container section-inner principles__grid">
      <div class="principles__statement reveal reveal--left">
        <div class="eyebrow-bars principles__eyebrow" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h2 id="principles-title">{!! rich($principles['title'] ?? '', true) !!}</h2>
      </div>
      <div class="principles__list reveal reveal--right">
        @foreach($principles['items'] ?? [] as $item)
          <article class="principle-item">
            <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
            {!! rich($item['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <section class="goals" data-cms-section="goals" aria-labelledby="goals-title">
    <div class="container section-inner goals__layout">
      <div class="section-heading reveal reveal--right">
        <h2 id="goals-title">{!! rich($goals['title'] ?? '', true) !!}</h2>
        @if(!empty($goals['body']))
          {!! rich($goals['body']) !!}
        @endif
      </div>
      <div class="image-frame goals__visual reveal reveal--left">
        @if(!empty($goals['image']))
          <img src="{{ media($goals['image']) }}" alt="{{ $goals['image_alt'] ?? '' }}" />
        @endif
      </div>
      <div class="goals-list reveal reveal--right">
        @foreach($goals['items'] ?? [] as $item)
          <article class="goal-item">
            <span class="goal-item__number" aria-hidden="true">{{ $item['number'] ?? str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
              <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
              {!! rich($item['body'] ?? '') !!}
            </div>
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endsection
