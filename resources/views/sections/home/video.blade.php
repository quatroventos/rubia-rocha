@php
  $video = $c['video'] ?? [];
@endphp

@if(!empty($video['src']))
  <section class="portfolio-video" data-cms-section="video" aria-labelledby="portfolio-video-title">
    <div class="container portfolio-video__grid">
      <div class="portfolio-video__copy">
        <h2 id="portfolio-video-title">{!! rich($video['title'] ?? '', true) !!}</h2>
        @if(!empty($video['body']))
          {!! rich($video['body']) !!}
        @endif
      </div>
      <div class="portfolio-video__frame" aria-label="Vídeo de portfólio do escritório">
        <video controls preload="metadata">
          <source src="{{ media($video['src']) }}" />
        </video>
      </div>
    </div>
  </section>
@endif
