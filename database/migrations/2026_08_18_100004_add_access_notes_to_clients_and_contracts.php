<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site access details (key safe, gate code, dog in the yard, "ring at the
 * bakery next door") are operational gold in field service, but they used to be
 * buried in the free-text notes nobody reads. They get their own column so the
 * detail pages can pin them where a technician will actually look.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('access_notes')->nullable()->after('notes');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->text('access_notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('access_notes');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('access_notes');
        });
    }
};
