<div>
    <x-admin.page-header title="Studies" subtitle="Each study has its own question set, participants and settings.">
        <x-slot:actions>
            @can('create', App\Models\Study::class)
                <a href="{{ route('admin.studies.create') }}" class="btn btn-primary" wire:navigate>+ New study</a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search studies…"
               class="field-input max-w-sm" aria-label="Search studies">
    </div>

    <div class="card overflow-hidden">
        @if ($studies->isEmpty())
            <x-admin.empty-state icon="🧪" title="No studies found"
                                 :message="$search !== '' ? 'Try a different search.' : 'Create a study to start collecting data.'" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Study</th>
                        <th>Ages</th>
                        <th>Language</th>
                        <th class="text-right">Questions</th>
                        <th class="text-right">Participants</th>
                        <th class="text-right">Sessions</th>
                        <th>Status</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($studies as $study)
                        <tr wire:key="study-{{ $study->id }}">
                            <td>
                                <p class="font-bold">{{ $study->name }}</p>
                                <p class="text-xs text-muted">Owner: {{ $study->owner?->name ?? '—' }}</p>
                            </td>
                            <td class="whitespace-nowrap">{{ $study->age_min }}–{{ $study->age_max }}</td>
                            <td>{{ $study->locale }}</td>
                            <td class="text-right">{{ $study->questions_count }}</td>
                            <td class="text-right">{{ $study->participants_count }}</td>
                            <td class="text-right">{{ $study->quiz_sessions_count }}</td>
                            <td>
                                @if ($study->is_active)
                                    <span class="badge bg-mint/50 text-ink">Active</span>
                                @else
                                    <span class="badge bg-line text-muted">Paused</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                @can('update', $study)
                                    <button type="button" wire:click="toggleActive({{ $study->id }})" class="btn btn-secondary btn-sm">
                                        {{ $study->is_active ? 'Pause' : 'Activate' }}
                                    </button>
                                    <a href="{{ route('admin.studies.edit', $study) }}" class="btn btn-secondary btn-sm" wire:navigate>Edit</a>
                                @endcan
                                @can('delete', $study)
                                    <button type="button" wire:click="delete({{ $study->id }})"
                                            wire:confirm="Delete “{{ $study->name }}” and ALL of its participants, sessions and answers? This cannot be undone."
                                            class="btn btn-danger btn-sm">Delete</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $studies->links() }}</div>
</div>
