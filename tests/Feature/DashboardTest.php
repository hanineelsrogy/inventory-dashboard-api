<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
