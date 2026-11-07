<?php

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Livewire\Admin\Questions\QuestionForm;
use App\Livewire\Admin\Questions\QuestionIndex;
use App\Models\Question;
use App\Models\QuizResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->researcher = userWithRole(Role::Researcher);
});

it('creates a choice question with options, a correct answer and images', function () {
    Livewire::actingAs($this->researcher)
        ->test(QuestionForm::class)
        ->set('category', 'Animals')
        ->set('speakText', 'Touch the cat & the hat!')
        ->set('hint', 'Cat')
        ->set('image', UploadedFile::fake()->image('cat.png'))
        ->set('options.0.label', 'Cat')
        ->set('options.0.emoji', '🐱')
        ->set('options.1.label', 'Dog')
        ->set('options.2.label', 'Cow')
        ->set('optionImages.1', UploadedFile::fake()->image('dog.jpg'))
        ->set('correctOption', 0)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $question = Question::with('options')->firstOrFail();

    expect($question->type)->toBe(QuestionType::Choice)
        ->and($question->speak_text)->toBe('Touch the cat & the hat!')
        ->and($question->version)->toBe(1)
        ->and($question->created_by)->toBe($this->researcher->id)
        ->and($question->options)->toHaveCount(3)
        ->and($question->correctLabel())->toBe('Cat');

    Storage::disk('public')->assertExists($question->image_path);
    Storage::disk('public')->assertExists($question->options[1]->image_path);
});

it('creates a number keypad question without options', function () {
    Livewire::actingAs($this->researcher)
        ->test(QuestionForm::class)
        ->set('type', 'number_input')
        ->set('category', 'Numbers - Counting')
        ->set('speakText', 'Count the stars!')
        ->set('questionEmoji', '⭐⭐⭐⭐')
        ->set('correctNumber', 4)
        ->call('save')
        ->assertHasNoErrors();

    $question = Question::firstOrFail();

    expect($question->type)->toBe(QuestionType::NumberInput)
        ->and($question->correct_number)->toBe(4)
        ->and($question->options)->toHaveCount(0);
});

it('requires labels and a correct number where relevant', function () {
    Livewire::actingAs($this->researcher)
        ->test(QuestionForm::class)
        ->set('category', 'Animals')
        ->set('speakText', 'Touch the cat!')
        ->call('save')
        ->assertHasErrors(['options.0.label', 'options.1.label']);

    Livewire::actingAs($this->researcher)
        ->test(QuestionForm::class)
        ->set('type', 'number_input')
        ->set('category', 'Numbers')
        ->set('speakText', 'Count!')
        ->call('save')
        ->assertHasErrors(['correctNumber']);
});

it('rejects non-image uploads', function () {
    Livewire::actingAs($this->researcher)
        ->test(QuestionForm::class)
        ->set('image', UploadedFile::fake()->create('evil.php', 10, 'application/x-php'))
        ->call('save')
        ->assertHasErrors(['image']);
});

it('keeps between 2 and 6 options and tracks the correct one when removing', function () {
    $component = Livewire::actingAs($this->researcher)->test(QuestionForm::class);

    $component->assertCount('options', 3)
        ->set('correctOption', 2)
        ->call('removeOption', 0)
        ->assertSet('correctOption', 1)
        ->call('removeOption', 0)
        ->assertCount('options', 2);

    foreach (range(1, 6) as $ignored) {
        $component->call('addOption');
    }

    $component->assertCount('options', 6);
});

it('bumps the version and cleans up replaced files on edit', function () {
    $oldImage = UploadedFile::fake()->image('old.png')->store('questions/images', 'public');
    $question = Question::factory()->withOptions()->create(['image_path' => $oldImage, 'version' => 2]);

    Livewire::actingAs($this->researcher)
        ->test(QuestionForm::class, ['question' => $question])
        ->assertSet('speakText', $question->speak_text)
        ->set('speakText', 'Touch the new cat!')
        ->set('image', UploadedFile::fake()->image('new.png'))
        ->call('save')
        ->assertHasNoErrors();

    $question->refresh();

    expect($question->version)->toBe(3)
        ->and($question->speak_text)->toBe('Touch the new cat!')
        ->and($question->image_path)->not->toBe($oldImage);

    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($question->image_path);
});

it('archives instead of deleting questions that have answers', function () {
    $answered = Question::factory()->withOptions()->create();
    QuizResponse::factory()->create(['question_id' => $answered->id]);
    $unused = Question::factory()->withOptions()->create();

    Livewire::actingAs($this->researcher)
        ->test(QuestionIndex::class)
        ->call('delete', $answered->id)
        ->call('delete', $unused->id);

    expect($answered->fresh()->is_archived)->toBeTrue()
        ->and($unused->fresh())->toBeNull();
});

it('duplicates a question with its options but keeps shared files on delete', function () {
    $image = UploadedFile::fake()->image('shared.png')->store('questions/images', 'public');
    $original = Question::factory()->withOptions()->create(['image_path' => $image, 'legacy_id' => 'q01']);

    Livewire::actingAs($this->researcher)->test(QuestionIndex::class)->call('duplicate', $original->id);

    $copy = Question::whereKeyNot($original->id)->firstOrFail();
    expect($copy->options)->toHaveCount(3)
        ->and($copy->legacy_id)->toBeNull()
        ->and($copy->image_path)->toBe($image);

    Livewire::actingAs($this->researcher)->test(QuestionIndex::class)->call('delete', $copy->id);

    Storage::disk('public')->assertExists($image);
});

it('forbids teachers from saving questions', function () {
    Livewire::actingAs(userWithRole(Role::Teacher))
        ->test(QuestionForm::class)
        ->set('category', 'Animals')
        ->set('speakText', 'Touch the cat!')
        ->call('save')
        ->assertForbidden();
});
