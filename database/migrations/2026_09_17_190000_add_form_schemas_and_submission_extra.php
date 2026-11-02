<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('form_schemas')->nullable()->after('sitemap_extra');
        });

        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->json('extra')->nullable()->after('message');
        });

        Schema::table('career_submissions', function (Blueprint $table) {
            $table->json('extra')->nullable()->after('resume_name');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('form_schemas');
        });

        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropColumn('extra');
        });

        Schema::table('career_submissions', function (Blueprint $table) {
            $table->dropColumn('extra');
        });
    }
};
