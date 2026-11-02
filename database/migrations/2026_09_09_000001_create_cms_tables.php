<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_href')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp_url')->nullable();
            $table->text('address')->nullable();
            $table->text('map_embed_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('og_site_name')->nullable();
            $table->string('og_locale')->default('pt_BR');
            $table->string('twitter_card')->default('summary_large_image');
            $table->longText('tag_header')->nullable();
            $table->longText('tag_body')->nullable();
            $table->longText('tag_footer')->nullable();
            $table->json('sitemap_extra')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('href');
            $table->string('group');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type')->default('badge');
            $table->string('image_path')->nullable();
            $table->string('url')->nullable();
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('state', 8);
            $table->string('select_label');
            $table->string('eyebrow')->nullable();
            $table->text('address');
            $table->boolean('is_headquarters')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('form_options', function (Blueprint $table) {
            $table->id();
            $table->string('form');
            $table->string('field');
            $table->string('label');
            $table->string('value');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('template');
            $table->string('status')->default('published');
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title');
            $table->text('seo_description')->nullable();
            $table->string('canonical')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_image_alt')->nullable();
            $table->string('robots')->default('index, follow, max-image-preview:large');
            $table->string('twitter_card')->default('summary_large_image');
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('destination');
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('career_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('location');
            $table->string('linkedin')->nullable();
            $table->string('area');
            $table->string('seniority');
            $table->string('resume_path');
            $table->string('resume_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_submissions');
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('form_options');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('site_settings');
    }
};
