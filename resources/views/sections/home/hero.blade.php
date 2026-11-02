@php
  $hero = $c['hero'] ?? [];
@endphp

<section class="hero" data-cms-section="hero" aria-label="{{ $site->name ?? 'Quatro Ventos' }}">
  <div class="hero__word-cloud" aria-hidden="true">
    @foreach($hero['word_cloud'] ?? [] as $i => $word)
      @php
        $size = $word['size'] ?? 'medium';
        $accent = !empty($word['accent']) ? ' word-cloud__word--accent' : '';
        $duration = 12 + ($i % 5);
        $delay = -(($i % 10) + 1);
        $driftX = (($i % 2) ? -1 : 1) * (8 + ($i % 6));
        $driftY = (($i % 3) === 0 ? -1 : 1) * (7 + ($i % 5));
        $rotate = (($i % 2) ? -1 : 1) * (1 + ($i % 2));
      @endphp
      <span
        class="word-cloud__word word-cloud__word--{{ $size }}{{ $accent }}"
        style="--word-x: {{ $word['x'] ?? '50%' }}; --word-y: {{ $word['y'] ?? '50%' }}; --word-duration: {{ $duration }}s; --word-delay: {{ $delay }}s; --word-drift-x: {{ $driftX }}px; --word-drift-y: {{ $driftY }}px; --word-rotate: {{ $rotate }}deg"
      >{!! rich($word['text'] ?? '', true) !!}</span>
    @endforeach
  </div>
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <h1>
      @foreach($hero['title_lines'] ?? [] as $line)
        <span class="hero-title__line">
          {!! rich($line, true) !!}
          @if(!$loop->last)
            <span class="hero-title__marker">◾</span>
          @endif
        </span>
      @endforeach
    </h1>
    @if(!empty($hero['subtitle']))
      {!! rich($hero['subtitle']) !!}
    @endif
    @if(!empty($hero['cta_label']))
      <a class="button button--outline" href="{{ $hero['cta_href'] ?? '#solutions' }}">{!! rich($hero['cta_label'], true) !!}</a>
    @endif
  </div>
</section>
