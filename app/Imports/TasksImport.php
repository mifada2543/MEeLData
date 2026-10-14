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
 * - trainee_email harus ditemukan (email, atau nama trainee yang unik);
 * - task wajib ada;
 * - description boleh kosong;
 * - start_date & deadline harus valid;
 * - deadline >= start_date;
 * - status hanya progress | done | canceled (`late` ditolak);
 * - duplikat diperbolehkan dan dianggap task baru;
 * - setiap row gagal dilaporkan dengan nomor baris Excel asli
 *   (heading = row 1, sehingga index+2 adalah nomor baris file).
 *
 * Tanggal tanpa jam (`Y-m-d`, `d/m/Y`, atau sel Excel bertanggal) dianggap
 * berlaku sepanjang hari itu: start dibandingkan per hari, dan deadline
 * disimpan pada penghujung hari (23:59:59) supaya tidak langsung terlambat.
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
     * Cache id trainee per nama (lowercase) untuk file lama yang menulis
     * nama pada kolom email. Sebuah nama hanya dipakai bila unik.
     *
     * @var array<string, array<int, int>>
     */
    protected array $traineeIdsByName = [];

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
        'trainee_email' => ['email_trainee', 'email', 'nama_trainee', 'trainee'],
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

    /**
     * Format yang hanya menyebut tanggal, tanpa jam.
     *
     * `DateTime::createFromFormat` mengisi komponen yang tidak disebut format
     * dengan waktu SEKARANG, sehingga "2027-01-05" dihasilkan sebagai
     * 14:25:54. Akibatnya `isDateOnly` tidak pernah cocok: batas akhir tidak
     * dijadikan 23:59:59, dan tanggal "hari ini" justru ditolak sebagai lampau
     * karena dibandingkan per menit.
     */
    protected const DATE_ONLY_FORMATS = ['Y-m-d', 'd/m/Y'];

    public function collection(Collection $rows): void
    {
        $users = User::query()->get(['id', 'email', 'name']);

        $this->traineeIds = $users->pluck('id', 'email')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->traineeIdsByName = [];

        foreach ($users as $user) {
            $this->traineeIdsByName[mb_strtolower((string) $user->name)][] = (int) $user->id;
        }

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

        $traineeId = $this->resolveTraineeId($email);

        if ($traineeId === null) {
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
        // `datetime-local` dan App\Rules\NotBeforeNow. Tanggal tanpa jam
        // dibandingkan per hari (lihat isBeforeNow), sehingga sel Excel
        // bertanggal tidak selalu ditolak.
        if ($this->isBeforeNow($start)) {
            return $this->fail('Tanggal mulai tidak boleh sebelum waktu sekarang.', $row);
        }

        if ($deadline === '') {
            return $this->fail('Tanggal deadline wajib diisi.', $row);
        }

        $deadlineValue = $this->parseDate($deadline);

        if ($deadlineValue === null) {
            return $this->fail('Tanggal deadline "'.$deadline.'" tidak valid.', $row);
        }

        $deadlineIsDateOnly = $this->isDateOnly($deadlineValue);

        if ($this->isBeforeNow($deadlineValue)) {
            return $this->fail('Tanggal deadline tidak boleh sebelum waktu sekarang.', $row);
        }

        // Tanggal tanpa jam berakhir di penghujung harinya, supaya task yang
        // jatuh tempo hari ini tidak langsung dihitung terlambat oleh scheduler.
        if ($deadlineIsDateOnly) {
            $deadlineValue = $deadlineValue->copy()->endOfDay();
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
            'trainee_id' => $traineeId,
            'task' => $task,
            'desc' => $desc !== '' ? $desc : null,
            'start_date' => $start,
            'deadline' => $deadlineValue,
            'status' => $normalized->value,
        ];
    }

    /**
     * Cari id trainee dari nilai kolom email.
     *
     * File ekspor lama menulis nama trainee pada kolom itu; nilai non-email
     * dicocokkan ke nama asalkan namanya unik supaya hasil ekspor lawas tetap
     * bisa diimpor. Email yang tidak terdaftar tetap ditolak.
     */
    protected function resolveTraineeId(string $value): ?int
    {
        if (isset($this->traineeIds[$value])) {
            return $this->traineeIds[$value];
        }

        if (str_contains($value, '@')) {
            return null;
        }

        $matches = $this->traineeIdsByName[mb_strtolower($value)] ?? [];

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Tanggal sudah lewat dibanding waktu sekarang.
     *
     * Nilai berjam dibandingkan per menit (presisi input `datetime-local` dan
     * App\Rules\NotBeforeNow). Tanggal tanpa jam dibandingkan per hari:
     * "9 Oktober" berarti sepanjang 9 Oktober, jadi tidak ditolak hanya karena
     * jamnya 00:00.
     */
    protected function isBeforeNow(Carbon $date): bool
    {
        if ($this->isDateOnly($date)) {
            return $date->copy()->startOfDay()->lt(now()->startOfDay());
        }

        return $date->copy()->startOfMinute()->lt(now()->startOfMinute());
    }

    /**
     * Tanggal tanpa komponen jam: hasil parse `Y-m-d`/`d/m/Y` maupun sel
     * Excel bertanggal (dibaca PhpSpreadsheet sebagai 00:00:00).
     */
    protected function isDateOnly(Carbon $date): bool
    {
        return $date->format('H:i:s') === '00:00:00';
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
                // Tanggal tanpa jam dimulai dari tengah malam, bukan waktu
                // "sekarang" yang diwariskan createFromFormat (lihat
                // DATE_ONLY_FORMATS).
                if (in_array($format, self::DATE_ONLY_FORMATS, true)) {
                    $parsed->startOfDay();
                }

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
