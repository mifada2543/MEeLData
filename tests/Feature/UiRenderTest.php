<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Memastikan halaman yang dirender benar-benar dapat dibuka (200) untuk
 * kedua guard, termasuk view yang memakai komponen Alpine/Blade.
 */
class UiRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_render(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Sign in')
            ->assertSee('name="_token"', false);

        $this->get(route('admin.login'))->assertOk()
            ->assertSee('Admin email')
            ->assertSee('provisioned by seeder');

        $this->get(route('register'))->assertOk()
            ->assertSee('Create account')
            ->assertSee('Confirm password');
    }

    public function test_app_layout_renders_for_admin(): void
    {
        $admin = $this->admin(['name' => 'Rina']);

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Rina')
            ->assertSee('Dashboard');

        $this->actingAs($admin, 'admin')->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Tasks');

        $this->actingAs($admin, 'admin')->get(route('admin.tasks.create'))->assertOk()
            ->assertSee('Create Task');
    }

    public function test_app_layout_renders_for_trainee(): void
    {
        $trainee = $this->trainee(['name' => 'Andi']);

        $this->actingAs($trainee, 'web')->get(route('dashboard'))->assertOk()
            ->assertSee('Andi')
            ->assertSee('My Tasks');

        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk();
        $this->actingAs($trainee, 'web')->get(route('tasks.create'))->assertOk()
            ->assertSee('Create Task');
    }

    public function test_edit_form_renders_with_existing_values(): void
    {
        $trainee = $this->trainee(['name' => 'Andi Pratama']);
        $task = TraineeTask::factory()->create([
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
            'desc' => 'Perbaikan kabel jaringan.',
        ]);

        $this->actingAs($trainee, 'web')->get(route('tasks.edit', $task))->assertOk()
            ->assertSee('Edit Task')
            ->assertSee('Maintenance Kabel')
            ->assertSee('Andi Pratama');
    }

    public function test_trainee_select_is_disabled_and_shows_own_name(): void
    {
        $trainee = $this->trainee(['name' => 'Andi Pratama']);

        $response = $this->actingAs($trainee, 'web')->get(route('tasks.create'));

        $response->assertOk();
        // Field trainee untuk trainee adalah disabled/read-only.
        $response->assertSee('disabled', false);
        $response->assertSee('Andi Pratama');
    }

    public function test_admin_create_form_has_trainee_select_with_all_trainees(): void
    {
        $this->trainee(['name' => 'Andi Pratama']);
        $this->trainee(['name' => 'Budi Santoso']);

        $this->authAsAdmin()->get(route('admin.tasks.create'))->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('Budi Santoso')
            ->assertSee('<select', false);
    }

    public function test_admin_task_list_hides_import_for_trainee(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Import Tasks', false)
            ->assertSee('Delete Selected', false);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Import Tasks', false)
            ->assertDontSee('Delete Selected', false)
            ->assertDontSee('bulk-delete-form', false);
    }

    public function test_admin_task_list_shows_trainee_column_and_delete_action(): void
    {
        $trainee = $this->trainee(['name' => 'Budi Santoso']);
        TraineeTask::factory()->create([
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
        ]);

        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Edit')
            ->assertSee('Delete');
    }

    public function test_empty_state_is_shown_without_tasks(): void
    {
        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('No tasks found');

        $this->authAsAdmin()->get(route('admin.tasks.index', ['status' => 'done']))->assertOk()
            ->assertSee('No tasks match the selected filter')
            ->assertSee('reset the filter');
    }

    public function test_filter_modal_is_available_to_both_roles(): void
    {
        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Export Result', false);

        $this->authAsTrainee()->get(route('tasks.index'))->assertOk()
            ->assertSee('Export Result', false);
    }

    public function test_status_badge_renders_all_status_labels(): void
    {
        $admin = $this->admin();

        foreach (TaskStatus::cases() as $case) {
            TraineeTask::factory()->create(['status' => $case]);
        }

        $this->actingAs($admin, 'admin')->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Progress')
            ->assertSee('Done')
            ->assertSee('Late')
            ->assertSee('Canceled');
    }

    public function test_403_page_renders_for_trainee_on_admin_route(): void
    {
        $this->authAsTrainee()
            ->get(route('admin.tasks.index'))
            ->assertForbidden()
            ->assertSee('Forbidden');
    }

    public function test_validation_errors_are_shown_near_fields(): void
    {
        $this->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), ['task' => '', 'start_date' => '', 'deadline' => ''])
            ->assertRedirect(route('admin.tasks.create'))
            ->assertSessionHasErrors('task');

        $this->followingRedirects()
            ->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), ['task' => '', 'start_date' => '', 'deadline' => ''])
            ->assertOk()
            ->assertSee('The task field is required.');
    }

    public function test_import_result_section_renders_errors(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['trainee_email', 'task', 'description', 'start_date', 'deadline', 'status'], null, 'A1');
        $sheet->fromArray(['nobody@example.com', 'X', 'D', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->authAsAdmin()
            ->post(route('admin.tasks.import'), ['file' => new UploadedFile($path, 'tasks.xlsx', null, null, true)]);

        $response->assertRedirect();

        $this->followingRedirects()
            ->authAsAdmin()
            ->get(session()->previousUrl() ?: route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Import Result')
            ->assertSee('0 imported', false)
            ->assertSee('was not found')
            ->assertSee('nobody@example.com');

        @unlink($path);
    }

    public function test_flash_toast_renders_after_create(): void
    {
        $trainee = $this->trainee();

        $this->actingAs($trainee, 'web')
            ->followingRedirects()
            ->post(route('tasks.store'), [
                'task' => 'Task with toast',
                'desc' => 'desc',
                'start_date' => '2026-10-01 09:00:00',
                'deadline' => '2026-10-05 17:00:00',
            ])
            ->assertOk()
            ->assertSee('Task created successfully.');
    }
}
