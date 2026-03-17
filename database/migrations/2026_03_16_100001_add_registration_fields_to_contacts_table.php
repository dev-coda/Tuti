<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('person_type')->default('natural')->after('name'); // natural | juridica
            $table->string('department')->nullable()->after('city_id');       // departamento
            $table->string('status')->default('interesado')->after('read');   // interesado, en_validacion, creado, contactado
            $table->json('documents')->nullable()->after('address');          // stored file paths
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['person_type', 'department', 'status', 'documents']);
        });
    }
};
