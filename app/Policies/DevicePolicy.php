<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/**
 * People manage the devices they registered (admins pass via Gate::before).
 */
class DevicePolicy
{
    public function revoke(User $user, Device $device): bool
    {
        return $device->user_id === $user->id;
    }
}
