<?php

namespace App\Exports;

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
        return $this->query->with('trainee:id,name,email');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        // Header Bahasa Indonesia. Import admin menerima header ini kembali
        // (lihat TasksImport::HEADER_ALIASES), jadi hasil ekspor bisa
        // langsung diimpor oleh admin.
        //
        // Kolom `Email Trainee` wajib ada untuk import admin: admin mencari
        // trainee lewat email. Import trainee menolak kolom email yang
        // terisi, karena task selalu masuk ke akun yang sedang login.
        return [
            'UUID',
            'Trainee',
            'Email Trainee',
            'Tugas',
            'Deskripsi',
            'Tanggal Mulai',
            'Tanggal Deadline',
            'Status',
            'Dibuat Pada',
            'Diperbarui Pada',
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
            $task->trainee?->email,
            $task->task,
            $task->desc,
            $task->start_date?->format('Y-m-d H:i:s'),
            $task->deadline?->format('Y-m-d H:i:s'),
            // Nilai kanonik (bukan label) supaya file ekspor bisa
            // diimpor kembali tanpa perlu terjemahan.
            $task->status?->value,
            $task->created_at?->format('Y-m-d H:i:s'),
            $task->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
