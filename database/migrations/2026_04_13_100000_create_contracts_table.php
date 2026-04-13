<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number');
            $table->string('title');
            $table->string('kind')->nullable();
            $table->text('description')->nullable();
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'contract_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
