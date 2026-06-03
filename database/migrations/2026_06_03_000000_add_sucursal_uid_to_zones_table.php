<?php

use App\Models\Zone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a stable sucursal identity to each zone row.
     *
     * A `zones` row models one physical sucursal. `code`/`route`/`zone`/`day` are mutable
     * logistics attributes that Dynamics can re-assign; `sucursal_uid` is the row's frozen
     * identity so rutero sync never silently repurposes a row to a different sucursal and
     * `orders.zone_id` keeps a stable meaning.
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('sucursal_uid')->nullable()->after('code');
            $table->index(['user_id', 'sucursal_uid']);
        });

        DB::table('zones')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('zones')
                    ->where('id', $row->id)
                    ->update([
                        'sucursal_uid' => Zone::makeSucursalUid($row->code, $row->address),
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'sucursal_uid']);
            $table->dropColumn('sucursal_uid');
        });
    }
};
