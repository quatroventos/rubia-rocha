<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_key')->unique();
            $table->uuid('visitor_key')->index();
            $table->string('ip', 45);
            $table->string('country', 64)->nullable();
            $table->string('region', 96)->nullable();
            $table->string('city', 96)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('device', 16)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('os', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('origin', 255)->default('direct');
            $table->text('referrer')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at')->index();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->index('origin');
            $table->index('started_at');
            $table->index(['city', 'country']);
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_session_id')->constrained('page_sessions')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('path', 255);
            $table->unsignedInteger('load_time_ms')->nullable();
            $table->timestamp('created_at')->index();
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('page_sessions');
    }
};
