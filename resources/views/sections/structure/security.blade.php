@php
  $security = $c['security'] ?? [];
  $secTitle = $security['title'] ?? '';
  $secParts = str_contains($secTitle, ' e ') ? explode(' e ', $secTitle, 2) : [$secTitle, null];
@endphp

<section class="security" data-cms-section="security" aria-labelledby="security-title">
  <div class="container section-inner">
    <div class="section-heading">
      <h2 id="security-title">
        @if(is_string($secTitle) && str_contains($secTitle, '<'))
          {!! rich($secTitle, true) !!}
        @else
          {!! rich($secParts[0] ?? '', true) !!}
          @if($secParts[1])
            <span class="security-title-break">e {!! rich($secParts[1], true) !!}</span>
          @endif
        @endif
      </h2>
      @if(!empty($security['body']))
        {!! rich($security['body']) !!}
      @endif
    </div>
    <div class="security__grid">
      @if(!empty($security['statement']))
        {!! rich($security['statement'], false, 'security__statement') !!}
      @endif
      <div class="security-list">
        @foreach($security['items'] ?? [] as $item)
          <article class="accent-bullet">
            <div>
              <h3>{!! rich($item['title'] ?? '', true) !!}</h3>
              {!! rich($item['body'] ?? '') !!}
            </div>
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>
