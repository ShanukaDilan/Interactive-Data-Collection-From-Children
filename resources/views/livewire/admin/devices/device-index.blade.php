<div>
    <x-admin.page-header title="Devices"
                         subtitle="Tablets and phones set up for offline quizzes. Register a device from its quiz setup screen.">
        <x-slot:actions>
            <a href="{{ route('play.show') }}" class="btn btn-primary">▶ Open quiz on this device</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4">
        <label class="flex items-center gap-2 text-sm font-bold text-muted">
            <input type="checkbox" wire:model.live="showRevoked" class="size-4 rounded border-line text-coral focus:ring-coral">
            Show revoked devices
        </label>
    </div>

    <div class="card overflow-hidden">
        @if ($devices->isEmpty())
            <x-admin.empty-state icon="📱" title="No devices yet"
                                 message="Open the quiz on a classroom tablet while online and tap “Set up for offline use” at the bottom of the setup screen." />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Device</th>
                        @if (auth()->user()->isAdmin()) <th>Registered by</th> @endif
                        <th>Last synced</th>
                        <th>Last online</th>
                        <th class="text-right">Sessions</th>
                        <th>Status</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($devices as $device)
                        <tr wire:key="device-{{ $device->id }}">
                            <td>
                                <p class="font-bold">📱 {{ $device->name }}</p>
                                <p class="max-w-xs truncate text-xs text-muted" title="{{ $device->user_agent }}">Added {{ $device->created_at->format('j M Y') }}</p>
                            </td>
                            @if (auth()->user()->isAdmin()) <td>{{ $device->user?->name ?? '—' }}</td> @endif
                            <td class="whitespace-nowrap">{{ $device->last_synced_at?->diffForHumans() ?? 'never' }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $device->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-right">{{ $device->quiz_sessions_count }}</td>
                            <td>
                                @if ($device->isRevoked())
                                    <span class="badge bg-line text-muted">Revoked</span>
                                @else
                                    <span class="badge bg-mint/50 text-ink">Active</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if (! $device->isRevoked())
                                    @can('revoke', $device)
                                        <button type="button" wire:click="revoke({{ $device->id }})"
                                                wire:confirm="Revoke “{{ $device->name }}”? It will stop syncing immediately. Answers not yet synced on that device will be lost unless it syncs first."
                                                class="btn btn-danger btn-sm">Revoke</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $devices->links() }}</div>
</div>
