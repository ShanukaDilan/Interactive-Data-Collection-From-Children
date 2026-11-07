<?php

use App\Enums\Role;
use App\Models\Question;
use App\Models\Study;

dataset('admin pages', [
    'dashboard' => fn () => route('admin.dashboard'),
    'studies' => fn () => route('admin.studies.index'),
    'participants' => fn () => route('admin.participants.index'),
    'password' => fn () => route('admin.account.password'),
]);

it('lets every role open the shared pages', function (Role $role, string $url) {
    $this->actingAs(userWithRole($role))->get($url)->assertOk();
})->with([Role::Admin, Role::Researcher, Role::Teacher])->with('admin pages');

it('restricts the question bank to admins and researchers', function (Role $role, int $status) {
    $question = Question::factory()->withOptions()->create();
    $user = userWithRole($role);

    $this->actingAs($user)->get(route('admin.questions.index'))->assertStatus($status);
    $this->actingAs($user)->get(route('admin.questions.create'))->assertStatus($status);
    $this->actingAs($user)->get(route('admin.questions.edit', $question))->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'researcher' => [Role::Researcher, 200],
    'teacher' => [Role::Teacher, 403],
]);

it('restricts user management to admins', function (Role $role, int $status) {
    $user = userWithRole($role);

    $this->actingAs($user)->get(route('admin.users.index'))->assertStatus($status);
    $this->actingAs($user)->get(route('admin.users.create'))->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'researcher' => [Role::Researcher, 403],
    'teacher' => [Role::Teacher, 403],
]);

it('only lets a researcher edit studies they belong to', function () {
    $researcher = userWithRole(Role::Researcher);
    $own = Study::factory()->for($researcher, 'owner')->create();
    $assigned = Study::factory()->create();
    $assigned->members()->attach($researcher);
    $other = Study::factory()->create();

    $this->actingAs($researcher);

    $this->get(route('admin.studies.edit', $own))->assertOk();
    $this->get(route('admin.studies.edit', $assigned))->assertOk();
    $this->get(route('admin.studies.edit', $other))->assertForbidden();
});

it('never lets teachers edit studies, even assigned ones', function () {
    $teacher = userWithRole(Role::Teacher);
    $study = Study::factory()->create();
    $study->members()->attach($teacher);

    $this->actingAs($teacher)->get(route('admin.studies.edit', $study))->assertForbidden();
    $this->actingAs($teacher)->get(route('admin.studies.create'))->assertForbidden();
});

it('hides inaccessible studies from the list', function () {
    $teacher = userWithRole(Role::Teacher);
    $visible = Study::factory()->create(['name' => 'Visible Study']);
    $visible->members()->attach($teacher);
    Study::factory()->create(['name' => 'Hidden Study']);

    $this->actingAs($teacher)->get(route('admin.studies.index'))
        ->assertSee('Visible Study')
        ->assertDontSee('Hidden Study');
});
