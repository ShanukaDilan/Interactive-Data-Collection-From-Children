<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Users')]
class UserIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(User $user): void
    {
        $this->authorize('delete', $user);

        if ($user->is(Auth::user())) {
            $this->dispatch('toast', message: 'You cannot delete your own account.', type: 'error');

            return;
        }

        $name = $user->name;
        $user->tokens()->delete();
        $user->delete();

        activity('user')->causedBy(Auth::user())->withProperties(['email' => $user->email])->log('deleted');

        $this->dispatch('toast', message: "Deleted {$name}.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%'));
            })
            ->with('roles:id,name')
            ->withCount(['studies', 'ownedStudies'])
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.users.user-index', ['users' => $users]);
    }
}
