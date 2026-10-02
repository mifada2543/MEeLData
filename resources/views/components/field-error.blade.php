@props(['name'])

{{-- Pesan validasi tepat di bawah field terkait (design.md §18). --}}
@if (isset($errors))
    @error($name)
        <p class="mt-1.5 flex items-start gap-1.5 text-[13px] leading-relaxed text-late">
            <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @enderror
@endif