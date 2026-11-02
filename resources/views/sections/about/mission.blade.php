@php
  $mission = $c['mission'] ?? [];
  $vision = $c['vision'] ?? [];
@endphp

<section class="values-section" aria-label="Missão e visão">
  <div class="container section-inner">
    <div class="mission-grid" data-cms-section="mission">
      <article class="mission-card reveal reveal--left">
        <div>
          <h2>{!! rich($mission['title'] ?? 'Missão', true) !!}</h2>
          {!! rich($mission['body'] ?? '') !!}
        </div>
      </article>
      <article class="mission-card reveal reveal--right">
        <div>
          <h2>{!! rich($vision['title'] ?? 'Visão', true) !!}</h2>
          {!! rich($vision['body'] ?? '') !!}
        </div>
      </article>
    </div>
  </div>
</section>
