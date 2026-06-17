<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_routes', function (Blueprint $table) {
            $table->id();
            $table->string('zone', 3);
            $table->string('route', 4);
            $table->timestamps();

            $table->unique(['zone', 'route']);
            $table->index('zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_routes');
    }
};

