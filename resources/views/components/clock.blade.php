@php
    $tz = config('app.timezone');
    $now = now();
@endphp

{{--
    Jam aplikasi yang berjalan realtime.

    Server merender nilai awal, lalu Alpine/JavaScript memperbaruinya tiap
    detik di sisi klien - tanpa reload dan tanpa request AJAX berulang.
    Zona waktu diambil dari APP_TIMEZONE (Asia/Jakarta) via Intl, sehingga
    jam tetap benar walau perangkat pengguna berada di zona lain.

    Nilai awal dari server tetap dipertahankan agar halaman tetap tampil
    benar bila JavaScript tidak aktif atau gagal dimuat.
--}}
<p
    x-data="clock"
    data-timezone="{{ $tz }}"
    data-initial-date="{{ $now->translatedFormat('d M Y') }}"
    data-initial-time="{{ $now->format('H:i') }}"
    class="flex items-center gap-1.5 text-[13px] text-ink-muted"
>
    <x-icon name="clock" class="size-3.5 shrink-0 text-ink-faint" />

    <span class="font-medium tabular-nums text-ink-soft" x-text="date" data-role="date">{{ $now->translatedFormat('d M Y') }}</span>

    {{-- Jam & menit tetap tampil di layar kecil, bukan hanya di >= sm. --}}
    <span class="flex items-center tabular-nums">
        <span class="px-1 text-ink-faint">&middot;</span>
        <span x-text="time" data-role="time">{{ $now->format('H:i') }}</span>
    </span>

    <span class="hidden text-ink-faint lg:inline">{{ $tz }}</span>
</p>