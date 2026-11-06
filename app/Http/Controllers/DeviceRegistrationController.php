<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers the current browser as a classroom device (called from the quiz setup screen
 * by a signed-in teacher). The plain token is returned once and stored on the device.
 */
class DeviceRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        [$device, $token] = Device::register($request->user(), $validated['name'], $request->userAgent());

        activity('device')->causedBy($request->user())->performedOn($device)->log('registered');

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name],
            'token' => $token,
        ], 201);
    }
}
