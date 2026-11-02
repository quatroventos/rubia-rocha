@php
  $culture = $c['culture'] ?? [];
@endphp

<section class="culture" data-cms-section="culture" aria-labelledby="culture-title">
  <div class="container section-inner">
    <div class="section-heading">
      <h2 id="culture-title">{!! rich($culture['title'] ?? '', true) !!}</h2>
      @foreach($culture['paragraphs'] ?? [] as $p)
        {!! rich($p) !!}
      @endforeach
    </div>
    <div class="culture__body">
      <div class="culture__image">
        @if(!empty($culture['image']))
          <img src="{{ media($culture['image']) }}" alt="{{ $culture['image_alt'] ?? '' }}" />
        @endif
      </div>
      <div class="culture-list">
        @foreach($culture['items'] ?? [] as $item)
          <article class="accent-bullet">
            <div>
              <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
              {!! rich($item['body'] ?? '') !!}
            </div>
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>
