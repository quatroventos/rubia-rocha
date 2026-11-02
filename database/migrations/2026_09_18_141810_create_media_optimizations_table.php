<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_optimizations', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('optimized_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('quality')->default(90);
            $table->unsignedInteger('max_width')->nullable();
            $table->unsignedBigInteger('original_bytes')->default(0);
            $table->unsignedBigInteger('optimized_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('optimized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_optimizations');
    }
};
