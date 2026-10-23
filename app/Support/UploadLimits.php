<?php

namespace App\Support;

/**
 * Membaca batas unggahan PHP yang benar-benar berlaku.
 *
 * Impor Excel mengizinkan berkas .xlsx sampai 10 MB (OQ-4), tetapi batas itu
 * hanya berlaku bila `upload_max_filesize` dan `post_max_size` di php.ini
 * lebih besar. Bila PHP membatasi lebih kecil, berkas dipotong sebelum
 * sampai ke Laravel dan pengguna hanya melihat "berkas gagal diunggah"
 * tanpa tahu penyebabnya.
 *
 * Kelas ini dipakai untuk menampilkan batas yang sebenarnya pada modal
 * import maupun pada pesan galat validasi, supaya label tidak menjanjikan
 * ukuran yang pada akhirnya ditolak server.
 */
class UploadLimits
{
    public const APP_LIMIT_BYTES = 10 * 1024 * 1024;

    public const POST_OVERHEAD_BYTES = 1 * 1024 * 1024;

    public static function effectiveLimitMb(): string
    {
        $candidates = [
            self::APP_LIMIT_BYTES,
            self::uploadLimitBytes(),
        ];

        $post = self::postLimitBytes();
        if ($post > self::POST_OVERHEAD_BYTES) {
            $candidates[] = $post - self::POST_OVERHEAD_BYTES;
        }

        $candidates = array_values(array_filter($candidates, static fn (int $b): bool => $b > 0));

        if ($candidates === []) {
            return (string) (self::APP_LIMIT_BYTES / 1048576);
        }

        $bytes = min($candidates);

        return self::formatMb($bytes);
    }

    /**
     * Nilai `upload_max_filesize` dalam byte. 0 berarti tidak terbaca/tanpa batas.
     */
    public static function uploadLimitBytes(): int
    {
        return self::toBytes((string) ini_get('upload_max_filesize'));
    }

    /**
     * Nilai `post_max_size` dalam byte. 0 berarti tidak terbaca/tanpa batas.
     */
    public static function postLimitBytes(): int
    {
        return self::toBytes((string) ini_get('post_max_size'));
    }

    /**
     * Pesan untuk `post_max_size` terlampaui.
     *
     * Dipakai dari penanganan 413 di bootstrap/app.php. Angka yang ditampilkan
     * adalah `post_max_size` dikurangi overhead, karena body POST berisi berkas
     * SEJAK token CSRF dan field lain - jadi hanya sebesar itu yang tersisa
     * untuk berkasnya.
     */
    public static function postSizeMessage(): string
    {
        $limit = self::postLimitBytes();

        if ($limit <= 0) {
            return 'Ukuran berkas melebihi batas unggahan server. Perkecil berkas atau minta administrator menaikkan `post_max_size` pada php.ini.';
        }

        return 'Ukuran berkas melebihi batas unggahan server (maks. '
            .self::formatMb(max(0, $limit - self::POST_OVERHEAD_BYTES)).' MB). '
            .'Perkecil berkas, atau minta administrator menaikkan `post_max_size` pada php.ini.';
    }

    /**
     * Ubah nilai ukuran php.ini ("2M", "16M", "1024K", "0", "") menjadi byte.
     * `0` atau string kosong berarti tanpa batas -> 0.
     *
     * @param  string  $value  nilai dari php.ini
     */
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024), // Gigabyte ke Byte
            'm' => (int) round($number * 1024 * 1024),        // Megabyte ke Byte
            'k' => (int) round($number * 1024),               // Kilobyte ke Byte
            default => (int) round($number),
        };
    }

    /**
     * Format byte menjadi MB tanpa nol di belakang koma, mis. "10" atau "2,5".
     *
     * Dipakai bersama oleh pesan galat impor (FormRequest dan penanganan 413)
     * supaya angka batas yang muncul selalu sama dengan label di modal.
     */
    public static function formatMb(int $bytes): string
    {
        $mb = rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, ',', '.'), '0'), ',');

        return $mb === '' ? '0' : $mb;
    }
}
