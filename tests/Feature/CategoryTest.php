<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_categories_with_product_counts(): void
    {
        $user = User::factory()->staff()->create();
        $category = Category::factory()->create(['name' => 'Hardware']);
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Hardware')
            ->assertJsonPath('data.0.products_count', 3);
    }

    public function test_categories_index_supports_pagination_and_sorting(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Beta Category']);
        Category::factory()->create(['name' => 'Alpha Category']);
        Category::factory()->create(['name' => 'Gamma Category']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/categories?per_page=2&sort_by=name&direction=asc');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.name', 'Alpha Category')
            ->assertJsonPath('data.1.name', 'Beta Category');
    }

    public function test_authenticated_user_can_create_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name'        => 'Office Supplies',
                'description' => 'Pens, paper, and desks.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Office Supplies')
            ->assertJsonPath('data.slug', 'office-supplies');

        $this->assertDatabaseHas('categories', [
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
        ]);
    }

    public function test_duplicate_category_name_auto_generates_unique_slug_suffix(): void
    {
        $user = User::factory()->create();

        // 1st category with name "Footwear"
        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => 'Footwear'])
            ->assertStatus(201);
        $this->assertEquals('footwear', $first->json('data.slug'));

        // 2nd category with identical name "Footwear"
        $second = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => 'Footwear'])
            ->assertStatus(201);
        $this->assertEquals('footwear-2', $second->json('data.slug'));

        // 3rd category with identical name "Footwear"
        $third = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => 'Footwear'])
            ->assertStatus(201);
        $this->assertEquals('footwear-3', $third->json('data.slug'));
    }

    public function test_user_can_view_single_category_with_products(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonCount(2, 'data.products');
    }

    public function test_user_can_update_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Old Title']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => 'New Title',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Title')
            ->assertJsonPath('data.slug', 'new-title');

        $this->assertDatabaseHas('categories', [
            'id'   => $category->id,
            'name' => 'New Title',
            'slug' => 'new-title',
        ]);
    }

    public function test_admin_can_delete_empty_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_staff_cannot_delete_category(): void
    {
        $staff = User::factory()->staff()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_that_contains_products_returns_409(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot delete category because it still contains products. Please reassign or delete the products first.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_store_category_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name']);
    }
}
