<?php

namespace App\Livewire\Admin\Account;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Change password')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->update(['password' => $this->password]);

        activity('auth')->causedBy($user)->log('Changed password');

        $this->reset();
        $this->dispatch('toast', message: 'Password updated.');
    }

    public function render(): View
    {
        return view('livewire.admin.account.change-password');
    }
}
