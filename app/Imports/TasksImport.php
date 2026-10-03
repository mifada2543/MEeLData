<?php

namespace App\Imports;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import task trainee dari Excel dengan partial success.
 *
 * Aturan:
 * - trainee_email harus ditemukan;
 * - task wajib ada;
 * - description boleh kosong;
 * - start_date & deadline harus valid;
 * - deadline >= start_date;
 * - status hanya progress | done | canceled (`late` ditolak);
 * - duplikat diperbolehkan dan dianggap task baru;
 * - setiap row gagal dilaporkan dengan nomor baris Excel asli
 *   (heading = row 1, sehingga index+2 adalah nomor baris file).
 *
 * Header kolom dan nilai status menerima dua bentuk supaya ramah dipakai:
 * - teknis    : trainee_email, task, description, start_date, deadline, status
 * - Indonesia : email_trainee, tugas, deskripsi, tanggal_mulai, deadline, status
 *               dan status "Progress" / "Selesai" / "Dibatalkan"
 */
class TasksImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, reason: string}> */
    protected array $errors = [];

    protected int $imported = 0;

    /** Nomor baris file untuk row yang sedang divalidasi. */
    protected int $currentRowNumber = 0;

    /**
     * Cache email trainee agar tidak query berulang.
     *
     * @var array<string, int>
     */
    protected array $traineeIds = [];

    /**
     * Header alternatif untuk setiap kunci internal.
     *
     * Maatwebsite mengubah header menjadi slug lowercase
     * (`Str::slug($value, '_')`), sehingga "Tanggal Mulai" menjadi
     * `tanggal_mulai`. Semua alias ditulis dalam bentuk slug itu agar
     * file ekspor (header Bahasa Indonesia) maupun file buatan sendiri
     * dengan header teknis sama-sama bisa diimpor.
     *
     * @var array<string, array<int, string>>
     */
    protected const HEADER_ALIASES = [
        'trainee_email' => ['email_trainee', 'email', 'nama_trainee'],
        'task' => ['tugas', 'nama_tugas'],
        'description' => ['deskripsi', 'keterangan'],
        'start_date' => ['tanggal_mulai', 'mulai'],
        'deadline' => ['tanggal_deadline'],
        'status' => ['status_tugas'],
    ];

    /**
     * Daftar format tanggal yang diterima. `d/m/Y` mengikuti format
     * tampilan Indonesia, `Y-m-d` mengikuti format umum hasil export.
     *
     * @var array<int, string>
     */
    protected const DATE_FORMATS = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
    ];

    public function collection(Collection $rows): void
    {
        $this->traineeIds = User::query()
            ->pluck('id', 'email')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rows as $index => $row) {
            // +2 karena baris pertama pada file adalah heading.
            $this->currentRowNumber = $index + 2;

            $validated = $this->validateRow($row);

            if ($validated === null) {
                continue;
            }

            try {
                TraineeTask::create($validated);
                $this->imported++;
            } catch (\Throwable $e) {
                report($e);
                $this->addError($this->currentRowNumber, 'Baris ini gagal disimpan. Periksa nilainya lalu coba lagi.');
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function validateRow(mixed $row): ?array
    {
        if (! $row instanceof Collection) {
            $row = collect((array) $row);
        }

        $email = $this->stringValue($row, 'trainee_email');
        $task = $this->stringValue($row, 'task');
        $desc = $this->stringValue($row, 'description');
        $startDate = $this->stringValue($row, 'start_date');
        $deadline = $this->stringValue($row, 'deadline');
        $status = mb_strtolower($this->stringValue($row, 'status'));

        // Trainee
        if ($email === '') {
            return $this->fail('Email trainee wajib diisi.', $row);
        }

        if (! isset($this->traineeIds[$email])) {
            return $this->fail('Trainee "'.$email.'" tidak ditemukan.', $row);
        }

        // Tugas
        if ($task === '') {
            return $this->fail('Nama tugas wajib diisi.', $row);
        }

        if (mb_strlen($task) > 255) {
            return $this->fail('Nama tugas tidak boleh lebih dari 255 karakter.', $row);
        }

        // Tanggal
        if ($startDate === '') {
            return $this->fail('Tanggal mulai wajib diisi.', $row);
        }

        $start = $this->parseDate($startDate);

        if ($start === null) {
            return $this->fail('Tanggal mulai "'.$startDate.'" tidak valid.', $row);
        }

        // Perbandingan per menit, sejalan dengan presisi input
        // `datetime-local` dan App\Rules\NotBeforeNow.
        if ($start->startOfMinute()->lt(now()->startOfMinute())) {
            return $this->fail('Tanggal mulai tidak boleh sebelum waktu sekarang.', $row);
        }

        if ($deadline === '') {
            return $this->fail('Tanggal deadline wajib diisi.', $row);
        }

        $deadlineValue = $this->parseDate($deadline);

        if ($deadlineValue === null) {
            return $this->fail('Tanggal deadline "'.$deadline.'" tidak valid.', $row);
        }

        if ($deadlineValue->startOfMinute()->lt(now()->startOfMinute())) {
            return $this->fail('Tanggal deadline tidak boleh sebelum waktu sekarang.', $row);
        }

        if ($deadlineValue->lt($start)) {
            return $this->fail('Tanggal deadline harus sama dengan atau setelah tanggal mulai.', $row);
        }

        // Status: menerima nilai kanonik maupun label Bahasa Indonesia.
        $normalized = TaskStatus::normalizeInput($status === '' ? 'progress' : $status);

        if ($normalized === null) {
            if ($status === TaskStatus::Late->value || $status === 'terlambat') {
                return $this->fail(
                    'Status "late"/"Terlambat" tidak diperbolehkan. Status tersebut diatur otomatis oleh sistem.',
                    $row
                );
            }

            return $this->fail(
                'Status "'.$status.'" tidak valid. Nilai yang diperbolehkan: '
                    .implode(', ', TaskStatus::manualValues()).'.',
                $row
            );
        }

        return [
            'trainee_id' => $this->traineeIds[$email],
            'task' => $task,
            'desc' => $desc !== '' ? $desc : null,
            'start_date' => $start,
            'deadline' => $deadlineValue,
            'status' => $normalized->value,
        ];
    }

    /**
     * Catat kegagalan untuk baris yang sedang divalidasi.
     */
    protected function fail(string $reason, Collection $row): null
    {
        $this->addError($this->currentRowNumber, $reason);

        return null;
    }

    protected function addError(int $rowNumber, string $reason): void
    {
        $this->errors[] = ['row' => $rowNumber, 'reason' => $reason];
    }

    protected function stringValue(Collection $row, string $key): string
    {
        $value = $row->get($key);

        // Coba header alternatif (mis. kolom "Deskripsi" untuk "description").
        if ($value === null || $value === '') {
            foreach (self::HEADER_ALIASES[$key] ?? [] as $alias) {
                $candidate = $row->get($alias);

                if ($candidate !== null && $candidate !== '') {
                    $value = $candidate;
                    break;
                }
            }
        }

        if ($value === null) {
            return '';
        }

        // PhpSpreadsheet dapat mengembalikan nilai numerik untuk kolom tanggal.
        if (is_numeric($value)) {
            return Carbon::instance(
                Date::excelToDateTimeObject((float) $value)
            )->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    protected function parseDate(string $value): ?Carbon
    {
        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                // Format tidak cocok; coba format berikutnya.
                continue;
            }

            // createFromFormat dapat menerima tanggal yang tidak lengkap,
            // sehingga hasil harus dibandingkan balik dengan input.
            if ($parsed->format($format) === $value) {
                return $parsed;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function imported(): int
    {
        return $this->imported;
    }

    /**
     * @return array<int, array{row: int, reason: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
