<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_credentials_from_config(): void
    {
        config([
            'auth.admin.name' => 'Test Admin',
            'auth.admin.email' => 'Admin@Test.example',
            'auth.admin.password' => 'Secret-From-Env-Only',
        ]);

        User::query()->create([
            'name' => config('auth.admin.name'),
            'email' => config('auth.admin.email'),
            'password' => config('auth.admin.password'),
            'is_admin' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'admin@test.example',
            'password' => 'Secret-From-Env-Only',
        ]);

        $response->assertRedirect(route('admin.reports.index'));
        $this->assertAuthenticated();
    }

    public function test_non_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
            'is_admin' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
