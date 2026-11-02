@php
  $culture = $c['culture'] ?? [];
@endphp

<section class="culture-section" data-cms-section="culture" aria-labelledby="cultura-title">
  <div class="container section-inner">
    <div class="culture-section__grid">
      <div class="culture-section__image reveal reveal--left">
        @if(!empty($culture['image']))
          <img src="{{ media($culture['image']) }}" alt="{{ $culture['image_alt'] ?? '' }}" />
        @endif
      </div>
      <div class="culture-section__content reveal reveal--right">
        <h2 id="cultura-title">{!! rich($culture['title'] ?? '', true) !!}</h2>
        @foreach($culture['paragraphs'] ?? [] as $p)
          {!! rich($p) !!}
        @endforeach
      </div>
    </div>
  </div>
</section>
