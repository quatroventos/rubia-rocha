@php
  $goals = $c['goals'] ?? [];
@endphp

<section class="goals" data-cms-section="goals" aria-labelledby="goals-title">
  <div class="container section-inner goals__layout">
    <div class="section-heading reveal reveal--right">
      <h2 id="goals-title">{!! rich($goals['title'] ?? '', true) !!}</h2>
      @if(!empty($goals['body']))
        {!! rich($goals['body']) !!}
      @endif
    </div>
    <div class="image-frame goals__visual reveal reveal--left">
      @if(!empty($goals['image']))
        <img src="{{ media($goals['image']) }}" alt="{{ $goals['image_alt'] ?? '' }}" />
      @endif
    </div>
    <div class="goals-list reveal reveal--right">
      @foreach($goals['items'] ?? [] as $item)
        <article class="goal-item">
          <span class="goal-item__number" aria-hidden="true">{{ $item['number'] ?? str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
          <div>
            <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
            {!! rich($item['body'] ?? '') !!}
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>
