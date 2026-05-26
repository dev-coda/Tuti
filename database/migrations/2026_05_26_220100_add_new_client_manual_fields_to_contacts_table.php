<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('new_client_mode')->nullable()->after('status');
            $table->json('new_client_payload')->nullable()->after('new_client_mode');
            $table->unsignedBigInteger('external_client_id')->nullable()->after('new_client_payload');
            $table->string('external_client_code')->nullable()->after('external_client_id');
            $table->timestamp('external_submitted_at')->nullable()->after('external_client_code');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'new_client_mode',
                'new_client_payload',
                'external_client_id',
                'external_client_code',
                'external_submitted_at',
            ]);
        });
    }
};

