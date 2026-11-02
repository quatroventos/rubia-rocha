@php
  $coverage = $c['coverage'] ?? [];
@endphp

<section class="national-coverage" data-cms-section="coverage">
  <div class="container national-coverage__grid">
    @if(!empty($coverage['image']))
      <img class="national-coverage__map" src="{{ media($coverage['image']) }}" alt="{{ $coverage['image_alt'] ?? '' }}" />
    @endif
    <div class="national-coverage__content">
      <h2>{!! rich($coverage['title'] ?? '', true) !!}</h2>
      <div class="national-coverage__copy">
        @foreach($coverage['paragraphs'] ?? [] as $p)
          {!! rich($p) !!}
        @endforeach
      </div>
      <div class="national-coverage__card">
        <ul class="accent-list accent-list--compact">
          @foreach($coverage['items'] ?? [] as $item)
            <li>{!! rich($item, true) !!}</li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</section>
