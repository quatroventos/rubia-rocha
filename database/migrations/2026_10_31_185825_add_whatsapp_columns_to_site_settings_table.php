<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('smtp_from_name');
            $table->string('evolution_url')->nullable()->after('whatsapp_enabled');
            $table->text('evolution_api_key')->nullable()->after('evolution_url');
            $table->string('evolution_instance')->nullable()->after('evolution_api_key');
            $table->string('whatsapp_notify_number', 32)->nullable()->after('evolution_instance');
            $table->text('whatsapp_admin_template')->nullable()->after('whatsapp_notify_number');
            $table->text('whatsapp_visitor_template')->nullable()->after('whatsapp_admin_template');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_enabled',
                'evolution_url',
                'evolution_api_key',
                'evolution_instance',
                'whatsapp_notify_number',
                'whatsapp_admin_template',
                'whatsapp_visitor_template',
            ]);
        });
    }
};
