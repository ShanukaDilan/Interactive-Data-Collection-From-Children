<div>
    <x-admin.page-header title="Users" subtitle="Admins manage everything; researchers run studies; teachers run sessions.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary" wire:navigate>+ New user</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="field-input max-w-sm" aria-label="Search users">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-right">Studies</th>
                    <th class="text-right"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    @php($role = $user->roles->first()?->name)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="font-bold">
                            {{ $user->name }}
                            @if ($user->is(auth()->user())) <span class="badge ml-1 bg-canvas text-muted">you</span> @endif
                        </td>
                        <td class="text-muted">{{ $user->email }}</td>
                        <td>
                            <span @class([
                                'badge text-ink',
                                'bg-coral/20' => $role === 'admin',
                                'bg-lav/40' => $role === 'researcher',
                                'bg-sky/25' => $role === 'teacher',
                                'bg-line' => $role === null,
                            ])>{{ $role ? Str::ucfirst($role) : 'No role' }}</span>
                        </td>
                        <td class="text-right">{{ $user->owned_studies_count + $user->studies_count }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm" wire:navigate>Edit</a>
                            @unless ($user->is(auth()->user()))
                                <button type="button" wire:click="delete({{ $user->id }})"
                                        wire:confirm="Delete {{ $user->name }}? Their studies stay, without an owner."
                                        class="btn btn-danger btn-sm">Delete</button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty-state icon="👥" title="No users match" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
