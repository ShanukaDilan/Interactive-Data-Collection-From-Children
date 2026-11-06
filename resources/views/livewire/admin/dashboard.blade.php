<div>
    <x-admin.page-header title="Hello, {{ Str::before(auth()->user()->name, ' ') }} 👋"
                         subtitle="Overview of the studies you can access." />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-admin.stat label="Studies" :value="$studyCount" icon="🧪" tone="lav" />
        <x-admin.stat label="Participants" :value="$participantCount" icon="🧒" tone="sun" />
        <x-admin.stat label="Quiz sessions" :value="$sessionCount" icon="🎮" tone="sky" />
        <x-admin.stat label="Answers recorded" :value="number_format($responseCount)" icon="✋" tone="peach" />
        <x-admin.stat label="Accuracy" :value="$accuracy !== null ? $accuracy.'%' : '—'" icon="🎯" tone="mint" />
        <x-admin.stat label="Avg response time" :value="$averageResponseSeconds !== null ? $averageResponseSeconds.' s' : '—'" icon="⏱️" tone="coral" />
    </div>

    <div class="card mt-8 overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="font-display text-lg">Recent sessions</h2>
        </div>

        @if ($recentSessions->isEmpty())
            <x-admin.empty-state icon="🎈" title="No sessions yet"
                                 message="Sessions appear here once a child plays a quiz on a registered device." />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Started</th>
                        <th>Study</th>
                        <th>Child</th>
                        <th>Age</th>
                        <th>Mode</th>
                        <th class="text-right">Score</th>
                        <th class="text-right"><span class="sr-only">Open</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($recentSessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td class="whitespace-nowrap text-muted">{{ $session->started_at?->diffForHumans() ?? '—' }}</td>
                            <td class="font-bold">{{ $session->study->name }}</td>
                            <td><span class="badge bg-sun/40">{{ $session->childIdentifier() ?: 'anonymous' }}</span></td>
                            <td>{{ $session->child_age ?? '—' }}</td>
                            <td>{{ $session->quiz_mode->label() }}</td>
                            <td class="text-right font-bold">{{ $session->correct_count }} / {{ $session->responses_count }}</td>
                            <td class="text-right"><a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-secondary btn-sm" wire:navigate>Timeline</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
