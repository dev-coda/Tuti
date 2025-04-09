<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        DB::table('settings')->insert([
            'id' => 6,  
            'name' => 'Monto Minimo',
            'key' => 'min_amount',
            'value' => '100000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down() {
        DB::table('settings')->where('id', 6)->delete();
    }
};
