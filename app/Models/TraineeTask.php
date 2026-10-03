<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\TraineeTaskFactory;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $trainee_id
 * @property string $task
 * @property string|null $desc
 * @property Carbon $start_date
 * @property Carbon $deadline
 * @property TaskStatus $status
 * @property int|null $deleted_by
 * @property Carbon|null $deleted_at
 */
class TraineeTask extends Model
{
    /** @use HasFactory<TraineeTaskFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'trainee_tasks';

    /**
     * `id` (UUID) dihasilkan otomatis oleh HasUuids, sehingga tidak perlu
     * generator manual. `id` sengaja tidak ada di $fillable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'trainee_id',
        'task',
        'desc',
        'start_date',
        'deadline',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'start_date' => 'datetime',
            'deadline' => 'datetime',
            'deleted_at' => 'datetime',
            'deleted_by' => 'integer',
        ];
    }

    /**
     * User yang menghapus task ini, atau `null` bila dihapus oleh sistem
     * (auto-arsip task terlambat oleh scheduler).
     *
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    /**
     * Scope wajib untuk seluruh query task: membatasi data sesuai aktor.
     * Admin melihat seluruh task, trainee hanya task miliknya.
     * Halaman list, export, dan dashboard memakai scope ini agar konsisten.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeVisibleTo(Builder $query, ?AuthenticatableContract $actor): Builder
    {
        if (User::isAdminActor($actor)) {
            return $query;
        }

        return $query->where($query->qualifyColumn('trainee_id'), $actor?->getAuthIdentifier());
    }

    /**
     * Filter berdasarkan status. Dipakai oleh list, export, dan dashboard.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeStatusIs(Builder $query, ?string $status): Builder
    {
        if ($status !== null && $status !== '' && in_array($status, TaskStatus::values(), true)) {
            return $query->where($query->qualifyColumn('status'), $status);
        }

        return $query;
    }

    /**
     * Task `progress` yang deadline-nya sudah lewat **melewati jendela
     * penandaan** (default 120 detik).
     *
     * Dipakai oleh scheduler `tasks:mark-late`, jadi inilah satu-satunya
     * definisi yang menentukan kapan sebuah task menjadi `late`.
     *
     * Di dalam jendela tersebut task masih berstatus `progress`
     * (lihat `scopeAwaitingScheduler`) - itulah yang membuat hitungan mundur
     * "sudah lewat …" sempat terlihat.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeOverdueProgress(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('status'), TaskStatus::Progress->value)
            ->where(
                $query->qualifyColumn('deadline'),
                '<',
                now()->subSeconds($this->lateMarkAfterSeconds())
            );
    }

    /**
     * Seluruh task `progress` yang deadline-nya sudah lewat, termasuk yang
     * masih di dalam jendela penandaan.
     *
     * Ini adalah isi "kelompok 4" di daftar tugas dan sumber data untuk
     * hitungan mundur realtime. Dipakai juga untuk badge peringatan, dengan
     * penyaring `scopeOverdueProgress()` - kalau tidak, setiap task yang
     * baru lewat deadline akan memicu peringatan "scheduler tidak aktif".
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeAwaitingScheduler(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('status'), TaskStatus::Progress->value)
            ->where($query->qualifyColumn('deadline'), '<', now());
    }

    /**
     * Jendela penandaan dalam detik (configurabel).
     */
    public static function lateMarkAfterSeconds(): int
    {
        return max(0, (int) config('meeldata.late_mark_after_seconds', 120));
    }

