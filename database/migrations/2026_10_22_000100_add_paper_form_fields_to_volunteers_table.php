<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Questions from the paper registration form that the online form did not ask.
        // Free-text answers about the person are encrypted, like the existing ones.
        Schema::table('volunteers', function (Blueprint $table) {
            $table->string('nickname', 80)->nullable()->after('full_name');
            $table->boolean('phone_has_whatsapp')->default(false)->after('neighbourhood');
            $table->text('whatsapp_phone')->nullable()->after('phone_has_whatsapp'); // only when it differs from the login phone
            $table->text('social_media')->nullable()->after('whatsapp_phone'); // encrypted JSON list of {network, handle}
            $table->string('other_area', 120)->nullable()->after('training');
            $table->json('experience_role_ids')->nullable()->after('other_area');
            $table->string('other_experience', 120)->nullable()->after('experience_role_ids');
            $table->text('experience_description')->nullable()->after('other_experience');
            $table->text('expectations')->nullable()->after('motivations');
            $table->text('goals')->nullable()->after('expectations');
            $table->text('suggestions')->nullable()->after('comments');
            $table->boolean('other_days_by_arrangement')->default(false)->after('variable_hours');
            $table->text('frequency_note')->nullable()->after('max_frequency');
            $table->string('training_availability', 20)->default('none')->after('other_days_by_arrangement');
        });

        // The yes/no "available for training" becomes one of four answers.
        DB::table('volunteers')->where('available_for_training', true)->update(['training_availability' => 'regular']);

        Schema::table('volunteers', function (Blueprint $table) {
            $table->dropColumn('available_for_training');
        });

        // Experience was only recorded per serving role so far.
        DB::table('volunteer_skills')
            ->where('has_experience', true)
            ->get(['volunteer_id', 'team_role_id'])
            ->groupBy('volunteer_id')
            ->each(fn ($skills, $volunteerId) => DB::table('volunteers')->where('id', $volunteerId)->update([
                'experience_role_ids' => json_encode($skills->pluck('team_role_id')->map(fn ($id) => (int) $id)->values()),
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->boolean('available_for_training')->default(false)->after('variable_hours');
        });

        DB::table('volunteers')->whereIn('training_availability', ['regular', 'with_notice'])->update(['available_for_training' => true]);

        Schema::table('volunteers', function (Blueprint $table) {
            $table->dropColumn([
                'nickname', 'phone_has_whatsapp', 'whatsapp_phone', 'social_media', 'other_area', 'experience_role_ids',
                'other_experience', 'experience_description', 'expectations', 'goals', 'suggestions',
                'other_days_by_arrangement', 'frequency_note', 'training_availability',
            ]);
        });
    }
};
