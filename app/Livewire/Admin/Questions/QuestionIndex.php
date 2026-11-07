<?php

namespace App\Livewire\Admin\Questions;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Support\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Questions')]
class QuestionIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: false)]
    public bool $showArchived = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'type', 'showArchived'], true)) {
            $this->resetPage();
        }
    }

    public function toggleArchived(Question $question): void
    {
        $this->authorize('update', $question);

        $question->update(['is_archived' => ! $question->is_archived]);

        $this->dispatch('toast', message: $question->is_archived ? 'Question archived.' : 'Question restored.');
    }

    public function duplicate(Question $question): void
    {
        $this->authorize('create', Question::class);

        DB::transaction(function () use ($question): void {
            $copy = $question->replicate(['legacy_id', 'version']);
            $copy->speak_text = $question->speak_text.' (copy)';
            $copy->version = 1;
            $copy->created_by = auth()->id();
            $copy->save();

            foreach ($question->options as $option) {
                $copy->options()->create($option->only(['position', 'label', 'emoji', 'bg_color', 'image_path', 'is_correct']));
            }
        });

        $this->dispatch('toast', message: 'Question duplicated.');
    }

    /**
     * Questions that already have answers are archived instead of deleted, to protect research data.
     */
    public function delete(Question $question): void
    {
        $this->authorize('delete', $question);

        if ($question->responses()->exists()) {
            $question->update(['is_archived' => true]);
            $this->dispatch('toast', message: 'This question has recorded answers, so it was archived instead.', type: 'error');

            return;
        }

        $paths = $question->mediaPaths();
        $unusedPaths = array_diff($paths, $question->mediaPathsUsedElsewhere($paths));

        $question->delete();
        Media::delete($unusedPaths);

        $this->dispatch('toast', message: 'Question deleted.');
    }

    public function render(): View
    {
        $questions = Question::query()
            ->where('is_archived', $this->showArchived)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('speak_text', 'like', '%'.$this->search.'%')
                        ->orWhere('hint', 'like', '%'.$this->search.'%')
                        ->orWhere('legacy_id', $this->search);
                });
            })
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->with('options')
            ->withCount(['studies', 'responses'])
            ->orderBy('category')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.admin.questions.question-index', [
            'questions' => $questions,
            'categories' => Question::query()->distinct()->orderBy('category')->pluck('category'),
            'types' => QuestionType::cases(),
        ]);
    }
}
