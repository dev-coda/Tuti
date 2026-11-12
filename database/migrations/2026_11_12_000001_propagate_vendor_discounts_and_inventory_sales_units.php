<?php

use App\Models\Vendor;
use App\Services\InventoryDaySalesAdjuster;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Vendor::query()->orderBy('id')->each(function (Vendor $vendor) {
            $vendor->propagateDiscountToBrands();
        });

        app(InventoryDaySalesAdjuster::class)->applyUncountedPackageUnits();
    }

    public function down(): void
    {
        // Discount copies and stock corrections are not reversible.
    }
};
