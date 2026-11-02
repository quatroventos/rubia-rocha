@php
  $technology = $c['technology'] ?? [];
@endphp

<section class="technology" data-cms-section="technology" aria-labelledby="technology-title">
  <div class="container section-inner">
    <div class="section-heading">
      <h2 id="technology-title">{!! rich($technology['title'] ?? '', true) !!}</h2>
      @if(!empty($technology['body']))
        {!! rich($technology['body']) !!}
      @endif
    </div>
    <div class="technology__layout">
      <div class="technology__mini-grid">
        @foreach($technology['mini_cards'] ?? [] as $card)
          <article class="tech-card">
            <span class="card-icon" aria-hidden="true">
              {!! \App\Support\SiteIcons::techMini($loop->index) !!}
            </span>
            <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
            {!! rich($card['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
      <div class="technology__image">
        @if(!empty($technology['image']))
          <img src="{{ media($technology['image']) }}" alt="{{ $technology['image_alt'] ?? '' }}" />
        @endif
      </div>
    </div>
    <div class="technology__wide-grid">
      @foreach($technology['wide_cards'] ?? [] as $card)
        <article class="tech-card tech-card--wide {{ $loop->first ? 'tech-card--dark' : 'tech-card--accent' }}">
          <span class="card-icon" aria-hidden="true">
            {!! \App\Support\SiteIcons::techWide($loop->index) !!}
          </span>
          <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
          {!! rich($card['body'] ?? '') !!}
        </article>
      @endforeach
    </div>
  </div>
</section>
