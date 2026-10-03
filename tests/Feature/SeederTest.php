<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
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

        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->count());
        $this->assertGreaterThan(0, User::where('role', User::ROLE_TRAINEE)->count());
        $this->assertGreaterThan(0, TraineeTask::count());
    }

    public function test_admin_is_created_inside_users_table_with_admin_role(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->assertSame(env('ADMIN_EMAIL', 'admin@meel.test'), $admin->email);
        $this->assertSame(env('ADMIN_NAME', 'Program Admin'), $admin->name);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_seeded_admin_password_is_hashed(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

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

        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->count());
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

    public function test_seeded_admin_can_login_through_the_shared_gateway(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post(route('login.store'), [
            'email' => env('ADMIN_EMAIL', 'admin@meel.test'),
            'password' => env('ADMIN_PASSWORD', 'admin123'),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated('web');
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

    public function test_seeded_tasks_are_owned_by_trainees(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        // Admin tidak boleh memiliki task.
        $this->assertSame(0, TraineeTask::where('trainee_id', $admin->id)->count());
        $this->assertGreaterThan(0, TraineeTask::whereIn('status', [
            TaskStatus::Progress->value,
            TaskStatus::Done->value,
        ])->count());
    }
}
