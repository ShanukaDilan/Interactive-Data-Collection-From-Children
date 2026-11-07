<?php

use App\Enums\Role;
use App\Livewire\Admin\Devices\DeviceIndex;
use App\Models\Device;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuizSession;
use App\Models\Study;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = userWithRole(Role::Teacher);
    $this->study = Study::factory()->create();
    $this->study->members()->attach($this->teacher);
    $this->study->questions()->attach(Question::factory()->withOptions()->create(), ['position' => 1]);
});

function registerDeviceFor($user): array
{
    return test()->actingAs($user)
        ->postJson(route('play.devices.store'), ['name' => 'Blue tablet'])
        ->assertCreated()
        ->json();
}

function deviceSyncPayload(Study $study): array
{
    return ['sessions' => [[
        'uuid' => (string) Str::uuid(),
        'study_id' => $study->id,
        'quiz_mode' => 'correct',
        'child_age' => 5,
        'responses' => [[
            'client_uuid' => (string) Str::uuid(),
            'question_id' => $study->questions()->first()?->id,
            'is_correct' => true,
            'attempts' => 1,
            'response_time_ms' => 1800,
        ]],
    ]]];
}

it('registers a device with a token limited to the device ability', function () {
    $result = registerDeviceFor($this->teacher);

    $device = Device::sole();
    $token = PersonalAccessToken::findToken($result['token']);

    expect($result['device']['name'])->toBe('Blue tablet')
        ->and($device->user_id)->toBe($this->teacher->id)
        ->and($token->id)->toBe($device->personal_access_token_id)
        ->and($token->abilities)->toBe([Device::ABILITY]);
});

it('requires a signed-in user to register a device', function () {
    $this->postJson(route('play.devices.store'), ['name' => 'Sneaky'])->assertUnauthorized();

    expect(Device::count())->toBe(0);
});

it('serves study config and bundles to a device token without a browser session', function () {
    Participant::factory()->for($this->study)->create(['display_name' => 'Nimal']);
    $token = registerDeviceFor($this->teacher)['token'];
    auth()->forgetGuards();

    $config = $this->withToken($token)->getJson(route('api.v1.play.config'))->assertOk()->json();

    expect($config['device']['name'])->toBe('Blue tablet')
        ->and($config['studies'])->toHaveCount(1)
        ->and($config['studies'][0]['participants'][0]['name'])->toBe('Nimal');

    $this->withToken($token)->getJson(route('api.v1.play.bundle', $this->study))
        ->assertOk()
        ->assertJsonCount(1, 'questions');
});

it('syncs answers from a device and records which device sent them', function () {
    $token = registerDeviceFor($this->teacher)['token'];
    auth()->forgetGuards();
    $device = Device::sole();

    $this->withToken($token)->postJson(route('api.v1.play.sync'), deviceSyncPayload($this->study))
        ->assertOk()
        ->assertJson(['responses_created' => 1]);

    $session = QuizSession::sole();

    expect($session->device_id)->toBe($device->id)
        ->and($session->conducted_by)->toBe($this->teacher->id)
        ->and($device->fresh()->last_synced_at)->not->toBeNull();
});

it('rejects a revoked device immediately', function () {
    $token = registerDeviceFor($this->teacher)['token'];
    Device::sole()->revoke();
    auth()->forgetGuards();

    $this->withToken($token)->getJson(route('api.v1.play.config'))->assertUnauthorized();
    $this->withToken($token)->postJson(route('api.v1.play.sync'), deviceSyncPayload($this->study))->assertUnauthorized();

    expect(QuizSession::count())->toBe(0);
});

it('does not accept ordinary user tokens or sessions on the device API', function () {
    $plain = $this->teacher->createToken('other', ['*'])->plainTextToken;
    auth()->forgetGuards();

    $this->withToken($plain)->getJson(route('api.v1.play.config'))->assertUnauthorized();
    $this->getJson(route('api.v1.play.config'))->assertUnauthorized();
});

it('still limits a device to its owner’s studies', function () {
    $token = registerDeviceFor($this->teacher)['token'];
    auth()->forgetGuards();
    $foreign = Study::factory()->create();

    $this->withToken($token)->getJson(route('api.v1.play.bundle', $foreign))->assertForbidden();
    $this->withToken($token)->postJson(route('api.v1.play.sync'), deviceSyncPayload($foreign))
        ->assertOk()
        ->assertJsonPath('rejected.0.reason', 'study_not_accessible');
});

it('serves a service worker that precaches the quiz assets', function () {
    $response = $this->get(route('service-worker'))
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    $script = $response->getContent();
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

    expect($script)->not->toContain('__VERSION__')
        ->and($script)->not->toContain('__PRECACHE__')
        ->and($script)->toContain('/build/'.$manifest['resources/js/quiz/main.js']['file'])
        ->and($script)->toContain('/build/'.$manifest['resources/css/quiz.css']['file'])
        ->and($script)->toContain('.woff2');
});

it('serves a web app manifest that starts on the quiz', function () {
    $this->get(route('web-manifest'))
        ->assertOk()
        ->assertJsonPath('start_url', '/play')
        ->assertJsonPath('display', 'fullscreen');
});

it('embeds the device endpoints in the quiz page config', function () {
    $this->actingAs($this->teacher)->get(route('play.show'))
        ->assertOk()
        ->assertSee(route('web-manifest'), false)
        ->assertSee(json_encode(route('api.v1.play.sync')), false)
        ->assertSee('Set up for offline use');
});

it('lists a teacher’s own devices and lets them revoke one', function () {
    registerDeviceFor($this->teacher);
    $otherTeacher = userWithRole(Role::Teacher);
    [$othersDevice] = Device::register($otherTeacher, 'Other tablet');
    $mine = Device::where('user_id', $this->teacher->id)->sole();

    $this->actingAs($this->teacher)->get(route('admin.devices.index'))
        ->assertOk()
        ->assertSee('Blue tablet')
        ->assertDontSee('Other tablet');

    Livewire::actingAs($this->teacher)->test(DeviceIndex::class)->call('revoke', $othersDevice->id)->assertForbidden();
    Livewire::actingAs($this->teacher)->test(DeviceIndex::class)->call('revoke', $mine->id);

    expect($mine->fresh()->isRevoked())->toBeTrue()
        ->and(PersonalAccessToken::find($mine->personal_access_token_id))->toBeNull()
        ->and($othersDevice->fresh()->isRevoked())->toBeFalse();
});

it('shows every device to admins', function () {
    Device::register($this->teacher, 'Blue tablet');

    $this->actingAs(userWithRole(Role::Admin))->get(route('admin.devices.index'))
        ->assertOk()
        ->assertSee('Blue tablet')
        ->assertSee($this->teacher->name);
});
