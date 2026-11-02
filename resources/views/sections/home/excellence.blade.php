@php
  $excellence = $c['excellence'] ?? [];
@endphp

<section class="dark-feature" data-cms-section="excellence">
  <div class="container">
    <article class="dark-feature__card">
      <div class="dark-feature__media">
        @if(!empty($excellence['image']))
          <img src="{{ media($excellence['image']) }}" alt="{{ $excellence['image_alt'] ?? '' }}" />
        @endif
      </div>
      <div class="dark-feature__content">
        <h2>{!! rich($excellence['title'] ?? '', true) !!}</h2>
        @foreach($excellence['paragraphs'] ?? [] as $p)
          {!! rich($p) !!}
        @endforeach
      </div>
    </article>
  </div>
</section>