    /**
     * Deadline akan segera jatuh tempo (dalam N hari) dan belum selesai.
     * Task berstatus `late` tidak termasuk karena deadline-nya sudah lewat.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeApproachingDeadline(Builder $query, int $days = 3): Builder
    {
        return $query
            ->whereBetween($query->qualifyColumn('deadline'), [now(), now()->addDays($days)])
            ->whereNotIn($query->qualifyColumn('status'), [
                TaskStatus::Done->value,
                TaskStatus::Canceled->value,
                TaskStatus::Late->value,
            ]);
    }

    /**
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeStatusLate(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), TaskStatus::Late->value);
    }

    /**
     * Task `late` yang deadline-nya sudah tidak lagi lewat.
     *
     * `late` berarti "deadline sudah lewat dan belum selesai" - jadi begitu
     * deadline digeser ke masa depan, premisen itu tidak berlaku lagi dan
     * statusnya tidak lagi benar. Ini ruleset pasangan dari
     * `scopeOverdueProgress()`, dipanggil scheduler `tasks:mark-late`.
     *
     * Kedua ruleset saling melengkapi tanpa tumpang tindih:
     *
     *   progress & deadline < now - 120s  -> late
     *   late     & deadline >= now         -> progress
     *
     * Tidak ada jeda yang bisa memicu flip-flop bolak-balik.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeLateWithFutureDeadline(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('status'), TaskStatus::Late->value)
            ->where($query->qualifyColumn('deadline'), '>=', now());
    }

    /**
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeStatusIn(Builder $query, array $statuses): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), $statuses);
    }

    /**
     * Urutan prioritas kerja, dari paling tinggi ke paling rendah:
     *
     * 1. Sedang dikerjakan    - `progress` yang deadline-nya belum lewat
     * 2. Selesai             - `done`
     * 3. Dibatalkan          - `canceled`
     * 4. Progress lewat      - `progress` yang deadline-nya sudah lewat
     * 5. Terlambat           - `late`
     *
     * Kelompok 4 praktis hanya buffer sesaat: `tasks:mark-late` berjalan
     * setiap menit, jadi task di sana menjadi `late` (kelompok 5) paling
     * lambat satu menit kemudian. Kalau kelompok 4 menetapberarti
     * scheduler tidak aktif - hal itu ditampilkan sebagai peringatan, bukan
     * sekadar kategori.
     *
     * Catatan penting: `now()` dikirim sebagai binding, BUKAN memakai
     * `NOW()` SQL. Kolom tanggal menyimpan waktu dalam timezone aplikasi
     * (Asia/Jakarta), sedangkan `NOW()` memakai timezone session MySQL. Di
     * server UTC keduanya meleset 7 jam dan pengurutan jadi salah.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeByWorkPriority(Builder $query): Builder
    {
        $now = now();

        return $query->orderByRaw(
            'CASE
                WHEN status = ? AND deadline >= ? THEN 1
                WHEN status = ?                  THEN 2
                WHEN status = ?                  THEN 3
                WHEN status = ? AND deadline <  ? THEN 4
                WHEN status = ?                  THEN 5
                ELSE 6
            END ASC',
            [
                TaskStatus::Progress->value, $now,
                TaskStatus::Done->value,
                TaskStatus::Canceled->value,
                TaskStatus::Progress->value, $now,
                TaskStatus::Late->value,
            ]
        );
    }

    /**
     * Hanya task yang sudah soft-delete (halaman Sampah).
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeTrashed(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc($query->qualifyColumn('created_at'));
    }

    /**
     * Deadline sudah terlewati (helper tampilan tabel).
     */
    public function isOverdue(): bool
    {
        return $this->deadline !== null && $this->deadline->lt(now());
    }

    /**
     * Deadline terlewati sementara task belum selesai dan belum dibatalkan,
     * sehingga masih menunggu scheduler menandainya sebagai late.
     */
    public function isAwaitingLateMarking(): bool
    {
        return $this->status === TaskStatus::Progress && $this->isOverdue();
    }

    /**
     * Task aktif (`progress`) yang punya hitungan mundur di daftar tugas.
     *
     * Berlaku baik deadline belum lewat (`sisa …`) maupun sudah lewat
     * (`dalam …` / `terlambat …`). `done` dan `canceled` tidak perlu: task
     * yang sudah tutup tidak punya "sisa waktu".
     */
    public function hasCountdown(): bool
    {
        return $this->status === TaskStatus::Progress;
    }

    /**
     * Kelompok prioritas (1..5), harus konsisten dengan
     * `scopeByWorkPriority()` agar label di tabel cocok dengan urutannya.
     */
    public function workPriority(): int
    {
        return match ($this->status) {
            TaskStatus::Done => 2,
            TaskStatus::Canceled => 3,
            TaskStatus::Late => 5,
            default => $this->isOverdue() ? 4 : 1,
        };
    }

