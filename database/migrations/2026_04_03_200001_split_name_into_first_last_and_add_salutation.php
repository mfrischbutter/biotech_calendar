<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Users ---
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id')->default('');
            $table->string('last_name')->after('first_name')->default('');
        });

        // Migrate existing data: split "name" at the first space
        DB::table('users')->orderBy('id')->each(function ($user) {
            $parts = explode(' ', $user->name, 2);
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        // --- Clients ---
        Schema::table('clients', function (Blueprint $table) {
            $table->string('salutation')->nullable()->after('id');
            $table->string('first_name')->after('salutation')->default('');
            $table->string('last_name')->after('first_name')->default('');
        });

        // Migrate existing data
        DB::table('clients')->orderBy('id')->each(function ($client) {
            $parts = explode(' ', $client->name, 2);
            DB::table('clients')->where('id', $client->id)->update([
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        // --- Users ---
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id')->default('');
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $name = trim($user->first_name.' '.$user->last_name);
            DB::table('users')->where('id', $user->id)->update(['name' => $name]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });

        // --- Clients ---
        Schema::table('clients', function (Blueprint $table) {
            $table->string('name')->after('id')->default('');
        });

        DB::table('clients')->orderBy('id')->each(function ($client) {
            $name = trim($client->first_name.' '.$client->last_name);
            DB::table('clients')->where('id', $client->id)->update(['name' => $name]);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['salutation', 'first_name', 'last_name']);
        });
    }
};
