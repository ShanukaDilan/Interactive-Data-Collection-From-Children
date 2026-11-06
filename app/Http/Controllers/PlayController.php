<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncQuizDataRequest;
use App\Models\Study;
use App\Services\PlayConfigBuilder;
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
    public function show(Request $request, PlayConfigBuilder $config): View
    {
        return view('play.index', ['config' => $config->forPage($request->user())]);
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
