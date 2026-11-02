<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('backup_hour')->default(3)->after('form_schemas');
            $table->unsignedTinyInteger('backup_retention_days')->default(7)->after('backup_hour');
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('ok');
            $table->string('trigger')->default('manual');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['backup_hour', 'backup_retention_days']);
        });
    }
};
