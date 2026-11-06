<div>
    <x-admin.page-header :title="$study ? 'Edit study' : 'New study'" :subtitle="$study?->name">
        <x-slot:actions>
            <a href="{{ route('admin.studies.index') }}" class="btn btn-secondary" wire:navigate>← Back to studies</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form wire:submit="save" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Settings --}}
            <section class="card space-y-5 p-6">
                <h2 class="font-display text-lg">Settings</h2>

                <div>
                    <label for="name" class="field-label">Study name</label>
                    <input id="name" type="text" wire:model="name" class="field-input" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="field-label">Description <span class="font-normal text-muted">(optional)</span></label>
                    <textarea id="description" rows="3" wire:model="description" class="field-input"></textarea>
                    @error('description') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="locale" class="field-label">Speech language</label>
                        <select id="locale" wire:model="locale" class="field-input">
                            @foreach ($locales as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('locale') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="defaultQuizMode" class="field-label">Default quiz mode</label>
                        <select id="defaultQuizMode" wire:model="defaultQuizMode" class="field-input">
                            @foreach (App\Enums\QuizMode::cases() as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                        @error('defaultQuizMode') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ageMin" class="field-label">Youngest age</label>
                        <input id="ageMin" type="number" min="2" max="12" wire:model="ageMin" class="field-input">
                        @error('ageMin') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ageMax" class="field-label">Oldest age</label>
                        <input id="ageMax" type="number" min="2" max="12" wire:model="ageMax" class="field-input">
                        @error('ageMax') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="questionsPerSession" class="field-label">Questions per session</label>
                        <input id="questionsPerSession" type="number" min="1" wire:model="questionsPerSession" class="field-input" placeholder="All">
                        <p class="field-help">Leave empty to ask every question in the set.</p>
                        @error('questionsPerSession') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="retentionDays" class="field-label">Keep data for (days)</label>
                        <input id="retentionDays" type="number" min="1" wire:model="retentionDays" class="field-input" placeholder="Forever">
                        <p class="field-help">Optional data-retention period for ethics compliance.</p>
                        @error('retentionDays') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-3">
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" wire:model="shuffleQuestions" class="size-4 rounded border-line text-coral focus:ring-coral">
                        Shuffle question order
                    </label>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" wire:model="shuffleOptions" class="size-4 rounded border-line text-coral focus:ring-coral">
                        Shuffle answer positions
                    </label>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line text-coral focus:ring-coral">
                        Active (available on devices)
                    </label>
                </div>
            </section>

            {{-- Question set --}}
            <section class="card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg">Question set <span class="text-sm text-muted">({{ count($questionIds) }})</span></h2>
                </div>
                @error('questionIds') <p class="field-error mb-3">{{ $message }}</p> @enderror

                @if ($selectedQuestions->isEmpty())
                    <p class="rounded-xl bg-canvas px-4 py-6 text-center text-sm text-muted">No questions yet — add some from the bank below.</p>
                @else
                    <ol class="divide-y divide-line rounded-xl border border-line">
                        @foreach ($selectedQuestions as $index => $question)
                            <li wire:key="selected-{{ $question->id }}" class="flex items-center gap-3 px-3 py-2.5">
                                <span class="w-6 text-right text-xs font-extrabold text-muted">{{ $index + 1 }}</span>
                                <span class="text-xl" aria-hidden="true">{{ $question->question_emoji ? Str::limit($question->question_emoji, 6, '') : ($question->options->firstWhere('is_correct', true)?->emoji ?? '❓') }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold">{{ $question->speak_text }}</p>
                                    <p class="text-xs text-muted">{{ $question->category }} · {{ $question->type->label() }}</p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" wire:click="moveQuestion({{ $index }}, -1)" @disabled($loop->first) class="btn btn-secondary btn-sm" aria-label="Move up">↑</button>
                                    <button type="button" wire:click="moveQuestion({{ $index }}, 1)" @disabled($loop->last) class="btn btn-secondary btn-sm" aria-label="Move down">↓</button>
                                    <button type="button" wire:click="removeQuestion({{ $question->id }})" class="btn btn-danger btn-sm" aria-label="Remove">✕</button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="mt-6">
                    <h3 class="mb-2 text-sm font-extrabold">Add from question bank</h3>
                    <input type="search" wire:model.live.debounce.300ms="questionSearch" placeholder="Search by text or category…" class="field-input mb-3" aria-label="Search question bank">
                    <ul class="max-h-80 divide-y divide-line overflow-y-auto rounded-xl border border-line">
                        @forelse ($availableQuestions as $question)
                            <li wire:key="available-{{ $question->id }}" class="flex items-center gap-3 px-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm">{{ $question->speak_text }}</p>
                                    <p class="text-xs text-muted">{{ $question->category }}</p>
                                </div>
                                <button type="button" wire:click="addQuestion({{ $question->id }})" class="btn btn-secondary btn-sm">+ Add</button>
                            </li>
                        @empty
                            <li class="px-3 py-4 text-center text-sm text-muted">No more questions match.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>

        {{-- Team + save --}}
        <div class="space-y-6">
            <section class="card p-6">
                <h2 class="font-display mb-1 text-lg">Team</h2>
                <p class="mb-4 text-sm text-muted">Researchers and teachers who can work on this study.</p>

                @if ($staff->isEmpty())
                    <p class="text-sm text-muted">No researcher or teacher accounts yet.</p>
                @else
                    <div class="max-h-80 space-y-2 overflow-y-auto">
                        @foreach ($staff as $member)
                            <label wire:key="member-{{ $member->id }}" class="flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-canvas">
                                <input type="checkbox" value="{{ $member->id }}" wire:model="memberIds"
                                       class="size-4 rounded border-line text-coral focus:ring-coral"
                                       @disabled($study && $study->owner_id === $member->id)>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold">{{ $member->name }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $member->email }}</span>
                                </span>
                                <span class="badge bg-canvas text-muted">{{ $study && $study->owner_id === $member->id ? 'owner' : $member->roles->first()?->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="card sticky top-6 p-6">
                <button type="submit" class="btn btn-primary w-full py-3" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $study ? 'Save changes' : 'Create study' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </div>
    </form>
</div>
