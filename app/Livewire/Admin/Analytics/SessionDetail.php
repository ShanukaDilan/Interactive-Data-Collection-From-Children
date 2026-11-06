<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\QuizSession;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\AnswerDataset;
use App\Services\Analytics\Metrics;
use App\Services\Analytics\PatternDetector;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Question-by-question timeline of one session: every tap, its timing and position, and replays.
 */
#[Layout('layouts::admin')]
class SessionDetail extends Component
{
    public QuizSession $session;

    public function mount(QuizSession $session): void
    {
        $this->authorize('view', $session);
        $this->session = $session->load(['study:id,name', 'participant:id,code,study_id,age', 'conductor:id,name']);
    }

    public function render(): View
    {
        $answers = AnswerDataset::forStudies([$this->session->study_id], new AnalyticsFilters(sessionUuid: $this->session->uuid))->answers;

        return view('livewire.admin.analytics.session-detail', [
            'answers' => $answers,
            'summary' => Metrics::summarize($answers),
            'insights' => (new PatternDetector)->forChild($answers),
            'longestMs' => max(1, (int) $answers->max(fn ($a) => max($a->responseTimeMs, $a->promptEndedMs ?? 0))),
        ])->title('Session timeline');
    }
}
