@php
  $purpose = $c['purpose'] ?? [];
@endphp

<section class="purpose" data-cms-section="purpose" aria-labelledby="purpose-title">
  <div class="container section-inner">
    <div class="purpose__heading reveal reveal--left">
      <h2 id="purpose-title">{!! rich($purpose['title'] ?? '', true) !!}</h2>
      @if(!empty($purpose['body']))
        {!! rich($purpose['body']) !!}
      @endif
    </div>
    <div class="purpose__body">
      <div class="purpose-list reveal reveal--left">
        @foreach($purpose['cards'] ?? [] as $card)
          <article class="purpose-card">
            <span class="icon-box" aria-hidden="true">
              {!! \App\Support\SiteIcons::purpose($loop->index) !!}
            </span>
            <div>
              <h3>{!! rich($card['title'] ?? '', true) !!}</h3>
              {!! rich($card['body'] ?? '') !!}
            </div>
          </article>
        @endforeach
      </div>
      <div class="image-frame reveal reveal--right">
        @if(!empty($purpose['image']))
          <img src="{{ media($purpose['image']) }}" alt="{{ $purpose['image_alt'] ?? '' }}" />
        @endif
      </div>
    </div>
  </div>
</section>
