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
        Schema::create('swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('volunteers')->cascadeOnDelete();
            $table->foreignId('target_volunteer_id')->nullable()->constrained('volunteers')->nullOnDelete();
            $table->foreignId('accepted_by_id')->nullable()->constrained('volunteers')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('auto_approved')->default(false);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swap_requests');
    }
};
