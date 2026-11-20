<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A schedule can cover a single event (e.g. an extraordinary one) instead of a date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->unique()->after('ends_on')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
