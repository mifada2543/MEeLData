<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Progress = 'progress';
    case Done = 'done';
    case Late = 'late';
    case Canceled = 'canceled';

    /**
     * Label Bahasa Indonesia untuk tampilan (badge, dropdown, filter,
     * tabel laporan impor).
     *
     * PENTING: label ini hanya untuk TAMPILAN. Nilai kanonik enum
     * (progress/done/canceled) tetap dipakai di database, parameter
     * filter `?status=`, kolom Status pada file ekspor, dan validasi
     * impor - sehingga file hasil ekspor selalu bisa diimpor kembali.
     */
    public function label(): string
    {
        return match ($this) {
            self::Progress => 'Progress',
            self::Done => 'Selesai',
            self::Late => 'Terlambat',
            self::Canceled => 'Dibatalkan',
        };
    }

    /**
     * Kelas warna badge per status.
     *
     * Dipakai bersama oleh <x-status-badge> dan <x-status-select> agar
     * badge statis dan badge yang bisa diklik selalu warnanya sama persis.
     *
     * Urutan nilai: [teks, latar, border, titik].
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::Progress => ['text-progress', 'bg-progress-surface', 'border-progress-border', 'bg-progress'],
            self::Done => ['text-done', 'bg-done-surface', 'border-done-border', 'bg-done'],
            self::Late => ['text-late', 'bg-late-surface', 'border-late-border', 'bg-late'],
            self::Canceled => ['text-canceled', 'bg-canceled-surface', 'border-canceled-border', 'bg-canceled'],
        };
    }

    /**
     * Status yang dimiliki sistem: hanya dihasilkan scheduler
     * (tasks:mark-late) dan tidak dapat diubah lewat interaksi user.
     *
     * PRD OQ-05 tidak memutuskan aturan transisi status, dan edge-case
     * table PRD menyebut "Task sudah `Late` -> tidak perlu diubah lagi".
     * Karena itu badge `Terlambat` dirender statis (tanpa pemicu) dan
     * UpdateStatusRequest menolak perubahan dari status ini.
     */
    public function isSystemOwned(): bool
    {
        return $this === self::Late;
    }

    /**
     * Status yang boleh dipilih manual oleh user.
     * `late` hanya dihasilkan oleh scheduler (tasks:mark-late).
     */
    public static function manualCases(): array
    {
        return [self::Progress, self::Done, self::Canceled];
    }

    /**
     * Nilai status yang valid untuk input manual (dropdown & import).
     */
    public static function manualValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::manualCases());
    }

    /**
     * Semua nilai status yang tersimpan di database (termasuk `late`).
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Menerjemahkan input status dari humans menjadi nilai kanonik.
     *
     * Menerima nilai kanonik (progress/done/canceled) maupun label
     * Bahasa Indonesia (Progress/Selesai/Dibatalkan), sehingga file
     * impor yang disusun manual maupun hasil ekspor bisa sama-sama
     * diterima. Case-insensitive.
     *
     * `late` TIDAK pernah diterima: status tersebut hanya dihasilkan
     * oleh scheduler.
     */
    public static function normalizeInput(string $input): ?self
    {
        $needle = mb_strtolower(trim($input));

        foreach (self::manualCases() as $case) {
            if (mb_strtolower($case->value) === $needle || mb_strtolower($case->label()) === $needle) {
                return $case;
            }
        }

        return null;
    }
}
