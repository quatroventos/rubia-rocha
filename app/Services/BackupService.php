<?php

namespace App\Services;

use App\Jobs\PrerenderSiteJob;
use App\Models\Backup;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class BackupService
{
    public const DISK = 'local';

    public const DIRECTORY = 'backups';

    /**
     * @var list<string>
     */
    private const SKIP_TABLES = [
        'backups',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'migrations',
    ];

    /**
     * @return array<string, mixed>
     */
    public function panel(bool $trashed = false): array
    {
        $query = $trashed ? Backup::onlyTrashed() : Backup::query();

        return [
            ...$this->settings(),
            'trashed' => $trashed,
            'items' => $query
                ->with('user:id,name')
                ->latest('id')
                ->get()
                ->map(fn (Backup $backup) => $this->summary($backup))
                ->all(),
        ];
    }

    /**
     * @return array{hour: int, retention_days: int, next_at: string, last_at: string|null}
     */
    public function settings(): array
    {
        $hour = $this->hour();
        $next = now()->copy()->setTime($hour, 0, 0);

        if ($next->lte(now())) {
            $next->addDay();
        }

        $last = Backup::query()
            ->where('status', 'ok')
            ->latest('id')
            ->value('created_at');

        return [
            'hour' => $hour,
            'retention_days' => $this->retentionDays(),
            'next_at' => $next->toIso8601String(),
            'last_at' => $last instanceof Carbon ? $last->toIso8601String() : ($last ? Carbon::parse($last)->toIso8601String() : null),
        ];
    }

    public function updateSettings(int $hour, int $retentionDays): array
    {
        $site = SiteSetting::query()->firstOrFail();
        $site->update([
            'backup_hour' => $hour,
            'backup_retention_days' => $retentionDays,
        ]);

        return $this->panel();
    }

    public function run(?User $user, string $trigger = 'manual'): Backup
    {
        $backup = Backup::query()->create([
            'filename' => '',
            'size' => 0,
            'status' => 'failed',
            'trigger' => $trigger,
            'user_id' => $user?->id,
        ]);

        $temp = tempnam(sys_get_temp_dir(), 'cms-backup-');

        if ($temp === false) {
            $backup->update(['error' => 'Não foi possível criar arquivo temporário.']);

            return $backup->fresh() ?? $backup;
        }

        try {
            $filename = self::DIRECTORY.'/rubiarocha-'.now()->format('Ymd-His').'-'.$backup->id.'.zip';
            $this->writeArchive($temp);
            Storage::disk(self::DISK)->put($filename, (string) file_get_contents($temp));

            $backup->update([
                'filename' => $filename,
                'size' => Storage::disk(self::DISK)->size($filename),
                'status' => 'ok',
                'error' => null,
            ]);

            $this->prune();
        } catch (Throwable $exception) {
            $backup->update(['error' => $exception->getMessage()]);
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }

        return $backup->fresh() ?? $backup;
    }

    /**
     * Registra um zip gerado por outra instalação (ex.: o ambiente local) para restaurar depois.
     *
     * @return array<string, mixed>
     */
    public function import(UploadedFile $file, ?User $user): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('Arquivo zip inválido.');
        }

        $valid = $zip->locateName('database.json') !== false && $zip->locateName('manifest.json') !== false;
        $zip->close();

        if (! $valid) {
            throw new RuntimeException('O zip não é um backup do CMS (faltam manifest.json e database.json).');
        }

        $backup = Backup::query()->create([
            'filename' => '',
            'size' => 0,
            'status' => 'failed',
            'trigger' => 'upload',
            'user_id' => $user?->id,
        ]);

        $filename = Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, 'rubiarocha-import-'.now()->format('Ymd-His').'-'.$backup->id.'.zip');

        if ($filename === false) {
            $backup->update(['error' => 'Não foi possível gravar o arquivo enviado.']);

            throw new RuntimeException('Não foi possível gravar o arquivo enviado.');
        }

        $backup->update([
            'filename' => $filename,
            'size' => Storage::disk(self::DISK)->size($filename),
            'status' => 'ok',
            'error' => null,
        ]);

        return $this->panel();
    }

    public function download(Backup $backup): StreamedResponse
    {
        if ($backup->status !== 'ok' || $backup->filename === '' || ! Storage::disk(self::DISK)->exists($backup->filename)) {
            abort(404);
        }

        return Storage::disk(self::DISK)->download($backup->filename, basename($backup->filename));
    }

    public function restore(Backup $backup): array
    {
        if ($backup->status !== 'ok' || $backup->filename === '' || ! Storage::disk(self::DISK)->exists($backup->filename)) {
            abort(404, 'Backup indisponível.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'cms-restore-');

        if ($temp === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário.');
        }

        $extract = $temp.'_dir';

        try {
            file_put_contents($temp, Storage::disk(self::DISK)->get($backup->filename));

            $zip = new ZipArchive;

            if ($zip->open($temp) !== true) {
                throw new RuntimeException('Arquivo de backup inválido.');
            }

            mkdir($extract, 0700, true);
            $zip->extractTo($extract);
            $zip->close();

            $databasePath = $extract.'/database.json';

            if (! is_file($databasePath)) {
                throw new RuntimeException('Dump do banco não encontrado no backup.');
            }

            $payload = json_decode((string) file_get_contents($databasePath), true, 512, JSON_THROW_ON_ERROR);
            $tables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];

            $this->restoreTables($tables);
            $this->restoreFiles($extract.'/files');
            app(SystemOptions::class)->flush();
            PrerenderSiteJob::schedule();
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }

            $this->deleteDirectory($extract);
        }

        return $this->panel();
    }

    public function delete(Backup $backup): array
    {
        $backup->delete();

        return $this->panel();
    }

    public function recover(Backup $backup): array
    {
        $backup->restore();

        return $this->panel();
    }

    public function prune(): void
    {
        $cutoff = now()->subDays($this->retentionDays());

        Backup::withTrashed()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->get()
            ->each(function (Backup $backup): void {
                if ($backup->filename !== '' && Storage::disk(self::DISK)->exists($backup->filename)) {
                    Storage::disk(self::DISK)->delete($backup->filename);
                }

                $backup->forceDelete();
            });
    }

    public function hour(): int
    {
        $hour = null;

        try {
            if (Schema::hasTable('site_settings')) {
                $hour = SiteSetting::query()->value('backup_hour');
            }
        } catch (Throwable) {
            $hour = null;
        }

        if ($hour === null) {
            $hour = config('cms.backup.hour', 3);
        }

        return max(0, min(23, (int) $hour));
    }

    public function retentionDays(): int
    {
        $days = null;

        try {
            if (Schema::hasTable('site_settings')) {
                $days = SiteSetting::query()->value('backup_retention_days');
            }
        } catch (Throwable) {
            $days = null;
        }

        if ($days === null) {
            $days = config('cms.backup.retention_days', 7);
        }

        return max(1, min(90, (int) $days));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'filename' => $backup->filename !== '' ? basename($backup->filename) : null,
            'size' => $backup->size,
            'status' => $backup->status,
            'trigger' => $backup->trigger,
            'user_name' => $backup->user?->name,
            'error' => $backup->error,
            'created_at' => $backup->created_at?->toIso8601String(),
        ];
    }

    private function writeArchive(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo zip.');
        }

        $tables = [];

        foreach ($this->dumpableTables() as $table) {
            $tables[$table] = DB::table($table)->get()->map(fn ($row) => $this->serializeRow((array) $row))->all();
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'tables' => array_keys($tables),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $zip->addFromString('database.json', json_encode([
            'tables' => $tables,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        foreach (Storage::disk('public')->allFiles() as $file) {
            $contents = Storage::disk('public')->get($file);
            $zip->addFromString('files/public/'.$file, $contents ?? '');
        }

        foreach (Storage::disk('local')->allFiles('curriculos') as $file) {
            $contents = Storage::disk('local')->get($file);
            $zip->addFromString('files/private/'.$file, $contents ?? '');
        }

        $zip->close();
    }

    /**
     * @return list<string>
     */
    private function dumpableTables(): array
    {
        return collect(Schema::getTableListing())
            ->map(function (string $table): string {
                return str_contains($table, '.')
                    ? substr($table, strrpos($table, '.') + 1)
                    : $table;
            })
            ->unique()
            ->reject(function (string $table): bool {
                $name = strtolower($table);

                return in_array($name, self::SKIP_TABLES, true)
                    || str_starts_with($name, 'sqlite_')
                    || str_starts_with($name, 'telescope_')
                    || str_starts_with($name, 'pulse_');
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function serializeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = Carbon::parse($value)->toDateTimeString();
            } elseif (is_bool($value)) {
                $row[$key] = $value ? 1 : 0;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $tables
     */
    private function restoreTables(array $tables): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($tables): void {
            DB::transaction(function () use ($tables): void {
                foreach ($this->dumpableTables() as $table) {
                    DB::table($table)->delete();
                }

                $ordered = collect($tables)
                    ->sortBy(function ($rows, string $table): int {
                        return match (true) {
                            $table === 'users' => 0,
                            $table === 'roles' => 1,
                            $table === 'permissions' => 2,
                            str_starts_with($table, 'model_has_') || str_starts_with($table, 'role_has_') => 3,
                            $table === 'site_settings' => 4,
                            $table === 'pages' => 5,
                            default => 50,
                        };
                    });

                foreach ($ordered as $table => $rows) {
                    if (! is_string($table) || ! Schema::hasTable($table) || in_array($table, self::SKIP_TABLES, true)) {
                        continue;
                    }

                    if (! is_array($rows) || $rows === []) {
                        continue;
                    }

                    foreach (array_chunk($rows, 100) as $chunk) {
                        $prepared = [];

                        foreach ($chunk as $row) {
                            if (! is_array($row)) {
                                continue;
                            }

                            $prepared[] = $this->prepareRowForInsert($row);
                        }

                        if ($prepared === []) {
                            continue;
                        }

                        try {
                            DB::table($table)->insert($prepared);
                        } catch (Throwable $exception) {
                            throw new RuntimeException(
                                "Falha ao restaurar a tabela {$table}: ".$exception->getMessage(),
                                0,
                                $exception
                            );
                        }
                    }
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function prepareRowForInsert(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_bool($value)) {
                $row[$key] = $value ? 1 : 0;
            } elseif (is_array($value)) {
                $row[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $row;
    }

    private function restoreFiles(string $filesRoot): void
    {
        foreach (Storage::disk('public')->allFiles() as $file) {
            Storage::disk('public')->delete($file);
        }

        foreach (Storage::disk('local')->allFiles('curriculos') as $file) {
            Storage::disk('local')->delete($file);
        }

        $publicRoot = $filesRoot.'/public';

        if (is_dir($publicRoot)) {
            foreach ($this->filesIn($publicRoot) as $absolute) {
                $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen($publicRoot))), '/');
                Storage::disk('public')->put($relative, (string) file_get_contents($absolute));
            }
        }

        $privateRoot = $filesRoot.'/private';

        if (is_dir($privateRoot)) {
            foreach ($this->filesIn($privateRoot) as $absolute) {
                $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen($privateRoot))), '/');
                Storage::disk('local')->put($relative, (string) file_get_contents($absolute));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
