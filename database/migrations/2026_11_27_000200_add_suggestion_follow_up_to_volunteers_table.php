<?php

use App\Enums\SuggestionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinators follow up on the suggestions volunteers leave in their registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->string('suggestion_status', 20)->nullable()->after('suggestions')->index();
            $table->text('suggestion_note')->nullable()->after('suggestion_status');
            $table->foreignId('suggestion_handled_by')->nullable()->after('suggestion_note')->constrained('users')->nullOnDelete();
            $table->timestamp('suggestion_handled_at')->nullable()->after('suggestion_handled_by');
        });

        // Suggestions are encrypted, so emptiness is only known after decrypting.
        DB::table('volunteers')->whereNotNull('suggestions')->whereNull('anonymized_at')->orderBy('id')
            ->each(function (object $row): void {
                try {
                    $text = decrypt($row->suggestions, false);
                } catch (Throwable) {
                    return;
                }

                if (trim((string) $text) !== '') {
                    DB::table('volunteers')->where('id', $row->id)->update(['suggestion_status' => SuggestionStatus::New->value]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggestion_handled_by');
            $table->dropColumn(['suggestion_status', 'suggestion_note', 'suggestion_handled_at']);
        });
    }
};
