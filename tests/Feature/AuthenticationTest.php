<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Satu gateway login untuk admin maupun trainee (tabel `users` + kolom `role`).
 */
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

    public function test_registration_always_creates_trainee_account(): void
    {
        $this->post('/register', [
            'name' => 'Fake Admin',
            'email' => 'fake-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'fake-admin@example.com',
            'role' => User::ROLE_TRAINEE,
        ]);
    }

    public function test_admin_role_cannot_be_mass_assigned_via_registration(): void
    {
        // `role` tidak ada di $fillable sehingga diabaikan meski dikirim.
        $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sneaky@example.com',
            'role' => User::ROLE_TRAINEE,
        ]);
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

    public function test_admin_can_login_through_the_same_gateway(): void
    {
        $admin = $this->admin([
            'email' => 'admin@meel.test',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@meel.test',
            'password' => 'admin123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_trainee_login_redirects_to_trainee_dashboard(): void
    {
        User::factory()->create([
            'email' => 'andi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => 'andi@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));
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

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
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

    public function test_authenticated_user_cannot_revisit_login_or_register(): void
    {
        $this->authAsTrainee();

        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/register')->assertRedirect(route('dashboard'));
    }

    public function test_there_is_no_separate_admin_login_route(): void
    {
        // Admin login sekarang memakai halaman yang sama dengan trainee.
        $this->get('/admin/login')->assertNotFound();
    }
}
