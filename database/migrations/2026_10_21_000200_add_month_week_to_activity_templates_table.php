<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Monthly repetition, e.g. "1.º domingo de cada mês": 1–4, or -1 for the last one.
        // When set, interval_weeks is ignored.
        Schema::table('activity_templates', function (Blueprint $table) {
            $table->tinyInteger('month_week')->nullable()->after('interval_weeks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_templates', function (Blueprint $table) {
            $table->dropColumn('month_week');
        });
    }
};
