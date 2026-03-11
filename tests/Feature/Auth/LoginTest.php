<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', [
                'email'    => 'john@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'created_at']])
            ->assertJsonPath('data.email', 'john@example.com');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'john@example.com', 'password' => 'password123']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_fails_with_empty_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'john@example.com',
            'password' => '',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_after_login_get_user_is_accessible(): void
    {
        $user = User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'password123',
        ]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', [
                'email'    => 'john@example.com',
                'password' => 'password123',
            ]);

        $response = $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/auth/user');

        $response->assertOk();
    }

    public function test_admin_user_login_returns_admin_role(): void
    {
        User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', [
                'email'    => 'admin@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()->assertJsonPath('data.role', 'admin');
    }
}
