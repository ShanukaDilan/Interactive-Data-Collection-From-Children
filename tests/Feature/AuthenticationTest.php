<?php

use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

it('redirects guests from the dashboard to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('shows the login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('Learning Fun!');
});

it('signs in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $user->assignRole(Role::Teacher->value);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Recent sessions');
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks out after repeated failed attempts', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});
