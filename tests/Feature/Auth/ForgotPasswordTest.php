<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_for_existing_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_returns_422_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_requires_email_field(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', []);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_token_is_written_to_password_reset_tokens_table(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'john@example.com']);
    }

    public function test_second_request_within_60_seconds_is_throttled(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'john@example.com']);

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }
}
