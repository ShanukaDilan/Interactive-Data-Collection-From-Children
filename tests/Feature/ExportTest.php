<?php

use App\Enums\Role;
use App\Models\Participant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizTap;
use App\Models\Study;
use App\Models\User;
use App\Services\Exports\Codebook;
use Illuminate\Testing\TestResponse;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * One study with a named child who answers two questions (one wrong first), plus another
 * study the exporting user must never see.
 *
 * @return array{study: Study, other: Study, researcher: User}
 */
function seedExportData(): array
{
    $study = Study::factory()->create(['slug' => 'pilot']);
    $child = Participant::factory()->for($study)->create(['code' => 'C-1234', 'display_name' => 'Nimal Perera', 'age' => 5]);
    $session = QuizSession::factory()->for($study)->for($child)->completed()->create(['child_age' => 5, 'quiz_mode' => 'correct']);

    $first = QuizResponse::factory()->for($session)->create([
        'position' => 0, 'category' => 'Numbers', 'question_text' => 'How many apples?',
        'correct_label' => '3', 'selected_label' => '3', 'selected_number' => 3,
        'is_correct' => true, 'attempts' => 2, 'response_time_ms' => 4200,
        'first_tap_ms' => 2500, 'prompt_ended_ms' => 1800, 'replay_count' => 1,
        'option_order' => null, 'selected_position' => null,
    ]);
    QuizTap::factory()->for($first, 'response')->create(['sequence' => 1, 'selected_label' => '4', 'selected_number' => 4, 'screen_position' => null, 'is_correct' => false, 'elapsed_ms' => 2500]);
    QuizTap::factory()->for($first, 'response')->create(['sequence' => 2, 'selected_label' => '3', 'selected_number' => 3, 'screen_position' => null, 'is_correct' => true, 'elapsed_ms' => 4200]);

    $second = QuizResponse::factory()->for($session)->create([
        'position' => 1, 'category' => 'Animals', 'question_text' => '=HYPERLINK("http://evil.test","Find the cat")',
        'correct_label' => 'Cat', 'selected_label' => 'Cat', 'selected_number' => null,
        'is_correct' => true, 'attempts' => 1, 'response_time_ms' => 1500,
        'first_tap_ms' => 1500, 'prompt_ended_ms' => 2000, 'answered_during_prompt' => true,
        'option_order' => ['Dog', 'Cat', 'Cow'], 'selected_position' => 1,
    ]);
    QuizTap::factory()->for($second, 'response')->create(['sequence' => 1, 'selected_label' => 'Cat', 'screen_position' => 1, 'is_correct' => true, 'elapsed_ms' => 1500, 'during_prompt' => true]);

    $other = Study::factory()->create(['slug' => 'secret']);
    $otherSession = QuizSession::factory()->for($other)->create();
    QuizResponse::factory()->for($otherSession)->create(['question_text' => 'Other study question']);

    $researcher = userWithRole(Role::Researcher);
    $study->members()->attach($researcher);

    return compact('study', 'other', 'researcher');
}

/** Read the downloaded file and remove it (tests never "send" it, so it isn't auto-deleted). */
function downloadedRows(TestResponse $response, ?string $sheet = null): array
{
    $path = $response->baseResponse->getFile()->getPathname();
    $type = str_contains($response->headers->get('content-disposition'), '.xlsx') ? 'xlsx' : 'csv';
    $reader = SimpleExcelReader::create($path, $type)->noHeaderRow();
    if ($sheet !== null) {
        $reader = $reader->fromSheetName($sheet);
    }

    return $reader->getRows()->all();
}

afterEach(function () {
    foreach (glob(storage_path('app/private/exports/*')) ?: [] as $file) {
        @unlink($file);
    }
});

it('exports one row per answer with the codebook columns, codes only and 1-based positions', function () {
    ['researcher' => $researcher] = seedExportData();

    $response = $this->actingAs($researcher)->get(route('admin.export', ['file' => 'responses.csv']));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('learning-fun_all-studies_responses_')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname(), length: 3))->toBe("\xEF\xBB\xBF");

    $rows = downloadedRows($response);
    $header = array_shift($rows);
    $records = array_map(fn (array $row) => array_combine($header, $row), $rows);

    expect($header)->toBe(array_keys(Codebook::responses()))
        ->and($records)->toHaveCount(2)
        ->and(collect($records)->pluck('question_text')->implode(' '))->not->toContain('Other study');

    [$counting, $animal] = $records;
    expect($counting)->toMatchArray([
        'study' => 'pilot', 'child_id' => 'C-1234', 'child_age' => '5', 'quiz_mode' => 'correct',
        'question_position' => '1', 'is_correct' => '1', 'first_try_correct' => '0', 'attempts' => '2',
        'reaction_ms' => '700', 'replay_count' => '1', 'selected_number' => '3',
    ])->and($animal)->toMatchArray([
        'question_position' => '2', 'first_try_correct' => '1', 'reaction_ms' => '',
        'answered_during_prompt' => '1', 'option_count' => '3', 'option_order' => 'Dog | Cat | Cow',
        'selected_position' => '2',
        'question_text' => '\'=HYPERLINK("http://evil.test","Find the cat")',
    ]);

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->not->toContain('Nimal');
});

