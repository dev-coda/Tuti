<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_routes_with_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('zone', 10);
            $table->string('route', 10)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'zone', 'route']);
        });

        $rows = DB::table('supervisor_routes')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('supervisor_routes_with_routes')->insert([
                'id' => $row->id,
                'user_id' => $row->user_id,
                'zone' => $row->zone,
                'route' => filled($row->route) ? $row->route : '',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('supervisor_routes');
        Schema::rename('supervisor_routes_with_routes', 'supervisor_routes');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $maxId = (int) (DB::table('supervisor_routes')->max('id') ?: 0);
            if ($maxId > 0) {
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence('supervisor_routes', 'id'), ?, true)",
                    [$maxId]
                );
            }
        }
    }

    public function down(): void
    {
        $keepIds = DB::table('supervisor_routes')
            ->select('user_id', 'zone', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'zone')
            ->pluck('keep_id');

        Schema::create('supervisor_routes_zone_only', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('zone', 10);
            $table->string('route', 10)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'zone']);
        });

        $rows = DB::table('supervisor_routes')
            ->whereIn('id', $keepIds)
            ->orderBy('id')
            ->get();

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
};
