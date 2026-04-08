<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['clients', 'appointments', 'companies'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('latitude', 10, 7)->nullable()->after('city');
                $blueprint->decimal('longitude', 10, 7)->nullable()->after('latitude');
                $blueprint->string('place_id')->nullable()->after('longitude');
            });
        }
    }

    public function down(): void
    {
        $tables = ['clients', 'appointments', 'companies'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['latitude', 'longitude', 'place_id']);
            });
        }
    }
};
