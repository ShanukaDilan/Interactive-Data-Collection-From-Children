<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device API: requires a Bearer token belonging to an active (not revoked) registered device.
 * Browser-session authentication is deliberately not accepted here.
 */
class EnsureActiveDevice
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $device = Device::forToken($token);

        if ($device === null || ! $token->can(Device::ABILITY)) {
            return response()->json(['message' => 'This device is not registered or has been revoked.'], 401);
        }

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
