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
            ['andi@example.com', 'Task A', 'Desc A', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
            ['andi@example.com', 'Task B', 'Desc B', now()->addDays(1)->format('Y-m-d H:i:s'), now()->addDays(5)->format('Y-m-d H:i:s'), 'done'],
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
            ['andi@example.com', 'Valid Task', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
            // Row 3: trainee tidak ditemukan
            ['nobody@example.com', 'Unknown Trainee', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
            // Row 4: deadline invalid
            ['andi@example.com', 'Bad Deadline', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), 'not-a-date', 'progress'],
            // Row 5: status late ditolak
            ['andi@example.com', 'Late Task', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'late'],
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
        $this->assertStringContainsString('tidak valid', $reasons);
        $this->assertStringContainsString('"late"/"Terlambat" tidak diperbolehkan', $reasons);
    }

    public function test_row_without_trainee_email_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['', 'No Email', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);

        $errors = collect(session('import_errors'));
        $this->assertSame(2, $errors->first()['row']);
        $this->assertStringContainsString('Email trainee wajib diisi', $errors->first()['reason']);
    }

    public function test_row_without_task_name_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', '', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'Nama tugas wajib diisi',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_description_may_be_empty(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'No Description', '', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
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
            ['andi@example.com', 'Reversed', 'Desc', now()->addDays(9)->format('Y-m-d H:i:s'), now()->addDays(0)->format('Y-m-d H:i:s'), 'progress'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'harus sama dengan atau setelah tanggal mulai',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_invalid_status_fails(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Bad Status', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'completed'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'tidak valid',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_status_late_is_rejected(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Late Status', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'late'],
        ]);

        $this->authAsAdmin()->post(route('admin.tasks.import'), ['file' => $file]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            '"late"/"Terlambat" tidak diperbolehkan',
            collect(session('import_errors'))->first()['reason'],
        );
    }

    public function test_empty_status_defaults_to_progress(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $file = $this->makeXlsx([
            $this->header(),
            ['andi@example.com', 'Default Status', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), ''],
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
            ['andi@example.com', 'Duplicate', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
            ['andi@example.com', 'Duplicate', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
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
            ['andi@example.com', 'For Andi', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
            ['budi@example.com', 'For Budi', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'],
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
                'start_date' => now()->addDays(0)->format('Y-m-d H:i:s'),
                'deadline' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'status' => 'progress',
            ],
            (object) [
                'trainee_email' => 'missing@example.com',
                'task' => 'Invalid',
                'description' => 'desc',
                'start_date' => now()->addDays(0)->format('Y-m-d H:i:s'),
                'deadline' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'status' => 'progress',
            ],
        ]));

        $this->assertSame(1, $import->imported());
        $this->assertCount(1, $import->errors());
        $this->assertSame(3, $import->errors()[0]['row']);
    }

    public function test_import_accepts_indonesian_headers_and_status_labels(): void
    {
        // File hasil ekspor memakai header Bahasa Indonesia; impor harus
        // menerimanya kembali agar round-trip berjalan.
        $trainee = $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-id-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'Email Trainee', 'Tugas', 'Deskripsi', 'Tanggal Mulai', 'Tanggal Deadline', 'Status',
        ], null, 'A1');
        $sheet->fromArray([
            'andi@example.com', 'Tugas Berbahasa', 'Deskripsi', now()->addDay()->format('d/m/Y H:i'), now()->addDays(4)->format('d/m/Y H:i'), 'Selesai',
        ], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        $this->authAsAdmin()->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'tasks.xlsx', null, null, true),
        ]);

        $this->assertDatabaseHas('trainee_tasks', [
            'trainee_id' => $trainee->id,
            'task' => 'Tugas Berbahasa',
            'status' => 'done',
        ]);

        @unlink($path);
    }

    public function test_import_still_accepts_technical_english_headers(): void
    {
        // Header teknis lama harus tetap bisa dipakai.
        $trainee = $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-en-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'trainee_email', 'task', 'description', 'start_date', 'deadline', 'status',
        ], null, 'A1');
        $sheet->fromArray([
            'andi@example.com', 'Technical Header', 'Desc', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress',
        ], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        $this->authAsAdmin()->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'tasks.xlsx', null, null, true),
        ]);

        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Technical Header']);

        @unlink($path);
    }

    public function test_import_rejects_indonesian_late_label(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-late-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'Email Trainee', 'Tugas', 'Deskripsi', 'Tanggal Mulai', 'Tanggal Deadline', 'Status',
        ], null, 'A1');
        $sheet->fromArray([
            'andi@example.com', 'Terlambat', 'Desc', now()->addDay()->format('d/m/Y H:i'), now()->addDays(4)->format('d/m/Y H:i'), 'Terlambat',
        ], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        $this->authAsAdmin()->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'tasks.xlsx', null, null, true),
        ]);

        $this->assertDatabaseCount('trainee_tasks', 0);
        $this->assertStringContainsString(
            'tidak diperbolehkan',
            collect(session('import_errors'))->first()['reason']
        );

        @unlink($path);
    }

    /**
     * Galat unggahan dari PHP harus jadi pesan jelas, bukan 500.
     *
     * Saat berkas melewati `upload_max_filesize`, PHP memotongnya dan mengisi
     * `$_FILES['file']['error'] = UPLOAD_ERR_INI_SIZE`. `$_POST` masih utuh,
     * jadi FormRequest tetap jalan dan `withValidator` bisa mengganti pesan
     * generik "Kolom berkas gagal diunggah." dengan batas server yang sebenarnya.
     *
     * Regression: implementasi pertama memanggil `MessageBag::remove()` yang
     * tidak ada di Laravel 12, sehingga kasus ini justru jadi HTTP 500 - dan
     * test lama tidak menangkapnya karena berkas uji selalu kecil.
     */
    public function test_php_upload_error_becomes_readable_message_not_500(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-big-').'.xlsx';
        (new Xlsx(new Spreadsheet))->save($path);

        $response = $this->authAsAdmin()->post(route('admin.tasks.import'), [
            // testMode = true: file benar-benar ada, tetapi kode errornya
            // disimulasikan sebagai UPLOAD_ERR_INI_SIZE.
            'file' => new UploadedFile($path, 'besar.xlsx', null, UPLOAD_ERR_INI_SIZE, true),
        ]);

        // FormRequest gagal -> redirect back. Tujuan persisnya ikut referer,
        // jadi di sini cukup dipastikan itu redirect, bukan respons galat.
        $response->assertRedirect();
        $response->assertSessionHasErrors('file');

        $errors = session('errors')->getBag('default')->get('file');
        $this->assertCount(1, $errors, 'Galat `file` harus diganti jadi satu pesan yang spesifik.');
        $this->assertStringContainsString('batas unggahan server', $errors[0]);
        $this->assertStringNotContainsString('Kolom berkas gagal diunggah', $errors[0]);

        @unlink($path);
    }

    /**
     * `post_max_size` terlampaui -> middleware ValidatePostSize melempar 413
     * sebelum controller jalan, jadi FormRequest tidak pernah ikut. Handler
     * 413 di bootstrap/app.php yang menanganinya: balik ke halaman tugas dengan
     * pesan yang menyebut batas server, bukan halaman "Post Too Large".
     */
    public function test_post_max_size_overflow_redirects_with_clear_message(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-post-').'.xlsx';
        (new Xlsx(new Spreadsheet))->save($path);

        $postLimit = \App\Support\UploadLimits::postLimitBytes();

        $response = $this->authAsAdmin()->withServerVariables([
            'CONTENT_LENGTH' => (string) ($postLimit > 0 ? $postLimit + 1048576 : 999999999),
        ])->post(route('admin.tasks.import'), [
            'file' => new UploadedFile($path, 'besar.xlsx', null, null, true),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');

        $errors = session('errors')->getBag('default')->get('file');
        $this->assertStringContainsString('post_max_size', $errors[0]);
        $this->assertStringNotContainsString('Post Too Large', $errors[0]);

        @unlink($path);
    }
}
