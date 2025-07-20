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
        Schema::table('featured_categories', function (Blueprint $table) {
            $table->string('custom_image')->nullable()->after('position');
            $table->string('custom_title')->nullable()->after('custom_image');
            $table->string('custom_url')->nullable()->after('custom_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('featured_categories', function (Blueprint $table) {
            $table->dropColumn(['custom_image', 'custom_title', 'custom_url']);
        });
    }
};
