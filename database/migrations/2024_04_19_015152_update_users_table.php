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
        Schema::table('users', function (Blueprint $table) {
            // Single dropColumn() call so SQLite (tests) can rebuild the table once.
            $table->dropColumn([
                'code',
                'company',
                'address',
                'area',
                'phone',
                'mobile',
                'document_front',
                'document_back',
                'company_document',
                'has_whatsapp',
                'visit_by_tronex',
                'state_id',
                'city_id',
                'route',
                'zone',
                'day',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('company')->nullable();
            $table->string('address')->nullable();
            $table->string('area')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('document_front')->nullable();
            $table->string('document_back')->nullable();
            $table->string('company_document')->nullable();
            $table->boolean('has_whatsapp')->default(false);
            $table->boolean('visit_by_tronex')->default(false);
            $table->foreignId('state_id')->nullable()->constrained('states');
            $table->foreignId('city_id')->nullable()->constrained('cities');
            $table->string('route')->nullable();
            $table->integer('zone')->nullable();
            $table->integer('day')->nullable();
        });
    }
};
