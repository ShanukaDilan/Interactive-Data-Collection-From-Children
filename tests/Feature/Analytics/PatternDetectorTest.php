<?php

use App\Services\Analytics\Answer;
use App\Services\Analytics\ChildProfile;
use App\Services\Analytics\Insight;
use App\Services\Analytics\ItemAnalyzer;
use App\Services\Analytics\Metrics;
use App\Services\Analytics\PatternDetector;

/**
 * Build an analysis Answer. `taps` may be given as a short list of [label, position, correct, elapsed_ms, during_prompt].
 *
 * @param  array<string, mixed>  $overrides
 */
function answer(array $overrides = []): Answer
{
    static $id = 0;
    $id++;

    $taps = array_map(fn (array $tap) => [
        'label' => $tap[0],
        'number' => is_numeric($tap[0]) ? (int) $tap[0] : null,
        'position' => $tap[1] ?? null,
        'correct' => $tap[2] ?? true,
        'elapsed_ms' => $tap[3] ?? 2500,
        'during_prompt' => $tap[4] ?? false,
    ], $overrides['taps'] ?? [['Cat', 0, true]]);
    unset($overrides['taps']);

    $defaults = [
        'id' => $id,
        'sessionId' => 1,
        'studyId' => 1,
        'participantId' => 1,
        'childAge' => 5,
        'mode' => 'correct',
        'sessionStartedAt' => null,
        'questionId' => $id,
        'questionKey' => 'id:'.$id,
        'questionText' => 'Touch the cat!',
        'category' => 'Animals',
        'correctLabel' => 'Cat',
        'selectedLabel' => 'Cat',
        'selectedNumber' => null,
        'isCorrect' => true,
        'attempts' => count($taps),
        'responseTimeMs' => $taps[array_key_last($taps)]['elapsed_ms'],
        'firstTapMs' => $taps[0]['elapsed_ms'],
        'promptEndedMs' => 1500,
        'replayCount' => 0,
        'answeredDuringPrompt' => false,
        'optionOrder' => ['Cat', 'Dog', 'Cow'],
        'selectedPosition' => $taps[array_key_last($taps)]['position'],
        'position' => $id,
        'taps' => $taps,
    ];

    return new Answer(...array_merge($defaults, $overrides));
}

/**
 * @param  list<Insight>  $insights
 */
function insightTypes(array $insights): array
{
    return array_map(fn (Insight $insight) => $insight->type, $insights);
}

it('uses first-try accuracy, not final correctness, in Find the Answer mode', function () {
    $answers = collect([
        answer(),
        answer(['taps' => [['Dog', 1, false], ['Cat', 0, true]]]),
        answer(['taps' => [['Cow', 2, false], ['Dog', 1, false], ['Cat', 0, true]]]),
        answer(),
    ]);

    $stats = Metrics::summarize($answers);

    expect($stats['first_try_rate'])->toBe(0.5)
        ->and($stats['mean_attempts'])->toBe(1.75)
        ->and($answers->every(fn (Answer $a) => $a->isCorrect))->toBeTrue();
});

it('computes medians and quantiles of response time', function () {
    expect(Metrics::quantile(collect([1000, 2000, 3000, 4000, 60000]), 0.5))->toBe(3000)
        ->and(Metrics::quantile(collect([1000, 2000, 3000, 4000]), 0.5))->toBe(2500)
        ->and(Metrics::quantile(collect([]), 0.5))->toBeNull();
});

it('detects position bias from first taps only', function () {
    // Child always taps the left card first; the correct card is elsewhere half the time.
    $answers = collect(range(1, 10))->map(fn (int $i) => $i % 2 === 0
        ? answer(['position' => $i, 'taps' => [['Dog', 0, false], ['Cat', 2, true]]])
        : answer(['position' => $i, 'taps' => [['Cat', 0, true]]]));

    $insight = (new PatternDetector)->positionBias($answers);

    expect($insight)->not->toBeNull()
        ->and($insight->evidence['position'])->toBe('left')
        ->and($insight->evidence['share'])->toBe(1.0);
});

it('does not report position bias when first taps are spread out', function () {
    $answers = collect(range(0, 11))->map(fn (int $i) => answer(['position' => $i, 'taps' => [['Cat', $i % 3, true]]]));

    expect((new PatternDetector)->positionBias($answers))->toBeNull();
});

it('needs enough choices before reporting position bias', function () {
    $answers = collect(range(1, 4))->map(fn (int $i) => answer(['position' => $i, 'taps' => [['Cat', 0, true]]]));

    expect((new PatternDetector)->positionBias($answers))->toBeNull();
});

