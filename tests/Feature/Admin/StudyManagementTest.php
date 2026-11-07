<?php

use App\Enums\QuizMode;
use App\Enums\Role;
use App\Livewire\Admin\Studies\StudyForm;
use App\Livewire\Admin\Studies\StudyIndex;
use App\Models\Question;
use App\Models\Study;
use Livewire\Livewire;

it('creates a study with members and an ordered question set', function () {
    $researcher = userWithRole(Role::Researcher);
    $teacher = userWithRole(Role::Teacher);
    [$first, $second] = Question::factory()->count(2)->withOptions()->create();

    Livewire::actingAs($researcher)
        ->test(StudyForm::class)
        ->set('name', 'Phonics Pilot 2026')
        ->set('locale', 'si-LK')
        ->set('defaultQuizMode', 'free')
        ->set('ageMin', 4)
        ->set('ageMax', 5)
        ->set('memberIds', [$teacher->id])
        ->call('addQuestion', $second->id)
        ->call('addQuestion', $first->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $study = Study::where('name', 'Phonics Pilot 2026')->firstOrFail();

    expect($study->slug)->toBe('phonics-pilot-2026')
        ->and($study->owner_id)->toBe($researcher->id)
        ->and($study->locale)->toBe('si-LK')
        ->and($study->default_quiz_mode)->toBe(QuizMode::Free)
        ->and($study->members->pluck('id')->all())->toBe([$teacher->id])
        ->and($study->questions->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('reorders and removes questions', function () {
    $admin = userWithRole(Role::Admin);
    $study = Study::factory()->create();
    [$a, $b, $c] = Question::factory()->count(3)->withOptions()->create();
    $study->questions()->attach([$a->id => ['position' => 1], $b->id => ['position' => 2], $c->id => ['position' => 3]]);

    Livewire::actingAs($admin)
        ->test(StudyForm::class, ['study' => $study])
        ->assertSet('questionIds', [$a->id, $b->id, $c->id])
        ->call('moveQuestion', 2, -1)
        ->call('removeQuestion', $a->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($study->fresh()->questions->pluck('id')->all())->toBe([$c->id, $b->id]);
});

it('validates the age range', function () {
    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(StudyForm::class)
        ->set('name', 'Bad ages')
        ->set('ageMin', 6)
        ->set('ageMax', 4)
        ->call('save')
        ->assertHasErrors(['ageMax' => 'gte']);
});

it('generates unique slugs', function () {
    Study::factory()->create(['slug' => 'counting']);

    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(StudyForm::class)
        ->set('name', 'Counting')
        ->call('save');

    expect(Study::where('slug', 'counting-2')->exists())->toBeTrue();
});

it('lets only the owner or an admin delete a study', function () {
    $owner = userWithRole(Role::Researcher);
    $member = userWithRole(Role::Researcher);
    $study = Study::factory()->for($owner, 'owner')->create();
    $study->members()->attach($member);

    Livewire::actingAs($member)->test(StudyIndex::class)->call('delete', $study->id)->assertForbidden();
    expect($study->fresh())->not->toBeNull();

    Livewire::actingAs($owner)->test(StudyIndex::class)->call('delete', $study->id);
    expect($study->fresh())->toBeNull();
});

it('pauses and activates a study', function () {
    $study = Study::factory()->create(['is_active' => true]);

    Livewire::actingAs(userWithRole(Role::Admin))->test(StudyIndex::class)->call('toggleActive', $study->id);

    expect($study->fresh()->is_active)->toBeFalse();
});

it('records an audit log entry when a study changes', function () {
    $study = Study::factory()->create(['name' => 'Before']);

    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(StudyForm::class, ['study' => $study])
        ->set('name', 'After')
        ->call('save');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'study',
        'subject_id' => $study->id,
        'event' => 'updated',
    ]);
});
