@php
  $specialties = $c['specialties'] ?? [];
@endphp

<section class="specialties" data-cms-section="specialties" aria-labelledby="especializacao-title">
  <div class="container section-inner">
    <div class="section-heading">
      <h2 id="especializacao-title">{!! rich($specialties['title'] ?? '', true) !!}</h2>
      @if(!empty($specialties['subtitle']))
        {!! rich($specialties['subtitle']) !!}
      @endif
    </div>
    <div class="specialties__grid">
      @foreach($specialties['cards'] ?? [] as $card)
        <article class="specialty-card">
          <span class="card-icon" aria-hidden="true">
            {!! \App\Support\SiteIcons::specialty($loop->index) !!}
          </span>
          <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
          {!! rich($card['body'] ?? '') !!}
        </article>
      @endforeach
    </div>
    @if(!empty($specialties['note']))
      <p class="specialties__note">
        <span class="specialties__note-text">{!! rich($specialties['note'], true) !!}</span>
      </p>
    @endif
  </div>
</section>
