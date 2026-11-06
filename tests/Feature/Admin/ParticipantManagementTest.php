<?php

use App\Enums\Role;
use App\Livewire\Admin\Participants\ParticipantIndex;
use App\Models\Participant;
use App\Models\Study;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('adds a participant with a generated code, encrypted name and consent', function () {
    $teacher = userWithRole(Role::Teacher);
    $study = Study::factory()->create();
    $study->members()->attach($teacher);

    Livewire::actingAs($teacher)
        ->test(ParticipantIndex::class)
        ->call('create')
        ->assertSet('studyId', $study->id)
        ->set('age', 5)
        ->set('displayName', 'Nimal')
        ->set('consent', true)
        ->call('save')
        ->assertHasNoErrors();

    $participant = Participant::firstOrFail();

    expect($participant->code)->toMatch('/^P-[A-Z0-9]{4}$/')
        ->and($participant->display_name)->toBe('Nimal')
        ->and($participant->consent_by)->toBe($teacher->id)
        ->and($participant->consent_recorded_at)->not->toBeNull()
        ->and(DB::table('participants')->value('display_name'))->not->toBe('Nimal');
});

it('does not write the child name into the audit log', function () {
    $study = Study::factory()->create();

    Livewire::actingAs(userWithRole(Role::Admin))
        ->test(ParticipantIndex::class)
        ->call('create')
        ->set('studyId', $study->id)
        ->set('displayName', 'Secret Name')
        ->call('save');

    expect(DB::table('activity_log')->where('log_name', 'participant')->exists())->toBeTrue()
        ->and(DB::table('activity_log')->where('properties', 'like', '%Secret Name%')->exists())->toBeFalse();
});

it('rejects participants in studies the user cannot access', function () {
    $teacher = userWithRole(Role::Teacher);
    $foreign = Study::factory()->create();

    Livewire::actingAs($teacher)
        ->test(ParticipantIndex::class)
        ->set('studyId', $foreign->id)
        ->call('save')
        ->assertHasErrors(['studyId']);

    expect(Participant::count())->toBe(0);
});

it('lets researchers but not teachers delete participants', function () {
    $study = Study::factory()->create();
    $teacher = userWithRole(Role::Teacher);
    $researcher = userWithRole(Role::Researcher);
    $study->members()->attach([$teacher->id, $researcher->id]);
    $participant = Participant::factory()->for($study)->create();

    Livewire::actingAs($teacher)->test(ParticipantIndex::class)->call('delete', $participant->id)->assertForbidden();
    Livewire::actingAs($researcher)->test(ParticipantIndex::class)->call('delete', $participant->id);

    expect($participant->fresh())->toBeNull();
});

it('only lists participants from accessible studies', function () {
    $teacher = userWithRole(Role::Teacher);
    $mine = Study::factory()->create();
    $mine->members()->attach($teacher);
    $visible = Participant::factory()->for($mine)->create();
    $hidden = Participant::factory()->for(Study::factory())->create();

    $this->actingAs($teacher)->get(route('admin.participants.index'))
        ->assertSee($visible->code)
        ->assertDontSee($hidden->code);
});
