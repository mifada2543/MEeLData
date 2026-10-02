<?php

namespace App\Exports;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export berbasis query. Query sudah mencakup scope ownership (admin vs
 * trainee) dan filter status aktif dari halaman list, sehingga hasilnya
 * konsisten dengan data yang sedang ditampilkan. Tidak pernah
 * mengambil seluruh tabel.
 */
class TasksExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with('trainee:id,name');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'UUID',
            'Trainee',
            'Task',
            'Description',
            'Start Date',
            'Deadline',
            'Status',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * @param  TraineeTask  $task
     * @return array<int, string|null>
     */
    public function map($task): array
    {
        return [
            $task->id,
            $task->trainee?->name,
            $task->task,
            $task->desc,
            $task->start_date?->format('Y-m-d H:i:s'),
            $task->deadline?->format('Y-m-d H:i:s'),
            $this->statusLabel($task->status),
            $task->created_at?->format('Y-m-d H:i:s'),
            $task->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function statusLabel(?TaskStatus $status): string
    {
        return $status instanceof TaskStatus ? $status->label() : '';
    }
}
