<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
        });

        DB::transaction(function () {
            $groups = DB::table('appointments')
                ->select('title', 'kind', 'street', 'zip', 'city', 'company_id')
                ->distinct()
                ->get();

            $counter = [];

            foreach ($groups as $group) {
                $companyId = $group->company_id;
                if (! isset($counter[$companyId])) {
                    $counter[$companyId] = 1;
                }

                $contractNumber = 'A-'.str_pad($counter[$companyId], 4, '0', STR_PAD_LEFT);
                $counter[$companyId]++;

                $contractId = DB::table('contracts')->insertGetId([
                    'company_id' => $companyId,
                    'contract_number' => $contractNumber,
                    'title' => $group->title ?? 'Untitled',
                    'kind' => $group->kind,
                    'street' => $group->street,
                    'zip' => $group->zip,
                    'city' => $group->city,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $appointments = DB::table('appointments')
                    ->where('company_id', $companyId)
                    ->where('title', $group->title)
                    ->where(function ($q) use ($group) {
                        if ($group->kind === null) {
                            $q->whereNull('kind');
                        } else {
                            $q->where('kind', $group->kind);
                        }
                    })
                    ->where(function ($q) use ($group) {
                        if ($group->street === null) {
                            $q->whereNull('street');
                        } else {
                            $q->where('street', $group->street);
                        }
                    })
                    ->where(function ($q) use ($group) {
                        if ($group->zip === null) {
                            $q->whereNull('zip');
                        } else {
                            $q->where('zip', $group->zip);
                        }
                    })
                    ->where(function ($q) use ($group) {
                        if ($group->city === null) {
                            $q->whereNull('city');
                        } else {
                            $q->where('city', $group->city);
                        }
                    })
                    ->get();

                $clientIds = $appointments->pluck('client_id')->filter()->unique();
                foreach ($clientIds as $clientId) {
                    DB::table('contract_client')->insert([
                        'contract_id' => $contractId,
                        'client_id' => $clientId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($appointments as $appt) {
                    DB::table('appointments')
                        ->where('id', $appt->id)
                        ->update(['contract_id' => $contractId]);

                    if ($appt->employee_id) {
                        DB::table('appointment_user')->insertOrIgnore([
                            'appointment_id' => $appt->id,
                            'user_id' => $appt->employee_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable(false)->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'title', 'kind', 'client_id', 'employee_id',
                'street', 'zip', 'city', 'latitude', 'longitude', 'place_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->string('kind')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_id')->nullable();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropColumn('contract_id');
        });
    }
};
