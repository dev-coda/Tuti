<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'active' => true,
            'discount' => 0,
            'discount_type' => 'percentage',
            'first_purchase_only' => false,
            'vendor_id' => Vendor::factory(),
        ];
    }

    public function withDiscount(float $discount = 10, string $type = 'percentage'): static
    {
        return $this->state(fn () => [
            'discount' => $discount,
            'discount_type' => $type,
        ]);
    }
}
