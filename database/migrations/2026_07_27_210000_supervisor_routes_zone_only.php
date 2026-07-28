<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collapse existing zone+route pairs into one row per (user, zone).
        $keepIds = DB::table('supervisor_routes')
            ->select('user_id', 'zone', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'zone')
            ->pluck('keep_id');

        DB::table('supervisor_routes')
            ->whereNotIn('id', $keepIds)
            ->delete();

        Schema::create('supervisor_routes_zone_only', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('zone', 10);
            $table->string('route', 10)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'zone']);
        });

        $rows = DB::table('supervisor_routes')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('supervisor_routes_zone_only')->insert([
                'id' => $row->id,
                'user_id' => $row->user_id,
                'zone' => $row->zone,
                'route' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('supervisor_routes');
        Schema::rename('supervisor_routes_zone_only', 'supervisor_routes');
    }

    public function down(): void
    {
        Schema::create('supervisor_routes_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('zone', 10);
            $table->string('route', 10);
            $table->timestamps();

            $table->unique(['user_id', 'zone', 'route']);
        });

        $rows = DB::table('supervisor_routes')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('supervisor_routes_legacy')->insert([
                'id' => $row->id,
                'user_id' => $row->user_id,
                'zone' => $row->zone,
                'route' => $row->route ?: '0000',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('supervisor_routes');
        Schema::rename('supervisor_routes_legacy', 'supervisor_routes');
    }
};
