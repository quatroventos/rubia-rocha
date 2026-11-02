@php
  $hero = $c['hero'] ?? [];
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
