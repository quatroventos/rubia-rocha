@php
  $principles = $c['principles'] ?? [];
@endphp

<section class="principles" data-cms-section="principles" aria-labelledby="principles-title">
  <div class="container section-inner principles__grid">
    <div class="principles__statement reveal reveal--left">
      <div class="eyebrow-bars principles__eyebrow" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
      </div>
      <h2 id="principles-title">{!! rich($principles['title'] ?? '', true) !!}</h2>
    </div>
    <div class="principles__list reveal reveal--right">
      @foreach($principles['items'] ?? [] as $item)
        <article class="principle-item">
          <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
          {!! rich($item['body'] ?? '') !!}
        </article>
      @endforeach
    </div>
  </div>
</section>
