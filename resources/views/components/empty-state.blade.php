@props([
    'title',
    'message' => null,
    'icon' => 'inbox',
    'actionLabel' => null,
    'actionHref' => null,
])

{{-- Empty state (design.md §16): bukan sekadar teks, tapi memberi arah aksi. --}}
<div class="flex flex-col items-center justify-center px-6 py-16 text-center sm:py-20">
    <div class="flex size-11 items-center justify-center rounded-xl border border-line bg-surface-sunken text-ink-faint">
        <x-icon :name="$icon" class="size-5" />
    </div>

    <h2 class="mt-4 text-[15px] font-semibold text-ink">{{ $title }}</h2>

    @if ($message)
        <p class="mt-1.5 max-w-sm text-sm text-ink-muted">{{ $message }}</p>
    @endif

    @if ($actionLabel && $actionHref)
        <x-button :href="$actionHref" class="mt-6">
            <x-icon name="plus" class="size-4" />
            {{ $actionLabel }}
        </x-button>
    @endif
</div>