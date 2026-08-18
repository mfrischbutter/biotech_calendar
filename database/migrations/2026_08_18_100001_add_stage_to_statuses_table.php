<?php

use App\Models\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statuses are user-editable labels, but the app needs to reason about where a
 * job sits in the lifecycle ("how much is ready to invoice?"). `stage` gives
 * each status a machine-readable meaning while the name stays free text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->string('stage')->default(Status::STAGE_ACTIVE)->after('color');
        });

        // Best-effort classification of the statuses seeded before this migration.
        $map = [
            'Block – noch nicht bestätigt' => Status::STAGE_UNCONFIRMED,
            'Für Fakturierung bereit' => Status::STAGE_READY_TO_INVOICE,
            'Bereit zur Abrechnung' => Status::STAGE_READY_TO_INVOICE,
            'Fakturiert' => Status::STAGE_INVOICED,
            'Abgerechnet' => Status::STAGE_INVOICED,
            'Maßnahme Storniert' => Status::STAGE_CANCELLED,
        ];

        foreach ($map as $name => $stage) {
            DB::table('statuses')->where('name', $name)->update(['stage' => $stage]);
        }
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
