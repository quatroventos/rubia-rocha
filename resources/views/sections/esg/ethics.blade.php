@php
  $ethics = $c['ethics'] ?? [];
@endphp

<section class="ethics" id="canal-denuncias" data-cms-section="ethics" aria-labelledby="ethics-title">
  <div class="container section-inner ethics__layout">
    <div class="ethics__content reveal reveal--left">
      <div class="eyebrow-bars" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
      </div>
      <h2 id="ethics-title">{!! rich($ethics['title'] ?? '', true) !!}</h2>
      @foreach($ethics['paragraphs'] ?? [] as $p)
        {!! rich($p, false, ($loop->last && $loop->count > 1) ? 'ethics__assurance' : null) !!}
      @endforeach
    </div>
    <div class="reveal reveal--right">
      <div class="ethics__media">
        @if(!empty($ethics['image']))
          <img src="{{ media($ethics['image']) }}" alt="{{ $ethics['image_alt'] ?? '' }}" />
        @endif
      </div>
      <div class="ethics__qr-card">
        <div class="ethics__qr-frame">
          @if(!empty($ethics['qr_image']))
            <img src="{{ media($ethics['qr_image']) }}" alt="{{ $ethics['qr_title'] ?? 'QR Code' }}" />
          @else
            <span class="ethics__qr-placeholder">QR Code do Canal de Denúncias</span>
          @endif
        </div>
        <div class="ethics__qr-copy">
          <p>
            <strong>{!! rich($ethics['qr_title'] ?? 'Acesse o canal', true) !!}</strong>
            {!! rich($ethics['qr_body'] ?? '', true) !!}
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
