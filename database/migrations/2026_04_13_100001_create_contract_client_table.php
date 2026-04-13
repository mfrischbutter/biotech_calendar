<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_client');
    }
};
