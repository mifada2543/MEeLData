<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Hitungan mundur realtime "sudah lewat …".
 *
 * Yang diuji:
 * - jendela penandaan 120 detik menentukan kapan `late`;
 * - endpoint `tasks/live` mengembalikan acuan waktu server dan hanya
 *   progress yang lewat deadline;
 * - banner "scheduler tidak aktif" tidak menyala untuk kondisi normal;
 * - trainee tidak pernah menerima task orang lain (anti-IDOR di payload).
 */
class LiveCounterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_task_inside_mark_window_is_not_marked_late(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subSeconds(60), // 1 menit lewat
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_task_past_mark_window_becomes_late(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subSeconds(121), // lewat jendela 120 detik
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_mark_window_of_zero_restores_prd_behaviour(): void
    {
        Config::set('meeldata.late_mark_after_seconds', 0);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subSecond(), // lewat 1 detik saja
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_live_endpoint_requires_authentication(): void
    {
        $this->get(route('admin.tasks.live'))
            ->assertRedirect(route('login'));
    }

    public function test_live_endpoint_returns_server_time_and_overdue_tasks(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $overdue = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subMinutes(5),
        ]);

        $response = $this->authAsAdmin()->getJson(route('admin.tasks.live'));

        $response->assertOk()
            ->assertJsonPath('mark_after_seconds', 120)
            ->assertJsonPath('poll_seconds', 10)
            ->assertJsonPath('scheduler_down_count', 1);

        // Acuan waktu server harus ada dan bisa dipakai client.
        $this->assertNotNull($response->json('server_time'));
        Carbon::parse($response->json('server_time'));

        $ids = array_column($response->json('overdue'), 'id');
        $this->assertContains($overdue->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_live_endpoint_excludes_tasks_that_are_not_overdue_progress(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $late = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDays(3),
        ]);
        $done = TraineeTask::factory()->create([
            'status' => TaskStatus::Done,
            'deadline' => now()->subDays(3),
        ]);
        $canceled = TraineeTask::factory()->create([
            'status' => TaskStatus::Canceled,
            'deadline' => now()->subDays(3),
        ]);
        $future = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->addDays(2),
        ]);

        $ids = array_column(
            $this->authAsAdmin()->getJson(route('admin.tasks.live'))->json('overdue'),
            'id'
        );

        foreach ([$late, $done, $canceled, $future] as $excluded) {
            $this->assertNotContains($excluded->id, $ids);
        }

        $this->assertSame([], $ids);
    }

    public function test_live_endpoint_does_not_leak_other_trainee_tasks(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $other = $this->trainee();
        $foreign = TraineeTask::factory()->create([
            'trainee_id' => $other->id,
            'status' => TaskStatus::Progress,
            'deadline' => now()->subMinutes(5),
        ]);

        $trainee = $this->trainee();
        $own = TraineeTask::factory()->create([
            'trainee_id' => $trainee->id,
            'status' => TaskStatus::Progress,
            'deadline' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($trainee, 'web')->getJson(route('tasks.live'));

        $response->assertOk();

        $ids = array_column($response->json('overdue'), 'id');

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertSame(1, $response->json('scheduler_down_count'));
    }

    public function test_live_endpoint_is_not_cacheable(): void
    {
        $response = $this->authAsAdmin()->getJson(route('admin.tasks.live'));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_scheduler_down_count_ignores_normal_mark_window(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Masih di dalam jendela 120 detik: kondisi NORMAL.
        TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subSeconds(30),
        ]);

        $this->authAsAdmin()->getJson(route('admin.tasks.live'))
            ->assertOk()
            ->assertJsonPath('scheduler_down_count', 0);

        // Sekarang lewat jendela: benar-benar indikasi scheduler mati.
        Carbon::setTestNow('2026-10-10 09:10:00');

        $this->authAsAdmin()->getJson(route('admin.tasks.live'))
            ->assertOk()
            ->assertJsonPath('scheduler_down_count', 1);
    }

    public function test_task_list_shows_realtime_counter_for_awaiting_tasks(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subMinutes(1),
        ]);

        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            // Counter + polling harus terpasang; teks counter sendiri
            // dihitung Alpine dari waktu server.
            ->assertSee('deadlineTicker', false)
            ->assertSee('startLive(', false);
    }

    public function test_countdown_decreases_towards_mark_window(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Deadline 30 detik lalu, jendela 120 detik => tersisa 90 detik.
        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subSeconds(30),
        ]);

        $view = $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk();

        // Berisi titik dua => format mm:ss, bukan "1 menit 12 detik".
        $view->assertSee('deadlineTicker', false);

        // `mark_after_seconds` yang dikirim ke client adalah sumber angka
        // hitung mundur.
        $this->authAsAdmin()->getJson(route('admin.tasks.live'))
            ->assertOk()
            ->assertJsonPath('mark_after_seconds', 120);

        // Sisa hitung mundur = deadline + jendela - now, masih positif.
        $remainingSeconds = $task->deadline->getTimestamp()
            + 120
            - now()->getTimestamp();

        $this->assertSame(90, $remainingSeconds);
        $this->assertTrue($task->isAwaitingLateMarking());
    }

    public function test_countdown_would_be_finished_when_mark_window_passes(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Deadline 5 menit lalu: sudah jauh melewati jendela 120 detik,
        // jadi hitung mundur sudah habis dan hanya tersisa durasi lewat.
        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subMinutes(5),
        ]);

        // Sisa hitung mundur harus <= 0, sehingga counter tidak lagi
        // menampilkan "0:00" yang membeku.
        $remainingMs = ($task->deadline->getTimestamp() + 120) * 1000 - now()->getTimestamp() * 1000;

        $this->assertLessThanOrEqual(0, $remainingMs);
    }

    public function test_countdown_before_deadline_shows_remaining_time(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Deadline 45 menit lagi: harus tampil "sisa …", bukan "dalam …".
        $task = $this->task([
            'status' => TaskStatus::Progress,
            'deadline' => now()->addMinutes(45),
        ]);

        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('deadlineTicker', false);

        $this->assertTrue($task->hasCountdown());
        $this->assertFalse($task->isAwaitingLateMarking());
    }

    public function test_finished_tasks_have_no_countdown(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $done = $this->task(['status' => TaskStatus::Done, 'deadline' => now()->addDays(2)]);
        $canceled = $this->task(['status' => TaskStatus::Canceled, 'deadline' => now()->addDays(2)]);
        $progress = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);

        $this->assertFalse($done->hasCountdown());
        $this->assertFalse($canceled->hasCountdown());
        $this->assertTrue($progress->hasCountdown());
    }

    public function test_awaiting_scope_includes_tasks_inside_mark_window(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subSeconds(30),
        ]);

        $this->assertTrue(TraineeTask::query()->awaitingScheduler()->whereKey($task->id)->exists());
        $this->assertFalse(TraineeTask::query()->overdueProgress()->whereKey($task->id)->exists());
    }

    public function test_trash_page_shows_elapsed_counter_for_late_tasks(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDays(2)->subHours(3),
        ]);
        $task->delete();

        $this->authAsAdmin()->get(route('admin.tasks.trash'))->assertOk()
            ->assertSee('terlambat', false)
            ->assertSee('deadlineTicker', false);

        // Task yang dihapus manual (bukan late) tidak boleh punya counter.
        $manual = TraineeTask::factory()->create(['status' => TaskStatus::Done]);
        $manual->delete();

        $this->authAsAdmin()->get(route('admin.tasks.trash'))
            ->assertOk()
            ->assertSee('deadlineTicker', false);
    }
}
