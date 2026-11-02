@php
  $strip = $c['strip'] ?? [];
@endphp

@if(!empty($strip['image']))
  <section class="about-strip" data-cms-section="strip" aria-label="Ambiente jurídico">
    <div class="container section-inner">
      <div class="about-strip__image reveal reveal--scale">
        <img src="{{ media($strip['image']) }}" alt="{{ $strip['alt'] ?? '' }}" />
      </div>
    </div>
  </section>
@endif
