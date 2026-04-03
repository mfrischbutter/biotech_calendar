<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Create a default company and backfill all existing users
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Default Company',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->whereNull('company_id')->update(['company_id' => $companyId]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
