<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('draft_reconciliation_note')->nullable()->after('scheduled_transmission_date');
            $table->timestamp('draft_reconciliation_at')->nullable()->after('draft_reconciliation_note');
            $table->index('draft_reconciliation_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['draft_reconciliation_at']);
            $table->dropColumn(['draft_reconciliation_note', 'draft_reconciliation_at']);
        });
    }
};
