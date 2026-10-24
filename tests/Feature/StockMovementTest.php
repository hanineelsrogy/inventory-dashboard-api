<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_with_stock_records_initial_stock_movement(): void
    {
        $user = User::factory()->staff()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Gaming Mouse',
                'sku' => 'TECH-MOUSE-001',
                'price' => 49.99,
                'stock' => 25,
                'status' => 'active',
                'reason' => 'Warehouse intake shipment A1',
            ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productId,
            'user_id' => $user->id,
            'change' => 25,
            'stock_before' => 0,
            'stock_after' => 25,
            'reason' => 'Warehouse intake shipment A1',
        ]);
    }

    public function test_updating_product_stock_records_delta_movement_with_custom_reason(): void
    {
        $user = User::factory()->staff()->create();
        $product = Product::factory()->create(['stock' => 50]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'stock' => 42,
                'reason' => '8 units damaged in transit',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'change' => -8,
            'stock_before' => 50,
            'stock_after' => 42,
            'reason' => '8 units damaged in transit',
        ]);

        $this->assertEquals(42, $product->fresh()->stock);
    }

    public function test_updating_product_without_stock_change_does_not_create_movement(): void
    {
        $user = User::factory()->staff()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);

        // Clear any previous movements (e.g. from creation if factory created it)
        $initialMovementCount = $product->movements()->count();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'price' => 25.00,
            ]);

        $response->assertStatus(200);

        $this->assertEquals($initialMovementCount, $product->movements()->count());
    }

    public function test_authenticated_user_can_view_product_movements_history(): void
    {
        $user = User::factory()->staff()->create();
        $product = Product::factory()->create(['stock' => 10]);

        // Perform stock updates through API
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'stock' => 25,
                'reason' => 'Restock shipment',
            ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'stock' => 20,
                'reason' => 'Sold 5 units',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/products/{$product->id}/movements");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.change', -5)
            ->assertJsonPath('data.0.stock_after', 20)
            ->assertJsonPath('data.1.change', 15)
            ->assertJsonPath('data.1.stock_after', 25);
    }

    public function test_guest_cannot_view_product_movements(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}/movements");

        $response->assertStatus(401);
    }

    public function test_movements_for_nonexistent_product_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products/99999/movements');

        $response->assertStatus(404);
    }
}
