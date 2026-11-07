<?php

use App\Enums\Role;
use App\Livewire\Admin\Account\ChangePassword;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\Study;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('creates a teacher with a hashed password and study assignments', function () {
    $study = Study::factory()->create();

    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(UserForm::class)
        ->set('name', 'Teacher Kamala')
        ->set('email', 'kamala@example.com')
        ->set('role', 'teacher')
        ->set('password', 'a-long-password-123')
        ->set('password_confirmation', 'a-long-password-123')
        ->set('studyIds', [$study->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'kamala@example.com')->firstOrFail();

    expect($user->hasRole('teacher'))->toBeTrue()
        ->and(Hash::check('a-long-password-123', $user->password))->toBeTrue()
        ->and($user->studies->pluck('id')->all())->toBe([$study->id]);
});

it('keeps the password when left blank on edit and changes role', function () {
    $user = userWithRole(Role::Teacher, ['password' => 'original-password']);

    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(UserForm::class, ['user' => $user])
        ->set('role', 'researcher')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->hasRole('researcher'))->toBeTrue()
        ->and($user->hasRole('teacher'))->toBeFalse()
        ->and(Hash::check('original-password', $user->password))->toBeTrue();
});

it('prevents admins from removing their own admin role or account', function () {
    $admin = userWithRole(Role::Admin);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['user' => $admin])
        ->set('role', 'teacher')
        ->call('save')
        ->assertHasErrors(['role']);

    Livewire::actingAs($admin)->test(UserIndex::class)->call('delete', $admin->id);

    expect($admin->fresh())->not->toBeNull()
        ->and($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('rejects duplicate emails', function () {
    $existing = User::factory()->create();

    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(UserForm::class)
        ->set('name', 'Someone')
        ->set('email', $existing->email)
        ->set('password', 'a-long-password-123')
        ->set('password_confirmation', 'a-long-password-123')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

it('forbids non-admins from saving users', function () {
    Livewire::actingAs(userWithRole(Role::Researcher))
        ->test(UserForm::class)
        ->set('name', 'Sneaky')
        ->set('email', 'sneaky@example.com')
        ->set('password', 'a-long-password-123')
        ->set('password_confirmation', 'a-long-password-123')
        ->set('role', 'admin')
        ->call('save')
        ->assertForbidden();

    expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

it('changes the signed-in user password after checking the current one', function () {
    $user = userWithRole(Role::Teacher, ['password' => 'old-password-123']);

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('current_password', 'wrong')
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('save')
        ->assertHasErrors(['current_password']);

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('current_password', 'old-password-123')
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue();
});
