<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Imports\TasksImport;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmpPath;

    protected function tearDown(): void
    {
        if (isset($this->tmpPath) && is_file($this->tmpPath)) {
            @unlink($this->tmpPath);
        }

        parent::tearDown();
    }

    /**
     * Membuat file .xlsx sungguhan (bukan fake upload) supaya validasi
     * mimes dan pembacaan baris oleh PhpSpreadsheet teruji nyata.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    protected function makeXlsx(array $rows): UploadedFile
    {
        $this->tmpPath = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            $sheet->fromArray($row, null, 'A'.($rowIndex + 1));
        }

        (new Xlsx($spreadsheet))->save($this->tmpPath);

        return new UploadedFile($this->tmpPath, 'tasks.xlsx', null, null, true);
    }

    protected function header(): array
    {
        return ['trainee_email', 'task', 'description', 'start_date', 'deadline', 'status'];
    }

    public function test_valid_import_creates_tasks(): void
    {
        $trainee = $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Task A', 'Desc A', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
            ['andi@example.com', 'Task B', 'Desc B', '2026-10-02 09:00:00', '2026-10-06 17:00:00', 'done'],
        ]);

        $response = $this->authAsAdmin()
            ->post(route('admin.tasks.import'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('imported_count', 2);
        $response->assertSessionHas('import_errors', []);

        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Task A', 'trainee_id' => $trainee->id]);
        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Task B', 'status' => TaskStatus::Done->value]);
    }

    public function test_partial_success_reports_failed_rows_with_row_numbers(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            // Row 2: valid
            ['andi@example.com', 'Valid Task', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
            // Row 3: trainee tidak ditemukan
            ['nobody@example.com', 'Unknown Trainee', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
            // Row 4: deadline invalid
            ['andi@example.com', 'Bad Deadline', 'Desc', '2026-10-01 09:00:00', 'not-a-date', 'progress'],
            // Row 5: status late ditolak
            ['andi@example.com', 'Late Task', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'late'],
        ]);

        $response = $this->authAsAdmin()
            ->post(route('admin.tasks.import'), ['file' => $file]);

        $response->assertSessionHas('imported_count', 1);

        $errors = collect(session('import_errors'));

        // Valid task tersimpan, tiga task gagal.
        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Valid Task']);
        $this->assertDatabaseMissing('trainee_tasks', ['task' => 'Unknown Trainee']);
        $this->assertDatabaseMissing('trainee_tasks', ['task' => 'Bad Deadline']);
        $this->assertDatabaseMissing('trainee_tasks', ['task' => 'Late Task']);

        // Nomor baris mengacu pada baris Excel asli (heading = row 1).
        $rows = $errors->pluck('row')->all();
        $this->assertSame([3, 4, 5], $rows);

        $reasons = $errors->pluck('reason')->implode(' | ');
        $this->assertStringContainsString('nobody@example.com', $reasons);
        $this->assertStringContainsString('not a valid date', $reasons);
        $this->assertStringContainsString('"late" is not allowed', $reasons);
    }

    public function test_row_without_trainee_email_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['', 'No Email', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);

        $errors = collect(session('import_errors'));
        $this->assertSame(2, $errors->first()['row']);
        $this->assertStringContainsString('Trainee email is required', $errors->first()['reason']);
    }

    public function test_row_without_task_name_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', '', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'Task is required',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_description_may_be_empty(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'No Description', '', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseHas('trainee_tasks', [
            'task' => 'No Description',
            'desc' => null,
        ]);
    }

    public function test_deadline_before_start_date_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Reversed', 'Desc', '2026-10-10 09:00:00', '2026-10-01 09:00:00', 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'after or equal to start date',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_invalid_status_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Bad Status', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'completed'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'is invalid',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_status_late_is_rejected(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Late Status', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'late'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            '"late" is not allowed',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_empty_status_defaults_to_progress(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Default Status', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', ''],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseHas('trainee_tasks', [
            'task' => 'Default Status',
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_duplicate_rows_create_separate_tasks(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Duplicate', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
            ['andi@example.com', 'Duplicate', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        // Duplikat diperbolehkan dan dianggap dua task baru.
        $this->assertDatabaseCount('trainee_tasks', 2);
        $this->assertSame(2, session('imported_count'));
    }

    public function test_import_requires_file(): void
    {
        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.import'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_import_rejects_non_excel_file(): void
    {
        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.import'), [
                'file' => UploadedFile::fake()->create('notes.txt', 10),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_import_rejects_file_over_10mb(): void
    {
        // Dimensi file di-set Explicitly agar melewati batas 10 MB.
        $file = UploadedFile::fake()->create('tasks.xlsx', 10241, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_trainee_cannot_reach_import(): void
    {
        $this->authAsTrainee()
            ->post(route('admin.tasks.import'))
            ->assertForbidden();
    }

    public function test_import_creates_tasks_for_correct_trainee(): void
    {
        $andi = $this->trainee(['email' => 'andi@example.com']);
        $budi = $this->trainee(['email' => 'budi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'For Andi', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
            ['budi@example.com', 'For Budi', 'Desc', '2026-10-01 09:00:00', '2026-10-05 17:00:00', 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertSame($andi->id, TraineeTask::where('task', 'For Andi')->first()->trainee_id);
        $this->assertSame($budi->id, TraineeTask::where('task', 'For Budi')->first()->trainee_id);
    }

    public function test_import_class_reports_errors_and_count(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $import = new TasksImport;

        $import->collection(collect([
            (object) [
                'trainee_email' => 'andi@example.com',
                'task' => 'Valid',
                'description' => 'desc',
                'start_date' => '2026-10-01 09:00:00',
                'deadline' => '2026-10-05 17:00:00',
                'status' => 'progress',
            ],
            (object) [
                'trainee_email' => 'missing@example.com',
                'task' => 'Invalid',
                'description' => 'desc',
                'start_date' => '2026-10-01 09:00:00',
                'deadline' => '2026-10-05 17:00:00',
                'status' => 'progress',
            ],
        ]));

        $this->assertSame(1, $import->imported());
        $this->assertCount(1, $import->errors());
        $this->assertSame(3, $import->errors()[0]['row']);
    }
}
