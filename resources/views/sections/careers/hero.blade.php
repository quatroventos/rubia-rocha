@php
  $hero = $c['hero'] ?? [];
@endphp

<section class="career-hero" data-cms-section="hero" aria-label="{{ $hero['title'] ?? 'Trabalhe Conosco' }}">
  @if(!empty($hero['image']))
    <img src="{{ media($hero['image']) }}" alt="{{ $hero['image_alt'] ?? '' }}" />
  @endif
</section>
