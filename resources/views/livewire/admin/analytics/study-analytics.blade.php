@php
    use App\Services\Analytics\Metrics;
    $palette = ['coral' => '#FF6B6B', 'sky' => '#4ECDC4', 'lav' => '#A68BFF', 'peach' => '#FFB347', 'mint' => '#6BCB9B'];
@endphp

<div>
    <x-admin.page-header title="Analytics" subtitle="How children are answering: accuracy, speed, and behaviour patterns.">
        @if ($canExport)
            <x-slot:actions>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="btn btn-secondary" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                        ⬇️ Export data
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right
                         class="absolute right-0 z-20 mt-2 w-80 rounded-2xl border border-line bg-white p-2 shadow-xl">
                        <p class="px-3 pb-2 pt-1 text-xs text-muted">Uses the filters below. Children appear only by code.</p>
                        @foreach ([
                            ['workbook.xlsx', '📗 Excel workbook', 'Answers, every tap, codebook and filter notes in one file'],
                            ['responses.csv', '📄 Answers (CSV)', 'One row per answer — for R, SPSS, Python'],
                            ['taps.csv', '👆 Taps (CSV)', 'One row per tap, including wrong tries'],
                            ['codebook.csv', '📘 Codebook (CSV)', 'What every column means'],
                            ['legacy.csv', '🗂️ Original app format (CSV)', 'Same 13 columns as the old download'],
                        ] as [$file, $label, $hint])
                            <a href="{{ route('admin.export', ['file' => $file, ...$exportQuery]) }}" @click="open = false"
                               class="block rounded-xl px-3 py-2 hover:bg-sun/15 focus:bg-sun/15 focus:outline-none">
                                <span class="block text-sm font-bold text-ink">{{ $label }}</span>
                                <span class="block text-xs text-muted">{{ $hint }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    {{-- Filters --}}
    <div class="card mb-6 grid grid-cols-2 gap-3 p-4 md:grid-cols-3 xl:grid-cols-7">
        <select wire:model.live="study" class="field-input col-span-2 md:col-span-1 xl:col-span-2" aria-label="Study">
            <option value="">All my studies</option>
            @foreach ($studies as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="category" class="field-input" aria-label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $name)
                <option value="{{ $name }}">{{ $name }}</option>
            @endforeach
        </select>
        <select wire:model.live="age" class="field-input" aria-label="Age">
            <option value="">All ages</option>
            @foreach (range(2, 12) as $value)
                <option value="{{ $value }}">Age {{ $value }}</option>
            @endforeach
        </select>
        <select wire:model.live="mode" class="field-input" aria-label="Mode">
            <option value="">Both modes</option>
            <option value="correct">Find the Answer</option>
            <option value="free">Free Choice</option>
        </select>
        <input type="date" wire:model.live="from" class="field-input" aria-label="From date">
        <div class="flex gap-2">
            <input type="date" wire:model.live="to" class="field-input" aria-label="To date">
            <button type="button" wire:click="resetFilters" class="btn btn-secondary shrink-0" title="Clear filters" aria-label="Clear filters">✕</button>
        </div>
    </div>

    <div wire:loading.delay class="mb-4 text-sm font-bold text-muted">Updating…</div>

    @if ($truncated)
        <div class="mb-4 rounded-2xl border border-peach/50 bg-peach/10 px-4 py-3 text-sm">Showing the first {{ number_format(App\Services\Analytics\AnswerDataset::MAX_ANSWERS) }} answers. Narrow the filters for complete figures.</div>
    @endif

    @if ($summary['answers'] === 0)
        <div class="card"><x-admin.empty-state icon="📊" title="No answers match these filters" message="Answers appear here once children play quizzes in your studies." /></div>
    @else
        {{-- KPIs --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 2xl:grid-cols-6">
            <x-admin.stat label="Children" :value="$summary['children']" icon="🧒" tone="sun" />
            <x-admin.stat label="Sessions" :value="$summary['sessions']" icon="🎮" tone="sky" />
            <x-admin.stat label="Answers" :value="number_format($summary['answers'])" icon="✋" tone="peach" />
            <x-admin.stat label="Right first try" :value="Metrics::percent($summary['first_try_rate'])" icon="🎯" tone="mint" :hint="'avg '.$summary['mean_attempts'].' taps / question'" />
            <x-admin.stat label="Time to first tap" :value="Metrics::seconds($summary['median_rt_ms'])" icon="⏱️" tone="coral" hint="median" />
            <x-admin.stat label="After question ends" :value="Metrics::seconds($summary['median_reaction_ms'])" icon="🗣️" tone="lav" hint="median reaction" />
        </div>

        <p class="mt-3 text-xs text-muted">
            <strong>Right first try</strong> = correct on the first tap in “Find the Answer” (children always reach the right card eventually, so this is the meaningful score)
            @if ($mixedModes) or picked the target answer in “Free Choice”@endif.
            Times are medians, because a few long pauses would distort an average.
        </p>

        {{-- Charts --}}
        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section class="card p-5">
                <h2 class="font-display mb-3 text-lg">Right first try by category</h2>
                <x-admin.chart type="bar" horizontal format="percent" :max="100" label="Accuracy by category"
                               :labels="$charts['category_accuracy']['labels']"
                               :datasets="[['label' => 'Right first try', 'data' => $charts['category_accuracy']['values'], 'color' => $palette['sky']]]"
                               :height="max(12, count($charts['category_accuracy']['labels']) * 2.4).'rem'" />
            </section>
            <section class="card p-5">
                <h2 class="font-display mb-3 text-lg">Time to first tap by category</h2>
                <x-admin.chart type="bar" horizontal format="seconds" label="Median response time by category"
                               :labels="$charts['category_time']['labels']"
                               :datasets="[['label' => 'Median', 'data' => $charts['category_time']['values'], 'color' => $palette['peach']]]"
                               :height="max(12, count($charts['category_time']['labels']) * 2.4).'rem'" />
            </section>
            <section class="card p-5">
                <h2 class="font-display mb-3 text-lg">Right first try by age</h2>
                <x-admin.chart type="bar" format="percent" :max="100" label="Accuracy by age"
                               :labels="$charts['age_accuracy']['labels']"
                               :datasets="[['label' => 'Right first try', 'data' => $charts['age_accuracy']['values'], 'color' => $palette['lav']]]" />
            </section>
            <section class="card p-5">
                <h2 class="font-display mb-1 text-lg">Through the session</h2>
                <p class="mb-3 text-xs text-muted">Accuracy and speed by question number — a drop at the end can mean tiredness.</p>
                <x-admin.chart type="line" format="percent" :max="100" label="Accuracy by question position"
                               :labels="$charts['position_accuracy']['labels']"
                               :datasets="[['label' => 'Right first try', 'data' => $charts['position_accuracy']['values'], 'color' => $palette['coral']]]" />
            </section>
        </div>

        {{-- Patterns --}}
        <section class="card mt-6 p-5">
            <h2 class="font-display mb-3 text-lg">Behaviour patterns</h2>
            <x-admin.insight-list :insights="$insights" />
        </section>

        {{-- Children --}}
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h2 class="font-display text-lg">Children</h2>
                <p class="text-xs text-muted">Icons show patterns worth a look: 🎯 position bias · 🔁 repeating choices · ⚡ fast tapping · 😴 tiring.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Child</th>
                        <th>Age</th>
                        <th class="text-right">Sessions</th>
                        <th class="text-right">Answers</th>
                        <th class="text-right">Right first try</th>
                        <th class="text-right">Median time</th>
                        <th>Patterns</th>
                        <th class="text-right"><span class="sr-only">Open</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($children as $row)
                        <tr wire:key="child-{{ $row['participant']?->id ?? $row['session_uuid'] }}">
                            <td>
                                @if ($row['participant'])
                                    <span class="badge bg-sun/50 font-mono text-ink">{{ $row['participant']->code }}</span>
                                    @if ($row['participant']->display_name) <span class="ml-1 text-sm">{{ $row['participant']->display_name }}</span> @endif
                                @else
                                    <span class="text-muted">Anonymous session</span>
                                @endif
                            </td>
                            <td>{{ $row['age'] ?? '—' }}</td>
                            <td class="text-right">{{ $row['sessions'] }}</td>
                            <td class="text-right">{{ $row['answers'] }}</td>
                            <td class="text-right font-bold">{{ Metrics::percent($row['first_try_rate']) }}</td>
                            <td class="text-right">{{ Metrics::seconds($row['median_rt_ms']) }}</td>
                            <td class="text-lg" title="Patterns">{{ implode(' ', $row['flags']) }}</td>
                            <td class="text-right">
                                @if ($row['participant'])
                                    <a href="{{ route('admin.participants.report', $row['participant']) }}" class="btn btn-secondary btn-sm" wire:navigate>Report</a>
                                @elseif ($row['session_uuid'])
                                    <a href="{{ route('admin.sessions.show', $row['session_uuid']) }}" class="btn btn-secondary btn-sm" wire:navigate>Session</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Item analysis (researchers) --}}
        @if ($showResearch)
            <section class="card mt-6 overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="font-display text-lg">Question quality <span class="badge ml-1 bg-lav/30 align-middle text-ink">Researchers</span></h2>
                    <p class="text-xs text-muted">
                        <strong>Difficulty</strong> = share right first try. <strong>Discrimination</strong> = how much better the strongest third of children did than the weakest third (higher is better; under 0.1 means the question does not separate them).
                        <strong>Wrong picks</strong> = which incorrect cards attracted taps.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Question</th>
                            <th class="text-right">Answers</th>
                            <th class="text-right">Difficulty</th>
                            <th class="text-right">Discrimination</th>
                            <th class="text-right">Avg taps</th>
                            <th class="text-right">Median time</th>
                            <th>Wrong picks</th>
                            <th>Flags</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            <tr wire:key="item-{{ $item['key'] }}">
                                <td class="max-w-xs">
                                    <p class="truncate font-bold" title="{{ $item['text'] }}">{{ $item['text'] }}</p>
                                    <p class="text-xs text-muted">{{ $item['category'] }} · answer: {{ $item['correct_label'] }}</p>
                                </td>
                                <td class="text-right">{{ $item['answers'] }}</td>
                                <td class="text-right font-bold">{{ Metrics::percent($item['difficulty']) }}</td>
                                <td class="text-right">{{ $item['discrimination'] === null ? '—' : number_format($item['discrimination'], 2) }}</td>
                                <td class="text-right">{{ $item['mean_attempts'] }}</td>
                                <td class="text-right">{{ Metrics::seconds($item['median_rt_ms']) }}</td>
                                <td class="text-xs">
                                    @forelse ($item['distractors'] as $label => $count)
                                        <span class="badge mb-1 whitespace-nowrap bg-coral/10 text-ink">{{ $label }} × {{ $count }}</span>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                    @if ($item['never_chosen'])
                                        <span class="block text-muted">Never picked: {{ implode(', ', $item['never_chosen']) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @foreach ($item['flags'] as $flag)
                                        <span class="badge mb-1 whitespace-nowrap bg-peach/25 text-ink">{{ $flag }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
