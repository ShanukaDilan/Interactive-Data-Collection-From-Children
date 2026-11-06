<?php

namespace App\Livewire\Admin\Questions;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Support\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
class QuestionForm extends Component
{
    use WithFileUploads;

    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    /** Default pastel card colours, cycled for new options. */
    public const OPTION_COLOURS = ['#FFF0F0', '#F0F4FF', '#F0FFF4', '#FFFDF0', '#F8F0FF', '#FFF8E0'];

    public ?Question $question = null;

    public string $type = 'choice';

    public string $category = '';

    public string $speakText = '';

    public string $hint = '';

    public string $questionEmoji = '';

    public ?int $correctNumber = null;

    public ?string $imagePath = null;

    public ?string $audioPath = null;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    /** @var TemporaryUploadedFile|null */
    public $audio = null;

    /** @var list<array{label: string, emoji: string, bg_color: string, image_path: ?string}> */
    public array $options = [];

    /** @var array<int, TemporaryUploadedFile|null> Option image uploads keyed by option index. */
    public array $optionImages = [];

    public int $correctOption = 0;

    public function mount(?Question $question = null): void
    {
        if ($question?->exists) {
            $this->question = $question->load('options');
            $this->fill([
                'type' => $question->type->value,
                'category' => $question->category,
                'speakText' => $question->speak_text,
                'hint' => (string) $question->hint,
                'questionEmoji' => (string) $question->question_emoji,
                'correctNumber' => $question->correct_number,
                'imagePath' => $question->image_path,
                'audioPath' => $question->audio_path,
                'options' => $question->options->map(fn ($option) => [
                    'label' => $option->label,
                    'emoji' => (string) $option->emoji,
                    'bg_color' => $option->bg_color,
                    'image_path' => $option->image_path,
                ])->all(),
                'correctOption' => max(0, (int) $question->options->search(fn ($option) => $option->is_correct)),
            ]);
        }

        while (count($this->options) < 3) {
            $this->addOption();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $maxKb = config('quiz.uploads.max_kb');
        $isChoice = $this->type === QuestionType::Choice->value;

        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'category' => ['required', 'string', 'max:80'],
            'speakText' => ['required', 'string', 'max:255'],
            'hint' => ['nullable', 'string', 'max:120'],
            'questionEmoji' => ['nullable', 'string', 'max:64'],
            'correctNumber' => [Rule::requiredIf(! $isChoice), 'nullable', 'integer', 'between:0,99'],
            'image' => ['nullable', 'image', 'mimes:'.implode(',', Media::IMAGE_MIMES), "max:{$maxKb}"],
            'audio' => ['nullable', 'file', 'mimes:'.implode(',', Media::AUDIO_MIMES), "max:{$maxKb}"],
            'options' => $isChoice ? ['array', 'min:'.self::MIN_OPTIONS, 'max:'.self::MAX_OPTIONS] : ['array'],
            'options.*.label' => $isChoice ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'options.*.emoji' => ['nullable', 'string', 'max:32'],
            'options.*.bg_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'optionImages.*' => ['nullable', 'image', 'mimes:'.implode(',', Media::IMAGE_MIMES), "max:{$maxKb}"],
            'correctOption' => $isChoice ? ['required', 'integer', 'min:0', 'max:'.(count($this->options) - 1)] : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'speakText' => 'spoken question',
            'correctNumber' => 'correct number',
            'options.*.label' => 'answer label',
            'options.*.bg_color' => 'card colour',
            'optionImages.*' => 'answer image',
        ];
    }

    public function addOption(): void
    {
        if (count($this->options) >= self::MAX_OPTIONS) {
            return;
        }

        $this->options[] = [
            'label' => '',
            'emoji' => '',
            'bg_color' => self::OPTION_COLOURS[count($this->options) % count(self::OPTION_COLOURS)],
            'image_path' => null,
        ];
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) <= self::MIN_OPTIONS || ! isset($this->options[$index])) {
            return;
        }

        array_splice($this->options, $index, 1);

        $images = $this->optionImages;
        unset($images[$index]);
        $this->optionImages = collect($images)
            ->mapWithKeys(fn ($file, $key) => [$key > $index ? $key - 1 : $key => $file])
            ->all();

        if ($this->correctOption === $index) {
            $this->correctOption = 0;
        } elseif ($this->correctOption > $index) {
            $this->correctOption--;
        }
    }

    public function removeImage(): void
    {
        $this->image = null;
        $this->imagePath = null;
    }

    public function removeAudio(): void
    {
        $this->audio = null;
        $this->audioPath = null;
    }

    public function removeOptionImage(int $index): void
    {
        unset($this->optionImages[$index]);

        if (isset($this->options[$index])) {
            $this->options[$index]['image_path'] = null;
        }
    }

    public function save(): void
    {
        $this->question ? $this->authorize('update', $this->question) : $this->authorize('create', Question::class);

        $this->validate();

        $isChoice = $this->type === QuestionType::Choice->value;
        $previousPaths = $this->question?->mediaPaths() ?? [];
        $newlyStored = [];

        $store = function ($file, string $kind) use (&$newlyStored): string {
            return $newlyStored[] = Media::store($file, $kind);
        };

        try {
            $imagePath = $this->image ? $store($this->image, 'image') : $this->imagePath;
            $audioPath = $this->audio ? $store($this->audio, 'audio') : $this->audioPath;

            $options = collect($isChoice ? $this->options : [])
                ->values()
                ->map(fn (array $option, int $index) => [
                    'position' => $index,
                    'label' => trim($option['label']),
                    'emoji' => trim($option['emoji']) ?: null,
                    'bg_color' => strtoupper($option['bg_color']),
                    'image_path' => isset($this->optionImages[$index]) && $this->optionImages[$index]
                        ? $store($this->optionImages[$index], 'image')
                        : $option['image_path'],
                    'is_correct' => $index === $this->correctOption,
                ]);

            $question = DB::transaction(function () use ($isChoice, $imagePath, $audioPath, $options): Question {
                $question = $this->question ?? new Question(['created_by' => Auth::id(), 'version' => 1]);

                $question->fill([
                    'type' => $this->type,
                    'category' => trim($this->category),
                    'speak_text' => trim($this->speakText),
                    'hint' => trim($this->hint) ?: null,
                    'question_emoji' => trim($this->questionEmoji) ?: null,
                    'correct_number' => $isChoice ? null : $this->correctNumber,
                    'image_path' => $imagePath,
                    'audio_path' => $audioPath,
                ]);

                if ($question->exists) {
                    $question->version++;
                }

                $question->save();
                $question->options()->delete();
                $question->options()->createMany($options->all());

                return $question->load('options');
            });
        } catch (\Throwable $exception) {
            Media::delete($newlyStored);

            throw $exception;
        }

        $orphaned = array_diff($previousPaths, $question->mediaPaths());
        Media::delete(array_diff($orphaned, $question->mediaPathsUsedElsewhere($orphaned)));

        session()->flash('status', $this->question ? 'Question saved.' : 'Question created.');

        $this->redirectRoute('admin.questions.edit', $question, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.questions.question-form', [
            'categories' => collect(config('quiz.categories'))
                ->merge(Question::query()->distinct()->pluck('category'))
                ->unique()
                ->sort()
                ->values(),
            'imageAccept' => collect(Media::IMAGE_MIMES)->map(fn ($ext) => '.'.$ext)->implode(','),
            'audioAccept' => collect(Media::AUDIO_MIMES)->map(fn ($ext) => '.'.$ext)->implode(','),
        ])->title($this->question ? 'Edit question' : 'New question');
    }
}
