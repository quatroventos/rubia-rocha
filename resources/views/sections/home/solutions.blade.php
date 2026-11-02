@php
  $solutions = $c['solutions'] ?? [];
@endphp

<section class="solutions" id="solutions" data-cms-section="solutions" aria-labelledby="solutions-title">
  <div class="container">
    <div class="solutions__heading">
      <h2 id="solutions-title">{!! rich($solutions['title'] ?? 'Soluções', true) !!}</h2>
      @if(!empty($solutions['subtitle']))
        {!! rich($solutions['subtitle']) !!}
      @endif
    </div>
    <div class="solutions__grid">
      <div class="solutions__visual">
        @if(!empty($solutions['image']))
          <img src="{{ media($solutions['image']) }}" alt="{{ $solutions['image_alt'] ?? '' }}" />
        @endif
      </div>
      <div class="solutions__cards" aria-label="Soluções">
        @foreach($solutions['cards'] ?? [] as $card)
          <article class="solution-card">
            <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
            {!! rich($card['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>
