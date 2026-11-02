@php
  $pillars = $c['pillars'] ?? [];
@endphp

<section class="pillars" data-cms-section="pillars" aria-labelledby="pillars-title">
  <div class="container section-inner">
    <div class="section-heading reveal">
      <h2 id="pillars-title">{!! rich($pillars['title'] ?? '', true) !!}</h2>
      @if(!empty($pillars['subtitle']))
        {!! rich($pillars['subtitle']) !!}
      @endif
    </div>
    <div class="pillars__grid">
      @foreach($pillars['cards'] ?? [] as $card)
        <article class="pillar-card reveal">
          <span class="pillar-card__label" aria-hidden="true">{{ $card['number'] ?? str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
          <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
          {!! rich($card['body'] ?? '') !!}
          @if(!empty($card['list']))
            <ul>
              @foreach($card['list'] as $item)
                <li>{!! rich($item, true) !!}</li>
              @endforeach
            </ul>
          @endif
        </article>
      @endforeach
    </div>
  </div>
</section>
