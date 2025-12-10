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
        Schema::create('route_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('route')->unique(); // Route number (e.g., "1300", "1301")
            $table->enum('cycle', ['A', 'B', 'C']); // Cycle assignment
            $table->timestamps();
            
            $table->index('route');
            $table->index('cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_cycles');
    }
};
