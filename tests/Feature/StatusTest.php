<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
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

    public function test_manual_late_recovery_back_to_progress(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);

        $this->authAsAdmin()
            ->patch(route('admin.tasks.status', $task), ['status' => 'progress'])
            ->assertRedirect();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_late_recovery_to_done_and_canceled(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);

        $this->authAsAdmin()->patch(route('admin.tasks.status', $task), ['status' => 'done']);
        $this->assertDatabaseHas('trainee_tasks', ['id' => $task->id, 'status' => 'done']);

        $this->authAsAdmin()->patch(route('admin.tasks.status', $task), ['status' => 'canceled']);
        $this->assertDatabaseHas('trainee_tasks', ['id' => $task->id, 'status' => 'canceled']);
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

        $response->assertOk()->assertSee('Done');
    }

    public function test_enum_exposes_manual_values_without_late(): void
    {
        $this->assertSame(['progress', 'done', 'canceled'], TaskStatus::manualValues());
        $this->assertContains('late', TaskStatus::values());
        $this->assertSame('Late', TaskStatus::Late->label());
    }
}
