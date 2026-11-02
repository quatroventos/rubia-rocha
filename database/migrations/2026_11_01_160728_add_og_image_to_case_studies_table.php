<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('og_image', 500)->nullable()->after('cover_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn('og_image');
        });
    }
};
