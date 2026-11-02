@php
  $hero = $c['hero'] ?? [];
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
