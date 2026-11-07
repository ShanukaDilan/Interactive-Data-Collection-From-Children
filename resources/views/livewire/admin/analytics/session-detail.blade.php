@php
    use App\Services\Analytics\Metrics;
    use App\Services\Analytics\PatternDetector;
@endphp

<div>
    <x-admin.page-header title="Session timeline"
                         :subtitle="collect([
                             $session->study->name,
                             $session->participant?->code ?? 'Anonymous',
                             $session->child_age ? 'age '.$session->child_age : null,
                             $session->quiz_mode->label(),
                             $session->started_at?->format('j M Y, H:i'),
                             $session->conductor ? 'run by '.$session->conductor->name : null,
                         ])->filter()->implode(' · ')">
        <x-slot:actions>
            @if ($session->participant)
                <a href="{{ route('admin.participants.report', $session->participant) }}" class="btn btn-secondary" wire:navigate>Child report</a>
            @endif
            <a href="{{ route('admin.analytics') }}" class="btn btn-secondary" wire:navigate>← Analytics</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-admin.stat label="Questions" :value="$summary['answers']" icon="✋" tone="peach"
                      :hint="$session->completed_at ? 'completed' : 'ended early or still syncing'" />
        <x-admin.stat label="Right first try" :value="Metrics::percent($summary['first_try_rate'])" icon="🎯" tone="mint" />
        <x-admin.stat label="Time to first tap" :value="Metrics::seconds($summary['median_rt_ms'])" icon="⏱️" tone="coral" hint="median" />
        <x-admin.stat label="Replayed question" :value="Metrics::percent($summary['replay_rate'])" icon="🔊" tone="lav" hint="share of questions" />
    </div>

    <section class="card mt-6 p-5">
        <h2 class="font-display mb-3 text-lg">Patterns in this session</h2>
        <x-admin.insight-list :insights="$insights" />
    </section>

    <section class="card mt-6 p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg">Question by question</h2>
            <div class="flex flex-wrap gap-4 text-xs text-muted">
                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-mint ring-2 ring-white"></span> correct tap</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-coral ring-2 ring-white"></span> wrong tap</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 border-l-2 border-dashed border-lav"></span> question finished speaking</span>
            </div>
        </div>

        <ol class="space-y-4">
            @forelse ($answers as $index => $answer)
                <li wire:key="answer-{{ $answer->id }}" class="rounded-2xl border border-line p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-extrabold text-muted">Q{{ ($answer->position ?? $index) + 1 }} · {{ $answer->category }}</p>
                            <p class="font-bold">{{ $answer->questionText }}</p>
                            <p class="text-xs text-muted">
                                Answer: <strong>{{ $answer->correctLabel }}</strong>
                                @if ($answer->optionOrder) · cards shown: {{ implode(' · ', $answer->optionOrder) }} @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @if ($answer->firstTryCorrect())
                                <span class="badge bg-mint/50 text-ink">✓ first try</span>
                            @elseif ($answer->mode === 'free')
                                <span class="badge bg-canvas text-muted">chose {{ $answer->selectedLabel }}</span>
                            @else
                                <span class="badge bg-peach/30 text-ink">{{ $answer->attempts }} taps</span>
                            @endif
                            @if ($answer->replayCount > 0)
                                <span class="badge bg-lav/30 text-ink">🔊 × {{ $answer->replayCount }}</span>
                            @endif
                            @if ($answer->firstTap()['during_prompt'] ?? false)
                                <span class="badge bg-sky/20 text-ink">tapped during question</span>
                            @endif
                        </div>
                    </div>

                    @if ($answer->hasTaps())
                        {{-- Timeline --}}
                        <div class="relative mt-4 h-10 rounded-full bg-canvas" aria-hidden="true">
                            @if ($answer->promptEndedMs !== null)
                                <div class="absolute inset-y-0 border-l-2 border-dashed border-lav"
                                     style="left: {{ min(100, $answer->promptEndedMs / $longestMs * 100) }}%"></div>
                            @endif
                            @foreach ($answer->taps as $tap)
                                <div @class(['absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white', 'bg-mint' => $tap['correct'], 'bg-coral' => ! $tap['correct']])
                                     style="left: {{ min(100, $tap['elapsed_ms'] / $longestMs * 100) }}%"
                                     title="{{ $tap['label'] }} at {{ Metrics::seconds($tap['elapsed_ms']) }}"></div>
                            @endforeach
                        </div>

                        <ol class="mt-3 flex flex-wrap gap-2 text-xs">
                            @foreach ($answer->taps as $tapIndex => $tap)
                                <li @class(['rounded-lg px-2 py-1', 'bg-mint/30' => $tap['correct'], 'bg-coral/10' => ! $tap['correct']])>
                                    {{ $tapIndex + 1 }}. <strong>{{ $tap['label'] }}</strong>
                                    @if ($tap['position'] !== null && $answer->optionCount())
                                        ({{ PatternDetector::positionName($tap['position'], $answer->optionCount()) }})
                                    @endif
                                    · {{ Metrics::seconds($tap['elapsed_ms']) }}
                                    {{ $tap['correct'] ? '✓' : '✗' }}
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="mt-3 text-xs text-muted">Chose <strong>{{ $answer->selectedLabel }}</strong> after {{ Metrics::seconds($answer->responseTimeMs) }} ({{ $answer->attempts }} {{ Str::plural('tap', $answer->attempts) }}). Detailed tap timing was not recorded for this answer.</p>
                    @endif
                </li>
            @empty
                <li><x-admin.empty-state icon="🎈" title="No answers in this session" /></li>
            @endforelse
        </ol>
    </section>
</div>
