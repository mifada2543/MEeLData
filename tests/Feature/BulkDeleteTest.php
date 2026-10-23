<?php

namespace Tests\Feature;

use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_selected_tasks(): void
    {
        $admin = $this->admin();
        $tasks = TraineeTask::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.tasks.bulk-delete'), [
                'ids' => $tasks->pluck('id')->all(),
            ]);

        $response->assertRedirect(route('admin.tasks.index'));

        // Soft delete: row tetap ada, deleted_at terisi.
        foreach ($tasks as $task) {
            $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);
        }

        $this->assertSame(0, TraineeTask::count());
    }

    public function test_bulk_delete_only_deletes_selected_tasks(): void
    {
        $admin = $this->admin();
        $selected = TraineeTask::factory()->count(2)->create();
        $kept = TraineeTask::factory()->count(2)->create();

        $this->actingAs($admin, 'web')->post(route('admin.tasks.bulk-delete'), [
            'ids' => $selected->pluck('id')->all(),
        ]);

        $this->assertSame(2, TraineeTask::count());

        foreach ($kept as $task) {
            $this->assertDatabaseHas('trainee_tasks', [
                'id' => $task->id,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_bulk_delete_requires_at_least_one_id(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.bulk-delete'), ['ids' => []]);

        $response->assertSessionHasErrors('ids');
    }

    public function test_bulk_delete_requires_ids_field(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.bulk-delete'), []);

        $response->assertSessionHasErrors('ids');
    }

    public function test_bulk_delete_rejects_invalid_uuid(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.bulk-delete'), ['ids' => ['not-a-uuid']]);

        $response->assertSessionHasErrors('ids.0');
    }

    public function test_bulk_delete_ignores_already_deleted_tasks(): void
    {
        $admin = $this->admin();
        $alreadyDeleted = TraineeTask::factory()->create();
        $alreadyDeleted->delete();

        $active = TraineeTask::factory()->create();

        $this->actingAs($admin, 'web')->post(route('admin.tasks.bulk-delete'), [
            'ids' => [$alreadyDeleted->id, $active->id],
        ]);

        $this->assertSame(0, TraineeTask::count());
        $this->assertSame(2, TraineeTask::withTrashed()->count());
    }

    public function test_bulk_delete_reports_deleted_count(): void
    {
        $admin = $this->admin();
        $tasks = TraineeTask::factory()->count(2)->create();

        $this->actingAs($admin, 'web')
            ->post(route('admin.tasks.bulk-delete'), ['ids' => $tasks->pluck('id')->all()])
            ->assertSessionHas('status', '2 tugas berhasil dihapus.');
    }

    public function test_bulk_delete_is_scoped_by_admin_visibility(): void
    {
        $admin = $this->admin();
        $tasks = TraineeTask::factory()->count(2)->create();

        // Admin melihat seluruh task, sehingga semua id di bawah ini valid.
        $this->actingAs($admin, 'web')->post(route('admin.tasks.bulk-delete'), [
            'ids' => $tasks->pluck('id')->all(),
        ]);

        $this->assertSame(0, TraineeTask::count());
    }

    /**
     * Duplikat ID di parameter `ids[]` harus dibuang (deduplikasi).
     * Bug lama: UI checkbox ganda mengirim UUID yang sama dua kali.
     * Server-side deduplikasi memastikan flash message menunjukkan
     * jumlah task yang benar-benar dihapus (tanpa duplikat).
     */
    public function test_bulk_delete_deduplicates_duplicate_ids(): void
    {
        $admin = $this->admin();
        $task = TraineeTask::factory()->create();

        // Kirim ID yang sama 3 kali
        $response = $this->actingAs($admin, 'web')->post(route('admin.tasks.bulk-delete'), [
            'ids' => [$task->id, $task->id, $task->id],
        ]);

        $response->assertSessionHas('status', '1 tugas berhasil dihapus.');
        $this->assertSame(1, TraineeTask::withTrashed()->count());
    }
}
