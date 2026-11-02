@php
  $pillars = $c['pillars'] ?? [];
  $pillarTitles = $pillars['titles'] ?? [];
@endphp

<section class="intro-card-section" data-cms-section="pillars">
  <div class="container">
    <article class="intro-card">
      <h2>
        @if(!empty($pillars['eyebrow']))
          <span class="intro-card__eyebrow">{!! rich($pillars['eyebrow'], true) !!}</span>
        @endif
        <strong class="intro-card__pillar-title">
          @foreach($pillarTitles as $title)
            @if(!$loop->last)
              <span class="intro-card__pillar-item">
                <span>{!! rich($title, true) !!}</span>
                <span class="intro-card__pillar-marker" aria-hidden="true"></span>
              </span>
            @else
              <span>{!! rich($title, true) !!}</span>
            @endif
          @endforeach
        </strong>
      </h2>
      @if(!empty($pillars['intro']))
        {!! rich($pillars['intro']) !!}
      @endif
      <ul class="accent-list">
        @foreach($pillars['items'] ?? [] as $item)
          <li><strong>{!! rich($item['title'] ?? '', true) !!}:</strong> {!! rich($item['body'] ?? '', true) !!}</li>
        @endforeach
      </ul>
    </article>
  </div>
</section>
