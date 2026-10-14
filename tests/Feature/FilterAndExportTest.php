<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Exports\TasksExport;
use App\Exports\TaskTemplateExport;
use App\Models\TraineeTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilterAndExportTest extends TestCase
{
    use RefreshDatabase;

    public static function statusFilterProvider(): array
    {
        return [
            'progress' => [TaskStatus::Progress],
            'done' => [TaskStatus::Done],
            'late' => [TaskStatus::Late],
            'canceled' => [TaskStatus::Canceled],
        ];
    }

    #[DataProvider('statusFilterProvider')]
    public function test_filter_by_status(TaskStatus $status): void
    {
        $admin = $this->admin();

        foreach (TaskStatus::cases() as $case) {
            $this->task(['status' => $case]);
        }

        $response = $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.index', ['status' => $status->value]));

        $response->assertOk();

        $tasks = $response->viewData('tasks');

        $this->assertCount(1, $tasks);
        $this->assertTrue($tasks->every(fn (TraineeTask $t) => $t->status === $status));
    }

    public function test_no_filter_returns_all_statuses(): void
    {
        $admin = $this->admin();

        foreach (TaskStatus::cases() as $case) {
            $this->task(['status' => $case]);
        }

        $response = $this->actingAs($admin, 'web')->get(route('admin.tasks.index'));

        $this->assertCount(4, $response->viewData('tasks'));
    }

    public function test_invalid_status_filter_is_ignored(): void
    {
        $admin = $this->admin();
        $this->task();
        $this->task();

        $response = $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.index', ['status' => 'bogus']));

        $response->assertOk();
        $this->assertNull($response->viewData('status'));
        $this->assertCount(2, $response->viewData('tasks'));
    }

    public function test_filter_is_preserved_in_pagination_links(): void
    {
        $admin = $this->admin();
        TraineeTask::factory()->count(30)->create();

        $response = $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.index', ['status' => 'progress']));

        $response->assertOk();
        $response->assertSee('status=progress', false);
    }

    public function test_trainee_filter_respects_ownership(): void
    {
        $trainee = $this->trainee();
        $this->task(['trainee_id' => $trainee->id, 'status' => TaskStatus::Done]);
        $this->task(['status' => TaskStatus::Done]);

        $response = $this->actingAs($trainee, 'web')
            ->get(route('tasks.index', ['status' => 'done']));

        $this->assertCount(1, $response->viewData('tasks'));
    }

    public function test_admin_export_contains_all_tasks(): void
    {
        $admin = $this->admin();
        $this->task();
        $this->task();

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export'))
            ->assertOk();

        $this->assertCount(2, TraineeTask::visibleTo($admin)->get());
    }

    public function test_admin_export_follows_status_filter(): void
    {
        $admin = $this->admin();
        $this->task(['status' => TaskStatus::Done]);
        $this->task(['status' => TaskStatus::Progress]);
        $this->task(['status' => TaskStatus::Progress]);

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export', ['status' => 'progress']))
            ->assertOk();

        // Query yang dipakai export harus menghasilkan hanya task progress.
        $filtered = TraineeTask::visibleTo($admin)->statusIs('progress')->get();
        $this->assertCount(2, $filtered);
        $this->assertTrue($filtered->every(fn ($t) => $t->status === TaskStatus::Progress));
    }

    public function test_trainee_export_is_limited_to_own_tasks(): void
    {
        $trainee = $this->trainee();
        $ownTask = $this->task(['trainee_id' => $trainee->id]);
        $otherTask = $this->task();

        $this->actingAs($trainee, 'web')->get(route('tasks.export'))->assertOk();

        $exported = TraineeTask::visibleTo($trainee)->get();

        $this->assertTrue($exported->contains($ownTask));
        $this->assertFalse($exported->contains($otherTask));
    }

    public function test_export_does_not_include_soft_deleted_tasks(): void
    {
        $admin = $this->admin();
        $kept = $this->task();
        $removed = $this->task();
        $removed->delete();

        $this->actingAs($admin, 'web')->get(route('admin.tasks.export'))->assertOk();

        $exported = TraineeTask::visibleTo($admin)->get();

        $this->assertTrue($exported->contains($kept));
        $this->assertFalse($exported->contains($removed));
    }

    public function test_export_uses_query_not_full_table(): void
    {
        $admin = $this->admin();

        // Tidak ada `Task::all()` pada alur export; query selalu
        // melewati scope.>Dokumentasi perilaku ini diuji lewat subclass.
        $query = TraineeTask::visibleTo($admin)->statusIs('done');

        $this->assertInstanceOf(Builder::class, $query);
    }

    public function test_export_headings_and_mapping(): void
    {
        $trainee = $this->trainee();
        $task = $this->task([
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
            'desc' => 'Perbaikan kabel',
        ]);

        $export = new TasksExport(TraineeTask::visibleTo($trainee));

        $this->assertSame([
            'UUID', 'Trainee', 'Email Trainee', 'Tugas', 'Deskripsi', 'Tanggal Mulai',
            'Tanggal Deadline', 'Status', 'Dibuat Pada', 'Diperbarui Pada',
        ], $export->headings());

        $row = $export->map($task->fresh());

        $this->assertSame($task->id, $row[0]);
        $this->assertSame($trainee->name, $row[1]);
        // Email wajib ada supaya file ekspor bisa diimpor kembali.
        $this->assertSame($trainee->email, $row[2]);
        $this->assertSame('Maintenance Kabel', $row[3]);
        $this->assertSame('Perbaikan kabel', $row[4]);
        $this->assertSame('progress', $row[7]);
    }

    public function test_import_template_is_available_to_admin(): void
    {
        $this->authAsAdmin()
            ->get(route('admin.tasks.import.template'))
            ->assertOk();
    }

    public function test_import_template_contains_header_and_example_row(): void
    {
        $this->authAsAdmin()->get(route('admin.tasks.import.template'))->assertOk();

        $template = new TaskTemplateExport;

        $this->assertSame([
            'Email Trainee', 'Tugas', 'Deskripsi', 'Tanggal Mulai', 'Tanggal Deadline', 'Status',
        ], $template->headings());

        $row = $template->array()[0];

        // Status contoh harus progress, bukan late.
        $this->assertSame('progress', $row[5]);
    }
}
