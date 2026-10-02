@php
    // Form bersama untuk Create & Edit (design.md §9 & §11).
    //
    // Catatan penting: input `disabled` (trainee) TIDAK ikut terkirim ke
    // server. Backend tetap memaksa trainee_id dari authenticated user
    // (lihat StoreTaskRequest::taskAttributes).
    $dateTimeFormat = 'Y-m-d\TH:i';
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $action }}"
    x-data="{ busy: false, confirmOpen: false }"
    @submit="busy = true"
>
    @csrf
    @unless ($task->exists)
        {{-- Create --}}
    @else
        @method('PUT')
    @endunless

    <div class="space-y-5">
        {{-- Trainee --}}
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <label for="trainee_id" class="block text-[13px] font-medium text-ink-soft">Trainee</label>

                @unless ($isAdmin)
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-ink-faint">
                        <x-icon name="shield" class="size-3" />
                        locked to your account
                    </span>
                @endunless
            </div>

            @if ($isAdmin)
                <div class="relative mt-1.5">
                    <select
                        id="trainee_id"
                        name="trainee_id"
                        required
                        class="h-10 w-full appearance-none rounded-md border border-line-strong bg-surface pl-3 pr-9 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                    >
                        <option value="">Select a trainee</option>
                        @foreach ($trainees as $trainee)
                            <option
                                value="{{ $trainee->id }}"
                                @selected((int) old('trainee_id', $task->trainee_id) === $trainee->id)
                            >
                                {{ $trainee->name }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-ink-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </div>
            @else
                <div class="mt-1.5 flex h-10 items-center gap-2.5 rounded-md border border-line bg-surface-sunken px-3">
                    <x-icon name="user" class="size-4 shrink-0 text-ink-faint" />
                    <input
                        id="trainee_id"
                        type="text"
                        value="{{ auth()->user()->name }}"
                        disabled
                        class="min-w-0 flex-1 cursor-not-allowed bg-transparent text-sm text-ink-soft"
                    >
                </div>
            @endif

            <x-field-error name="trainee_id" />
        </div>

        {{-- Task --}}
        <div>
            <label for="task" class="block text-[13px] font-medium text-ink-soft">Task</label>
            <input
                id="task"
                name="task"
                type="text"
                value="{{ old('task', $task->task) }}"
                required
                maxlength="255"
                placeholder="e.g. Maintenance Kabel Jaringan"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >
            <x-field-error name="task" />
        </div>

        {{-- Description --}}
        <div>
            <label for="desc" class="block text-[13px] font-medium text-ink-soft">Description</label>
            <textarea
                id="desc"
                name="desc"
                rows="4"
                placeholder="Optional context for this task"
                class="mt-1.5 w-full resize-y rounded-md border border-line-strong bg-surface px-3 py-2.5 text-sm leading-relaxed text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >{{ old('desc', $task->desc) }}</textarea>
            <x-field-error name="desc" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            {{-- Start date --}}
            <div>
                <label for="start_date" class="block text-[13px] font-medium text-ink-soft">Start Date</label>
                <input
                    id="start_date"
                    name="start_date"
                    type="datetime-local"
                    value="{{ old('start_date', $task->start_date?->format($dateTimeFormat)) }}"
                    required
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="start_date" />
            </div>

            {{-- Deadline --}}
            <div>
                <label for="deadline" class="block text-[13px] font-medium text-ink-soft">Deadline</label>
                <input
                    id="deadline"
                    name="deadline"
                    type="datetime-local"
                    value="{{ old('deadline', $task->deadline?->format($dateTimeFormat)) }}"
                    required
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="deadline" />
            </div>
        </div>

        {{-- Status saat ini (diubah lewat inline update dari tabel) --}}
        @if ($task->exists)
            <div class="flex items-center justify-between gap-3 rounded-lg border border-line bg-surface-sunken px-4 py-3">
                <div>
                    <p class="text-[13px] font-medium text-ink-soft">Current status</p>
                    <p class="mt-0.5 text-xs text-ink-faint">Change it inline from the task list.</p>
                </div>
                <x-status-badge :status="$task->status" />
            </div>
        @endif
    </div>

    {{-- Aksi: Cancel/Reset (reset ke nilai awal, tanpa request) + Submit --}}
    <div class="mt-7 flex items-center justify-end gap-2.5 border-t border-line pt-5">
        <x-button
            variant="secondary"
            type="button"
            @click="$el.closest('form').reset()"
            x-bind:disabled="busy"
        >
            {{ $task->exists ? 'Reset' : 'Cancel' }}
        </x-button>

        {{-- Submit pertama membuka konfirmasi, bukan langsung mengirim. --}}
        <x-button type="button" @click="confirmOpen = true" x-bind:disabled="busy">
            <span x-show="!busy">{{ $submitLabel }}</span>
            <span x-cloak x-show="busy">Saving...</span>
        </x-button>
    </div>

    {{-- Submit confirmation (design.md §10): konfirmasi sebelum data dikirim. --}}
    <x-confirm-modal
        title="Save this task?"
        message="Are you sure you want to save this task?"
        confirm-label="Confirm"
        busy-label="Saving"
        open="confirmOpen"
        form-id="{{ $formId }}"
    />
</form>