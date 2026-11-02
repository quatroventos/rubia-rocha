<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_options', function (Blueprint $table) {
            $table->string('email')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('form_options', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
