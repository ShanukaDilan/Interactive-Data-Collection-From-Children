<div>
    <x-admin.page-header title="Participants"
                         subtitle="Children are identified by a pseudonymous code. Names are optional, encrypted, and never exported by default.">
        <x-slot:actions>
            @if ($studies->isNotEmpty())
                <button type="button" wire:click="create" class="btn btn-primary">+ Add participant</button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by code, e.g. P-7K3D" class="field-input sm:max-w-xs" aria-label="Search participants">
        <select wire:model.live="studyFilter" class="field-input sm:max-w-64" aria-label="Filter by study">
            <option value="">All my studies</option>
            @foreach ($studies as $study)
                <option value="{{ $study->id }}">{{ $study->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card overflow-hidden">
        @if ($participants->isEmpty())
            <x-admin.empty-state icon="🧒" title="No participants yet"
                                 message="Add children before a session, or let teachers run anonymous sessions." />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Study</th>
                        <th>Age</th>
                        <th>Consent</th>
                        <th class="text-right">Sessions</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($participants as $participant)
                        <tr wire:key="participant-{{ $participant->id }}">
                            <td><span class="badge bg-sun/50 font-mono text-ink">{{ $participant->code }}</span></td>
                            <td>{{ $participant->display_name ?: '—' }}</td>
                            <td class="text-muted">{{ $participant->study->name }}</td>
                            <td>{{ $participant->age ?? '—' }}</td>
                            <td>
                                @if ($participant->consent_recorded_at)
                                    <span class="badge bg-mint/50 text-ink" title="{{ $participant->consent_recorded_at->toDayDateTimeString() }}">✓ Recorded</span>
                                @else
                                    <span class="badge bg-peach/25 text-ink">Missing</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $participant->quiz_sessions_count }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.participants.report', $participant) }}" class="btn btn-secondary btn-sm" wire:navigate>📊 Report</a>
                                @can('update', $participant)
                                    <button type="button" wire:click="edit({{ $participant->id }})" class="btn btn-secondary btn-sm">Edit</button>
                                @endcan
                                @can('delete', $participant)
                                    <button type="button" wire:click="delete({{ $participant->id }})"
                                            wire:confirm="Remove participant {{ $participant->code }}? Their recorded answers stay in the dataset but are no longer linked to them."
                                            class="btn btn-danger btn-sm">Remove</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $participants->links() }}</div>

    {{-- Add / edit modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[55] flex items-end justify-center bg-ink/40 p-4 sm:items-center" wire:keydown.escape.window="closeForm">
            <form wire:submit="save" class="card w-full max-w-lg space-y-5 p-6" role="dialog" aria-modal="true" aria-labelledby="participant-form-title">
                <div class="flex items-center justify-between">
                    <h2 id="participant-form-title" class="font-display text-xl">{{ $editingId ? 'Edit participant' : 'Add participant' }}</h2>
                    <button type="button" wire:click="closeForm" class="text-muted" aria-label="Close">✕</button>
                </div>

                <div>
                    <label for="studyId" class="field-label">Study</label>
                    <select id="studyId" wire:model="studyId" class="field-input">
                        @foreach ($studies as $study)
                            <option value="{{ $study->id }}">{{ $study->name }}</option>
                        @endforeach
                    </select>
                    @error('studyId') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label for="age" class="field-label">Age</label>
                        <input id="age" type="number" min="2" max="12" wire:model="age" class="field-input">
                        @error('age') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="displayName" class="field-label">Name <span class="font-normal text-muted">(optional)</span></label>
                        <input id="displayName" type="text" wire:model="displayName" class="field-input" autocomplete="off">
                        @error('displayName') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="field-label">Notes <span class="font-normal text-muted">(optional)</span></label>
                    <textarea id="notes" rows="2" wire:model="notes" class="field-input"></textarea>
                    @error('notes') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-3 rounded-xl bg-canvas p-3 text-sm">
                    <input type="checkbox" wire:model="consent" class="mt-0.5 size-4 rounded border-line text-coral focus:ring-coral">
                    <span><strong>Parental consent recorded.</strong> Tick once a parent or guardian has agreed to take part. The time and your name are saved.</span>
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">{{ $editingId ? 'Save' : 'Add participant' }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
