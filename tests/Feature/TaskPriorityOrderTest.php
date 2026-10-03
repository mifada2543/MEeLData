<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Http\Controllers\TraineeTaskController;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Urutan prioritas 5 tingkat:
 *
 * 1. Sedang Dikerjakan   - progress, deadline belum lewat
 * 2. Selesai            - done
 * 3. Dibatalkan         - canceled
 * 4. Lewat Deadline     - progress, deadline lewat (menunggu scheduler)
 * 5. Terlambat          - late
 */
class TaskPriorityOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_priority_scope_returns_expected_sequence(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $order = [
            ['progress', now()->addDays(2)],
            ['done', now()->addDays(2)],
            ['canceled', now()->addDays(2)],
            ['progress', now()->subDays(1)],
            ['late', now()->subDays(1)],
        ];

        $expected = [
            TaskStatus::Progress->value,
            TaskStatus::Done->value,
            TaskStatus::Canceled->value,
            TaskStatus::Progress->value,
            TaskStatus::Late->value,
        ];

        foreach ($order as $index => [$status, $deadline]) {
            $this->task(['status' => $status, 'deadline' => $deadline]);
        }

        $admin = $this->admin();

        $statuses = TraineeTask::query()
            ->visibleTo($admin)
            ->byWorkPriority()
            ->pluck('status')
            ->map(fn ($status) => $status->value)
            ->all();

        $this->assertSame($expected, $statuses);
    }

    public function test_finished_tasks_with_past_deadline_stay_in_high_group(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $done = $this->task(['status' => TaskStatus::Done, 'deadline' => now()->subDays(5)]);
        $canceled = $this->task(['status' => TaskStatus::Canceled, 'deadline' => now()->subDays(5)]);
        $overdue = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->subDays(5)]);

        // `done` dan `canceled` tidak boleh ikut turun ke kelompok 4.
        $this->assertSame(2, $done->workPriority());
        $this->assertSame(3, $canceled->workPriority());
        $this->assertSame(4, $overdue->workPriority());

        $admin = $this->admin();

        $ids = TraineeTask::query()
            ->visibleTo($admin)
            ->byWorkPriority()
            ->pluck('id')
            ->all();

        $this->assertSame(
            [$done->id, $canceled->id, $overdue->id],
            $ids
        );
    }

    public function test_work_priority_matches_the_sql_scope(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $cases = [
            [TaskStatus::Progress, now()->addDays(1), 1],
            [TaskStatus::Done, now()->addDays(1), 2],
            [TaskStatus::Canceled, now()->addDays(1), 3],
            [TaskStatus::Progress, now()->subDays(1), 4],
            [TaskStatus::Late, now()->addDays(1), 5],
        ];

        $admin = $this->admin();

        foreach ($cases as [$status, $deadline, $expected]) {
            $task = $this->task(['status' => $status, 'deadline' => $deadline]);

            $this->assertSame($expected, $task->workPriority());

            // Urutan di SQL harus monoton naik sesuai workPriority.
            $position = array_search(
                $task->id,
                TraineeTask::query()->visibleTo($admin)->byWorkPriority()->pluck('id')->all(),
                true
            );

            $this->assertIsInt($position);
        }
    }

    public function test_trainee_scope_still_applies_with_priority_ordering(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $mine = $this->trainee();
        $other = $this->trainee();

        $mineLate = $this->task([
            'trainee_id' => $mine->id,
            'status' => TaskStatus::Late,
            'deadline' => now()->subDays(1),
        ]);
        $mineOk = $this->task([
            'trainee_id' => $mine->id,
            'status' => TaskStatus::Progress,
            'deadline' => now()->addDays(2),
        ]);
        $foreign = $this->task([
            'trainee_id' => $other->id,
            'status' => TaskStatus::Progress,
            'deadline' => now()->addDays(2),
        ]);

        $ids = TraineeTask::query()
            ->visibleTo($mine)
            ->byWorkPriority()
            ->pluck('id')
            ->all();

        $this->assertSame([$mineOk->id, $mineLate->id], $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_status_filter_still_applies_with_priority_ordering(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Filter `progress` menyisakan kelompok 1 dan 4, dan mengabaikan `late`.
        $upcoming = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);
        $overdue = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->subDays(1)]);
        $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(1)]);

        $admin = $this->admin();

        $ids = TraineeTask::query()
            ->visibleTo($admin)
            ->statusIs(TaskStatus::Progress->value)
            ->byWorkPriority()
            ->pluck('id')
            ->all();

        $this->assertSame([$upcoming->id, $overdue->id], $ids);
    }

    public function test_export_query_uses_the_same_priority_order(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $late = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(1)]);
        $progress = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);

        $controller = app(TraineeTaskController::class);

        $exportIds = $controller->exportQuery($this->admin())->pluck('id')->all();
        $listIds = TraineeTask::query()
            ->visibleTo($this->admin())
            ->byWorkPriority()
            ->latest()
            ->pluck('id')
            ->all();

        $this->assertSame($listIds, $exportIds, 'Urutan export harus sama dengan halaman list.');
        $this->assertSame([$progress->id, $late->id], $exportIds);
    }

    public function test_list_page_renders_group_labels_in_order(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(1)]);
        $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);
        $this->task(['status' => TaskStatus::Done, 'deadline' => now()->addDays(2)]);
        $this->task(['status' => TaskStatus::Canceled, 'deadline' => now()->addDays(2)]);

        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()->getContent();

        preg_match_all('/<td colspan="\d+" class="px-4 py-2">(.*?)<\/td>/s', $html, $matches);

        $labels = array_map(
            fn ($label) => trim(preg_replace('/\s+/', ' ', strip_tags($label))),
            $matches[1]
        );

        $this->assertSame(
            ['Sedang Dikerjakan', 'Selesai', 'Dibatalkan', 'Terlambat'],
            $labels
        );
    }

    public function test_awaiting_label_uses_warning_colour(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->subDays(1)]);
        $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);

        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'text-late',
            $html,
            'Label kelompok 4 harus memakai warna peringatan.'
        );
    }
}
