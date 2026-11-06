<?php

use App\Enums\Role;
use App\Livewire\Admin\Analytics\ParticipantReport;
use App\Models\Participant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizTap;
use App\Models\ReportLink;
use App\Models\Study;
use App\Models\User;
use Livewire\Livewire;

/**
 * Seed one study with a child who always taps the left card first, plus peers of the same age.
 *
 * @return array{study: Study, child: Participant, session: QuizSession}
 */
function seedAnalyticsStudy(int $peers = 6): array
{
    $study = Study::factory()->create();
    $child = Participant::factory()->for($study)->create(['age' => 5, 'display_name' => 'Nimal']);
    $session = QuizSession::factory()->for($study)->for($child)->completed()->create(['child_age' => 5]);

    foreach (range(0, 9) as $i) {
        $correctFirst = $i % 2 === 0;
        $response = QuizResponse::factory()->for($session)->create([
            'category' => $i < 5 ? 'Numbers' : 'Phonics',
            'question_text' => "Question {$i}",
            'correct_label' => 'Cat',
            'is_correct' => true,
            'attempts' => $correctFirst ? 1 : 2,
            'position' => $i,
            'option_order' => ['Cat', 'Dog', 'Cow'],
            'prompt_ended_ms' => 1500,
        ]);
        QuizTap::factory()->for($response, 'response')->create(['sequence' => 1, 'screen_position' => 0, 'selected_label' => $correctFirst ? 'Cat' : 'Dog', 'is_correct' => $correctFirst, 'elapsed_ms' => 2000]);
        if (! $correctFirst) {
            QuizTap::factory()->for($response, 'response')->create(['sequence' => 2, 'screen_position' => 2, 'selected_label' => 'Cat', 'is_correct' => true, 'elapsed_ms' => 3500]);
        }
    }

    foreach (range(1, $peers) as $p) {
        $peer = Participant::factory()->for($study)->create(['age' => 5]);
        $peerSession = QuizSession::factory()->for($study)->for($peer)->create(['child_age' => 5]);
        QuizResponse::factory()->count(4)->for($peerSession)->create(['category' => 'Numbers', 'is_correct' => true, 'attempts' => 1]);
    }

    return compact('study', 'child', 'session');
}

function memberOf(Study $study, Role $role): User
{
    $user = userWithRole($role);
    $study->members()->attach($user);

    return $user;
}

it('shows study analytics with patterns to a teacher, without question quality', function () {
    ['study' => $study] = seedAnalyticsStudy();

    $this->actingAs(memberOf($study, Role::Teacher))
        ->get(route('admin.analytics', ['study' => $study->id]))
        ->assertOk()
        ->assertSee('Right first try')
        ->assertSee('Position bias')
        ->assertSee('Nimal')
        ->assertDontSee('Question quality');
});

it('shows question quality to researchers', function () {
    ['study' => $study] = seedAnalyticsStudy();

    $this->actingAs(memberOf($study, Role::Researcher))
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Question quality')
        ->assertSee('Discrimination');
});

it('never includes answers from studies the user cannot access', function () {
    seedAnalyticsStudy();
    $mine = Study::factory()->create();
    $teacher = memberOf($mine, Role::Teacher);

    $this->actingAs($teacher)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('No answers match these filters')
        ->assertDontSee('Nimal');
});

it('shows the child report with strengths relative to themselves for teachers', function () {
    ['study' => $study, 'child' => $child] = seedAnalyticsStudy();

    $this->actingAs(memberOf($study, Role::Teacher))
        ->get(route('admin.participants.report', $child))
        ->assertOk()
        ->assertSee('Report · '.$child->code)
        ->assertSee('Numbers')
        ->assertSee('Position bias')
        ->assertDontSee('age 5 average');
});

it('adds anonymous same-age averages for researchers', function () {
    ['study' => $study, 'child' => $child] = seedAnalyticsStudy();

    $this->actingAs(memberOf($study, Role::Researcher))
        ->get(route('admin.participants.report', $child))
        ->assertOk()
        ->assertSee('age 5 average');
});

