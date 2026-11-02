@php
  $hero = $c['hero'] ?? [];
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
