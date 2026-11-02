@php
  $wide = fn (array $field): bool => \App\Cms\FormDefinition::isWide($field);
@endphp

@foreach(($formSchema['sections'] ?? []) as $section)
  <fieldset class="form-section">
    <legend>{{ $section['label'] }}</legend>
    @php $pending = []; @endphp
    @foreach(($section['fields'] ?? []) as $field)
      @if($wide($field))
        @if($pending !== [])
          <div class="form-row">
            @foreach($pending as $item)
              @include('partials.site-form-field', ['field' => $item, 'lgpdLabel' => $lgpdLabel])
            @endforeach
          </div>
          @php $pending = []; @endphp
        @endif
        @include('partials.site-form-field', ['field' => $field, 'lgpdLabel' => $lgpdLabel])
      @else
        @php $pending[] = $field; @endphp
        @if(count($pending) === 2)
          <div class="form-row">
            @foreach($pending as $item)
              @include('partials.site-form-field', ['field' => $item, 'lgpdLabel' => $lgpdLabel])
            @endforeach
          </div>
          @php $pending = []; @endphp
        @endif
      @endif
    @endforeach
    @if($pending !== [])
      <div class="form-row">
        @foreach($pending as $item)
          @include('partials.site-form-field', ['field' => $item, 'lgpdLabel' => $lgpdLabel])
        @endforeach
      </div>
    @endif
  </fieldset>
@endforeach