it('hides peer averages when too few other children exist', function () {
    ['study' => $study, 'child' => $child] = seedAnalyticsStudy(peers: 2);

    $this->actingAs(memberOf($study, Role::Researcher))
        ->get(route('admin.participants.report', $child))
        ->assertOk()
        ->assertDontSee('age 5 average');
});

it('forbids reports and timelines outside the user’s studies', function () {
    ['child' => $child, 'session' => $session] = seedAnalyticsStudy();
    $outsider = memberOf(Study::factory()->create(), Role::Teacher);

    $this->actingAs($outsider)->get(route('admin.participants.report', $child))->assertForbidden();
    $this->actingAs($outsider)->get(route('admin.sessions.show', $session))->assertForbidden();
});

it('shows every tap on the session timeline', function () {
    ['study' => $study, 'session' => $session] = seedAnalyticsStudy();

    $this->actingAs(memberOf($study, Role::Teacher))
        ->get(route('admin.sessions.show', $session))
        ->assertOk()
        ->assertSee('Question by question')
        ->assertSee('Dog')
        ->assertSee('(left)', false)
        ->assertSee('2 taps');
});

it('creates a parent link storing only the token hash', function () {
    ['study' => $study, 'child' => $child] = seedAnalyticsStudy();

    $component = Livewire::actingAs(memberOf($study, Role::Teacher))
        ->test(ParticipantReport::class, ['participant' => $child])
        ->set('linkDays', 7)
        ->call('createLink');

    $url = $component->get('newLinkUrl');
    $token = basename(parse_url($url, PHP_URL_PATH));
    $link = ReportLink::sole();

    expect(strlen($token))->toBe(ReportLink::TOKEN_LENGTH)
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->token_hash)->not->toBe($token)
        ->and(round(now()->diffInDays($link->expires_at)))->toEqual(7.0);
});

it('opens the parent report from a valid link, self-only, and counts the view', function () {
    ['study' => $study, 'child' => $child] = seedAnalyticsStudy();
    [$link, $token] = ReportLink::issue($child, memberOf($study, Role::Teacher), 30);

    $this->get(route('parent-report.show', $token))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Nimal’s progress', false)
        ->assertSee('Numbers')
        ->assertDontSee($child->code)
        ->assertDontSee('age 5 average')
        ->assertDontSee('Age avg')
        ->assertDontSee('Dashboard');

    expect($link->fresh()->view_count)->toBe(1);
});

it('rejects expired, revoked and unknown parent links with the same 404', function () {
    ['child' => $child] = seedAnalyticsStudy();
    $creator = userWithRole(Role::Teacher);

    [$expired, $expiredToken] = ReportLink::issue($child, $creator, 7);
    $expired->update(['expires_at' => now()->subMinute()]);
    [$revoked, $revokedToken] = ReportLink::issue($child, $creator, 7);
    $revoked->update(['revoked_at' => now()]);

    $this->get(route('parent-report.show', $expiredToken))->assertNotFound();
    $this->get(route('parent-report.show', $revokedToken))->assertNotFound();
    $this->get(route('parent-report.show', str_repeat('a', ReportLink::TOKEN_LENGTH)))->assertNotFound();
    $this->get('/r/short')->assertNotFound();
});

it('lets teachers revoke links but not create them for other studies', function () {
    ['study' => $study, 'child' => $child] = seedAnalyticsStudy();
    $teacher = memberOf($study, Role::Teacher);
    [$link] = ReportLink::issue($child, $teacher, 30);

    Livewire::actingAs($teacher)
        ->test(ParticipantReport::class, ['participant' => $child])
        ->call('revokeLink', $link->id);

    expect($link->fresh()->revoked_at)->not->toBeNull();

    $outsider = memberOf(Study::factory()->create(), Role::Teacher);
    Livewire::actingAs($outsider)
        ->test(ParticipantReport::class, ['participant' => $child])
        ->assertForbidden();
});
