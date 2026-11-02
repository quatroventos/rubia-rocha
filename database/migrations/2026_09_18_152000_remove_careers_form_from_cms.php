<?php

use App\Models\FormOption;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        FormOption::query()->where('form', 'careers')->forceDelete();

        $settings = SiteSetting::query()->first();

        if ($settings === null) {
            return;
        }

        $schemas = $settings->form_schemas ?? [];

        if (! is_array($schemas) || ! array_key_exists('careers', $schemas)) {
            return;
        }

        unset($schemas['careers']);
        $settings->update(['form_schemas' => $schemas === [] ? null : $schemas]);
    }

    public function down(): void
    {
        //
    }
};
