<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seo_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'serpbear_url',
                'serpbear_api_key',
                'serpbear_username',
                'serpbear_password',
                'serpbear_domain',
                'serpbear_country',
                'serpbear_devices',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_integrations', function (Blueprint $table) {
            $table->string('serpbear_url')->nullable();
            $table->text('serpbear_api_key')->nullable();
            $table->string('serpbear_username')->nullable();
            $table->text('serpbear_password')->nullable();
            $table->string('serpbear_domain')->nullable();
            $table->string('serpbear_country', 2)->nullable();
            $table->string('serpbear_devices', 10)->nullable();
        });
    }
};
