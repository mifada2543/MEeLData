<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import: 1 header row + 1 contoh data row (OQ-4).
 * Header & contoh memakai Bahasa Indonesia; import juga menerima header
 * teknis (trainee_email, task, ...) untuk kompatibilitas.
 * Status contoh memakai `progress` karena `late` tidak boleh diinput.
 */
class TaskTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            [
                'andi@example.com',
                'Perbaikan Kabel Jaringan',
                'Perbaikan kabel jaringan lantai 2 dan pengujian konektivitas.',
                now()->format('d/m/Y H:i:s'),
                now()->addDays(3)->format('d/m/Y H:i:s'),
                'progress',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Email Trainee',
            'Tugas',
            'Deskripsi',
            'Tanggal Mulai',
            'Tanggal Deadline',
            'Status',
        ];
    }
}
