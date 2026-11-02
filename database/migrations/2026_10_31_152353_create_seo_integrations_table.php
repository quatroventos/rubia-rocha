<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('openseo_url')->nullable();
            $table->text('openseo_api_key')->nullable();
            $table->string('openseo_host_header')->nullable();
            $table->string('openseo_project_id')->nullable();
            $table->string('openseo_site_url')->nullable();
            $table->unsignedInteger('openseo_location_code')->nullable();
            $table->string('openseo_language_code', 10)->nullable();
            $table->boolean('openseo_paid_enabled')->nullable();
            $table->string('serpbear_url')->nullable();
            $table->text('serpbear_api_key')->nullable();
            $table->string('serpbear_username')->nullable();
            $table->text('serpbear_password')->nullable();
            $table->string('serpbear_domain')->nullable();
            $table->string('serpbear_country', 2)->nullable();
            $table->string('serpbear_devices', 10)->nullable();
            $table->text('openai_api_key')->nullable();
            $table->string('openai_model')->nullable();
            $table->text('prompt_writer')->nullable();
            $table->text('prompt_seo_title')->nullable();
            $table->text('prompt_seo_description')->nullable();
            $table->text('prompt_keyword')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_integrations');
    }
};
