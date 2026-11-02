@php
  $conclusion = $c['conclusion'] ?? [];
@endphp

<section class="conclusion" data-cms-section="conclusion" aria-labelledby="conclusion-title">
  <div class="container conclusion__inner reveal reveal--scale">
    <div class="eyebrow-bars" aria-hidden="true">
      <span></span><span></span><span></span><span></span>
    </div>
    <h2 id="conclusion-title">{!! rich($conclusion['title'] ?? '', true) !!}</h2>
    @if(!empty($conclusion['body']))
      {!! rich($conclusion['body']) !!}
    @endif
  </div>
</section>
