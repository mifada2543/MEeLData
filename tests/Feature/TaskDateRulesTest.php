<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Aturan tanggal: task tidak boleh dibuat/diimpor dengan tanggal lampau.
 *
 * Regresi yang ditutup: user membuat task dengan tanggal kemarin sehingga
 * task itu langsung berstatus terlambat.
 */
class TaskDateRulesTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- create

    public function test_create_rejects_past_start_date(): void
    {
        $this->authAsAdmin()->post(route('admin.tasks.store'), [
            'trainee_id' => $this->trainee()->id,
            'task' => 'Task lampau',
            ...$this->pastDates(),
        ])->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('trainee_tasks', 0);
    }

    public function test_create_rejects_past_deadline_even_with_future_start(): void
    {
        $this->authAsAdmin()->post(route('admin.tasks.store'), [
            'trainee_id' => $this->trainee()->id,
            'task' => 'Deadline lampau',
            'start_date' => now()->format('Y-m-d H:i:s'),
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('deadline');

        $this->assertDatabaseCount('trainee_tasks', 0);
    }

    public function test_create_accepts_current_minute(): void
    {
        // Input `datetime-local` berpresisi menit, jadi memilih menit yang
        // sedang berjalan harus tetap diterima.
        $minute = now()->format('Y-m-d H:i');

        $this->authAsAdmin()->post(route('admin.tasks.store'), [
            'trainee_id' => $this->trainee()->id,
            'task' => 'Mulai menit ini',
            'start_date' => $minute,
            'deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Mulai menit ini']);
    }

    public function test_create_rejects_deadline_before_start(): void
    {
        $this->authAsAdmin()->post(route('admin.tasks.store'), [
            'trainee_id' => $this->trainee()->id,
            'task' => 'Urutan salah',
            'start_date' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'deadline' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('deadline');
    }

    public function test_trainee_create_also_rejects_past_dates(): void
    {
        $trainee = $this->trainee();

        $this->actingAs($trainee, 'web')->post(route('tasks.store'), [
            'task' => 'Trainee lampau',
            ...$this->pastDates(),
        ])->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('trainee_tasks', 0);
    }

    // ------------------------------------------------------------------ edit

    public function test_edit_keeps_untouched_past_start_date(): void
    {
        $task = $this->task([
            'start_date' => now()->subMonth(),
            'deadline' => now()->addDays(3),
        ]);

        $before = $task->start_date;

        $this->authAsAdmin()->put(route('admin.tasks.update', $task), [
            'task' => 'Deskripsi diperbaiki',
            'desc' => 'hanya desc',
            // Dikirim apa adanya oleh form, tidak disentuh admin.
            'start_date' => $before->format('Y-m-d H:i:s'),
            'deadline' => $task->deadline->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'task' => 'Deskripsi diperbaiki',
            'desc' => 'hanya desc',
        ]);
    }

    public function test_edit_tolerates_second_precision_difference(): void
    {
        // Database menyimpan presisi detik, form mengirim presisi menit.
        // Nilai yang tidak disentuh admin tetap harus dianggap tidak berubah.
        $task = $this->task([
            'start_date' => now()->subMonth()->addSeconds(37),
            'deadline' => now()->addDays(3),
        ]);

        $this->authAsAdmin()->put(route('admin.tasks.update', $task), [
            'task' => $task->task,
            'start_date' => $task->start_date->copy()->startOfMinute()->format('Y-m-d H:i:s'),
            'deadline' => $task->deadline->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();
    }

    public function test_edit_rejects_backdating_start_date(): void
    {
        $task = $this->task([
            'start_date' => now()->subMonth(),
            'deadline' => now()->addDays(3),
        ]);

        $this->authAsAdmin()
            ->from(route('admin.tasks.edit', $task))
            ->put(route('admin.tasks.update', $task), [
                'task' => $task->task,
                'start_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('start_date');
    }

    public function test_edit_rejects_backdating_deadline(): void
    {
        $task = $this->task([
            'start_date' => now()->addDay(),
            'deadline' => now()->addDays(10),
        ]);

        $this->authAsAdmin()
            ->from(route('admin.tasks.edit', $task))
            ->put(route('admin.tasks.update', $task), [
                'task' => $task->task,
                'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
                'deadline' => now()->subDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('deadline');
    }

    public function test_edit_can_extend_deadline_of_legacy_task(): void
    {
        // Memperpanjang deadline task yang sudah lewat harus tetap bisa.
        $task = $this->task([
            'status' => TaskStatus::Progress,
            'start_date' => now()->subMonth(),
            'deadline' => now()->subDays(2),
        ]);

        $this->authAsAdmin()->put(route('admin.tasks.update', $task), [
            'task' => $task->task,
            'start_date' => $task->start_date->format('Y-m-d H:i:s'), // tetap lampau
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),  // diperpanjang
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);
    }

    // ---------------------------------------------------------------- import

    public function test_import_rejects_row_with_past_start_date(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = $this->importFile('Row Lampau', now()->subDays(3), now()->addDays(2));

        $this->authAsAdmin()->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'tasks.xlsx', null, null, true),
        ]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'tidak boleh sebelum waktu sekarang',
            collect(session('import_errors'))->first()['reason']
        );

        @unlink($path);
    }

    public function test_import_rejects_row_with_past_deadline(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = $this->importFile('Deadline Lampau', now()->addDay(), now()->subDay());

        $this->authAsAdmin()->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'tasks.xlsx', null, null, true),
        ]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'Tanggal deadline tidak boleh sebelum waktu sekarang',
            collect(session('import_errors'))->first()['reason']
        );

        @unlink($path);
    }

    public function test_import_accepts_current_minute(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = $this->importFile('Menit Ini', now(), now()->addDays(3));

        $this->authAsAdmin()->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'tasks.xlsx', null, null, true),
        ]);

        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Menit Ini']);

        @unlink($path);
    }

    /**
     * @param  Carbon  $start
     * @param  Carbon  $deadline
     */
    protected function importFile(string $task, $start, $deadline): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import-date-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'trainee_email', 'task', 'description', 'start_date', 'deadline', 'status',
        ], null, 'A1');
        $sheet->fromArray([
            'andi@example.com',
            $task,
            'Desc',
            $start->format('Y-m-d H:i:s'),
            $deadline->format('Y-m-d H:i:s'),
            'progress',
        ], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
