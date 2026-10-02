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
        ];
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
     * Halaman list dan export memakai scope ini agar konsisten.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeVisibleTo(Builder $query, ?AuthenticatableContract $actor): Builder
    {
        if ($actor instanceof Admin) {
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
     * Task yang masih berjalan dan deadline-nya sudah terlewati.
     * Dipakai oleh scheduler `tasks:mark-late`.
     *
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeOverdueProgress(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('status'), TaskStatus::Progress->value)
            ->where($query->qualifyColumn('deadline'), '<', now());
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
     * @param  Builder<TraineeTask>  $query
     */
    public function scopeStatusIn(Builder $query, array $statuses): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), $statuses);
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
}
