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
 */
class TasksImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, reason: string}> */
    protected array $errors = [];

    protected int $imported = 0;

    /**
     * Cache email trainee agar tidak query berulang.
     *
     * @var array<string, int>
     */
    protected array $traineeIds = [];

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
                $this->addError($rowNumber, 'Failed to save this row. Please check the values and try again.');
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
        $status = strtolower($this->stringValue($row, 'status'));

        // Trainee
        if ($email === '') {
            return $this->fail('Trainee email is required.', $row);
        }

        if (! isset($this->traineeIds[$email])) {
            return $this->fail("Trainee \"{$email}\" was not found.", $row);
        }

        // Task
        if ($task === '') {
            return $this->fail('Task is required.', $row);
        }

        if (mb_strlen($task) > 255) {
            return $this->fail('Task may not be greater than 255 characters.', $row);
        }

        // Dates
        if ($startDate === '') {
            return $this->fail('Start date is required.', $row);
        }

        $start = $this->parseDate($startDate);

        if ($start === null) {
            return $this->fail("Start date \"{$startDate}\" is not a valid date.", $row);
        }

        if ($deadline === '') {
            return $this->fail('Deadline is required.', $row);
        }

        $deadlineValue = $this->parseDate($deadline);

        if ($deadlineValue === null) {
            return $this->fail("Deadline \"{$deadline}\" is not a valid date.", $row);
        }

        if ($deadlineValue->lt($start)) {
            return $this->fail('Deadline must be after or equal to start date.', $row);
        }

        // Status
        if ($status === '') {
            $status = TaskStatus::Progress->value;
        }

        if (! in_array($status, TaskStatus::manualValues(), true)) {
            if ($status === TaskStatus::Late->value) {
                return $this->fail('Status "late" is not allowed. "Late" is set automatically by the system.', $row);
            }

            return $this->fail(
                "Status \"{$status}\" is invalid. Allowed values: ".implode(', ', TaskStatus::manualValues()).'.',
                $row
            );
        }

        return [
            'trainee_id' => $this->traineeIds[$email],
            'task' => $task,
            'desc' => $desc !== '' ? $desc : null,
            'start_date' => $start,
            'deadline' => $deadlineValue,
            'status' => $status,
        ];
    }

    protected function fail(string $reason, Collection $row): null
    {
        $this->addError($this->currentRowNumber, $reason);

        return null;
    }

    /**
     * Nomor baris file untuk row yang sedang divalidasi.
     * Dipasang di loop collection().
     */
    protected int $currentRowNumber = 0;

    protected function addError(int $rowNumber, string $reason): void
    {
        $this->errors[] = ['row' => $rowNumber, 'reason' => $reason];
    }

    protected function stringValue(Collection $row, string $key): string
    {
        $value = $row->get($key);

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

    /**
     * Daftar format tanggal yang diterima. `d/m/Y` mengikuti format
     * tampilan Indonesia, `Y-m-d` mengikuti format umum hasil export.
     */
    protected const DATE_FORMATS = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
    ];

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
