<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_dashboard_returns_accurate_metrics_against_known_data(): void
    {
        $user = User::factory()->create();

        // 2 Categories
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();

        // Products with exact known attributes
        // Product 1: active, stock 2 (low stock), price 10.00 -> value 20.00
        Product::factory()->create([
            'category_id' => $cat1->id,
            'name' => 'Item 1',
            'stock' => 2,
            'price' => 10.00,
            'status' => 'active',
        ]);

        // Product 2: active, stock 10 (normal), price 50.00 -> value 500.00
        Product::factory()->create([
            'category_id' => $cat1->id,
            'name' => 'Item 2',
            'stock' => 10,
            'price' => 50.00,
            'status' => 'active',
        ]);

        // Product 3: inactive, stock 1 (low stock), price 100.00 -> value 100.00 (excluded from valuation by default)
        Product::factory()->create([
            'category_id' => $cat2->id,
            'name' => 'Item 3',
            'stock' => 1,
            'price' => 100.00,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_categories', 2)
            ->assertJsonPath('data.total_products', 3)
            ->assertJsonPath('data.active_products', 2)
            ->assertJsonPath('data.low_stock_count', 2)
            ->assertJsonCount(3, 'data.latest_products');

        $this->assertEquals(520.00, $response->json('data.total_inventory_value'));
    }

    public function test_inactive_products_included_when_valuation_policy_enabled(): void
    {
        $user = User::factory()->create();
        Config::set('inventory.valuation_includes_inactive', true);

        // Active: 10 * 10 = 100
        Product::factory()->create([
            'stock' => 10,
            'price' => 10.00,
            'status' => 'active',
        ]);

        // Inactive: 5 * 20 = 100
        Product::factory()->create([
            'stock' => 5,
            'price' => 20.00,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200);
        $this->assertEquals(200.00, $response->json('data.total_inventory_value'));
    }

    public function test_dashboard_low_stock_count_respects_custom_reorder_levels(): void
    {
        $user = User::factory()->create();

        // Product 1: stock 8, reorder_level 15 -> low stock (even though stock > global 5)
        Product::factory()->create([
            'stock' => 8,
            'reorder_level' => 15,
            'status' => 'active',
        ]);

        // Product 2: stock 3, reorder_level 2 -> NOT low stock (even though stock < global 5)
        Product::factory()->create([
            'stock' => 3,
            'reorder_level' => 2,
            'status' => 'active',
        ]);

        // Product 3: stock 4, reorder_level null -> low stock (fallback to global default 5)
        Product::factory()->create([
            'stock' => 4,
            'reorder_level' => null,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.low_stock_count', 2);
    }

    public function test_dashboard_stats_are_cached_and_invalidated_on_product_lifecycle(): void
    {
        $user = User::factory()->staff()->create();
        $category = Category::factory()->create();

        // 1. Initial request primes the cache
        $this->assertFalse(Cache::has('dashboard:stats'));
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard/stats');
        $response->assertStatus(200);
        $this->assertTrue(Cache::has('dashboard:stats'));

        // 2. Creating a product invalidates the cache
        $product = Product::factory()->create(['category_id' => $category->id]);
        $this->assertFalse(Cache::has('dashboard:stats'));

        // Re-prime cache
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard/stats');
        $this->assertTrue(Cache::has('dashboard:stats'));

        // 3. Updating a product invalidates the cache
        $product->update(['stock' => 99]);
        $this->assertFalse(Cache::has('dashboard:stats'));

        // Re-prime cache
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard/stats');
        $this->assertTrue(Cache::has('dashboard:stats'));

        // 4. Soft deleting a product invalidates the cache
        $product->delete();
        $this->assertFalse(Cache::has('dashboard:stats'));

        // Re-prime cache
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard/stats');
        $this->assertTrue(Cache::has('dashboard:stats'));

        // 5. Restoring a product invalidates the cache
        $product->restore();
        $this->assertFalse(Cache::has('dashboard:stats'));
    }
}
