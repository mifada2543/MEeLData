<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import: 1 header row + 1 contoh data row (OQ-4).
 * Header & contoh memakai Bahasa Indonesia; import juga menerima header
 * teknis (trainee_email, task, ...) untuk kompatibilitas.
 *
 * Kolom `Email Trainee` hanya disertakan untuk admin, karena admin wajib
 * menentukan penerima task. Template trainee tidak memuat kolom itu: task
 * selalu masuk ke akun yang sedang login.
 *
 * Status contoh memakai `progress` karena `late` tidak boleh diinput.
 */
class TaskTemplateExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly bool $includeTraineeEmail = true,
    ) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $row = [
            'Perbaikan Kabel Jaringan',
            'Perbaikan kabel jaringan lantai 2 dan pengujian konektivitas.',
            now()->format('d/m/Y H:i:s'),
            now()->addDays(3)->format('d/m/Y H:i:s'),
            'progress',
        ];

        if ($this->includeTraineeEmail) {
            array_unshift($row, 'andi@example.com');
        }

        return [$row];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $headings = [
            'Tugas',
            'Deskripsi',
            'Tanggal Mulai',
            'Tanggal Deadline',
            'Status',
        ];

        if ($this->includeTraineeEmail) {
            array_unshift($headings, 'Email Trainee');
        }

        return $headings;
    }
}
