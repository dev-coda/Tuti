<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            
            DB::statement("
                WITH ranked_zones AS (
                    SELECT id, address, user_id,
                        ROW_NUMBER() OVER (PARTITION BY address, user_id ORDER BY id) AS rn
                    FROM zones
                ),
                duplicates AS (
                    SELECT z1.id AS duplicate_id, z2.id AS correct_id
                    FROM ranked_zones z1
                    JOIN ranked_zones z2
                        ON z1.address = z2.address
                        AND z1.user_id = z2.user_id
                        AND z2.rn = 1
                    WHERE z1.rn > 1
                )
                UPDATE orders
                SET zone_id = (
                    SELECT correct_id FROM duplicates WHERE duplicates.duplicate_id = orders.zone_id
                )
                WHERE EXISTS (
                    SELECT 1 FROM duplicates WHERE duplicates.duplicate_id = orders.zone_id
                );
            ");

            DB::statement("
                WITH ranked_zones AS (
                    SELECT id, address, user_id,
                        ROW_NUMBER() OVER (PARTITION BY address, user_id ORDER BY id) AS rn
                    FROM zones
                )
                DELETE FROM zones
                WHERE id IN (
                    SELECT id FROM ranked_zones WHERE rn > 1
                )
                AND NOT EXISTS (
                    SELECT 1 FROM orders WHERE orders.zone_id = zones.id
                );
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \Exception('This migration cannot be reversed.');
    }
};
