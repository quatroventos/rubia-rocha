@php
  $iconMods = ['negotiation', 'legal', 'specialized'];
@endphp

<section class="expertise-cards" data-cms-section="cards" aria-label="Áreas de expertise">
  <div class="container">
    <div class="expertise-card-grid">
      @foreach($c['cards'] ?? [] as $card)
        <article class="expertise-card reveal">
          <span class="expertise-card__icon expertise-card__icon--{{ $iconMods[$loop->index] ?? 'negotiation' }}" aria-hidden="true">
            @if(!empty($card['icon']))
              <img src="{{ media($card['icon']) }}" alt="{{ $card['icon_alt'] ?? '' }}" />
            @endif
          </span>
          <h2>{!! rich($card['title'] ?? '', true) !!}</h2>
          @if(!empty($card['lead']))
            {!! rich($card['lead'], false, 'expertise-card__lead') !!}
          @endif
          @if(!empty($card['body']))
            {!! rich($card['body']) !!}
          @endif
        </article>
      @endforeach
    </div>
  </div>
</section>
