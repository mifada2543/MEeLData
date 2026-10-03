<?php

namespace App\Rules;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Menolak nilai tanggal yang sudah lewat (lebih tua dari waktu sekarang).
 *
 * Dipakai pada create task, import Excel, dan restore task supaya tidak ada
 * task baru yang langsung berstatus terlambat.
 *
 * Perbandingan dilakukan per MENIT, bukan per detik. Input
 * `datetime-local` hanya berpresisi menit, jadi kalau dibandingkan per detik,
 * memilih menit yang sedang berjalan akan selalu ditolak (menit 14:57.00
 * dianggap lebih tua dari 14:57.03).
 *
 * Bandingkan dengan aturan `date`: nilai yang formatnya salah akan
 * dilewati di sini dan tetap dilaporkan oleh aturan `date`.
 */
class NotBeforeNow implements ValidationRule
{
    public function __construct(private readonly string $label) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // ditangani aturan `required`
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);
        } catch (Throwable) {
            return; // format salah -> biarkan aturan `date` yang melapor
        }

        if ($date->startOfMinute()->lt(now()->startOfMinute())) {
            $fail("{$this->label} tidak boleh sebelum waktu sekarang.");
        }
    }
}
