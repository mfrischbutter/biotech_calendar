<?php

use App\Models\StaffRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Named permission presets ("Techniker", "Einsatzleiter", ...).
 *
 * Deliberately NOT called `roles`: `users.role` already exists and means the
 * account type (owner vs. employee). A staff role is a reusable bundle of the
 * granular permissions in the `permissions` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('staff_role_id')->nullable()->after('role')
                ->constrained('staff_roles')->nullOnDelete();
        });

        // Give every existing company the preset roles so the Employees screen
        // has something to offer immediately after deploy.
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            StaffRole::seedForCompany((int) $companyId);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_role_id');
        });

        Schema::dropIfExists('staff_roles');
    }
};
