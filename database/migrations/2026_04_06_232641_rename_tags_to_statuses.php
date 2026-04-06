<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('tags', 'statuses');

        Schema::table('appointments', function (Blueprint $table) {
            $table->renameColumn('tag_id', 'status_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->renameColumn('status_id', 'tag_id');
        });

        Schema::rename('statuses', 'tags');
    }
};
