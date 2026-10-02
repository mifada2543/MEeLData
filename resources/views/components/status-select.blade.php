@props(['task', 'routePrefix' => ''])

{{--
    Inline status update langsung dari tabel (design.md §8).

    Bentuk native (form + button submit) sehingga tetap berfungsi tanpa
    JavaScript, dan `late` sengaja tidak tersedia sebagai pilihan manual
    karena hanya dihasilkan scheduler.
--}}
<div x-data="{ open: false, busy: false }" class="relative inline-block text-left">
    <button
        type="button"
        class="mt-0.5 flex items-center gap-1 rounded px-1 py-0.5 text-[11px] font-medium text-ink-faint opacity-0 transition-opacity hover:bg-surface-hover hover:text-ink-soft focus-visible:opacity-100 focus-visible:outline-none group-hover/row:opacity-100 focus-within:opacity-100"
        @click="open = !open"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="true"
    >
        Change
        <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-cloak
        x-show="open"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-trap.noscroll="open"
        class="absolute left-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-line bg-surface p-1 shadow-popover"
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
</div>

<x-field-error name="status" />