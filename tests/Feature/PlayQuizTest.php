<?php

use App\Enums\QuizMode;
use App\Enums\Role;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\Study;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sessionPayload(Study $study, array $overrides = [], int $responseCount = 2): array
{
    $question = $study->questions()->first();

    return array_merge([
        'uuid' => (string) Str::uuid(),
        'study_id' => $study->id,
        'participant_id' => null,
        'child_age' => 5,
        'quiz_mode' => 'correct',
        'started_at' => now()->subMinutes(3)->toIso8601String(),
        'completed_at' => null,
        'responses' => collect(range(1, $responseCount))->map(fn (int $i) => [
            'client_uuid' => (string) Str::uuid(),
            'question_id' => $question?->id,
            'question_ref' => $question?->reference(),
            'question_version' => $question?->version,
            'question_text' => $question?->speak_text,
            'category' => $question?->category,
            'correct_label' => 'Cat',
            'selected_label' => 'Cat',
            'is_correct' => true,
            'attempts' => $i,
            'response_time_ms' => 1500 * $i,
            'position' => $i - 1,
            'answered_at' => now()->subMinute()->toIso8601String(),
        ])->all(),
    ], $overrides);
}

beforeEach(function () {
    $this->teacher = userWithRole(Role::Teacher);
    $this->study = Study::factory()->create(['locale' => 'si-LK', 'questions_per_session' => 5]);
    $this->study->members()->attach($this->teacher);

    $choice = Question::factory()->withOptions()->create();
    $counting = Question::factory()->numberInput(4)->create();
    $archived = Question::factory()->withOptions()->create(['is_archived' => true]);
    $this->study->questions()->attach([
        $choice->id => ['position' => 1],
        $counting->id => ['position' => 2],
        $archived->id => ['position' => 3],
    ]);
});

it('requires sign-in to play', function () {
    $this->get(route('play.show'))->assertRedirect(route('login'));
    $this->getJson(route('play.bundle', $this->study))->assertUnauthorized();
    $this->postJson(route('play.sync'), [])->assertUnauthorized();
});

it('shows the setup screen with only accessible, active studies', function () {
    Participant::factory()->for($this->study)->create(['display_name' => 'Nimal']);
    Study::factory()->create(['name' => 'Someone Else Study']);
    $paused = Study::factory()->inactive()->create(['name' => 'Paused Study']);
    $paused->members()->attach($this->teacher);

    $this->actingAs($this->teacher)->get(route('play.show'))
        ->assertOk()
        ->assertSee($this->study->name)
        ->assertSee('Nimal')
        ->assertDontSee('Someone Else Study')
        ->assertDontSee('Paused Study');
});

it('serves the study bundle without archived questions', function () {
    $bundle = $this->actingAs($this->teacher)
        ->getJson(route('play.bundle', $this->study))
        ->assertOk()
        ->json();

    expect($bundle['study']['locale'])->toBe('si-LK')
        ->and($bundle['study']['questions_per_session'])->toBe(5)
        ->and($bundle['questions'])->toHaveCount(2)
        ->and($bundle['questions'][0]['type'])->toBe('choice')
        ->and($bundle['questions'][0]['options'])->toHaveCount(3)
        ->and($bundle['questions'][0]['correct_label'])->not->toBeNull()
        ->and($bundle['questions'][1]['type'])->toBe('number-input')
        ->and($bundle['questions'][1]['correct_number'])->toBe(4)
        ->and($bundle['version'])->toBeString();
});

it('forbids bundles for studies the user cannot access', function () {
    $other = Study::factory()->create();

    $this->actingAs($this->teacher)->getJson(route('play.bundle', $other))->assertForbidden();
});

it('stores a session with its answers and snapshot data', function () {
    $participant = Participant::factory()->for($this->study)->create();
    $payload = sessionPayload($this->study, ['participant_id' => $participant->id, 'quiz_mode' => 'free']);

    $this->actingAs($this->teacher)
        ->postJson(route('play.sync'), ['sessions' => [$payload]])
        ->assertOk()
        ->assertJson(['accepted' => [$payload['uuid']], 'rejected' => [], 'responses_created' => 2]);

    $session = QuizSession::where('uuid', $payload['uuid'])->firstOrFail();

    expect($session->study_id)->toBe($this->study->id)
        ->and($session->participant_id)->toBe($participant->id)
        ->and($session->conducted_by)->toBe($this->teacher->id)
        ->and($session->quiz_mode)->toBe(QuizMode::Free)
        ->and($session->child_age)->toBe(5)
        ->and($session->responses)->toHaveCount(2)
        ->and($session->responses->first()->question_text)->toBe($this->study->questions()->first()->speak_text);
});

