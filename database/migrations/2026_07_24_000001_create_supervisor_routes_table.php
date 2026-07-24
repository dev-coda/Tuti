<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('zone', 10);
            $table->string('route', 10);
            $table->timestamps();

            $table->unique(['user_id', 'zone', 'route']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_routes');
    }
};
