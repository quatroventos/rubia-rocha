<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('services');
            $table->string('cover_ratio', 10)->nullable()->after('cover_alt');
        });
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn(['tags', 'cover_ratio']);
        });
    }
};
