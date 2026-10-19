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
        // Anonymisation removes the birth date and everything derived from it (FR-028).
        Schema::table('volunteers', function (Blueprint $table) {
            $table->text('birth_date')->nullable()->change();
            $table->unsignedSmallInteger('birth_year')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->text('birth_date')->nullable(false)->change();
            $table->unsignedSmallInteger('birth_year')->nullable(false)->change();
        });
    }
};
