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

    public function test_reset_fails_with_short_new_password(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_reset_fails_with_password_mismatch(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_reset_fails_with_wrong_email_for_token(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        User::factory()->create(['email' => 'other@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'other@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    public function test_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'anotherpassword',
            'password_confirmation' => 'anotherpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_new_password_works_for_login_after_reset(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', [
                'email'    => 'john@example.com',
                'password' => 'newpassword123',
            ]);

        $response->assertOk();
    }

    public function test_old_password_rejected_after_reset(): void
    {
        $user = User::factory()->create([
            'email'    => 'john@example.com',
            'password' => 'oldpassword123',
        ]);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'john@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', [
                'email'    => 'john@example.com',
                'password' => 'oldpassword123',
            ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Invalid credentials');
    }
}
