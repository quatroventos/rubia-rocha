@php
  $name = $field['name'];
  $id = 'field-'.$name;
  $type = $field['type'] ?? 'text';
  $required = (bool) ($field['required'] ?? false);
@endphp

@if($type === 'checkbox')
  <label class="checkbox-field" for="{{ $id }}">
    <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="1" @checked((bool) old($name)) @required($required) />
    <span>{!! rich($name === 'lgpd' ? ($lgpdLabel ?? $field['label']) : $field['label'], true) !!}</span>
  </label>
@else
  <div class="form-field">
    <label for="{{ $id }}">{{ $field['label'] }}</label>
    @if($type === 'textarea')
      <textarea id="{{ $id }}" name="{{ $name }}" @required($required)>{{ old($name) }}</textarea>
    @elseif($type === 'select')
      <select id="{{ $id }}" name="{{ $name }}" @required($required)>
        <option value="">Selecione</option>
        @foreach($field['options'] ?? [] as $option)
          @php
            $optionValue = is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option->value;
            $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option->label;
          @endphp
          <option value="{{ $optionValue }}" @selected((string) old($name) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
      </select>
    @elseif($type === 'file')
      <input id="{{ $id }}" name="{{ $name }}" type="file" accept=".pdf,.doc,.docx" @required($required) />
      @if(!empty($field['hint']))
        <small>{{ $field['hint'] }}</small>
      @endif
    @else
      <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name) }}"
        @required($required)
      />
    @endif
  </div>
@endif
