<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'team_members',
        'branches',
        'certifications',
        'users',
        'contact_submissions',
        'career_submissions',
        'form_options',
        'backups',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
