@php
  $map = $c['map'] ?? [];
@endphp

<section class="map-section" data-cms-section="map" aria-labelledby="map-title">
  <div class="container section-inner map-card">
    <div class="section-heading map-heading reveal">
      <h2 id="map-title">{!! rich($map['title'] ?? 'Onde estamos', true) !!}</h2>
      @if(!empty($map['body']))
        {!! rich($map['body']) !!}
      @endif
    </div>
    @if(!empty($site?->map_embed_url))
      <div class="map-frame reveal">
        <iframe
          src="{{ $site->map_embed_url }}"
          width="600"
          height="450"
          style="border: 0"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Mapa do {{ $site->name }}"
        ></iframe>
      </div>
    @endif
  </div>
</section>
