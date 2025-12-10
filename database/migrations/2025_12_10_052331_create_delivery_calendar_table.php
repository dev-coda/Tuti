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
        Schema::create('delivery_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('year'); // Year (e.g., "2.025")
            $table->string('month'); // Month name in Spanish (e.g., "Enero", "Febrero")
            $table->integer('week_number'); // Week number (e.g., 3, 4, 5)
            $table->date('start_date'); // Start date of the week
            $table->date('end_date'); // End date of the week
            $table->enum('cycle', ['A', 'B', 'C']); // Cycle for this week
            $table->timestamps();
            
            $table->index(['start_date', 'end_date']);
            $table->index('cycle');
            $table->index('week_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_calendar');
    }
};
