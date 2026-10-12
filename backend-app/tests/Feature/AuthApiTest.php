<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_access_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com', 'password' => Hash::make('secret-password')]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->getJson('/api/dashboard')->assertOk();
    }

    public function test_invalid_login_is_rejected_and_dashboard_is_protected(): void
    {
        User::factory()->create(['email' => 'operator@example.com', 'password' => Hash::make('secret-password')]);

        $this->postJson('/api/auth/login', ['email' => 'operator@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable();

        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
