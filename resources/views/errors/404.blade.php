@php($notFoundFile = storage_path('app/prerender/'.\App\Console\Commands\PrerenderSite::NOT_FOUND_FILE))
@include('layouts.error', [
  'title' => 'Página não encontrada',
  'kicker' => 'Erro 404',
  'heading' => 'Essa página não existe mais.',
  'message' => 'O endereço mudou ou foi removido. Os ensaios e eventos continuam a partir do início.',
  'fragment' => is_file($notFoundFile) ? file_get_contents($notFoundFile) : null,
])
