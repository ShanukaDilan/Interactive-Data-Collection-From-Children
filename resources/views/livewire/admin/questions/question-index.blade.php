<div>
    <x-admin.page-header title="Questions" subtitle="The shared question bank. Add questions to a study from the study's page.">
        <x-slot:actions>
            @can('create', App\Models\Question::class)
                <a href="{{ route('admin.questions.create') }}" class="btn btn-primary" wire:navigate>+ New question</a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search questions…" class="field-input sm:max-w-xs" aria-label="Search questions">
        <select wire:model.live="category" class="field-input sm:max-w-56" aria-label="Filter by category">
            <option value="">All categories</option>
            @foreach ($categories as $name)
                <option value="{{ $name }}">{{ $name }}</option>
            @endforeach
        </select>
        <select wire:model.live="type" class="field-input sm:max-w-48" aria-label="Filter by type">
            <option value="">All types</option>
            @foreach ($types as $questionType)
                <option value="{{ $questionType->value }}">{{ $questionType->label() }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm font-bold text-muted">
            <input type="checkbox" wire:model.live="showArchived" class="size-4 rounded border-line text-coral focus:ring-coral">
            Show archived
        </label>
    </div>

    <div class="card overflow-hidden">
        @if ($questions->isEmpty())
            <x-admin.empty-state icon="📝" title="No questions found" message="Adjust the filters or create a new question." />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Question</th>
                        <th>Category</th>
                        <th>Answers shown</th>
                        <th class="text-right">Studies</th>
                        <th class="text-right">Responses</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($questions as $question)
                        <tr wire:key="question-{{ $question->id }}">
                            <td class="max-w-md">
                                <p class="font-bold">{{ $question->speak_text }}</p>
                                <p class="text-xs text-muted">
                                    {{ $question->reference() }} · v{{ $question->version }}
                                    @if ($question->image_path) · 🖼️ @endif
                                    @if ($question->audio_path) · 🔊 @endif
                                </p>
                            </td>
                            <td><span class="badge bg-lav/30 text-ink">{{ $question->category }}</span></td>
                            <td>
                                @if ($question->type === App\Enums\QuestionType::NumberInput)
                                    <span class="badge bg-sun/40 text-ink">🔢 {{ $question->correct_number }}</span>
                                    <span class="ml-1 text-lg">{{ Str::limit($question->question_emoji, 12, '…') }}</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($question->options as $option)
                                            <span @class(['badge', 'bg-mint/60 text-ink ring-1 ring-sky' => $option->is_correct, 'bg-canvas text-muted' => ! $option->is_correct])
                                                  title="{{ $option->is_correct ? 'Correct answer' : '' }}">
                                                {{ $option->emoji }} {{ $option->label }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-right">{{ $question->studies_count }}</td>
                            <td class="text-right">{{ number_format($question->responses_count) }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="btn btn-secondary btn-sm" wire:navigate>Edit</a>
                                <button type="button" wire:click="duplicate({{ $question->id }})" class="btn btn-secondary btn-sm">Duplicate</button>
                                <button type="button" wire:click="toggleArchived({{ $question->id }})" class="btn btn-secondary btn-sm">
                                    {{ $question->is_archived ? 'Restore' : 'Archive' }}
                                </button>
                                <button type="button" wire:click="delete({{ $question->id }})"
                                        wire:confirm="Delete this question? If it already has answers it will be archived instead."
                                        class="btn btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $questions->links() }}</div>
</div>
