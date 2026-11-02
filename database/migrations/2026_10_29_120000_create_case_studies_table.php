<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('status', 20)->default('draft');
            $table->string('client');
            $table->string('title');
            $table->string('summary', 300)->nullable();
            $table->string('sector', 60)->nullable();
            $table->json('services')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('cover_path', 500)->nullable();
            $table->string('cover_alt')->nullable();
            $table->json('gallery')->nullable();
            $table->longText('challenge')->nullable();
            $table->longText('solution')->nullable();
            $table->json('deliverables')->nullable();
            $table->json('stack')->nullable();
            $table->json('results')->nullable();
            $table->json('testimonial')->nullable();
            $table->string('url', 500)->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'featured', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_studies');
    }
};
