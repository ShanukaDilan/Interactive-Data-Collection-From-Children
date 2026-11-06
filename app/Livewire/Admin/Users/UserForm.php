<?php

namespace App\Livewire\Admin\Users;

use App\Enums\Role;
use App\Models\Study;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class UserForm extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'teacher';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var list<int> */
    public array $studyIds = [];

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->user = $user;
            $this->fill([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? Role::Teacher->value,
                'studyIds' => $user->studies()->pluck('studies.id')->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$this->user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'studyIds' => ['array'],
            'studyIds.*' => ['integer', Rule::exists('studies', 'id')],
        ];
    }

    public function save(): void
    {
        $this->user ? $this->authorize('update', $this->user) : $this->authorize('create', User::class);

        $this->validate();

        if ($this->user?->is(Auth::user()) && $this->role !== Role::Admin->value) {
            $this->addError('role', 'You cannot remove your own admin role.');

            return;
        }

        $user = DB::transaction(function (): User {
            $user = $this->user ?? new User;
            $user->fill(['name' => $this->name, 'email' => $this->email]);

            if ($this->password !== '') {
                $user->password = $this->password;
            }

            $user->save();
            $user->syncRoles([$this->role]);
            $user->studies()->sync($this->studyIds);

            return $user;
        });

        activity('user')
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties(['role' => $this->role, 'password_changed' => $this->password !== ''])
            ->log($this->user ? 'updated' : 'created');

        session()->flash('status', $this->user ? 'User saved.' : 'User created.');

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.users.user-form', [
            'studies' => Study::orderBy('name')->get(['id', 'name', 'owner_id']),
        ])->title($this->user ? 'Edit user' : 'New user');
    }
}
