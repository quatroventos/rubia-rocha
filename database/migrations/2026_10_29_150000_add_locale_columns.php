<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'case_studies'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->string('locale', 10)->default('pt-BR')->after('slug');
                $table->foreignId('translation_of')->nullable()->after('locale')->constrained($name)->cascadeOnDelete();
                $table->string('source_hash', 64)->nullable()->after('translation_of');
                $table->timestamp('translated_at')->nullable()->after('source_hash');
                $table->dropUnique($name.'_slug_unique');
                $table->unique(['locale', 'slug']);
                $table->unique(['translation_of', 'locale']);
            });
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('locale', 10)->default('pt-BR')->after('group');
            $table->index(['locale', 'group']);
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('translations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('translations');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['locale', 'group']);
            $table->dropColumn('locale');
        });

        foreach (['pages', 'case_studies'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropUnique(['translation_of', 'locale']);
                $table->dropUnique(['locale', 'slug']);
                $table->dropConstrainedForeignId('translation_of');
                $table->dropColumn(['locale', 'source_hash', 'translated_at']);
                $table->unique('slug', $name.'_slug_unique');
            });
        }
    }
};
