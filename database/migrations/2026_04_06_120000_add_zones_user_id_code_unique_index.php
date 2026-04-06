<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @param  \Illuminate\Support\Collection<int, int>  $ids
     */
    private function mergeDuplicateZoneIds(\Illuminate\Support\Collection $ids): void
    {
        if ($ids->count() < 2) {
            return;
        }

        $keeper = $ids->first();
        foreach ($ids->slice(1) as $loserId) {
            DB::table('orders')->where('zone_id', $loserId)->update(['zone_id' => $keeper]);
            DB::table('zones')->where('id', $loserId)->delete();
        }
    }

    /**
     * Enforce one row per (user_id, code) for non-empty code so checkout rematch by CustRuteroID is unambiguous.
     * Duplicates are merged into the lowest id per group (orders are repointed first).
     */
    public function up(): void
    {
        $dupKeys = DB::table('zones')
            ->select('user_id', 'code')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->groupBy('user_id', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupKeys as $row) {
            $this->mergeDuplicateZoneIds(
                DB::table('zones')
                    ->where('user_id', $row->user_id)
                    ->where('code', $row->code)
                    ->orderBy('id')
                    ->pluck('id')
            );
        }

        $dupEmptyCodeByUser = DB::table('zones')
            ->select('user_id')
            ->where('code', '')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupEmptyCodeByUser as $row) {
            $this->mergeDuplicateZoneIds(
                DB::table('zones')
                    ->where('user_id', $row->user_id)
                    ->where('code', '')
                    ->orderBy('id')
                    ->pluck('id')
            );
        }

        Schema::table('zones', function (Blueprint $table) {
            $table->unique(['user_id', 'code'], 'zones_user_id_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropUnique('zones_user_id_code_unique');
        });
    }
};
