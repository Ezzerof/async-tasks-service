<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'created_at']])
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.email', 'john@example.com');

        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'role' => 'user']);
    }

    public function test_register_requires_unique_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'wrong',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_response_does_not_expose_sensitive_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_register_fails_with_name_too_long(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => str_repeat('a', 256),
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_register_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'not-an-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_fails_with_missing_name(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_after_register_get_user_is_accessible(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'john@example.com')->first();

        $response = $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/auth/user');

        $response->assertOk();
    }

    public function test_authenticated_user_can_still_register_new_account(): void
    {
        $existing = User::factory()->create();

        $response = $this->actingAs($existing, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/register', [
                'name'                  => 'New User',
                'email'                 => 'newuser@example.com',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(201);
    }
}
