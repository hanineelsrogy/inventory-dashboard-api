<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'sku' => strtoupper(fake()->unique()->bothify('PROD-####-??')),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(10, 100),
            'status' => 'active',
        ];
    }

    public function lowStock(int $stock = 2): static
    {
        return $this->state(fn () => [
            'stock' => $stock,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }
}
