{{--
    Baris filter status.

    Urutan prioritas (lihat `scopeByWorkPriority`) menaruh task terlambat di
    paling akhir - itu memang yang diminta, tapi artinya task terlambat bisa
    terdorong ke halaman BERIKUTNYA dan praktis tidak terlihat lagi dari
    daftar. Baris filter ini membuat setiap kelompok bisa dijangkau dalam satu
    klik, lengkap dengan jumlahnya.

    Halaman daftar selalu menampilkan seluruh task aktif apa pun statusnya;
    hanya task yang sudah soft-delete (di halaman Sampah) yang dikecualikan.
--}}
@php
    $filters = [['value' => null, 'label' => 'Semua', 'count' => array_sum($statusCounts)]];

    foreach (\App\Enums\TaskStatus::cases() as $case) {
        $filters[] = [
            'value' => $case->value,
            'label' => $case->label(),
            'count' => $statusCounts[$case->value] ?? 0,
        ];
    }
@endphp

{{--
    `w-full` supaya baris ini selalu baris sendiri, bukan berbagi baris
    dengan tombol Import/Tambah di action bar.

    Di bawah `md` chip TIDAK di-wrap: lima chip consume dua baris penuh di
    layar 390px, padahal horizontal swipe itu perilaku native yang sudah
    dikuasai pengguna HP. Jadi jadi satu baris yang bisa digeser, lalu
    kembali wrap dari `md` ke atas. `shrink-0` pada tiap chip wajib -
    tanpa itu flex akan memampatkan chip supaya muat, bukan menggeser.
--}}
<div
    class="flex w-full -mx-4 gap-1.5 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:overflow-visible md:px-0 md:pb-0"
    role="group"
    aria-label="Filter status"
>
    @foreach ($filters as $filter)
        @php
            $isActive = $status === $filter['value'];
            $url = $filter['value'] === null
                ? route($prefix.'tasks.index')
                : route($prefix.'tasks.index', ['status' => $filter['value']]);
        @endphp
        <a
            href="{{ $url }}"
            @class([
                'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 py-1.5 text-[13px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1',
                'border-transparent bg-accent text-white' => $isActive,
                'border-line-strong bg-surface text-ink-soft hover:bg-surface-hover hover:text-ink' => ! $isActive,
            ])
            @if ($isActive) aria-current="page" @endif
        >
            {{ $filter['label'] }}
            <span
                @class([
                    'rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums',
                    'bg-white/20' => $isActive,
                    'bg-surface-sunken text-ink-muted' => ! $isActive,
                ])
            >
                {{ $filter['count'] }}
            </span>
        </a>
    @endforeach
</div>