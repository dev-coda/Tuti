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
        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'name')) {
                $table->dropColumn('name');
            }
            if (!Schema::hasColumn('holidays', 'type_id')) {
                $table->integer('type_id')->default(1);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'type_id')) {
                $table->dropColumn('type_id');
            }
            if (!Schema::hasColumn('holidays', 'name')) {
                $table->string('name')->nullable(); // Make it nullable since we can't restore the original data
            }
        });
    }
};
