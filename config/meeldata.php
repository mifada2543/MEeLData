<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-arsip task terlambat
    |--------------------------------------------------------------------------
    |
    | Masa tenggang (hari) sebelum task berstatus `late` di-soft-delete
    | otomatis oleh scheduler `tasks:archive-late`.
    |
    | Diukur dari `deadline`, BUKAN dari kapan task ditandai `late`. Itu
    | penting karena `late` hanya ditandai paling lambat satu menit setelah
    | deadline terlewati (scheduler jalan `everyMinute`).
    |
    | Nilai 0 berarti langsung diarsipkan begitu menjadi `late`.
    |
    */

    'late_archive_grace_days' => (int) env('LATE_ARCHIVE_GRACE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Jendela sebelum ditandai `late`
    |--------------------------------------------------------------------------
    |
    | Task `progress` yang deadline-nya sudah lewat ditahan selama nilai ini
    | (detik) sebelum scheduler menandainya `late`. Default 120 detik, agar
    | pengguna sempat melihat hitungan mundur "sudah lewat 1 menit 12 detik"
    | di daftar tugas sebelum statusnya berubah.
    |
    | PRD §7 menulis `deadline < now()` tanpa tenggang. Set 0 untuk kembali
    | persis ke PRD (langsung ditandai pada menit yang sama).
    |
    */

    'late_mark_after_seconds' => (int) env('LATE_MARK_AFTER_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Polling halaman live
    |--------------------------------------------------------------------------
    |
    | Seberapa sering (detik) halaman daftar tugas mengambil waktu server dan
    | status task lewat endpoint JSON. Di antara dua polling, counter tetap
    | bergerak tiap detik memakai offset terhadap waktu server, sehingga
    | tidak perlu request tiap detik.
    |
    */

    'live_poll_seconds' => (int) env('LIVE_POLL_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Jam scheduler arsip
    |--------------------------------------------------------------------------
    |
    | Jam menjalankan `tasks:archive-late` (timezone aplikasi, Asia/Jakarta).
    | Dijalankan satu kali sehari karena task tidak perlu diarsipkan lebih
    | cepat dari itu.
    |
    */

    'late_archive_at' => env('LATE_ARCHIVE_AT', '00:05'),

];
