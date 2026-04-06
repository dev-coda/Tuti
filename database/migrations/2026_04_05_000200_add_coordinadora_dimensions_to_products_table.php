<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('coordinadora_weight_kg', 10, 3)->nullable()->after('sync_variations_with_dynamics');
            $table->decimal('coordinadora_height_cm', 10, 2)->nullable()->after('coordinadora_weight_kg');
            $table->decimal('coordinadora_width_cm', 10, 2)->nullable()->after('coordinadora_height_cm');
            $table->decimal('coordinadora_length_cm', 10, 2)->nullable()->after('coordinadora_width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'coordinadora_weight_kg',
                'coordinadora_height_cm',
                'coordinadora_width_cm',
                'coordinadora_length_cm',
            ]);
        });
    }
};
