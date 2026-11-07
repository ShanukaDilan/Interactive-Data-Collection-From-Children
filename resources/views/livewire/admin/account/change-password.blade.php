<div>
    <x-admin.page-header title="Change password" subtitle="Use a long password you don't use anywhere else." />

    <form wire:submit="save" class="card max-w-lg space-y-5 p-6">
        <div>
            <label for="current_password" class="field-label">Current password</label>
            <input id="current_password" type="password" wire:model="current_password" class="field-input" autocomplete="current-password" required>
            @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="field-label">New password</label>
            <input id="password" type="password" wire:model="password" class="field-input" autocomplete="new-password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirm new password</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="field-input" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Update password</button>
    </form>
</div>
