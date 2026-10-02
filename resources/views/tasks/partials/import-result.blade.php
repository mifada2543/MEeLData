@php
    $imported = (int) session('imported_count', 0);
    $errors = session('import_errors', []);
@endphp

{{-- Laporan hasil import: sukses + detail kegagalan per nomor baris Excel. --}}
<section aria-labelledby="import-result-title" class="mb-4 overflow-hidden rounded-xl border border-line bg-surface shadow-xs">
    <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
        <h2 id="import-result-title" class="text-sm font-semibold text-ink">Import Result</h2>

        <span class="rounded-full bg-surface-sunken px-2.5 py-1 text-[11px] font-medium tabular-nums text-ink-muted">
            {{ $imported }} imported · {{ count($errors) }} failed
        </span>
    </header>

    <div class="px-5 py-4">
        @if ($imported > 0)
            <p class="flex items-center gap-2 text-[13px] font-medium text-done">
                <x-icon name="check-circle" class="size-4 shrink-0" />
                {{ $imported }} {{ Str::plural('task', $imported) }} imported successfully.
            </p>
        @endif

        @if (count($errors) > 0)
            <p class="mt-1.5 flex items-center gap-2 text-[13px] font-medium text-late">
                <x-icon name="alert" class="size-4 shrink-0" />
                {{ count($errors) }} {{ Str::plural('row', count($errors)) }} failed and {{ count($errors) === 1 ? 'was' : 'were' }} not imported.
            </p>

            <div class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-line">
                <table class="w-full border-collapse text-sm">
                    <thead class="sticky top-0">
                        <tr class="bg-surface-sunken">
                            <th scope="col" class="table-header-cell w-20">Row</th>
                            <th scope="col" class="table-header-cell">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($errors as $error)
                            <tr>
                                <td class="table-body-cell w-20 text-[13px] font-medium tabular-nums text-ink">
                                    {{ $error['row'] }}
                                </td>
                                <td class="table-body-cell text-[13px] text-late">{{ $error['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>