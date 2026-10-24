<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_export_inventory_report(): void
    {
        $response = $this->getJson('/api/v1/reports/inventory/export');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_export_inventory_report_as_csv(): void
    {
        $user = User::factory()->staff()->create();
        $category = Category::factory()->create(['name' => 'Peripherals']);

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'sku' => 'KB-MECH-01',
            'price' => 99.50,
            'stock' => 10,
            'reorder_level' => 15,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/inventory/export');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=inventory-report-', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();

        // Verify CSV header row
        $this->assertStringContainsString('ID,Name,SKU,Category,Price,Stock,"Reorder Level","Inventory Value",Status,"Low Stock Alert","Created At"', $content);

        // Verify product row data
        $this->assertStringContainsString('Mechanical Keyboard', $content);
        $this->assertStringContainsString('KB-MECH-01', $content);
        $this->assertStringContainsString('Peripherals', $content);
        $this->assertStringContainsString('99.50', $content);
        $this->assertStringContainsString('995.00', $content); // 99.50 * 10
        $this->assertStringContainsString('Yes', $content); // Stock (10) < Reorder Level (15)
    }

    public function test_export_evaluates_low_stock_with_default_fallback(): void
    {
        $user = User::factory()->staff()->create();

        // Product without custom reorder_level (falls back to global default 5)
        Product::factory()->create([
            'name' => 'Surplus Cable',
            'stock' => 8,
            'reorder_level' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/inventory/export');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Surplus Cable', $content);
        $this->assertStringContainsString('No', $content); // Stock (8) >= default (5)
    }

    public function test_export_filters_by_category(): void
    {
        $user = User::factory()->staff()->create();
        $cat1 = Category::factory()->create(['name' => 'Audio']);
        $cat2 = Category::factory()->create(['name' => 'Monitors']);

        Product::factory()->create([
            'category_id' => $cat1->id,
            'name' => 'Studio Headphones',
        ]);

        Product::factory()->create([
            'category_id' => $cat2->id,
            'name' => '4K Ultra Display',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/inventory/export?category_id='.$cat1->id);

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Studio Headphones', $content);
        $this->assertStringNotContainsString('4K Ultra Display', $content);
    }

    public function test_export_filters_by_low_stock(): void
    {
        $user = User::factory()->staff()->create();

        Product::factory()->create([
            'name' => 'Scarce Item',
            'stock' => 2,
            'reorder_level' => 10,
        ]);

        Product::factory()->create([
            'name' => 'Plentiful Item',
            'stock' => 50,
            'reorder_level' => 10,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/inventory/export?low_stock=1');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Scarce Item', $content);
        $this->assertStringNotContainsString('Plentiful Item', $content);
    }

    public function test_products_export_alias_route_works(): void
    {
        $user = User::factory()->staff()->create();
        Product::factory()->create(['name' => 'Alias Item']);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/products/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('Alias Item', $response->streamedContent());
    }
}
