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
        // Recurring activity ("ModeloActividade"). Times are local (Africa/Maputo).
        Schema::create('activity_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedTinyInteger('weekday'); // ISO 1 (Mon) – 7 (Sun)
            $table->unsignedTinyInteger('interval_weeks')->default(1);
            $table->date('starts_on'); // reference week for interval_weeks > 1
            $table->string('period', 20);
            $table->time('arrival_time');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('teardown_minutes')->default(0);
            $table->string('location', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('template_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_role_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->string('min_level', 20)->nullable(); // null = any level
            $table->timestamps();

            $table->unique(['activity_template_id', 'team_role_id']);
        });

        // Concrete occurrence. Timestamps are UTC; `date` is the local calendar date.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->date('date')->index();
            $table->string('period', 20);
            $table->dateTime('arrival_at');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('teardown_until');
            $table->string('location', 160);
            $table->string('status', 20)->default('scheduled')->index();
            $table->boolean('is_customized')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['activity_template_id', 'date']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_role_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->string('min_level', 20)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'team_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('template_positions');
        Schema::dropIfExists('activity_templates');
    }
};
