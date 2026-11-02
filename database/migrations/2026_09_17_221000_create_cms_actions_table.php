<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('action');
            $table->string('description');
            $table->string('method', 12);
            $table->string('path', 500);
            $table->string('ip', 45)->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_actions');
    }
};
