@php
  $hero = $c['hero'] ?? [];
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
