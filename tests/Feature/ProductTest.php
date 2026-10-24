<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_products_with_clean_pagination(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(15)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);

        // Verify NO double wrapping: data is a flat list of products
        $this->assertIsArray($response->json('data'));
        $this->assertCount(10, $response->json('data'));
        $this->assertArrayNotHasKey('data', $response->json('data'));
    }

    public function test_can_filter_products_by_category(): void
    {
        $user = User::factory()->create();
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $cat1->id]);
        Product::factory()->count(3)->create(['category_id' => $cat2->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/products?category_id={$cat1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_filter_products_by_status(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(4)->create(['status' => 'active']);
        Product::factory()->count(2)->create(['status' => 'inactive']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?status=active');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 4);
    }

    public function test_can_filter_products_by_low_stock(): void
    {
        $user = User::factory()->create();
        // Config threshold is 5
        Product::factory()->create(['stock' => 2]); // low
        Product::factory()->create(['stock' => 4]); // low
        Product::factory()->create(['stock' => 5]); // NOT low
        Product::factory()->create(['stock' => 10]); // NOT low

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?low_stock=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_products_by_low_stock_respects_custom_reorder_level(): void
    {
        $user = User::factory()->create();

        // 1. Custom reorder_level 15, stock 8 -> low stock (even though stock > global default 5)
        $p1 = Product::factory()->create(['name' => 'High Threshold Item', 'stock' => 8, 'reorder_level' => 15]);

        // 2. Custom reorder_level 2, stock 3 -> NOT low stock (even though stock < global default 5)
        Product::factory()->create(['name' => 'Low Threshold Item', 'stock' => 3, 'reorder_level' => 2]);

        // 3. Fallback: reorder_level null, stock 4 -> low stock (< 5)
        $p3 = Product::factory()->create(['name' => 'Default Low Item', 'stock' => 4, 'reorder_level' => null]);

        // 4. Fallback: reorder_level null, stock 10 -> NOT low stock (>= 5)
        Product::factory()->create(['name' => 'Default High Item', 'stock' => 10, 'reorder_level' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?low_stock=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $returnedNames = collect($response->json('data'))->pluck('name');
        $this->assertTrue($returnedNames->contains('High Threshold Item'));
        $this->assertTrue($returnedNames->contains('Default Low Item'));
        $this->assertFalse($returnedNames->contains('Low Threshold Item'));
    }

    public function test_product_can_be_created_and_updated_with_reorder_level(): void
    {
        $user = User::factory()->staff()->create();
        $category = Category::factory()->create();

        $createResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Wireless Keyboard',
                'sku' => 'KEY-0099',
                'price' => 59.99,
                'stock' => 20,
                'reorder_level' => 15,
                'status' => 'active',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.reorder_level', 15);

        $productId = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/products/{$productId}", [
                'reorder_level' => 8,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.reorder_level', 8);

        $this->assertEquals(8, Product::find($productId)->reorder_level);
    }

    public function test_search_escapes_sql_wildcards_percent_and_underscore(): void
    {
        $user = User::factory()->create();

        // Create product with literal % in sku and literal _ in name
        $special = Product::factory()->create([
            'name' => 'Percent_Special',
            'sku' => 'SPEC%100',
        ]);

        // Regular products that should not match literal %
        Product::factory()->create(['name' => 'Normal Item 1', 'sku' => 'NORM-001']);
        Product::factory()->create(['name' => 'Normal Item 2', 'sku' => 'NORM-002']);

        // Search for literal '%'
        $percentSearch = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?search='.urlencode('%'));

        $percentSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $special->id);

        // Search for literal '_'
        $underscoreSearch = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?search=_');

        $underscoreSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $special->id);
    }

    public function test_sorting_whitelisted_columns(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Carrot', 'price' => 10.00]);
        Product::factory()->create(['name' => 'Apple', 'price' => 50.00]);
        Product::factory()->create(['name' => 'Banana', 'price' => 25.00]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?sort_by=price&direction=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Carrot')
            ->assertJsonPath('data.1.name', 'Banana')
            ->assertJsonPath('data.2.name', 'Apple');
    }

    public function test_per_page_cap_at_50_rejects_greater_with_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products?per_page=51');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_authenticated_user_can_create_product(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Mechanical Keyboard',
                'sku' => 'TECH-KEY-001',
                'price' => 89.99,
                'stock' => 15,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Mechanical Keyboard')
            ->assertJsonPath('data.sku', 'TECH-KEY-001');

        $this->assertDatabaseHas('products', [
            'sku' => 'TECH-KEY-001',
        ]);
    }

    public function test_user_can_view_single_product_with_category(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category.id', $product->category_id);
    }

    public function test_user_can_update_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20.00]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'price' => 35.50,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.price', '35.50');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 35.50,
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_restore_soft_deleted_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id);

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_staff_cannot_restore_soft_deleted_product_returns_403(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/restore");

        $response->assertStatus(403);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_cannot_restore_product_if_category_is_soft_deleted_returns_409(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $product->delete();
        $category->delete();

        // Attempting to restore product while category is deleted must fail
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/restore");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot restore product because its category has been deleted. Restore the category first.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // Restore category first
        $category->restore();

        // Now restoring product succeeds
        $retryResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/restore");

        $retryResponse->assertStatus(200);
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_staff_cannot_delete_product_returns_403(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_store_product_validation_failures(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'name', 'sku', 'price', 'stock']);
    }
}
