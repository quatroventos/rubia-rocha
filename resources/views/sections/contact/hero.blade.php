@php
  $hero = $c['hero'] ?? [];
@endphp

<section class="career-hero contact-hero" data-cms-section="hero" aria-labelledby="contact-title">
  <div class="container section-inner career-hero__grid">
    <div class="career-hero__copy reveal reveal--left">
      <div class="eyebrow-bars" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>
      <div class="section-heading">
        <h1 id="contact-title">{!! rich($hero['title'] ?? 'Contato', true) !!}</h1>
        @if(!empty($hero['body']))
          {!! rich($hero['body']) !!}
        @endif
      </div>
    </div>
  </div>
</section>
