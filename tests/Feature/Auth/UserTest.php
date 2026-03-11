<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/auth/user');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'created_at']])
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertUnauthorized();
    }

    public function test_response_does_not_expose_password_or_remember_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/auth/user');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_admin_user_profile_returns_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/auth/user');

        $response->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }
}
