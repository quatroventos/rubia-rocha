@php
  $management = $c['management'] ?? [];
@endphp

<section class="management-section" data-cms-section="management">
  <div class="container management-section__grid">
    <div class="management-section__content">
      <h2>{!! rich($management['title'] ?? '', true) !!}</h2>
      @foreach($management['paragraphs'] ?? [] as $p)
        {!! rich($p) !!}
      @endforeach
      <span class="accent-block" aria-hidden="true"></span>
    </div>
    @if(!empty($management['image']))
      <img class="management-section__image" src="{{ media($management['image']) }}" alt="{{ $management['image_alt'] ?? '' }}" />
    @endif
  </div>
</section>
