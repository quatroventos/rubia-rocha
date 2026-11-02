@php
  $cta = $c['cta'] ?? [];
  $ctaBits = preg_split('/\.\s*/', trim($cta['highlight'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
@endphp

<section class="final-cta" data-cms-section="cta">
  <div class="container final-cta__inner">
    <h2>
      {!! rich($cta['title'] ?? '', true) !!}<br />
      <strong>
        @foreach($ctaBits as $bit)
          <span class="final-cta__nowrap">{!! rich(rtrim($bit, '.'), true) !!}.</span>
        @endforeach
      </strong>
    </h2>
    @if(!empty($cta['button_label']))
      <a class="button button--outline" href="{{ url($cta['button_href'] ?? '/contato') }}">{!! rich($cta['button_label'], true) !!}</a>
    @endif
  </div>
</section>
