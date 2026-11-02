@php
  $strip = $c['strip'] ?? [];
@endphp

@if(!empty($strip['image']))
  <section class="image-strip" data-cms-section="strip" aria-label="Arquitetura jurídica">
    <img src="{{ media($strip['image']) }}" alt="{{ $strip['alt'] ?? '' }}" />
  </section>
@endif
