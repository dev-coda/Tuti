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
        Schema::table('orders', function (Blueprint $table) {
            // Track how many times processing has been attempted
            $table->integer('processing_attempts')->default(0)->after('status_id');

            // Track last attempt timestamp
            $table->timestamp('last_processing_attempt')->nullable()->after('processing_attempts');

            // Track if this order was manually retried by admin or scheduled command
            $table->boolean('manually_retried')->default(false)->after('last_processing_attempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['processing_attempts', 'last_processing_attempt', 'manually_retried']);
        });
    }
};
