<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weekly repetition that skips one week of the month, e.g. "every Sunday except the
        // 1st" (the 1st Sunday has its own activity): 1–4, or -1 for the last one.
        Schema::table('activity_templates', function (Blueprint $table) {
            $table->tinyInteger('except_month_week')->nullable()->after('month_week');
        });
    }

    public function down(): void
    {
        Schema::table('activity_templates', function (Blueprint $table) {
            $table->dropColumn('except_month_week');
        });
    }
};
