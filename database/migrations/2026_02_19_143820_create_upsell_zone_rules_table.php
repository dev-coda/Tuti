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
        Schema::create('upsell_zone_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upsell_zone_id')->constrained('upsell_zones')->onDelete('cascade');
            $table->foreignId('upsell_rule_id')->constrained('upsell_rules')->onDelete('cascade');
            $table->integer('priority')->default(0); // Priority within this zone
            $table->boolean('active')->default(true);
            $table->json('config')->nullable(); // Zone-specific rule configuration overrides
            $table->timestamps();

            $table->unique(['upsell_zone_id', 'upsell_rule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upsell_zone_rules');
    }
};
