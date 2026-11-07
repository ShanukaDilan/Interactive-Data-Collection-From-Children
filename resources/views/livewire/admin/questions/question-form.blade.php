@php
    $isChoice = $type === App\Enums\QuestionType::Choice->value;
    $imagePreview = $image && method_exists($image, 'isPreviewable') && $image->isPreviewable() ? $image->temporaryUrl() : App\Support\Media::url($imagePath);
@endphp

<div>
    <x-admin.page-header :title="$question ? 'Edit question' : 'New question'"
                         :subtitle="$question ? $question->reference().' · version '.$question->version : null">
        <x-slot:actions>
            <a href="{{ route('admin.questions.index') }}" class="btn btn-secondary" wire:navigate>← Back to questions</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($question && $question->responses()->exists())
        <div class="mb-6 rounded-2xl border border-peach/50 bg-peach/10 px-5 py-3 text-sm">
            <strong>Heads up:</strong> this question already has recorded answers. Saving creates version {{ $question->version + 1 }};
            earlier answers keep the text they were given with.
        </div>
    @endif

    <form wire:submit="save" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Question --}}
            <section class="card space-y-5 p-6">
                <h2 class="font-display text-lg">Question</h2>

                <fieldset>
                    <legend class="field-label">Answer type</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach (App\Enums\QuestionType::cases() as $questionType)
                            <label @class([
                                'flex cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3 text-sm font-bold transition',
                                'border-coral bg-coral-soft' => $type === $questionType->value,
                                'border-line hover:border-coral/40' => $type !== $questionType->value,
                            ])>
                                <input type="radio" wire:model.live="type" value="{{ $questionType->value }}" class="sr-only">
                                <span class="text-xl" aria-hidden="true">{{ $questionType === App\Enums\QuestionType::Choice ? '👆' : '🔢' }}</span>
                                {{ $questionType->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="category" class="field-label">Category</label>
                        <input id="category" type="text" list="category-list" wire:model="category" class="field-input" placeholder="e.g. Animals" required>
                        <datalist id="category-list">
                            @foreach ($categories as $name)
                                <option value="{{ $name }}">
                            @endforeach
                        </datalist>
                        @error('category') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="hint" class="field-label">Card text <span class="font-normal text-muted">(shown on screen)</span></label>
                        <input id="hint" type="text" wire:model.live.debounce.300ms="hint" class="field-input" placeholder="e.g. Cat">
                        @error('hint') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="speakText" class="field-label">Spoken question <span class="font-normal text-muted">(read aloud)</span></label>
                    <input id="speakText" type="text" wire:model.live.debounce.300ms="speakText" class="field-input" placeholder="e.g. Touch the cat!" required>
                    @error('speakText') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="questionEmoji" class="field-label">Question emoji / letter <span class="font-normal text-muted">(optional)</span></label>
                        <input id="questionEmoji" type="text" wire:model.live.debounce.300ms="questionEmoji" class="field-input text-xl" placeholder="🐜  or  g  or  🍎🍎🍎">
                        <p class="field-help">For counting questions, repeat the emoji once per item.</p>
                        @error('questionEmoji') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    @unless ($isChoice)
                        <div>
                            <label for="correctNumber" class="field-label">Correct number</label>
                            <input id="correctNumber" type="number" min="0" max="99" wire:model.live="correctNumber" class="field-input">
                            @error('correctNumber') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endunless
                </div>

                {{-- Media --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <span class="field-label">Question image</span>
                        @if ($imagePreview)
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $imagePreview }}" alt="Question image preview" class="size-20 rounded-xl border border-line object-cover">
                                <button type="button" wire:click="removeImage" class="btn btn-danger btn-sm">Remove</button>
                            </div>
                        @endif
                        <input type="file" wire:model="image" accept="{{ $imageAccept }}" class="field-input text-xs" aria-label="Upload question image">
                        <div wire:loading wire:target="image" class="field-help">Uploading…</div>
                        @error('image') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="field-label">Recorded audio <span class="font-normal text-muted">(replaces text-to-speech)</span></span>
                        @if ($audio || $audioPath)
                            <div class="mb-2 flex items-center gap-3">
                                @if ($audioPath && ! $audio)
                                    <audio controls src="{{ App\Support\Media::url($audioPath) }}" class="h-9 max-w-52"></audio>
                                @else
                                    <span class="badge bg-sky/20 text-ink">🔊 {{ $audio->getClientOriginalName() }}</span>
                                @endif
                                <button type="button" wire:click="removeAudio" class="btn btn-danger btn-sm">Remove</button>
                            </div>
                        @endif
                        <input type="file" wire:model="audio" accept="{{ $audioAccept }}" class="field-input text-xs" aria-label="Upload question audio">
                        <div wire:loading wire:target="audio" class="field-help">Uploading…</div>
                        @error('audio') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Options --}}
            @if ($isChoice)
                <section class="card p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-lg">Answer cards</h2>
                        <button type="button" wire:click="addOption" class="btn btn-secondary btn-sm"
                                @disabled(count($options) >= App\Livewire\Admin\Questions\QuestionForm::MAX_OPTIONS)>+ Add card</button>
                    </div>
                    @error('options') <p class="field-error mb-3">{{ $message }}</p> @enderror
                    @error('correctOption') <p class="field-error mb-3">{{ $message }}</p> @enderror

                    <div class="space-y-3">
                        @foreach ($options as $index => $option)
                            @php
                                $upload = $optionImages[$index] ?? null;
                                $optionPreview = $upload && method_exists($upload, 'isPreviewable') && $upload->isPreviewable()
                                    ? $upload->temporaryUrl()
                                    : App\Support\Media::url($option['image_path']);
                            @endphp
                            <div wire:key="option-{{ $index }}" @class([
                                'rounded-2xl border-2 p-4',
                                'border-sky bg-mint/10' => $correctOption === $index,
                                'border-line' => $correctOption !== $index,
                            ])>
                                <div class="flex flex-wrap items-start gap-3">
                                    <label class="flex shrink-0 cursor-pointer items-center gap-2 pt-2.5 text-xs font-extrabold text-muted">
                                        <input type="radio" wire:model.live="correctOption" value="{{ $index }}" class="size-4 border-line text-sky focus:ring-sky">
                                        Correct
                                    </label>

                                    <div class="w-20">
                                        <label class="sr-only" for="option-emoji-{{ $index }}">Emoji</label>
                                        <input id="option-emoji-{{ $index }}" type="text" wire:model.live.debounce.300ms="options.{{ $index }}.emoji" class="field-input text-center text-xl" placeholder="🐱">
                                    </div>

                                    <div class="min-w-40 flex-1">
                                        <label class="sr-only" for="option-label-{{ $index }}">Label</label>
                                        <input id="option-label-{{ $index }}" type="text" wire:model.live.debounce.300ms="options.{{ $index }}.label" class="field-input" placeholder="Label, e.g. Cat">
                                        @error("options.$index.label") <p class="field-error">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="sr-only" for="option-colour-{{ $index }}">Card colour</label>
                                        <input id="option-colour-{{ $index }}" type="color" wire:model.live="options.{{ $index }}.bg_color" class="h-10 w-12 cursor-pointer rounded-lg border border-line bg-white p-1">
                                    </div>

                                    <button type="button" wire:click="removeOption({{ $index }})" class="btn btn-danger btn-sm mt-1.5"
                                            @disabled(count($options) <= App\Livewire\Admin\Questions\QuestionForm::MIN_OPTIONS) aria-label="Remove card">✕</button>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-3 pl-0 sm:pl-[5.5rem]">
                                    @if ($optionPreview)
                                        <img src="{{ $optionPreview }}" alt="" class="size-12 rounded-lg border border-line object-cover">
                                        <button type="button" wire:click="removeOptionImage({{ $index }})" class="btn btn-secondary btn-sm">Remove image</button>
                                    @endif
                                    <input type="file" wire:model="optionImages.{{ $index }}" accept="{{ $imageAccept }}" class="text-xs text-muted" aria-label="Upload answer image">
                                    <span wire:loading wire:target="optionImages.{{ $index }}" class="text-xs text-muted">Uploading…</span>
                                    @error("optionImages.$index") <p class="field-error w-full">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Preview + save --}}
        <div class="space-y-6">
            <div class="card sticky top-6 overflow-hidden">
                <div class="border-b border-line px-5 py-3">
                    <h2 class="text-xs font-extrabold tracking-wide text-muted uppercase">Child's view preview</h2>
                </div>
                <div class="bg-gradient-to-br from-sun/30 via-white to-sky/20 p-5">
                    <div class="mb-4 rounded-2xl bg-white p-4 text-center shadow-sm">
                        @if ($imagePreview)
                            <img src="{{ $imagePreview }}" alt="" class="mx-auto mb-2 max-h-28 rounded-xl object-contain">
                        @endif
                        @if ($questionEmoji !== '')
                            <div class="mb-1 text-4xl leading-tight break-all">{{ $questionEmoji }}</div>
                        @endif
                        <p class="font-display text-xl text-ink">{{ $hint !== '' ? $hint : ($speakText !== '' ? $speakText : 'Your question') }}</p>
                        <p class="mt-1 text-xs text-muted">🔊 “{{ $speakText !== '' ? $speakText : '…' }}”</p>
                    </div>

                    @if ($isChoice)
                        <div @class(['grid gap-3', 'grid-cols-3' => count($options) <= 3, 'grid-cols-2' => count($options) > 3])>
                            @foreach ($options as $index => $option)
                                <div wire:key="preview-{{ $index }}" style="background: {{ $option['bg_color'] }}"
                                     @class(['flex flex-col items-center justify-center rounded-2xl p-3 text-center shadow-sm', 'ring-2 ring-sky' => $correctOption === $index])>
                                    <span class="text-3xl">{{ $option['emoji'] !== '' ? $option['emoji'] : '❓' }}</span>
                                    <span class="mt-1 text-xs font-extrabold text-ink">{{ $option['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['1','2','3','4','5','6','7','8','9','⌫','0','✓'] as $key)
                                <div class="rounded-xl bg-white py-2 text-center font-display text-lg shadow-sm">{{ $key }}</div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-center text-xs text-muted">Correct answer: <strong>{{ $correctNumber ?? '?' }}</strong></p>
                    @endif
                </div>

                <div class="border-t border-line p-5">
                    <button type="submit" class="btn btn-primary w-full py-3" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $question ? 'Save question' : 'Create question' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
