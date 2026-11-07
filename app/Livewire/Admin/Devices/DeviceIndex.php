<?php

namespace App\Livewire\Admin\Devices;

use App\Models\Device;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Classroom devices registered for offline use. Teachers see their own; admins see all.
 */
#[Layout('layouts::admin')]
#[Title('Devices')]
class DeviceIndex extends Component
{
    use WithPagination;

    #[Url(except: false)]
    public bool $showRevoked = false;

    public function revoke(Device $device): void
    {
        $this->authorize('revoke', $device);

        $device->revoke();
        activity('device')->causedBy(Auth::user())->performedOn($device)->log('revoked');

        $this->dispatch('toast', message: "“{$device->name}” can no longer sync. Answers already saved are kept.");
    }

    public function render(): View
    {
        $user = Auth::user();

        $devices = Device::query()
            ->unless($user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when(! $this->showRevoked, fn ($query) => $query->active())
            ->with('user:id,name')
            ->withCount('quizSessions')
            ->latest()
            ->paginate(20);

        return view('livewire.admin.devices.device-index', ['devices' => $devices]);
    }
}
