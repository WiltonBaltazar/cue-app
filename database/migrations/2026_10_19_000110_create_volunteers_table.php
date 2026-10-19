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
        Schema::create('volunteers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('full_name', 160);

            // Encrypted columns (🔒 in data-model.md) are text: ciphertext is long.
            $table->text('birth_date');
            $table->unsignedSmallInteger('birth_year');
            $table->boolean('is_minor')->default(false);
            $table->text('guardian_name')->nullable();
            $table->text('guardian_phone')->nullable();
            $table->text('neighbourhood')->nullable();
            $table->text('education_level')->nullable();
            $table->text('training')->nullable();
            $table->text('commitments')->nullable();
            $table->text('motivations')->nullable();
            $table->text('comments')->nullable();

            $table->string('max_frequency', 20);
            $table->boolean('variable_hours')->default(false);
            $table->boolean('available_for_training')->default(false);
            $table->boolean('can_support_others')->default(false);

            $table->timestamp('consent_accepted_at');
            $table->unsignedInteger('consent_version');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('previously_rejected_at')->nullable();

            $table->foreignId('guardian_consent_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('guardian_consent_confirmed_at')->nullable();
            $table->string('guardian_consent_method', 20)->nullable();

            $table->date('inactive_since')->nullable();
            $table->date('inactive_until')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('removal_requested_at')->nullable();
            $table->timestamp('removal_scheduled_for')->nullable();
            $table->timestamp('anonymized_at')->nullable();

            $table->timestamps();
        });

        Schema::create('volunteer_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_role_id')->constrained()->cascadeOnDelete();
            $table->boolean('interested')->default(true);
            $table->boolean('has_experience')->default(false);
            $table->string('level', 20)->default('beginner');
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['volunteer_id', 'team_role_id']);
        });

        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1 (Mon) – 7 (Sun)
            $table->string('period', 20);
            $table->timestamps();

            $table->unique(['volunteer_id', 'weekday', 'period']);
        });

        Schema::create('rejected_registrations', function (Blueprint $table) {
            $table->id();
            $table->char('phone_hash', 64)->index(); // HMAC-SHA256, no other personal data
            $table->timestamp('rejected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rejected_registrations');
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('volunteer_skills');
        Schema::dropIfExists('volunteers');
    }
};
