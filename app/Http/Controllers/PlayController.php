<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncQuizDataRequest;
use App\Models\Participant;
use App\Models\Study;
use App\Services\QuizSyncService;
use App\Services\StudyBundleBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The child-facing quiz: a teacher picks study/child on the setup screen, then hands over the device.
 */
class PlayController extends Controller
{
    public function show(Request $request): View
    {
        $studies = $request->user()->accessibleStudies()
            ->where('is_active', true)
            ->withCount(['questions' => fn ($query) => $query->where('is_archived', false)])
            ->with(['participants' => fn ($query) => $query->orderBy('code')])
            ->orderBy('name')
            ->get()
            ->map(fn (Study $study) => [
                'id' => $study->id,
                'name' => $study->name,
                'age_min' => $study->age_min,
                'age_max' => $study->age_max,
                'default_quiz_mode' => $study->default_quiz_mode->value,
                'question_count' => $study->questions_count,
                'bundle_url' => route('play.bundle', $study),
                'participants' => $study->participants->map(fn (Participant $participant) => [
                    'id' => $participant->id,
                    'code' => $participant->code,
                    'age' => $participant->age,
                    'name' => $participant->display_name,
                ])->values(),
            ])
            ->values();

        return view('play.index', [
            'config' => [
                'studies' => $studies,
                'syncUrl' => route('play.sync'),
            ],
        ]);
    }

    public function bundle(Study $study, StudyBundleBuilder $builder): JsonResponse
    {
        Gate::authorize('view', $study);

        abort_unless($study->is_active, 404);

        return response()->json($builder->build($study));
    }

    public function sync(SyncQuizDataRequest $request, QuizSyncService $sync): JsonResponse
    {
        return response()->json($sync->sync($request->user(), $request->validated('sessions')));
    }
}
