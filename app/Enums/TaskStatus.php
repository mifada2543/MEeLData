<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Progress = 'progress';
    case Done = 'done';
    case Late = 'late';
    case Canceled = 'canceled';

    /**
     * Label yang ditampilkan pada badge / tabel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Progress => 'Progress',
            self::Done => 'Done',
            self::Late => 'Late',
            self::Canceled => 'Canceled',
        };
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
}
