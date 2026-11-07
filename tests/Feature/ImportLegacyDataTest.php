<?php

use App\Enums\QuizMode;
use App\Enums\Role;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\Study;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Write a responses.jsonl like the old app's submit.php produced.
 */
function legacyJsonl(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'lf-jsonl-');
    file_put_contents($path, implode("\n", array_map(fn ($line) => is_string($line) ? $line : json_encode($line), $lines))."\n");

    return $path;
}

function legacyRow(array $overrides = []): array
{
    return array_merge([
        'session_id' => 'abc123',
        'child_age' => 5,
        'child_id' => 'Nimal &amp; Co',
        'quiz_mode' => 'correct',
        'question_id' => 'q01',
        'question_text' => 'What sound does ANT start with? Pick the right letter!',
        'category' => 'Phonics - Beginning Sound',
        'correct_label' => 'a',
        'selected_label' => 'a',
        'is_correct' => 1,
        'attempts' => 1,
        'response_time_ms' => 2300,
        'timestamp' => '2026-04-28 10:00:00',
    ], $overrides);
}

beforeEach(function () {
    userWithRole(Role::Admin);
});

it('imports questions and jsonl answers grouped into sessions', function () {
    $path = legacyJsonl([
        legacyRow(),
        legacyRow(['question_id' => 'q12', 'correct_label' => '7', 'selected_label' => '7', 'attempts' => 2, 'timestamp' => '2026-04-28 10:00:20']),
        legacyRow(['session_id' => 'def456', 'child_id' => '', 'quiz_mode' => 'free', 'is_correct' => 0, 'selected_label' => 'f']),
        'this is not json',
    ]);

    $this->artisan('quiz:import-legacy', [
        '--questions' => database_path('seeders/data/questions.json'),
        '--jsonl' => $path,
    ])->assertSuccessful();

    $study = Study::where('slug', 'legacy-import')->sole();
    $session = QuizSession::where('legacy_session_id', 'abc123')->sole();
    $free = QuizSession::where('legacy_session_id', 'def456')->sole();

    expect($study->questions()->count())->toBe(20)
        ->and($study->is_active)->toBeFalse()
        ->and(QuizSession::count())->toBe(2)
        ->and($session->responses)->toHaveCount(2)
        ->and($session->legacy_child_id)->toBe('Nimal & Co')
        ->and($session->participant_id)->toBeNull()
        ->and($session->child_age)->toBe(5)
        ->and($session->started_at->toDateTimeString())->toBe('2026-04-28 10:00:00')
        ->and($session->completed_at->toDateTimeString())->toBe('2026-04-28 10:00:20')
        ->and($session->responses[0]->question_id)->toBe(Question::where('legacy_id', 'q01')->value('id'))
        ->and($session->responses[1]->attempts)->toBe(2)
        ->and($session->responses[1]->selected_number)->toBe(7)
        ->and($free->quiz_mode)->toBe(QuizMode::Free)
        ->and($free->responses->first()->is_correct)->toBeFalse();

    unlink($path);
});

it('can be re-run without duplicating anything', function () {
    $path = legacyJsonl([legacyRow(), legacyRow(['question_id' => 'q02', 'timestamp' => '2026-04-28 10:00:10'])]);

    $this->artisan('quiz:import-legacy', ['--jsonl' => $path])->assertSuccessful();
    $this->artisan('quiz:import-legacy', ['--jsonl' => $path])->assertSuccessful();

    expect(QuizSession::count())->toBe(1)
        ->and(QuizResponse::count())->toBe(2);

    unlink($path);
});

it('creates pseudonymous participants with encrypted names when asked', function () {
    $path = legacyJsonl([
        legacyRow(),
        legacyRow(['session_id' => 'second-visit', 'timestamp' => '2026-05-02 09:00:00']),
    ]);

    $this->artisan('quiz:import-legacy', ['--jsonl' => $path, '--participants' => true])->assertSuccessful();

    $participant = Participant::sole();

    expect($participant->code)->toMatch('/^L-[0-9A-F]{6}$/')
        ->and($participant->display_name)->toBe('Nimal & Co')
        ->and(DB::table('participants')->value('display_name'))->not->toContain('Nimal')
        ->and($participant->quizSessions()->count())->toBe(2);

    unlink($path);
});

it('rolls everything back on a dry run', function () {
    $path = legacyJsonl([legacyRow()]);

    $this->artisan('quiz:import-legacy', ['--jsonl' => $path, '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Study::count())->toBe(0)
        ->and(QuizSession::count())->toBe(0);

    unlink($path);
});

it('imports from the old app database table', function () {
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');

    Schema::connection('legacy')->create('responses', function (Blueprint $table) {
        $table->id();
        foreach (['session_id', 'child_id', 'quiz_mode', 'question_id', 'question_text', 'category', 'correct_label', 'selected_label'] as $column) {
            $table->string($column)->nullable();
        }
        $table->integer('child_age')->nullable();
        $table->integer('is_correct')->default(0);
        $table->integer('attempts')->default(1);
        $table->integer('response_time_ms')->default(0);
        $table->dateTime('created_at')->nullable();
    });

    $row = legacyRow(['created_at' => '2026-04-30 11:00:00']);
    unset($row['timestamp']);
    DB::connection('legacy')->table('responses')->insert([$row, array_merge($row, ['question_id' => 'q03'])]);

    $this->artisan('quiz:import-legacy', ['--from-db' => true, '--study' => 'pilot-2026'])->assertSuccessful();

    $session = QuizSession::sole();

    expect($session->study->slug)->toBe('pilot-2026')
        ->and($session->responses)->toHaveCount(2)
        ->and($session->started_at->toDateString())->toBe('2026-04-30');
});

it('explains what is missing', function () {
    $this->artisan('quiz:import-legacy')->assertExitCode(2);
    $this->artisan('quiz:import-legacy', ['--jsonl' => 'nope.jsonl'])->expectsOutputToContain('File not found')->assertFailed();

    config(['database.connections.legacy.url' => null, 'database.connections.legacy.database' => null]);
    $this->artisan('quiz:import-legacy', ['--from-db' => true])->expectsOutputToContain('LEGACY_DB_URL')->assertFailed();
});
