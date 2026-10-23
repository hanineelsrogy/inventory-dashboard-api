<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_without_accept_header_returns_401_json(): void
    {
        // Making request without 'Accept: application/json' header
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors'  => null,
            ]);
    }

    public function test_forbidden_action_returns_standard_403_json(): void
    {
        $staff = User::factory()->staff()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => 'This action is unauthorized.',
                'errors'  => null,
            ]);
    }

    public function test_model_not_found_returns_standard_404_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/categories/99999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors'  => null,
            ]);
    }

    public function test_unknown_api_route_returns_standard_404_json(): void
    {
        $response = $this->getJson('/api/v1/totally-unknown-route');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors'  => null,
            ]);
    }

    public function test_validation_error_returns_standard_422_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['category_id', 'name', 'sku', 'price', 'stock'],
            ]);
    }
}