    /**
     * Menyamakan status turunan dengan deadline yang tersimpan.
     *
     * Satu-satunya koreksi yang dilakukan: `late` dengan deadline di masa
     * depan dikembalikan menjadi `progress`. Inilah yang membuat status
     * `late` bersifat TURUNAN, bukan status yang dipatok user.
     *
     * Dipanggil langsung setelah edit/restore supaya perubahannya terasa
     * seketika, dan dicerminkan lagi oleh scheduler
     * (`scopeLateWithFutureDeadline`) sebagai pengaman untuk kasus yang
     * tidak melalui controller - mis. impor atau ubah langsung di database.
     *
     * @return bool true bila statusnya sebenarnya diubah.
     */
    public function reconcileDerivedStatus(): bool
    {
        if ($this->status !== TaskStatus::Late || $this->isOverdue()) {
            return false;
        }

        $this->forceFill(['status' => TaskStatus::Progress->value])->save();

        return true;
    }

    /**
     * Apakah aktor ini boleh membuka dan menyimpan form edit task ini?
     *
     * Aturan:
     * - Admin bebas mengedit seluruh task, tanpa kecuali.
     * - Trainee hanya boleh mengedit task `progress` miliknya.
     * - `late` terkunci penuh untuk semua: statusnya berasal dari deadline,
     *   jadi memperbaiki metadata tidak akan mengubah apa pun.
     * - `done`/`canceled` yang deadline-nya SUDAH lewat juga terkunci: saat
     *   itu tidak ada lagi yang bisa "diperbaiki" karena tenggatnya selesai.
     * - `done`/`canceled` yang deadline-nya MASIH di depan boleh diedit,
     *   tapi hanya dengan konfirmasi eksplisit (lihat
     *   `editNeedsConfirmation()`).
     */
    public function isEditableBy(User $actor): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return match ($this->status) {
            TaskStatus::Progress => true,
            TaskStatus::Done, TaskStatus::Canceled => ! $this->isOverdue(),
            default => false,
        };
    }

    /**
     * Alasan task ini tidak boleh diedit trainee, untuk pesan 403.
     */
    public function editLockReason(): ?string
    {
        if ($this->status === TaskStatus::Late) {
            return 'Tugas yang sudah terlambat tidak dapat diubah oleh trainee.';
        }

        if (in_array($this->status, [TaskStatus::Done, TaskStatus::Canceled], true) && $this->isOverdue()) {
            return 'Tugas ini sudah lewat deadline, sehingga tidak dapat diubah lagi oleh trainee.';
        }

        return null;
    }

    /**
     * Apakah membuka form edit task ini perlu konfirmasi eksplisit?
     *
     * Hanya untuk task yang ditutup (`done`/`canceled`) lebih awal dari
     * deadline-nya: statusnya tidak akan berubah, jadi yang diedit cuma
     * metadata - dan metadata yang salah pada task yang sudah tutup
     * kesalahan yang perlu dikonfirmasi, bukan sekadar koreksi sepele.
     */
    public function editNeedsConfirmation(User $actor): bool
    {
        return ! $actor->isAdmin()
            && in_array($this->status, [TaskStatus::Done, TaskStatus::Canceled], true);
    }

    /**
     * Apakah hanya `deadline` yang boleh diubah untuk task ini?
     *
     * Berlaku khusus pada task `late`. Permintaan pengguna: "buat menjadi
     * tugas baru yang dimana tanggal pengerjaannya tidak bisa diubah hanya
     * tanggal deadline nya saja yang bisa diubah serta ubah statusnya menjadi
     * progress". Jadi nama, deskripsi, dan tanggal mulai ikut terkunci.
     *
     * Dicegukan server-side di `UpdateTaskRequest` - `readonly` di HTML
     * bukan pengaman karena request bisa dibuat-buat.
     */
    public function hasLockedEditFields(): bool
    {
        return $this->status === TaskStatus::Late;
    }

    /**
     * Memulihkan task yang sudah soft-delete, sekaligus mengganti
     * tanggalnya.
     *
     * Status `late` dikembalikan menjadi `progress` oleh
     * `reconcileDerivedStatus()` karena tanggal baru berada di masa depan,
     * sehingga task tidak akan langsung terlambat lagi - dan akan kembali
     * ditandai `late` bila deadline barunya terlewati.
     *
     * @param  array{start_date: Carbon, deadline: Carbon}  $dates
     */
    public function restoreWithDates(array $dates): void
    {
        $this->forceFill([
            'start_date' => $dates['start_date'],
            'deadline' => $dates['deadline'],
            'deleted_by' => null,
        ])->save();

        $this->restore();

        $this->reconcileDerivedStatus();
    }
}
