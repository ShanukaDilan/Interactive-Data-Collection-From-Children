<div>
    <x-admin.page-header :title="$user ? 'Edit user' : 'New user'" :subtitle="$user?->email">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" wire:navigate>← Back to users</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form wire:submit="save" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="card space-y-5 p-6 lg:col-span-2">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="field-label">Name</label>
                    <input id="name" type="text" wire:model="name" class="field-input" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="field-label">Email</label>
                    <input id="email" type="email" wire:model="email" class="field-input" required autocomplete="off">
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <fieldset>
                <legend class="field-label">Role</legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ([
                        App\Enums\Role::Admin->value => ['🛡️', 'Full access to everything'],
                        App\Enums\Role::Researcher->value => ['🔬', 'Studies, questions, data'],
                        App\Enums\Role::Teacher->value => ['🍎', 'Runs sessions in assigned studies'],
                    ] as $value => [$icon, $description])
                        <label @class([
                            'cursor-pointer rounded-xl border-2 p-3 transition',
                            'border-coral bg-coral-soft' => $role === $value,
                            'border-line hover:border-coral/40' => $role !== $value,
                        ])>
                            <input type="radio" wire:model.live="role" value="{{ $value }}" class="sr-only">
                            <span class="block text-sm font-extrabold">{{ $icon }} {{ Str::ucfirst($value) }}</span>
                            <span class="block text-xs text-muted">{{ $description }}</span>
                        </label>
                    @endforeach
                </div>
                @error('role') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="field-label">{{ $user ? 'New password' : 'Password' }}
                        @if ($user) <span class="font-normal text-muted">(leave blank to keep)</span> @endif
                    </label>
                    <input id="password" type="password" wire:model="password" class="field-input" autocomplete="new-password">
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="field-label">Confirm password</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" class="field-input" autocomplete="new-password">
                </div>
            </div>
        </section>

        <div class="space-y-6">
            <section class="card p-6">
                <h2 class="font-display mb-1 text-lg">Assigned studies</h2>
                <p class="mb-4 text-sm text-muted">{{ $role === 'admin' ? 'Admins can access every study.' : 'Which studies this person can work on.' }}</p>
                <div class="max-h-80 space-y-1 overflow-y-auto">
                    @forelse ($studies as $study)
                        <label wire:key="study-{{ $study->id }}" class="flex items-center gap-3 rounded-xl px-2 py-1.5 text-sm hover:bg-canvas">
                            <input type="checkbox" value="{{ $study->id }}" wire:model="studyIds" class="size-4 rounded border-line text-coral focus:ring-coral"
                                   @disabled($role === 'admin')>
                            <span class="flex-1 font-bold">{{ $study->name }}</span>
                            @if ($user && $study->owner_id === $user->id) <span class="badge bg-canvas text-muted">owner</span> @endif
                        </label>
                    @empty
                        <p class="text-sm text-muted">No studies yet.</p>
                    @endforelse
                </div>
            </section>

            <button type="submit" class="btn btn-primary w-full py-3">{{ $user ? 'Save user' : 'Create user' }}</button>
        </div>
    </form>
</div>
