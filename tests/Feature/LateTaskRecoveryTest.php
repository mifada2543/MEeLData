<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Task terlambat: status sebagai nilai turunan +edit yang dikunci.
 *
 * Bug yang ditutup: task berstatus "Terlambat" tidak pernah bisa kembali
 * menjadi "Sedang Dikerjakan" walau deadline-nya sudah diperbaiki ke masa
 * depan. Rewrite hanya punya satu arah (`progress -> late`), jadi satu
 * edit tanggal membuat task itu tersangkut "Terlambat" selamanya - padahal
 * badge-nya jelas-jelas tidak benar.
 *
 * Diperlakukan sebagai dua aturan yang saling melengkapi:
 *
 *   progress & deadline < now - 120s  ->  late
 *   late     & deadline >= now         ->  progress
 *
 * Test di bawah sengaja memakai offset RELATIF terhadap `now()`, bukan
 * tanggal hardcode: aturan "tidak boleh lampau" membuat tanggal hardcode
 * cepat basi.
 */
class LateTaskRecoveryTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- scheduler: reset

    public function test_late_task_with_future_deadline_returns_to_progress(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'deadline' => now()->addDay(),
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertSame(TaskStatus::Progress, $task->fresh()->status);
    }

    public function test_late_task_with_past_deadline_stays_late(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'deadline' => now()->subDay(),
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertSame(TaskStatus::Late, $task->fresh()->status);
    }

    public function test_scheduler_does_not_flip_flop_between_runs(): void
    {
        // Deadline tepat di detik yang sama dengan `now()` adalah batas kedua
        // aturan. Kalau batasnya tumpang tindih, dua scheduler yang jalan
        // berdekatan akan saling menimpa status tanpa henti.
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'deadline' => now()->addSecond(),
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();
        $this->assertSame(TaskStatus::Progress, $task->fresh()->status);

        $this->artisan('tasks:mark-late')->assertSuccessful();
        $this->assertSame(TaskStatus::Progress, $task->fresh()->status);
    }

    public function test_scheduler_never_touches_done_or_canceled(): void
    {
        $done = $this->task(['status' => TaskStatus::Done->value, 'deadline' => now()->addDay()]);
        $canceled = $this->task(['status' => TaskStatus::Canceled->value, 'deadline' => now()->addDay()]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertSame(TaskStatus::Done, $done->fresh()->status);
        $this->assertSame(TaskStatus::Canceled, $canceled->fresh()->status);
    }

    public function test_scheduler_still_marks_overdue_progress_as_late(): void
    {
        // Aturan lama harus tetap berlaku setelah aturan reset ditambahkan.
        $task = $this->task([
            'status' => TaskStatus::Progress->value,
            'deadline' => now()->subDay(),
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertSame(TaskStatus::Late, $task->fresh()->status);
    }

    // --------------------------------------------------- edit late oleh admin

    public function test_admin_pushing_deadline_forward_resets_status_to_progress(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'start_date' => now()->subDays(3),
            'deadline' => now()->subDay(),
        ]);

        $this->authAsAdmin()->put(route('admin.tasks.update', ['task' => $task->id]), [
            'task' => $task->task,
            'desc' => $task->desc,
            'start_date' => $task->start_date->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('admin.tasks.index'));

        $fresh = $task->fresh();

        $this->assertSame(TaskStatus::Progress, $fresh->status);
        $this->assertTrue($fresh->deadline->isFuture());
    }

    public function test_admin_edit_late_task_cannot_tamper_with_locked_fields(): void
    {
        // Lapisan pengaman: field terkunci dipangkas di
        // `UpdateTaskRequest::taskAttributes()`, bukan hanya di HTML. Request
        // dibuat-buat di sini harus tetap ditolak.
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'trainee_id' => $this->trainee()->id,
            'task' => 'Nama asli',
            'desc' => 'Deskripsi asli',
            'start_date' => now()->subDays(3),
            'deadline' => now()->subDay(),
        ]);

        $this->authAsAdmin()->put(route('admin.tasks.update', ['task' => $task->id]), [
            'task' => 'NAMA DIGANTI',
            'desc' => 'DESKRIPSI DIGANTI',
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => TaskStatus::Done->value,
        ])->assertSessionHasNoErrors();

        $fresh = $task->fresh();

        $this->assertSame('Nama asli', $fresh->task);
        $this->assertSame('Deskripsi asli', $fresh->desc);
        $this->assertTrue($fresh->start_date->lt(now()));
        // `status` juga dipangkas: tidak boleh dipakai mem-bypass jalur status
        // turunan dengan langsung men-set `done`.
        $this->assertSame(TaskStatus::Progress, $fresh->status);
    }

    public function test_admin_edit_late_task_keeps_late_status_when_deadline_unchanged(): void
    {
        $deadline = now()->subDay();

        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'start_date' => now()->subDays(3),
            'deadline' => $deadline,
        ]);

        $this->authAsAdmin()->put(route('admin.tasks.update', ['task' => $task->id]), [
            'task' => $task->task,
            'start_date' => $task->start_date->format('Y-m-d H:i:s'),
            'deadline' => $deadline->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::Late, $task->fresh()->status);
    }

    public function test_admin_edit_late_task_rejects_past_deadline(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'start_date' => now()->subDays(3),
            'deadline' => now()->subDay(),
        ]);

        $this->authAsAdmin()->put(route('admin.tasks.update', ['task' => $task->id]), [
            'task' => $task->task,
            'start_date' => $task->start_date->format('Y-m-d H:i:s'),
            'deadline' => now()->subHours(5)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('deadline');

        $this->assertSame(TaskStatus::Late, $task->fresh()->status);
    }

    public function test_edit_form_for_late_task_marks_locked_fields_as_disabled(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'start_date' => now()->subDays(3),
            'deadline' => now()->subDay(),
        ]);

        $response = $this->authAsAdmin()->get(route('admin.tasks.edit', ['task' => $task->id]));
        $response->assertOk();

        $html = $response->getContent();

        // Nama, deskripsi, tanggal mulai, dan trainee terkunci; deadline tetap
        // bisa diisi (satu-satunya jalan keluar dari "Terlambat").
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="task"[^>]*disabled|<input[^>]*disabled[^>]*id="task"/',
            (string) $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="start_date"[^>]*disabled|<input[^>]*disabled[^>]*id="start_date"/',
            (string) $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*id="deadline"[^>]*disabled|<input[^>]*disabled[^>]*id="deadline"/',
            (string) $html,
        );
        // Callout menjelaskan aturan main supaya lima field yang tiba-tiba mati
        // tidak terbaca sebagai bug.
        $this->assertStringContainsString('Geser ke masa depan', (string) $html);
    }

    // ------------------------------------------------- kunci edit untuk trainee

    public static function lockedForTraineeProvider(): array
    {
        return [
            'terlambat' => [TaskStatus::Late, -1],
            'selesai lewat deadline' => [TaskStatus::Done, -1],
            'dibatalkan lewat deadline' => [TaskStatus::Canceled, -1],
        ];
    }

    #[DataProvider('lockedForTraineeProvider')]
    public function test_trainee_cannot_open_edit_form_for_locked_task(TaskStatus $status, int $deadlineOffset): void
    {
        $trainee = $this->trainee();
        $task = $this->task([
            'trainee_id' => $trainee->id,
            'status' => $status->value,
            'deadline' => now()->addDays($deadlineOffset),
        ]);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.edit', ['task' => $task->id]))
            ->assertForbidden();
    }

    #[DataProvider('lockedForTraineeProvider')]
    public function test_trainee_cannot_update_locked_task_even_by_typing_url(TaskStatus $status, int $deadlineOffset): void
    {
        // Mutasi yang sama lewat jalur update - disembunyikan tombolnya saja
        // tidak cukup, karena URL bisa diketik manual.
        $trainee = $this->trainee();
        $task = $this->task([
            'trainee_id' => $trainee->id,
            'status' => $status->value,
            'task' => 'Tidak boleh berubah',
            'start_date' => now()->subDays(3),
            'deadline' => now()->addDays($deadlineOffset),
        ]);

        $this->actingAs($trainee, 'web')
            ->put(route('tasks.update', ['task' => $task->id]), [
                'task' => 'Berubah diam-diam',
                'start_date' => $task->start_date->format('Y-m-d H:i:s'),
                'deadline' => $task->deadline->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->assertSame('Tidak boleh berubah', $task->fresh()->task);
    }

    public function test_trainee_can_edit_own_progress_task(): void
    {
        $trainee = $this->trainee();
        $task = $this->task([
            'trainee_id' => $trainee->id,
            'status' => TaskStatus::Progress->value,
        ]);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.edit', ['task' => $task->id]))
            ->assertOk();
    }

    public function test_trainee_can_edit_done_task_whose_deadline_is_still_ahead(): void
    {
        $trainee = $this->trainee();
        $task = $this->task([
            'trainee_id' => $trainee->id,
            'status' => TaskStatus::Done->value,
            'deadline' => now()->addDays(2),
        ]);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.edit', ['task' => $task->id]))
            ->assertOk();

        $this->actingAs($trainee, 'web')->put(route('tasks.update', ['task' => $task->id]), [
            'task' => 'Nama dikoreksi trainee',
            'start_date' => $task->start_date->format('Y-m-d H:i:s'),
            'deadline' => $task->deadline->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nama dikoreksi trainee', $task->fresh()->task);
    }

    public function test_admin_can_edit_every_task_regardless_of_status(): void
    {
        $admin = $this->admin();

        foreach ([TaskStatus::Progress, TaskStatus::Done, TaskStatus::Canceled, TaskStatus::Late] as $status) {
            $task = $this->task(['status' => $status->value, 'deadline' => now()->subDay()]);

            $this->actingAs($admin, 'web')
                ->get(route('admin.tasks.edit', ['task' => $task->id]))
                ->assertOk();
        }
    }

    public function test_lock_reason_is_null_for_editable_task(): void
    {
        $trainee = $this->trainee();

        $progress = $this->task(['trainee_id' => $trainee->id, 'status' => TaskStatus::Progress->value]);
        $doneAhead = $this->task([
            'trainee_id' => $trainee->id,
            'status' => TaskStatus::Done->value,
            'deadline' => now()->addDay(),
        ]);

        $this->assertNull($progress->editLockReason());
        $this->assertNull($doneAhead->editLockReason());
    }

    // ------------------------------------------------------------------- UI

    public function test_trainee_list_hides_edit_button_for_locked_tasks(): void
    {
        $trainee = $this->trainee();

        $late = $this->task([
            'trainee_id' => $trainee->id,
            'task' => 'Tugas terlambat',
            'status' => TaskStatus::Late->value,
            'deadline' => now()->subDay(),
        ]);

        $html = (string) $this->actingAs($trainee, 'web')->get(route('tasks.index'))->getContent();

        $this->assertStringNotContainsString(
            route('tasks.edit', ['task' => $late->id]),
            $html,
            'Tugas terlambat tidak boleh punya tombol Edit di daftar trainee.',
        );
    }

    public function test_trainee_list_shows_edit_button_for_progress_task(): void
    {
        $trainee = $this->trainee();

        $task = $this->task([
            'trainee_id' => $trainee->id,
            'task' => 'Tugas berjalan',
            'status' => TaskStatus::Progress->value,
        ]);

        $html = (string) $this->actingAs($trainee, 'web')->get(route('tasks.index'))->getContent();

        $this->assertStringContainsString(route('tasks.edit', ['task' => $task->id]), $html);
    }

    public static function confirmationProvider(): array
    {
        return [
            'selesai' => [TaskStatus::Done],
            'dibatalkan' => [TaskStatus::Canceled],
        ];
    }

    #[DataProvider('confirmationProvider')]
    public function test_trainee_list_asks_confirmation_before_editing_closed_task(TaskStatus $status): void
    {
        $trainee = $this->trainee();

        $this->task([
            'trainee_id' => $trainee->id,
            'status' => $status->value,
            'deadline' => now()->addDays(2),
        ]);

        $html = (string) $this->actingAs($trainee, 'web')->get(route('tasks.index'))->getContent();

        $this->assertStringContainsString('Yakin ingin mengubah ini?', $html);
    }

    public function test_trainee_list_has_no_confirmation_for_progress_task(): void
    {
        $trainee = $this->trainee();

        $this->task([
            'trainee_id' => $trainee->id,
            'status' => TaskStatus::Progress->value,
            'deadline' => now()->addDays(2),
        ]);

        $html = (string) $this->actingAs($trainee, 'web')->get(route('tasks.index'))->getContent();

        $this->assertStringNotContainsString('Yakin ingin mengubah ini?', $html);
    }

    public function test_admin_list_has_no_confirmation_dialog(): void
    {
        // Admin bebas mengedit tanpa peringatan - aturannya hanya untuk trainee.
        $this->task([
            'status' => TaskStatus::Done->value,
            'deadline' => now()->addDays(2),
        ]);

        $html = (string) $this->authAsAdmin()->get(route('admin.tasks.index'))->getContent();

        $this->assertStringNotContainsString('Yakin ingin mengubah ini?', $html);
    }

    // ------------------------------------------------- helper reconcile status

    public function test_reconcile_returns_false_when_task_is_not_late(): void
    {
        $task = $this->task(['status' => TaskStatus::Progress->value, 'deadline' => now()->addDay()]);

        $this->assertFalse($task->reconcileDerivedStatus());
        $this->assertSame(TaskStatus::Progress, $task->fresh()->status);
    }

    public function test_reconcile_returns_false_when_late_deadline_is_still_past(): void
    {
        $task = $this->task(['status' => TaskStatus::Late->value, 'deadline' => now()->subDay()]);

        $this->assertFalse($task->reconcileDerivedStatus());
        $this->assertSame(TaskStatus::Late, $task->fresh()->status);
    }

    public function test_restore_still_resets_late_to_progress(): void
    {
        // Perilaku restore tidak boleh hilang saat aturan turunan ditambah.
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'start_date' => now()->subDays(5),
            'deadline' => now()->subDays(4),
        ]);
        $task->forceFill(['deleted_by' => $this->admin()->id])->save();
        $task->delete();

        $this->assertSoftDeleted($task);

        $newDeadline = now()->addDays(3);

        $task->restoreWithDates([
            'start_date' => now()->addDay(),
            'deadline' => $newDeadline,
        ]);

        $fresh = $task->fresh();

        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->deleted_by);
        $this->assertSame(TaskStatus::Progress, $fresh->status);
        $this->assertTrue($fresh->deadline->isFuture());
    }

    public function test_late_task_becomes_late_again_after_new_deadline_passes(): void
    {
        // Siklus hidup lengkap: deadline diperbaiki ke masa depan -> kembali
        // berjalan -> deadline barunya terlewati -> terlambat lagi.
        $task = $this->task([
            'status' => TaskStatus::Late->value,
            'start_date' => now()->subDays(3),
            'deadline' => now()->addDays(2),
        ]);

        $this->assertTrue($task->reconcileDerivedStatus());
        $this->assertSame(TaskStatus::Progress, $task->fresh()->status);

        $task->forceFill(['deadline' => Carbon::now()->subMinutes(5)])->save();
        $this->assertTrue($task->isAwaitingLateMarking());

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertSame(TaskStatus::Late, $task->fresh()->status);
    }
}
