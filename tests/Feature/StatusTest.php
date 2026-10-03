<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    public static function manualStatusProvider(): array
    {
        return [
            'progress' => ['progress'],
            'done' => ['done'],
            'canceled' => ['canceled'],
        ];
    }

    #[DataProvider('manualStatusProvider')]
    public function test_valid_manual_status_is_accepted(string $status): void
    {
        $task = $this->task(['status' => TaskStatus::Progress]);

        $this->authAsAdmin()
            ->patch(route('admin.tasks.status', $task), ['status' => $status])
            ->assertRedirect();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => $status,
        ]);
    }

    public function test_late_cannot_be_set_manually_by_admin(): void
    {
        $task = $this->task(['status' => TaskStatus::Progress]);

        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->patch(route('admin.tasks.status', $task), ['status' => 'late'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_late_cannot_be_set_manually_by_trainee(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['trainee_id' => $trainee->id, 'status' => TaskStatus::Progress]);

        $this->actingAs($trainee, 'web')
            ->from(route('tasks.index'))
            ->patch(route('tasks.status', $task), ['status' => 'late'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $task = $this->task(['status' => TaskStatus::Progress]);

        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->patch(route('admin.tasks.status', $task), ['status' => 'unknown'])
            ->assertSessionHasErrors('status');
    }

    public function test_status_is_required(): void
    {
        $task = $this->task();

        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->patch(route('admin.tasks.status', $task), [])
            ->assertSessionHasErrors('status');
    }

    public function test_trainee_can_update_status_of_own_task_inline(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['trainee_id' => $trainee->id, 'status' => TaskStatus::Progress]);

        $this->actingAs($trainee, 'web')
            ->patch(route('tasks.status', $task), ['status' => 'done'])
            ->assertRedirect();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Done->value,
        ]);
    }

    public function test_late_task_cannot_be_changed_back_to_progress(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);

        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->patch(route('admin.tasks.status', $task), ['status' => 'progress'])
            ->assertRedirect(route('admin.tasks.index'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_late_task_cannot_be_changed_to_done_or_canceled(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);

        foreach (['done', 'canceled'] as $target) {
            $this->authAsAdmin()
                ->from(route('admin.tasks.index'))
                ->patch(route('admin.tasks.status', $task), ['status' => $target])
                ->assertSessionHasErrors('status');

            $this->assertDatabaseHas('trainee_tasks', [
                'id' => $task->id,
                'status' => TaskStatus::Late->value,
            ]);
        }
    }

    public function test_late_task_cannot_be_changed_by_trainee(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['status' => TaskStatus::Late, 'trainee_id' => $trainee->id]);

        $this->authAsTrainee($trainee)
            ->from(route('tasks.index'))
            ->patch(route('tasks.status', $task), ['status' => 'done'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_late_rule_does_not_leak_existence_of_other_trainee_task(): void
    {
        // Trainee*>(task milik orang lain) harus tetap 403, BUKAN 422.
        // Kalau validasinya mencari task tanpa scope `visibleTo`, pesan
        // "Terlambat tidak dapat diubah" akan membocorkan keberadaan task.
        $task = $this->task(['status' => TaskStatus::Late]);

        $this->authAsTrainee()
            ->patch(route('tasks.status', $task), ['status' => 'done'])
            ->assertForbidden();
    }

    public function test_late_task_status_select_is_not_interactive(): void
    {
        // Status milik sistem dirender sebagai badge statis di halaman list,
        // bukan sebagai pemicu dropdown.
        TraineeTask::factory()->create(['status' => TaskStatus::Late]);

        $html = $this->authAsAdmin()
            ->get(route('admin.tasks.index', ['status' => 'late']))
            ->assertOk()
            ->getContent();

        preg_match('/<td[^>]*class="table-body-cell w-40">(.*?)<\/td>/s', $html, $cell);
        $this->assertNotEmpty($cell, 'Sel status tidak ditemukan.');

        // Tidak ada pemicu dropdown sama sekali.
        $this->assertStringNotContainsString('aria-haspopup="menu"', $cell[1]);
        $this->assertStringNotContainsString('<noscript>', $cell[1]);
        $this->assertStringNotContainsString('<button', $cell[1]);
        $this->assertStringNotContainsString('name="status"', $cell[1]);

        // Badge tetap tampil (dengan label + penjelasan) agar informasi
        // tidak hilang dan alasan status non-interaktifnya jelas.
        $this->assertStringContainsString('Terlambat', $cell[1]);
        $this->assertStringContainsString('diatur otomatis oleh sistem', $cell[1]);
    }

    public function test_non_late_task_status_select_is_interactive(): void
    {
        TraineeTask::factory()->create(['status' => TaskStatus::Progress]);

        $html = $this->authAsAdmin()
            ->get(route('admin.tasks.index', ['status' => 'progress']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-haspopup="menu"', $html);
    }

    public function test_status_select_does_not_offer_late_option(): void
    {
        $task = $this->task(['status' => TaskStatus::Progress]);

        // Komponen inline diuji terpisah karena halaman list memang
        // memuat nilai "late" pada filter status (memfilter late itu valid).
        $html = (string) $this->blade('<x-status-select :task="$task" route-prefix="admin." />', [
            'task' => $task,
        ]);

        foreach (TaskStatus::manualCases() as $case) {
            $this->assertStringContainsString('value="'.$case->value.'"', $html);
        }

        $this->assertStringNotContainsString('value="late"', $html);
    }

    public function test_status_badge_shows_label_text(): void
    {
        $task = $this->task(['status' => TaskStatus::Done]);

        $response = $this->authAsAdmin()->get(route('admin.tasks.index'));

        $response->assertOk()->assertSee('Selesai');
    }

    public function test_enum_exposes_manual_values_without_late(): void
    {
        $this->assertSame(['progress', 'done', 'canceled'], TaskStatus::manualValues());
        $this->assertContains('late', TaskStatus::values());
        $this->assertSame('Terlambat', TaskStatus::Late->label());
    }
}