it('detects perseveration on the same answer across consecutive questions', function () {
    $answers = collect(range(1, 5))->map(fn (int $i) => answer([
        'position' => $i,
        'correctLabel' => "Target {$i}",
        'taps' => [['Red', $i % 3, false], ["Target {$i}", ($i + 1) % 3, true]],
    ]));

    $insight = (new PatternDetector)->perseveration($answers);

    expect($insight)->not->toBeNull()
        ->and($insight->evidence['kind'])->toBe('label')
        ->and($insight->evidence['streak'])->toBe(5);
});

it('flags repeating the same wrong card after feedback', function () {
    $answers = collect([
        answer(['taps' => [['Dog', 1, false], ['Dog', 1, false], ['Cat', 0, true]]]),
        answer(['taps' => [['Cow', 2, false], ['Cow', 2, false], ['Cat', 0, true]]]),
    ]);

    expect((new PatternDetector)->repeatedWrongChoice($answers)?->type)->toBe('repeated_wrong');
});

it('detects rapid guessing and compares its accuracy with considered answers', function () {
    $rapid = collect(range(1, 5))->map(fn () => answer(['taps' => [['Dog', 1, false, 400, true], ['Cat', 0, true, 1500]]]));
    $considered = collect(range(1, 5))->map(fn () => answer(['taps' => [['Cat', 0, true, 3000]]]));

    $insight = (new PatternDetector)->rapidGuessing($rapid->merge($considered));

    expect($insight)->not->toBeNull()
        ->and($insight->evidence['rapid_rate'])->toBe(0.0)
        ->and($insight->evidence['considered_rate'])->toBe(1.0)
        ->and($insight->message)->toContain('listen to the whole question');
});

it('finds letter confusion pairs', function () {
    $answers = collect([
        answer(['correctLabel' => 'b', 'taps' => [['d', 1, false], ['b', 0, true]]]),
        answer(['correctLabel' => 'b', 'taps' => [['d', 2, false], ['b', 0, true]]]),
        answer(['correctLabel' => 'p', 'taps' => [['q', 1, false], ['p', 0, true]]]),
    ]);

    $insight = (new PatternDetector)->confusionPairs($answers);

    expect($insight->evidence['pairs'])->toBe(['b→d' => 2])
        ->and($insight->message)->toContain('“d” instead of “b”');
});

it('distinguishes off-by-one counting slips from guessing', function () {
    $slips = collect([
        answer(['isNumberInput' => true, 'correctLabel' => '5', 'optionOrder' => null, 'taps' => [['4', null, false], ['5', null, true]]]),
        answer(['isNumberInput' => true, 'correctLabel' => '7', 'optionOrder' => null, 'taps' => [['6', null, false], ['7', null, true]]]),
        answer(['isNumberInput' => true, 'correctLabel' => '3', 'optionOrder' => null, 'taps' => [['4', null, false], ['3', null, true]]]),
    ]);
    $guesses = collect([
        answer(['isNumberInput' => true, 'correctLabel' => '2', 'optionOrder' => null, 'taps' => [['9', null, false], ['2', null, true]]]),
        answer(['isNumberInput' => true, 'correctLabel' => '8', 'optionOrder' => null, 'taps' => [['1', null, false], ['8', null, true]]]),
        answer(['isNumberInput' => true, 'correctLabel' => '6', 'optionOrder' => null, 'taps' => [['2', null, false], ['6', null, true]]]),
    ]);

    $detector = new PatternDetector;

    expect($detector->countingErrors($slips)->message)->toContain('off by just one')
        ->and($detector->countingErrors($guesses)->message)->toContain('guessing');
});

it('relates replaying the question to accuracy', function () {
    $replayed = collect(range(1, 4))->map(fn () => answer(['replayCount' => 2, 'taps' => [['Dog', 1, false], ['Cat', 0, true]]]));
    $plain = collect(range(1, 4))->map(fn () => answer());

    $insight = (new PatternDetector)->replayEffect($replayed->merge($plain));

    expect($insight->evidence['replayed_rate'])->toBe(0.0)
        ->and($insight->evidence['other_rate'])->toBe(1.0)
        ->and($insight->message)->toContain('hard to understand');
});

it('compares recorded voice with computer voice and photos with emoji', function () {
    $recorded = collect(range(1, 4))->map(fn () => answer(['hasRecordedAudio' => true, 'hasImageOptions' => true]));
    $tts = collect(range(1, 4))->map(fn () => answer(['taps' => [['Dog', 1, false], ['Cat', 0, true]]]));
    $all = $recorded->merge($tts);
    $detector = new PatternDetector;

    expect($detector->recordedAudioEffect($all)->evidence)->toMatchArray(['with_rate' => 1.0, 'without_rate' => 0.0])
        ->and($detector->pictureCardEffect($all)->type)->toBe('picture_cards');
});

it('detects fatigue later in a session', function () {
    $answers = collect(range(0, 11))->map(fn (int $i) => $i < 6
        ? answer(['position' => $i])
        : answer(['position' => $i, 'taps' => [['Dog', 1, false], ['Cat', 0, true]]]));

    expect((new PatternDetector)->fatigue($answers)?->type)->toBe('fatigue');
});

