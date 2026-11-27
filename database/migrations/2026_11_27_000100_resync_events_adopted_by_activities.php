<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Events added by hand and then adopted by an activity were marked customised, so they kept
 * their own roles instead of the activity's. Unmark them: the next sync or "Gerar de novo"
 * applies the activity to those nobody is assigned to by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        $createdByHand = DB::table('activity_log')
            ->where('subject_type', Event::class)
            ->where('event', 'created')
            ->get(['subject_id', 'properties'])
            ->filter(fn (object $row): bool => (json_decode((string) $row->properties, true)['attributes']['type'] ?? null) === 'special')
            ->pluck('subject_id');

        DB::table('events')
            ->whereIn('id', $createdByHand)
            ->where('type', 'recurring')
            ->whereNotNull('activity_template_id')
            ->where('is_customized', true)
            ->update(['is_customized' => false]);
    }

    public function down(): void {}
};
