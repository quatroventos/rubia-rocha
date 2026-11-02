<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('seo_separator', 8)->default('|')->after('twitter_card');
            $table->string('seo_title_template')->default('%page% %sep% %site%')->after('seo_separator');
            $table->text('seo_description')->nullable()->after('seo_title_template');
            $table->string('seo_og_image', 500)->nullable()->after('seo_description');
            $table->string('seo_og_image_alt')->nullable()->after('seo_og_image');
            $table->string('seo_robots')->default('index, follow, max-image-preview:large')->after('seo_og_image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'seo_separator',
                'seo_title_template',
                'seo_description',
                'seo_og_image',
                'seo_og_image_alt',
                'seo_robots',
            ]);
        });
    }
};
