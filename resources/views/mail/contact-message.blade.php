Nova mensagem recebida pelo formulário do site.

{{ $senderName }}@if($senderEmail !== '') <{{ $senderEmail }}>@endif
@foreach($fields as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
