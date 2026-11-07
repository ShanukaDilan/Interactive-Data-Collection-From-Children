<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncQuizDataRequest;
use App\Models\Study;
use App\Services\PlayConfigBuilder;
use App\Services\QuizSyncService;
use App\Services\StudyBundleBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Endpoints used by a registered classroom device (Bearer device token), so a tablet can
 * refresh its studies and sync answers without an active browser session.
 */
class DevicePlayController extends Controller
{
    public function config(Request $request, PlayConfigBuilder $config): JsonResponse
    {
        $device = $request->attributes->get('device');

        return response()->json([
            'studies' => $config->studies($request->user()),
            'device' => ['id' => $device->id, 'name' => $device->name],
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    public function bundle(Request $request, Study $study, StudyBundleBuilder $builder): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $study);

        abort_unless($study->is_active, 404);

        return response()->json($builder->build($study));
    }

    public function sync(SyncQuizDataRequest $request, QuizSyncService $sync): JsonResponse
    {
        return response()->json($sync->sync(
            $request->user(),
            $request->validated('sessions'),
            $request->attributes->get('device'),
        ));
    }
}
