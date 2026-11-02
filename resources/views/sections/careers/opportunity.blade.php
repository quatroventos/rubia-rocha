@php
  $opportunity = $c['opportunity'] ?? [];
@endphp

<section class="career-form-section" data-cms-section="opportunity" aria-labelledby="application-title">
  <div class="container section-inner">
    <div class="career-opportunity-layout">
      <div class="career-opportunity-layout__intro">
        <div class="eyebrow-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <h1 id="application-title">{!! rich($opportunity['title'] ?? '', true) !!}</h1>
        @if(!empty($opportunity['lead']))
          <p class="career-opportunity-layout__lead">{!! rich($opportunity['lead'], true) !!}</p>
        @endif
      </div>

      <div class="career-opportunity-layout__details">
        @foreach($opportunity['paragraphs'] ?? [] as $p)
          {!! rich($p) !!}
        @endforeach
        @if(!empty($opportunity['cta_label']) && !empty($opportunity['cta_href']))
          <a
            class="button"
            href="{{ $opportunity['cta_href'] }}"
            target="_blank"
            rel="noopener"
          >{!! rich($opportunity['cta_label'], true) !!}</a>
        @endif
      </div>
    </div>
  </div>
</section>
