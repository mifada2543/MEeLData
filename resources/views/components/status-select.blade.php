@props(['task', 'routePrefix' => '', 'instance' => ''])

@php
    [$text, $surface, $border, $dot] = $task->status->badgeClasses();

    /*
        `instance` wajib diisi pemanggil yang merender task ini lebih dari
        sekali pada halaman yang sama - kartu mobile DAN tabel desktop, misalnya.
        Tanpa itu keduanya menghasilkan `id` yang sama persis, dan itu
        merusak tiga hal sekaligus:
          * HTML tidak valid (id harus unik di satu dokumen),
          * `aria-controls` pada tombol menunjuk menu yang ambigu sehingga
            screen reader bisa announcing menu tersembunyi,
          * `document.getElementById()` mengembalikan yang pertama - yaitu
            kartu yang sedang `display:none` di layar lebar - sehingga dropdown
            terlihat "tidak terbuka" padahal state-nya sudah berubah.
    */
    $menuId = 'status-menu-'.($instance !== '' ? $instance.'-' : '').$task->id;
@endphp

{{--
    Inline status update langsung dari tabel (design.md §8.1).

    Badge status itu sendiri yang jadi pemicu dropdown - tidak ada tombol
    "Ubah" terpisah. Spec §8.1 memang mensyaratkan "[ Progress ▼ ]" yang
    diklik langsung, dan menyatukan keduanya menghemat satu kolom.

    Bentuk menu tetap native (form + button submit) supaya tetap berfungsi
    tanpa JavaScript; untuk itu ada fallback <noscript> di bawah.

    Komponen ini HANYA untuk status yang boleh diubah user. Status milik
    sistem (`late`) tidak dirender di sini - pemanggil memakai
    <x-status-badge> statis, supaya tidak ada affordance yang menjanjikan
    aksi. Aturan yang sama ditegakkan server-side di UpdateStatusRequest.
--}}
<div
    x-data="{
        open: false,
        style: '',
        close() {
            this.open = false
            this.$refs.trigger?.focus()
        },
        toggle() {
            this.open = !this.open
            if (this.open) this.$nextTick(() => this.position())
        },
        /*
         * Menu diposisikan `fixed` (bukan `absolute` di dalam sel) karena
         * wrapper tabel memakai `overflow-x-auto`. Satu sumbu overflow `auto`
         * membuat sumbu lain ikut terpotong, sehingga dropdown di baris
         * terakhir ikut terclip. `fixed` keluar dari container scroll.
         *
         * Tapi `.table-scroll` juga memakai `contain: paint`, dan itu
         * menjadikan dirinya containing block untuk `position: fixed` di
         * dalamnya. Artinya koordinat menu dihitung RELATIF KE WADAH, bukan ke
         * viewport - kalau tidak, menu meleset ratusan piksel dan sering
         * terpotong di luar area yang terlihat.
         *
         * `x-teleport` terlihat menarik secara teori, tapi gagal di Alpine
         * untuk elemen ini (cloneNode dari undefined), jadi pendekatan di
         * bawah: area yang boleh dipakai adalah IRISAN antara kotak wadah
         * dengan viewport.
         */
        fixedHost() {
            let el = this.$refs.menu?.parentElement
            while (el && el !== document.body) {
                const cs = getComputedStyle(el)
                const contain = cs.contain || ''
                if (
                    contain.includes('paint') || contain.includes('layout') ||
                    contain.includes('strict') || contain.includes('content') ||
                    cs.transform !== 'none' || cs.filter !== 'none' ||
                    (cs.backdropFilter && cs.backdropFilter !== 'none') ||
                    cs.perspective !== 'none' || cs.willChange !== 'auto'
                ) {
                    return el
                }
                el = el.parentElement
            }
            return null
        },
        position() {
            const trigger = this.$refs.trigger
            const menu = this.$refs.menu
            if (!trigger || !menu) return

            const r = trigger.getBoundingClientRect()
            const w = menu.offsetWidth || 176
            const h = menu.offsetHeight || 0

            const host = this.fixedHost()
            const hb = host ? host.getBoundingClientRect() : { left: 0, top: 0, right: 0, bottom: 0 }

            // Batas yang boleh dipakai: irisan kotak wadah dengan viewport,
            // supaya menu tidak keluar dari `overflow` maupun dari layar.
            const pad = 8
            const minX = Math.max(hb.left, 0) + pad
            const rightEdge = Math.min(host ? hb.right : innerWidth, innerWidth) - pad
            const maxX = rightEdge - w
            const minY = Math.max(hb.top, 0) + pad
            const maxY = Math.max(minY, Math.min(host ? hb.bottom : innerHeight, innerHeight) - h - pad)

            // Menu menempel ke sisi kiri tombol. Kalau tidak muat, digeser ke
            // sisi KANAN tombol - bukan dilemmas ke batas wadah - supaya tetap
            // terlihat menempel pada pemicunya meski kolomnya mepet tepi.
            let left = r.left
            if (left + w > rightEdge) left = r.right - w
            left = Math.max(minX, Math.min(left, Math.max(minX, maxX)))

            // Koordinat `position: fixed` di dalam wadah bergulir dihitung dari
            // padding box wadah SETELAH digeser, jadi scrollLeft/scrollTop ikut
            // dikurangkan. Tanpa ini menu meleset sebesar nilai scroll: begitu
            // tabel digeser mendatar, menu tampak jauh ke kiri dari tombolnya.
            const offsetX = host ? hb.left - host.scrollLeft : 0
            const offsetY = host ? hb.top - host.scrollTop : 0

            const below = innerHeight - r.bottom
            const top = below < h + 12
                ? Math.max(minY, r.top - h - 4)
                : Math.max(minY, Math.min(r.bottom + 4, maxY))

            this.style = `left:${Math.round(left - offsetX)}px; top:${Math.round(top - offsetY)}px`
        },
    }"
    class="relative inline-block text-left align-middle"
    @scroll.capture.window="open && position()"
    @resize.window="open && position()"
