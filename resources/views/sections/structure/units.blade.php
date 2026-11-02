@php
  $units = $c['units'] ?? [];
@endphp

<section class="office-locations" id="filiais" data-cms-section="units">
  <div class="container office-locations__inner">
    <div class="section-heading">
      <h2>{!! rich($units['title'] ?? 'Nossas unidades', true) !!}</h2>
      @if(!empty($units['subtitle']))
        {!! rich($units['subtitle']) !!}
      @endif
    </div>

    <div class="office-locations__dropdown" data-branches aria-label="Endereços das unidades">
      <div class="office-locations__select-field">
        <label for="branch-selector">{!! rich($units['select_label'] ?? 'Selecione a unidade', true) !!}</label>
        <select class="office-locations__select" id="branch-selector" data-branch-select>
          @foreach($branches ?? [] as $branch)
            <option value="branch-{{ $branch->slug }}" @selected($loop->first)>{{ $branch->select_label }}</option>
          @endforeach
        </select>
      </div>
      <div class="office-locations__panel">
        @foreach($branches ?? [] as $branch)
          <article
            class="branch-panel{{ $loop->first ? ' is-active' : '' }}"
            id="branch-{{ $branch->slug }}"
            role="tabpanel"
            aria-labelledby="branch-selector"
            @if(!$loop->first) hidden @endif
          >
            <span class="branch-panel__eyebrow">{{ $branch->eyebrow }}</span>
            <div class="branch-panel__grid">
              <div>
                <h3>{{ $branch->city }} - {{ $branch->state }}</h3>
                <address>{!! nl2br(e($branch->address)) !!}</address>
              </div>
            </div>
          </article>
        @endforeach
      </div>
    </div>

    <div class="office-locations__mobile" aria-label="Endereços das unidades">
      @foreach($branches ?? [] as $branch)
        <details class="office-location" @if($loop->first) open @endif>
          <summary>{{ $branch->select_label }}</summary>
          <div class="office-location__body">
            <span class="office-location__eyebrow">{{ $branch->eyebrow }}</span>
            <h3>{{ $branch->city }} - {{ $branch->state }}</h3>
            <address>{!! nl2br(e($branch->address)) !!}</address>
          </div>
        </details>
      @endforeach
    </div>
  </div>
</section>
