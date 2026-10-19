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
        // The 72 h check and the 24 h reminder go out once per assignment (US6).
        Schema::table('assignments', function (Blueprint $table) {
            $table->timestamp('check_sent_at')->nullable()->after('responded_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('check_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['check_sent_at', 'reminder_sent_at']);
        });
    }
};
