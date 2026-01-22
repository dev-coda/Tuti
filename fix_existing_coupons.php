<?php
// Quick diagnostic script to check existing coupon limits
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking all coupons for potential empty string issues:\n\n";

$coupons = \App\Models\Coupon::all();

foreach ($coupons as $coupon) {
    echo "Coupon ID {$coupon->id} ({$coupon->code}):\n";
    echo "  usage_limit_per_customer: ";
    var_dump($coupon->usage_limit_per_customer);
    echo "  Type: " . gettype($coupon->usage_limit_per_customer) . "\n";
    echo "  Is null? " . (is_null($coupon->usage_limit_per_customer) ? "YES" : "NO") . "\n";
    echo "  Empty check: " . (empty($coupon->usage_limit_per_customer) ? "YES" : "NO") . "\n";
    echo "\n";
}
