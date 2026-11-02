@php
  $form = $c['form'] ?? [];
@endphp

<section class="career-form-section" aria-labelledby="contact-form-title">
  <div class="container section-inner career-form-grid">
    <div class="career-form-card reveal reveal--right" data-cms-section="form">
      <h2 id="contact-form-title">{!! rich($form['title'] ?? '', true) !!}</h2>
      @if(!empty($form['body']))
        {!! rich($form['body']) !!}
      @endif

      @if(isset($errors) && $errors->any())
        <p class="form-feedback form-feedback--error" id="form-feedback" role="alert">
          Não foi possível enviar. Revise os campos obrigatórios e tente novamente.
        </p>
      @else
        <p class="form-feedback" id="form-feedback" role="status" hidden></p>
      @endif

      <form class="application-form" action="{{ route('contato.store') }}" method="post">
        @csrf
        <div class="form-honeypot" aria-hidden="true">
          <label for="empresa_site">Empresa</label>
          <input id="empresa_site" name="empresa_site" type="text" tabindex="-1" autocomplete="off" />
        </div>
        @include('partials.site-form-fields', [
          'formSchema' => $formSchema ?? [],
          'lgpdLabel' => $formSchema['lgpd_label'] ?? $form['lgpd_label'] ?? 'Autorizo o armazenamento dos dados informados para fins de contato e retorno, conforme a legislação aplicável de proteção de dados.',
        ])
        <button class="button" type="submit">{!! rich($formSchema['submit_label'] ?? $form['submit_label'] ?? 'Enviar', true) !!}</button>
      </form>
    </div>
  </div>
</section>
