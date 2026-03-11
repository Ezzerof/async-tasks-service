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
}
