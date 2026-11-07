<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\Participant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Answer;
use App\Services\Analytics\AnswerDataset;
use App\Services\Analytics\ItemAnalyzer;
use App\Services\Analytics\Metrics;
use App\Services\Analytics\PatternDetector;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Analytics')]
class StudyAnalytics extends Component
{
    #[Url(except: '')]
    public string $study = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $age = '';

    #[Url(except: '')]
    public string $mode = '';

    #[Url(except: '')]
    public string $category = '';

    public function resetFilters(): void
    {
        $this->reset(['study', 'from', 'to', 'age', 'mode', 'category']);
    }

    public function render(): View
    {
        $user = Auth::user();
        $filters = AnalyticsFilters::fromArray($this->only(['study', 'from', 'to', 'age', 'mode', 'category']));
        $dataset = AnswerDataset::load($user, $filters);
        $answers = $dataset->answers;
        $showResearch = Gate::allows('view-research-analytics');

        $byCategory = Metrics::breakdown($answers, fn (Answer $a) => $a->category);
        $byAge = Metrics::breakdown($answers, fn (Answer $a) => $a->childAge !== null ? sprintf('%02d', $a->childAge) : null);
        $byPosition = Metrics::breakdown($answers, fn (Answer $a) => $a->position !== null ? sprintf('%03d', $a->position + 1) : null)
            ->filter(fn (array $stats) => $stats['answers'] >= 2);

        return view('livewire.admin.analytics.study-analytics', [
            'studies' => $user->accessibleStudies()->orderBy('name')->get(['id', 'name']),
            'categories' => QuizResponse::query()
                ->whereHas('quizSession', fn ($q) => $q->whereIn('study_id', $user->accessibleStudies()->select('id')))
                ->distinct()->orderBy('category')->pluck('category')->filter(),
            'truncated' => $dataset->truncated,
            'summary' => Metrics::summarize($answers),
            'charts' => [
                'category_accuracy' => self::series($byCategory, 'first_try_rate', 100),
                'category_time' => self::series($byCategory, 'median_rt_ms', 0.001),
                'age_accuracy' => self::series($byAge, 'first_try_rate', 100, fn (string $key) => 'Age '.(int) $key),
                'position_accuracy' => self::series($byPosition, 'first_try_rate', 100, fn (string $key) => 'Q'.(int) $key),
            ],
            'insights' => (new PatternDetector)->forGroup($answers),
            'children' => $this->childRows($answers),
            'items' => $showResearch ? (new ItemAnalyzer)->analyze($answers) : [],
            'showResearch' => $showResearch,
            'canExport' => Gate::allows('export-responses'),
            'exportQuery' => array_filter($this->only(['study', 'from', 'to', 'age', 'mode', 'category']), 'filled'),
            'mixedModes' => $answers->pluck('mode')->unique()->count() > 1,
        ]);
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $breakdown
     * @param  (callable(string): string)|null  $label
     * @return array{labels: list<string>, values: list<float|null>}
     */
    public static function series(Collection $breakdown, string $metric, float $scale, ?callable $label = null): array
    {
        return [
            'labels' => $breakdown->keys()->map(fn ($key) => $label ? $label((string) $key) : (string) $key)->values()->all(),
            'values' => $breakdown->map(fn (array $stats) => $stats[$metric] === null ? null : round($stats[$metric] * $scale, 1))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Answer>  $answers
     * @return Collection<int, array<string, mixed>>
     */
    private function childRows(Collection $answers): Collection
    {
        $groups = $answers->groupBy(fn (Answer $a) => $a->childKey());
        $participants = Participant::findMany($answers->pluck('participantId')->filter()->unique())->keyBy('id');
        $sessions = QuizSession::findMany($answers->whereNull('participantId')->pluck('sessionId')->unique(), ['id', 'uuid'])->keyBy('id');
        $detector = new PatternDetector;

        return $groups->map(function (Collection $childAnswers) use ($participants, $sessions, $detector): array {
            $first = $childAnswers->first();
            $participant = $first->participantId ? $participants->get($first->participantId) : null;

            return [
                'participant' => $participant,
                'session_uuid' => $participant ? null : $sessions->get($first->sessionId)?->uuid,
                'age' => $participant?->age ?? $first->childAge,
                'flags' => array_map(fn ($insight) => $insight->icon, array_filter(
                    [$detector->positionBias($childAnswers), $detector->perseveration($childAnswers), $detector->rapidGuessing($childAnswers), $detector->fatigue($childAnswers)],
                    fn ($insight) => $insight !== null && $insight->type !== 'warm_up',
                )),
                ...Metrics::summarize($childAnswers),
            ];
        })->sortBy('first_try_rate')->values();
    }
}
