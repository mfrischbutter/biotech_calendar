<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications: mentions, assignments, schedule conflicts, uploads.
 *
 * Named `app_notifications` so it never collides with Laravel's own
 * `notifications` table if the database notification channel is adopted later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['company_id', 'created_at']);
            // Supports the service's updateOrCreate() dedupe for repeatable
            // notices (conflicts, assignments) without forbidding genuinely
            // repeated ones like mentions.
            $table->index(['user_id', 'type', 'appointment_id'], 'app_notifications_dedupe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