it('is idempotent when the same batch is sent twice', function () {
    $payload = sessionPayload($this->study);

    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]])->assertOk();
    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]])
        ->assertOk()
        ->assertJson(['accepted' => [$payload['uuid']], 'responses_created' => 0]);

    expect(QuizSession::count())->toBe(1)
        ->and(QuizResponse::count())->toBe(2);
});

it('adds later answers and the completion time to an existing session', function () {
    $payload = sessionPayload($this->study, [], 1);
    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]]);

    $later = sessionPayload($this->study, ['uuid' => $payload['uuid'], 'completed_at' => now()->toIso8601String()], 1);
    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$later]])->assertOk();

    $session = QuizSession::where('uuid', $payload['uuid'])->firstOrFail();

    expect($session->responses)->toHaveCount(2)
        ->and($session->completed_at)->not->toBeNull();
});

it('rejects sessions for studies the user cannot access', function () {
    $other = Study::factory()->create();
    $payload = sessionPayload($other);

    $this->actingAs($this->teacher)
        ->postJson(route('play.sync'), ['sessions' => [$payload]])
        ->assertOk()
        ->assertJson(['accepted' => [], 'rejected' => [['uuid' => $payload['uuid'], 'reason' => 'study_not_accessible']]]);

    expect(QuizSession::count())->toBe(0);
});

it('rejects a reused session uuid from a different study', function () {
    $payload = sessionPayload($this->study);
    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]]);

    $otherStudy = Study::factory()->create();
    $otherStudy->members()->attach($this->teacher);
    $hijack = sessionPayload($otherStudy, ['uuid' => $payload['uuid']]);

    $this->actingAs($this->teacher)
        ->postJson(route('play.sync'), ['sessions' => [$hijack]])
        ->assertJson(['rejected' => [['uuid' => $payload['uuid'], 'reason' => 'session_conflict']]]);

    expect(QuizResponse::count())->toBe(2);
});

it('drops participants from other studies and clamps future timestamps', function () {
    $foreignParticipant = Participant::factory()->for(Study::factory())->create();
    $payload = sessionPayload($this->study, [
        'participant_id' => $foreignParticipant->id,
        'started_at' => now()->addYear()->toIso8601String(),
    ]);

    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]])->assertOk();

    $session = QuizSession::firstOrFail();

    expect($session->participant_id)->toBeNull()
        ->and($session->started_at->isFuture())->toBeFalse();
});

it('validates the payload', function () {
    $this->actingAs($this->teacher)
        ->postJson(route('play.sync'), ['sessions' => [['uuid' => 'not-a-uuid', 'study_id' => $this->study->id, 'quiz_mode' => 'nope', 'responses' => []]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sessions.0.uuid', 'sessions.0.quiz_mode']);
});

it('stores every tap and the interaction details, without duplicating taps on re-send', function () {
    $payload = sessionPayload($this->study, [], 1);
    $payload['responses'][0] = array_merge($payload['responses'][0], [
        'attempts' => 3,
        'first_tap_ms' => 900,
        'prompt_ended_ms' => 1800,
        'replay_count' => 2,
        'answered_during_prompt' => false,
        'option_order' => ['Dog', 'Cat', 'Cow'],
        'selected_position' => 1,
        'taps' => [
            ['selected_label' => 'Dog', 'screen_position' => 0, 'is_correct' => false, 'elapsed_ms' => 900, 'during_prompt' => true],
            ['selected_label' => 'Cow', 'screen_position' => 2, 'is_correct' => false, 'elapsed_ms' => 2500, 'during_prompt' => false],
            ['selected_label' => 'Cat', 'screen_position' => 1, 'is_correct' => true, 'elapsed_ms' => 4100, 'during_prompt' => false],
        ],
    ]);

    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]])->assertOk();
    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [$payload]])->assertOk();

    $response = QuizResponse::with('taps')->sole();

    expect($response->replay_count)->toBe(2)
        ->and($response->first_tap_ms)->toBe(900)
        ->and($response->prompt_ended_ms)->toBe(1800)
        ->and($response->option_order)->toBe(['Dog', 'Cat', 'Cow'])
        ->and($response->selected_position)->toBe(1)
        ->and($response->isFirstTryCorrect())->toBeFalse()
        ->and($response->taps)->toHaveCount(3)
        ->and($response->taps->pluck('selected_label')->all())->toBe(['Dog', 'Cow', 'Cat'])
        ->and($response->taps->first()->during_prompt)->toBeTrue()
        ->and($response->taps->last()->sequence)->toBe(3);
});

it('shows synced sessions on the dashboard', function () {
    $this->actingAs($this->teacher)->postJson(route('play.sync'), ['sessions' => [sessionPayload($this->study)]]);

    $this->actingAs($this->teacher)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee($this->study->name)
        ->assertSee('2 / 2');
});
