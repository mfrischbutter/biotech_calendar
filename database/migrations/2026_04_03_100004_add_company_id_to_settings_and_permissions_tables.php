<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Backfill settings with the first company
        $companyId = DB::table('companies')->value('id');
        if ($companyId) {
            DB::table('settings')->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->foreignId('company_id')->nullable(false)->change();
            $table->unique(['company_id', 'key']);
        });

        // Permissions
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Backfill from user's company
        DB::statement('
            UPDATE permissions
            SET company_id = (SELECT company_id FROM users WHERE users.id = permissions.user_id)
        ');

        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'key']);
            $table->dropConstrainedForeignId('company_id');
            $table->unique('key');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
