<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'price' => fake()->numberBetween(1000, 100000),
            'discount' => 0,
            'discount_type' => 'percentage',
            'first_purchase_only' => false,
            'delivery_days' => fake()->numberBetween(1, 15),
            'quantity_min' => 1,
            'quantity_max' => 100,
            'step' => 1,
            'package_quantity' => 1,
            'active' => true,
            'tax_id' => Tax::factory(),
            'brand_id' => Brand::factory(),
            'exclude_from_brand_discount' => false,
            'exclude_from_vendor_discount' => false,
            'safety_stock' => 0,
            'inventory_opt_out' => false,
        ];
    }

    public function withDiscount(float $discount = 10): static
    {
        return $this->state(fn () => [
            'discount' => $discount,
            'discount_type' => 'percentage',
        ]);
    }

    public function excludeFromBrandDiscount(): static
    {
        return $this->state(fn () => [
            'exclude_from_brand_discount' => true,
        ]);
    }

    public function excludeFromVendorDiscount(): static
    {
        return $this->state(fn () => [
            'exclude_from_vendor_discount' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
