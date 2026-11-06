<?php

namespace App\Livewire\Admin\Studies;

use App\Enums\QuizMode;
use App\Enums\Role;
use App\Models\Question;
use App\Models\Study;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class StudyForm extends Component
{
    public ?Study $study = null;

    public string $name = '';

    public string $description = '';

    public string $locale = 'en-US';

    public string $defaultQuizMode = 'correct';

    public int $ageMin = 4;

    public int $ageMax = 6;

    public ?int $questionsPerSession = null;

    public bool $shuffleQuestions = true;

    public bool $shuffleOptions = true;

    public ?int $retentionDays = null;

    public bool $isActive = true;

    /** @var list<int> */
    public array $memberIds = [];

    /** @var list<int> Ordered question ids in this study's set. */
    public array $questionIds = [];

    public string $questionSearch = '';

    public function mount(?Study $study = null): void
    {
        if ($study?->exists) {
            $this->study = $study;
            $this->fill([
                'name' => $study->name,
                'description' => (string) $study->description,
                'locale' => $study->locale,
                'defaultQuizMode' => $study->default_quiz_mode->value,
                'ageMin' => $study->age_min,
                'ageMax' => $study->age_max,
                'questionsPerSession' => $study->questions_per_session,
                'shuffleQuestions' => $study->shuffle_questions,
                'shuffleOptions' => $study->shuffle_options,
                'retentionDays' => $study->retention_days,
                'isActive' => $study->is_active,
                'memberIds' => $study->members()->pluck('users.id')->all(),
                'questionIds' => $study->questions()->pluck('questions.id')->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'locale' => ['required', Rule::in(array_keys(config('quiz.locales')))],
            'defaultQuizMode' => ['required', Rule::enum(QuizMode::class)],
            'ageMin' => ['required', 'integer', 'between:2,12'],
            'ageMax' => ['required', 'integer', 'between:2,12', 'gte:ageMin'],
            'questionsPerSession' => ['nullable', 'integer', 'between:1,200'],
            'shuffleQuestions' => ['boolean'],
            'shuffleOptions' => ['boolean'],
            'retentionDays' => ['nullable', 'integer', 'between:1,3650'],
            'isActive' => ['boolean'],
            'memberIds' => ['array'],
            'memberIds.*' => ['integer', Rule::exists('users', 'id')],
            'questionIds' => ['array'],
            'questionIds.*' => ['integer', Rule::exists('questions', 'id')],
        ];
    }

    public function addQuestion(int $questionId): void
    {
        if (! in_array($questionId, $this->questionIds, true)) {
            $this->questionIds[] = $questionId;
        }
    }

    public function removeQuestion(int $questionId): void
    {
        $this->questionIds = array_values(array_filter($this->questionIds, fn (int $id) => $id !== $questionId));
    }

    public function moveQuestion(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->questionIds[$index], $this->questionIds[$target])) {
            return;
        }

        [$this->questionIds[$index], $this->questionIds[$target]] = [$this->questionIds[$target], $this->questionIds[$index]];
    }

    public function save(): void
    {
        $this->study ? $this->authorize('update', $this->study) : $this->authorize('create', Study::class);

        $this->validate();

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'locale' => $this->locale,
            'default_quiz_mode' => $this->defaultQuizMode,
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'questions_per_session' => $this->questionsPerSession ?: null,
            'shuffle_questions' => $this->shuffleQuestions,
            'shuffle_options' => $this->shuffleOptions,
            'retention_days' => $this->retentionDays ?: null,
            'is_active' => $this->isActive,
        ];

        $study = DB::transaction(function () use ($attributes): Study {
            $study = $this->study ?? new Study([
                'owner_id' => Auth::id(),
                'slug' => $this->uniqueSlug($this->name),
            ]);
            $study->fill($attributes)->save();

            $study->members()->sync(array_diff($this->memberIds, [$study->owner_id]));
            $study->questions()->sync(
                collect($this->questionIds)
                    ->values()
                    ->mapWithKeys(fn (int $id, int $index) => [$id => ['position' => $index + 1]])
                    ->all()
            );

            return $study;
        });

        session()->flash('status', $this->study ? 'Study saved.' : 'Study created.');

        $this->redirectRoute('admin.studies.edit', $study, navigate: true);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'study';
        $slug = $base;
        $suffix = 2;

        while (Study::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function render(): View
    {
        $selected = Question::with('options')->findMany($this->questionIds)->keyBy('id');

        $available = Question::query()
            ->where('is_archived', false)
            ->whereNotIn('id', $this->questionIds)
            ->when($this->questionSearch !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('speak_text', 'like', '%'.$this->questionSearch.'%')
                        ->orWhere('category', 'like', '%'.$this->questionSearch.'%');
                });
            })
            ->orderBy('category')
            ->limit(50)
            ->get();

        return view('livewire.admin.studies.study-form', [
            'selectedQuestions' => collect($this->questionIds)->map(fn (int $id) => $selected->get($id))->filter()->values(),
            'availableQuestions' => $available,
            'staff' => User::role([Role::Researcher->value, Role::Teacher->value])
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'locales' => config('quiz.locales'),
        ])->title($this->study ? 'Edit study' : 'New study');
    }
}
