<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_shows_stats_for_all_tasks(): void
    {
        $admin = $this->admin();

        TraineeTask::factory()->count(2)->create(['status' => TaskStatus::Progress]);
        TraineeTask::factory()->create(['status' => TaskStatus::Done]);
        TraineeTask::factory()->create(['status' => TaskStatus::Late]);
        TraineeTask::factory()->create(['status' => TaskStatus::Canceled]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(5, $stats['total']);
        $this->assertSame(2, $stats['progress']);
        $this->assertSame(1, $stats['done']);
        $this->assertSame(1, $stats['late']);
    }

    public function test_trainee_dashboard_only_counts_own_tasks(): void
    {
        $trainee = $this->trainee();

        TraineeTask::factory()->count(3)->create(['trainee_id' => $trainee->id]);
        TraineeTask::factory()->count(5)->create();

        $response = $this->actingAs($trainee, 'web')->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('stats')['total']);
    }

    public function test_dashboard_lists_are_limited_to_five_items(): void
    {
        $admin = $this->admin();

        TraineeTask::factory()->count(8)->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertCount(5, $response->viewData('recentTasks'));
    }

    public function test_recent_tasks_are_ordered_newest_first(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $admin = $this->admin();

        $oldest = TraineeTask::factory()->create([
            'task' => 'Oldest',
            'created_at' => now()->subDays(5),
        ]);
        $newest = TraineeTask::factory()->create([
            'task' => 'Newest',
            'created_at' => now(),
        ]);

        $recent = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->viewData('recentTasks');

        $this->assertSame($newest->id, $recent->first()->id);
        $this->assertSame($oldest->id, $recent->last()->id);
    }

    public function test_approaching_deadline_window_is_three_days(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $admin = $this->admin();

        $within = TraineeTask::factory()->create([
            'task' => 'Due tomorrow',
            'status' => TaskStatus::Progress,
            'deadline' => '2026-10-11 17:00:00',
        ]);

        $boundary = TraineeTask::factory()->create([
            'task' => 'Due in three days',
            'status' => TaskStatus::Progress,
            'deadline' => '2026-10-13 12:00:00',
        ]);

        $outside = TraineeTask::factory()->create([
            'task' => 'Due next month',
            'status' => TaskStatus::Progress,
            'deadline' => '2026-11-10 17:00:00',
        ]);

        $approaching = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->viewData('approachingTasks');

        $ids = $approaching->pluck('id');

        $this->assertTrue($ids->contains($within->id));
        $this->assertTrue($ids->contains($boundary->id));
        $this->assertFalse($ids->contains($outside->id));
    }

    public function test_late_tasks_are_not_in_approaching_deadline_list(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $admin = $this->admin();

        $lateTask = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => '2026-10-05 17:00:00',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $approachingIds = $response->viewData('approachingTasks')->pluck('id');
        $lateIds = $response->viewData('lateTasks')->pluck('id');

        $this->assertFalse($approachingIds->contains($lateTask->id));
        $this->assertTrue($lateIds->contains($lateTask->id));
    }

    public function test_trainee_dashboard_never_shows_other_trainees_tasks(): void
    {
        $trainee = $this->trainee();
        $otherTask = TraineeTask::factory()->create([
            'task' => 'Other trainee secret task',
            'status' => TaskStatus::Late,
        ]);

        $response = $this->actingAs($trainee, 'web')->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Other trainee secret task');
        $this->assertCount(0, $response->viewData('lateTasks'));
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
