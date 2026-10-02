<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\TraineeTask;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TraineeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_admin_and_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Admin::count());
        $this->assertGreaterThan(0, User::count());
        $this->assertGreaterThan(0, TraineeTask::count());
    }

    public function test_admin_email_comes_from_env(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = Admin::first();

        $this->assertSame(env('ADMIN_EMAIL', 'admin@meel.test'), $admin->email);
        $this->assertSame(env('ADMIN_NAME', 'Program Admin'), $admin->name);
    }

    public function test_seeded_admin_password_is_hashed(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = Admin::first();

        $this->assertNotSame('admin123', $admin->password);
        $this->assertTrue(Hash::check(
            env('ADMIN_PASSWORD', 'admin123'),
            $admin->password,
        ));
    }

    public function test_admin_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Admin::count());
    }

    public function test_trainee_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $traineesAfterFirst = User::count();
        $tasksAfterFirst = TraineeTask::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($traineesAfterFirst, User::count());
        $this->assertSame($tasksAfterFirst, TraineeTask::count());
    }

    public function test_seeded_admin_can_login(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post(route('admin.login.store'), [
            'email' => env('ADMIN_EMAIL', 'admin@meel.test'),
            'password' => env('ADMIN_PASSWORD', 'admin123'),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated('admin');
    }

    public function test_seeded_trainee_can_login(): void
    {
        $this->seed(TraineeSeeder::class);

        $this->post(route('login.store'), [
            'email' => 'andi@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated('web');
    }
}
