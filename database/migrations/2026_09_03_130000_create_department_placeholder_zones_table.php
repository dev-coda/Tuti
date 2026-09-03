<?php

use App\Models\City;
use App\Services\Shipping\DaneCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('is_placeholder')->default(false)->after('shipping_express_enabled');
        });

        Schema::create('department_placeholder_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('zone', 8)->nullable();
            $table->string('route', 8)->nullable();
            $table->string('day')->nullable();
            $table->string('dane_code', 16)->nullable();
            $table->string('address')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique('state_id');
        });

        $preferred = City::query()
            ->preferred()
            ->with('state')
            ->orderBy('name')
            ->get();

        foreach ($preferred as $city) {
            if (! $city->state_id) {
                continue;
            }

            $dane = DaneCodeService::forCity($city->name, $city->state?->name);

            DB::table('department_placeholder_zones')->updateOrInsert(
                ['state_id' => $city->state_id],
                [
                    'city_id' => $city->id,
                    'dane_code' => $dane,
                    'address' => 'Zona placeholder — '.$city->name,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_placeholder_zones');

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('is_placeholder');
        });
    }
};