it('exports every tap in long format', function () {
    ['researcher' => $researcher] = seedExportData();

    $rows = downloadedRows($this->actingAs($researcher)->get(route('admin.export', ['file' => 'taps.csv'])));
    $header = array_shift($rows);
    $taps = array_map(fn (array $row) => array_combine($header, $row), $rows);

    expect($header)->toBe(array_keys(Codebook::taps()))
        ->and($taps)->toHaveCount(3)
        ->and(array_column($taps, 'tap_sequence'))->toBe(['1', '2', '1'])
        ->and(array_column($taps, 'selected_label'))->toBe(['4', '3', 'Cat'])
        ->and(array_column($taps, 'is_correct'))->toBe(['0', '1', '1'])
        ->and($taps[2]['screen_position'])->toBe('2')
        ->and($taps[0]['response_id'])->toBe($taps[1]['response_id']);
});

it('keeps the original app CSV layout available', function () {
    ['researcher' => $researcher] = seedExportData();

    $rows = downloadedRows($this->actingAs($researcher)->get(route('admin.export', ['file' => 'legacy.csv'])));

    expect($rows[0])->toBe(Codebook::LEGACY_HEADER)
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][1])->toBe('C-1234')
        ->and($rows[1][9])->toBe('Yes')
        ->and($rows[1][10])->toBe('2');
});

it('exports a codebook describing every column of both files', function () {
    ['researcher' => $researcher] = seedExportData();

    $rows = downloadedRows($this->actingAs($researcher)->get(route('admin.export', ['file' => 'codebook.csv'])));

    expect($rows[0])->toBe(['file', 'column', 'type', 'description', 'values'])
        ->and($rows)->toHaveCount(1 + count(Codebook::responses()) + count(Codebook::taps()))
        ->and(collect($rows)->every(fn (array $row) => filled($row[3])))->toBeTrue();
});

it('builds an Excel workbook with answers, taps, codebook and notes', function () {
    ['study' => $study, 'researcher' => $researcher] = seedExportData();

    $response = $this->actingAs($researcher)->get(route('admin.export', ['file' => 'workbook.xlsx', 'study' => $study->id]));
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('learning-fun_pilot_workbook_');

    expect(downloadedRows($response, 'Responses'))->toHaveCount(3)
        ->and(downloadedRows($response, 'Taps'))->toHaveCount(4)
        ->and(downloadedRows($response, 'Codebook'))->toHaveCount(1 + count(Codebook::rows()));

    $about = collect(downloadedRows($response, 'About'))->mapWithKeys(fn (array $row) => [$row[0] => $row[1]]);
    expect($about['Filters'])->toContain('study: '.$study->name)
        ->and((int) $about['Answers'])->toBe(2);
});

it('applies the analytics filters', function () {
    ['researcher' => $researcher] = seedExportData();

    $rows = downloadedRows($this->actingAs($researcher)->get(route('admin.export', ['file' => 'responses.csv', 'category' => 'Animals'])));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][array_search('category', $rows[0], true)])->toBe('Animals');
});

it('refuses studies the user cannot access', function () {
    ['other' => $other, 'researcher' => $researcher] = seedExportData();

    $this->actingAs($researcher)
        ->get(route('admin.export', ['file' => 'responses.csv', 'study' => $other->id]))
        ->assertNotFound();
});

it('does not let teachers download raw data', function () {
    ['study' => $study] = seedExportData();
    $teacher = userWithRole(Role::Teacher);
    $study->members()->attach($teacher);

    $this->actingAs($teacher)->get(route('admin.export', ['file' => 'responses.csv']))->assertForbidden();
    $this->actingAs($teacher)->get(route('admin.analytics'))->assertOk()->assertDontSee('Export data');
});

it('lets admins export everything and records each export in the audit log', function () {
    seedExportData();
    $admin = userWithRole(Role::Admin);

    $rows = downloadedRows($this->actingAs($admin)->get(route('admin.export', ['file' => 'responses.csv', 'mode' => 'correct'])));

    expect($rows)->toHaveCount(1 + QuizResponse::whereHas('quizSession', fn ($q) => $q->where('quiz_mode', 'correct'))->count());
    $this->assertDatabaseHas('activity_log', ['log_name' => 'export', 'description' => 'exported', 'causer_id' => $admin->id]);
});

it('only serves the known export files', function () {
    ['researcher' => $researcher] = seedExportData();

    $this->actingAs($researcher)->get('/admin/export/users.csv')->assertNotFound();
});

it('requires sign-in', function () {
    $this->get(route('admin.export', ['file' => 'responses.csv']))->assertRedirect(route('login'));
});

it('shows the export menu, with the current filters, to researchers', function () {
    ['study' => $study, 'researcher' => $researcher] = seedExportData();

    $this->actingAs($researcher)
        ->get(route('admin.analytics', ['study' => $study->id, 'age' => 5]))
        ->assertOk()
        ->assertSee('Export data')
        ->assertSee(route('admin.export', ['file' => 'workbook.xlsx', 'study' => $study->id, 'age' => 5]));
});
