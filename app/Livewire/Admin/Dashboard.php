<?php

namespace App\Livewire\Admin;

use App\Models\Participant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        $studyIds = Auth::user()->accessibleStudies()->pluck('id');

        $responses = QuizResponse::query()
            ->whereHas('quizSession', fn ($query) => $query->whereIn('study_id', $studyIds));

        $responseCount = (clone $responses)->count();

        return view('livewire.admin.dashboard', [
            'studyCount' => $studyIds->count(),
            'participantCount' => Participant::whereIn('study_id', $studyIds)->count(),
            'sessionCount' => QuizSession::whereIn('study_id', $studyIds)->count(),
            'responseCount' => $responseCount,
            'accuracy' => $responseCount > 0 ? round((clone $responses)->where('is_correct', true)->count() / $responseCount * 100) : null,
            'averageResponseSeconds' => $responseCount > 0 ? round((clone $responses)->avg('response_time_ms') / 1000, 1) : null,
            'recentSessions' => QuizSession::query()
                ->whereIn('study_id', $studyIds)
                ->with(['study:id,name', 'participant:id,code'])
                ->withCount(['responses', 'responses as correct_count' => fn ($query) => $query->where('is_correct', true)])
                ->latest('started_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
