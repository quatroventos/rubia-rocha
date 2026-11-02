@php
  $hero = $c['hero'] ?? [];
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
