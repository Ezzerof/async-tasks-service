<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/logout');

        $response->assertNoContent();
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    public function test_get_user_returns_401_after_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/logout');

        // Reset guard cache so the next request has no authenticated user
        $this->app->make('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertUnauthorized();
    }

    public function test_second_logout_returns_401(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/logout');

        // Reset guard cache so the next request has no authenticated user
        $this->app->make('auth')->forgetGuards();

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }
}
