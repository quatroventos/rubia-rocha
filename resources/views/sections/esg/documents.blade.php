@if(!empty($c['documents']['cards']))
  <section class="documents" id="documentos-esg" data-cms-section="documents" aria-labelledby="documents-title">
    <div class="container section-inner">
      <div class="section-heading reveal">
        <h2 id="documents-title">{!! rich($c['documents']['title'] ?? 'Documentos e canais institucionais', true) !!}</h2>
        @if(!empty($c['documents']['body']))
          {!! rich($c['documents']['body']) !!}
        @endif
      </div>
      <div class="documents__grid" id="politicas-institucionais">
        @foreach($c['documents']['cards'] as $doc)
          <article class="document-card {{ !empty($doc['channel']) ? 'document-card--channel' : '' }} reveal" @if(!empty($doc['id'])) id="{{ $doc['id'] }}" @endif>
            @if(!empty($doc['eyebrow']))
              <span class="document-card__eyebrow">{!! rich($doc['eyebrow'], true) !!}</span>
            @endif
            <h3>{!! rich($doc['title'] ?? '', true) !!}</h3>
            {!! rich($doc['body'] ?? '') !!}
            @if(!empty($doc['href']))
              <a class="document-card__link" href="{{ str_starts_with($doc['href'], '#') ? $doc['href'] : media($doc['href']) }}" @if(!str_starts_with($doc['href'] ?? '', '#')) target="_blank" rel="noopener" @endif>{{ $doc['link_label'] ?? 'Acessar documento' }}</a>
            @endif
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endif
