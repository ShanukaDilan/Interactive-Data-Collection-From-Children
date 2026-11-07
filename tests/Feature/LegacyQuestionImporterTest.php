<?php

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Study;
use App\Services\LegacyQuestionImporter;

it('imports the original questions.json with options and keypad questions', function () {
    $study = Study::factory()->create();

    $count = app(LegacyQuestionImporter::class)
        ->importFile(database_path('seeders/data/questions.json'), $study);

    expect($count)->toBe(20)
        ->and(Question::count())->toBe(20)
        ->and(Question::where('type', QuestionType::NumberInput)->count())->toBe(8)
        ->and($study->questions()->count())->toBe(20);

    $counting = Question::where('legacy_id', 'q12')->first();
    expect($counting->type)->toBe(QuestionType::NumberInput)
        ->and($counting->correct_number)->toBe(7)
        ->and($counting->correctLabel())->toBe('7');

    $colours = Question::where('legacy_id', 'q20')->first();
    expect($colours->options)->toHaveCount(4)
        ->and($colours->correctLabel())->toBe('Red');
});

it('decodes html entities left by the legacy app', function () {
    app(LegacyQuestionImporter::class)->import([[
        'id' => 'q99',
        'category' => 'Food &amp; Drink',
        'speak' => 'Touch the &quot;apple&quot;!',
        'options' => [
            ['label' => 'Fish &amp; Chips', 'correct' => true, 'bg' => '#fff0f0', 'emoji' => '🍟'],
        ],
    ]]);

    $question = Question::where('legacy_id', 'q99')->first();

    expect($question->category)->toBe('Food & Drink')
        ->and($question->speak_text)->toBe('Touch the "apple"!')
        ->and($question->options->first()->label)->toBe('Fish & Chips')
        ->and($question->options->first()->bg_color)->toBe('#FFF0F0');
});

it('can be re-run without duplicating questions or study links', function () {
    $study = Study::factory()->create();
    $importer = app(LegacyQuestionImporter::class);
    $path = database_path('seeders/data/questions.json');

    $importer->importFile($path, $study);
    $importer->importFile($path, $study);

    expect(Question::count())->toBe(20)
        ->and($study->questions()->count())->toBe(20)
        ->and(Question::where('legacy_id', 'q01')->first()->options)->toHaveCount(3);
});

it('does not overwrite edited questions when seeding again', function () {
    $this->seed();
    Question::where('legacy_id', 'q01')->update(['speak_text' => 'Edited in the admin']);

    $this->seed();

    expect(Question::where('legacy_id', 'q01')->value('speak_text'))->toBe('Edited in the admin')
        ->and(Question::count())->toBe(20);
});

it('seeds an admin user and the default study', function () {
    $this->seed();

    $study = Study::where('slug', 'learning-fun-default')->first();

    expect($study)->not->toBeNull()
        ->and($study->questions()->count())->toBe(20)
        ->and($study->owner->isAdmin())->toBeTrue();
});