>
    <button
        x-ref="trigger"
        type="button"
        class="group/badge inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition hover:brightness-[0.97] focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas focus-visible:ring-current focus-visible:outline-none {{ $text }} {{ $surface }} {{ $border }}"
        :class="open ? 'ring-2 ring-current' : ''"
        @click="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
        aria-controls="{{ $menuId }}"
        {{-- Label terlihat ("Progress") tetap jadi bagian dari accessible name (WCAG 2.5.3). --}}
        aria-label="Ubah status: {{ $task->status->label() }}"
    >
        <span class="size-1.5 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
        {{ $task->status->label() }}

        <x-icon
            name="chevron-down"
            class="size-3 shrink-0 opacity-60 transition group-hover/badge:opacity-100 group-focus-visible/badge:opacity-100"
            x-bind:class="open ? 'opacity-100' : ''"
        />
    </button>

    <div
        id="{{ $menuId }}"
        x-ref="menu"
        x-cloak
        x-show="open"
        x-bind:style="style"
        @click.outside="close()"
        @keydown.escape.window="close()"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-trap.noscroll="open"
        class="fixed top-0 left-0 z-50 w-44 overflow-hidden rounded-lg border border-line bg-surface p-1 shadow-popover"
        role="menu"
    >
        <form method="POST" action="{{ route($routePrefix.'tasks.status', ['task' => $task->id]) }}">
            @csrf
            @method('PATCH')

            @foreach (\App\Enums\TaskStatus::manualCases() as $case)
                <button
                    type="submit"
                    name="status"
                    value="{{ $case->value }}"
                    @disabled($task->status === $case)
                    class="flex w-full items-center justify-between gap-2 rounded px-2.5 py-1.5 text-left text-[13px] font-medium text-ink-soft transition-colors hover:bg-surface-hover hover:text-ink focus:bg-surface-hover focus:outline-none disabled:cursor-default disabled:opacity-45"
                    role="menuitem"
                >
                    {{ $case->label() }}
                    @if ($task->status === $case)
                        <x-icon name="check" class="size-3.5 text-done" />
                    @endif
                </button>
            @endforeach
        </form>
    </div>

    {{--
        Tanpa JavaScript tidak ada yang bisa membuka dropdown, jadi
        disediakan form native yang langsung bisa dipakai.
    --}}
    <noscript>
        <form
            method="POST"
            action="{{ route($routePrefix.'tasks.status', ['task' => $task->id]) }}"
            class="mt-1.5 flex items-center gap-1"
        >
            @csrf
            @method('PATCH')

            <label class="sr-only" for="status-fallback-{{ $task->id }}">
                Ubah status {{ $task->task }}
            </label>
            <select
                id="status-fallback-{{ $task->id }}"
                name="status"
                class="rounded-md border border-line bg-surface px-1.5 py-0.5 text-[11px] text-ink-soft"
            >
                @foreach (\App\Enums\TaskStatus::manualCases() as $case)
                    <option value="{{ $case->value }}" @selected($task->status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>

            <button
                type="submit"
                class="rounded-md bg-ink px-2 py-0.5 text-[11px] font-medium text-canvas hover:bg-ink-soft focus-visible:outline-none"
            >
                Simpan
            </button>
        </form>
    </noscript>
</div>

<x-field-error name="status" />