it('reports long pauses and free choice favourites', function () {
    $pauses = collect([answer(['taps' => [['Cat', 0, true, 20000]]]), answer(['taps' => [['Cat', 0, true, 18000]]])]);
    $free = collect(range(1, 6))->map(fn (int $i) => answer([
        'mode' => 'free',
        'selectedLabel' => $i <= 4 ? 'Red' : 'Blue',
        'isCorrect' => $i === 1,
        'taps' => [[$i <= 4 ? 'Red' : 'Blue', 0, $i === 1]],
    ]));

    $detector = new PatternDetector;

    expect($detector->longPauses($pauses)?->type)->toBe('long_pauses')
        ->and($detector->freeChoicePreferences($free)->evidence['favourites'])->toBe(['Red' => 4, 'Blue' => 2]);
});

it('counts children with a pattern at group level', function () {
    $biased = collect(range(1, 10))->map(fn (int $i) => answer(['participantId' => 1, 'sessionId' => 1, 'position' => $i, 'taps' => [['Cat', 0, true]]]));
    $spread = collect(range(1, 12))->map(fn (int $i) => answer(['participantId' => 2, 'sessionId' => 2, 'position' => $i, 'taps' => [['Cat', $i % 3, true]]]));

    $insights = collect((new PatternDetector)->forGroup($biased->merge($spread)));
    $bias = $insights->firstWhere('type', 'position_bias');

    expect($bias->evidence)->toBe(['flagged' => 1, 'children' => 2])
        ->and($bias->message)->toStartWith('1 of 2 children');
});

it('labels category strengths relative to the child’s own average with a minimum sample', function () {
    $numbers = collect(range(1, 4))->map(fn () => answer(['category' => 'Numbers']));
    $phonics = collect(range(1, 4))->map(fn () => answer(['category' => 'Phonics', 'taps' => [['x', 1, false], ['Cat', 0, true]]]));
    $colours = collect([answer(['category' => 'Colors'])]);

    $profile = ChildProfile::build($numbers->merge($phonics)->merge($colours));
    $labels = collect($profile['categories'])->pluck('label', 'category');

    expect($labels['Numbers'])->toBe(ChildProfile::STRENGTH)
        ->and($labels['Phonics'])->toBe(ChildProfile::PRACTICE)
        ->and($labels['Colors'])->toBe(ChildProfile::NOT_ENOUGH)
        ->and($profile['peers_available'])->toBeFalse();
});

it('does not call a weak area a strength just because the child’s average is low', function () {
    $wrong = [['x', 1, false], ['Cat', 0, true]];
    $letters = collect([answer(['category' => 'Letters']), ...array_map(fn () => answer(['category' => 'Letters', 'taps' => $wrong]), range(1, 2))]);
    $numbers = collect(range(1, 6))->map(fn () => answer(['category' => 'Numbers', 'taps' => $wrong]));

    $labels = collect(ChildProfile::build($letters->merge($numbers))['categories'])->pluck('label', 'category');

    expect($labels['Letters'])->toBe(ChildProfile::DEVELOPING);
});

it('does not count taps made before the question ended as a reaction time', function () {
    $early = answer(['promptEndedMs' => 3000, 'taps' => [['Cat', 0, true, 1200, true]]]);
    $late = answer(['promptEndedMs' => 3000, 'taps' => [['Cat', 0, true, 4500]]]);

    expect($early->reactionTimeMs())->toBeNull()
        ->and($late->reactionTimeMs())->toBe(1500)
        ->and(Metrics::summarize(collect([$early]))['median_reaction_ms'])->toBeNull();
});

it('only shows peer averages when enough other children contribute', function () {
    $child = collect([answer(['participantId' => 1])]);
    $fewPeers = collect(range(2, 4))->map(fn (int $p) => answer(['participantId' => $p]));
    $manyPeers = collect(range(2, 7))->map(fn (int $p) => answer(['participantId' => $p]));

    expect(ChildProfile::build($child, $fewPeers)['peers_available'])->toBeFalse()
        ->and(ChildProfile::build($child, $manyPeers)['peers_available'])->toBeTrue();
});

it('analyses item difficulty, distractors and flags', function () {
    $answers = collect(range(1, 10))->map(fn (int $i) => answer([
        'questionKey' => 'id:99',
        'participantId' => $i,
        'sessionId' => $i,
        'taps' => $i <= 7 ? [['Dog', 1, false], ['Cat', 0, true]] : [['Cat', 0, true]],
    ]));

    $item = (new ItemAnalyzer)->analyze($answers)[0];

    expect($item['difficulty'])->toBe(0.3)
        ->and($item['distractors'])->toBe(['Dog' => 7])
        ->and($item['never_chosen'])->toBe(['Cow']);
});
