<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainee_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Andi Pratama',
            'email' => 'andi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'andi@example.com']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'andi@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Andi',
            'email' => 'andi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Andi',
            'email' => 'andi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest('web');
    }

    public function test_admin_cannot_register_through_public_registration(): void
    {
        // Route registrasi hanya membuat user (trainee) pada guard web.
        // Akun admin tidak dapat dibuat dari sana.
        $response = $this->post('/register', [
            'name' => 'Fake Admin',
            'email' => 'fake-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('admins', ['email' => 'fake-admin@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'fake-admin@example.com']);
        $this->assertGuest('admin');
    }

    public function test_trainee_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'andi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'andi@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'andi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'andi@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_admin_can_login_with_seeded_account(): void
    {
        Admin::factory()->create([
            'email' => 'admin@meel.test',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@meel.test',
            'password' => 'admin123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
    }

    public function test_admin_cannot_login_via_trainee_login_form(): void
    {
        Admin::factory()->create([
            'email' => 'admin@meel.test',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@meel.test',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertGuest('admin');
    }

    public function test_trainee_can_logout(): void
    {
        $this->authAsTrainee();

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_admin_can_logout(): void
    {
        $this->authAsAdmin();

        $response = $this->post('/admin/logout');

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_root_redirects_trainee_to_dashboard(): void
    {
        $this->authAsTrainee();

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_root_redirects_admin_to_admin_dashboard(): void
    {
        $this->authAsAdmin();

        $this->get('/')->assertRedirect(route('admin.dashboard'));
    }
}
