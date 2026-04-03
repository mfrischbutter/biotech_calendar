<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE_COLORS = [
        'service' => '#3b82f6',
        'initial_visit' => '#f97316',
        'completed' => '#22c55e',
        'follow_up' => '#a855f7',
        'consultation' => '#ec4899',
        'documentation' => '#6b7280',
    ];

    private const TYPE_LABELS = [
        'service' => 'Servicetermin',
        'initial_visit' => 'Erstbegehung',
        'completed' => 'Abgeschlossen',
        'follow_up' => 'Nachkontrolle',
        'consultation' => 'Beratung',
        'documentation' => 'Dokumentation',
    ];

    public function up(): void
    {
        // Create tags from existing types for each company
        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            $sortOrder = 0;
            foreach (self::TYPE_LABELS as $type => $label) {
                DB::table('tags')->insert([
                    'company_id' => $companyId,
                    'name' => $label,
                    'color' => self::TYPE_COLORS[$type],
                    'sort_order' => $sortOrder++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Add tag_id to appointments
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('tag_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });

        // Backfill tag_id from type column
        foreach ($companies as $companyId) {
            $tags = DB::table('tags')->where('company_id', $companyId)->pluck('id', 'name');
            foreach (self::TYPE_LABELS as $type => $label) {
                if (isset($tags[$label])) {
                    DB::table('appointments')
                        ->where('company_id', $companyId)
                        ->where('type', $type)
                        ->update(['tag_id' => $tags[$label]]);
                }
            }
        }

        // Drop type column
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('type')->default('service')->after('end_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tag_id');
        });
    }
};
