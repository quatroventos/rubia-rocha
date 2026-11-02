<section class="values-section" aria-labelledby="valores-title">
  <div class="container section-inner">
    <div data-cms-section="values">
      <h2 class="values-section__title reveal" id="valores-title">{!! rich($c['values_title'] ?? 'Valores', true) !!}</h2>

      <div class="value-grid">
        @foreach($c['values'] ?? [] as $value)
          <article class="value-card reveal">
            <span class="value-card__icon" aria-hidden="true">
              {!! \App\Support\SiteIcons::value($loop->index) !!}
            </span>
            <h3>{!! rich($value['title'] ?? '', true) !!}</h3>
            {!! rich($value['body'] ?? '') !!}
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>

<section class="leaders" aria-labelledby="lideranca-title">
  <div class="container section-inner">
    <h2 class="reveal" id="lideranca-title">{!! rich($c['leaders_title'] ?? 'Liderança Executiva', true) !!}</h2>
    <div class="leaders__grid">
      @foreach($team ?? [] as $member)
        <article class="leader-card reveal">
          <div class="leader-card__image">
            @if($member->photo_path)
              <img src="{{ media($member->photo_path) }}" alt="{{ $member->name }}" />
            @endif
          </div>
          <h3>{{ $member->name }}</h3>
          <p>
            @if($member->role)
              <strong>{{ $member->role }}</strong>
            @endif
          </p>
          {!! rich($member->bio) !!}
        </article>
      @endforeach
    </div>
  </div>
</section>
