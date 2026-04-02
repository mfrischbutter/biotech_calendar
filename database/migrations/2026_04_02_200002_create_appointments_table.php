<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('type');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Recurrence fields
            $table->string('recurrence_type')->nullable();
            $table->unsignedInteger('recurrence_interval')->nullable();
            $table->date('recurrence_end')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->timestamps();

            $table->index(['start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
