<?php

use App\Cms\PageContentMigrator;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Page::query()->orderBy('id')->each(function (Page $page): void {
            $content = is_array($page->content) ? $page->content : [];

            if (isset($content['sections']) && is_array($content['sections'])) {
                return;
            }

            $page->forceFill([
                'content' => PageContentMigrator::normalize($content, $page->template),
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Irreversible content reshape.
    }
};
