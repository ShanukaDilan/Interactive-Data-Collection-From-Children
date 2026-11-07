<?php

namespace App\Livewire\Admin\Analytics;

use App\Models\Participant;
use App\Models\ReportLink;
use App\Services\Analytics\ChildReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class ParticipantReport extends Component
{
    public Participant $participant;

    public int $linkDays = 30;

    /** Plain share URL, shown once right after creating it. */
    public ?string $newLinkUrl = null;

    public function mount(Participant $participant): void
    {
        $this->authorize('view', $participant);
        $this->participant = $participant;
    }

    public function createLink(): void
    {
        $this->authorize('update', $this->participant);
        $this->validate(['linkDays' => ['required', 'integer', Rule::in([7, 30, 90])]]);

        [$link, $token] = ReportLink::issue($this->participant, Auth::user(), $this->linkDays);
        $this->newLinkUrl = route('parent-report.show', $token);

        activity('report_link')->causedBy(Auth::user())->performedOn($link)
            ->withProperties(['participant' => $this->participant->code, 'days' => $this->linkDays])
            ->log('created');
    }

    public function revokeLink(int $linkId): void
    {
        $this->authorize('update', $this->participant);

        $link = $this->participant->reportLinks()->findOrFail($linkId);
        $link->update(['revoked_at' => now()]);
        $this->newLinkUrl = null;

        activity('report_link')->causedBy(Auth::user())->performedOn($link)->log('revoked');
        $this->dispatch('toast', message: 'Link revoked. It no longer works.');
    }

    public function render(): View
    {
        $withPeers = Gate::allows('view-research-analytics');

        return view('livewire.admin.analytics.participant-report', [
            'report' => ChildReport::build($this->participant->load('study'), $withPeers),
            'withPeers' => $withPeers,
            'links' => $this->participant->reportLinks()->active()->with('creator:id,name')->latest()->get(),
        ])->title('Report · '.$this->participant->code);
    }
}
