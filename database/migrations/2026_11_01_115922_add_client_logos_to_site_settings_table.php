<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('client_logos')->nullable()->after('plan_links');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('client_logos');
        });
    }
};
