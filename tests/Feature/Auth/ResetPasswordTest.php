<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'invalid-token',
            'email'                 => 'john@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['token', 'email', 'password']);
    }
}
