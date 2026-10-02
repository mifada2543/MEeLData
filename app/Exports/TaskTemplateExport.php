<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import: 1 header row + 1 contoh data row (OQ-4).
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
                'trainee@example.com',
                'Maintenance Kabel Jaringan',
                'Perbaikan kabel jaringan lantai 2 dan pengujian konektivitas.',
                now()->format('Y-m-d H:i:s'),
                now()->addDays(3)->format('Y-m-d H:i:s'),
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
            'trainee_email',
            'task',
            'description',
            'start_date',
            'deadline',
            'status',
        ];
    }
}
