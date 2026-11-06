<?php

namespace App\Livewire\Admin\Participants;

use App\Models\Participant;
use App\Models\Study;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Participants')]
class ParticipantIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'study', except: null)]
    public ?int $studyFilter = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $studyId = null;

    public ?int $age = null;

    public string $displayName = '';

    public bool $consent = false;

    public string $notes = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'studyFilter'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->studyId = $this->studyFilter ?? Auth::user()->accessibleStudies()->value('id');
        $this->showForm = true;
    }

    public function edit(Participant $participant): void
    {
        $this->authorize('update', $participant);

        $this->resetForm();
        $this->fill([
            'editingId' => $participant->id,
            'studyId' => $participant->study_id,
            'age' => $participant->age,
            'displayName' => (string) $participant->display_name,
            'consent' => $participant->consent_recorded_at !== null,
            'notes' => (string) $participant->notes,
            'showForm' => true,
        ]);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'studyId' => ['required', 'integer', Rule::in(Auth::user()->accessibleStudies()->pluck('id'))],
            'age' => ['nullable', 'integer', 'between:2,12'],
            'displayName' => ['nullable', 'string', 'max:120'],
            'consent' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $participant = $this->editingId ? Participant::findOrFail($this->editingId) : new Participant;
        $study = Study::findOrFail($validated['studyId']);

        $participant->exists
            ? $this->authorize('update', $participant)
            : $this->authorize('create', [Participant::class, $study]);

        $participant->fill([
            'study_id' => $study->id,
            'age' => $validated['age'],
            'display_name' => $validated['displayName'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

        if ($validated['consent'] && $participant->consent_recorded_at === null) {
            $participant->consent_recorded_at = now();
            $participant->consent_by = Auth::id();
        } elseif (! $validated['consent']) {
            $participant->consent_recorded_at = null;
            $participant->consent_by = null;
        }

        $participant->save();

        $this->dispatch('toast', message: $this->editingId ? 'Participant saved.' : "Participant {$participant->code} added.");
        $this->resetForm();
    }

    public function delete(Participant $participant): void
    {
        $this->authorize('delete', $participant);

        $code = $participant->code;
        $participant->delete();

        $this->dispatch('toast', message: "Participant {$code} removed. Their past sessions are kept without a link.");
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'studyId', 'age', 'displayName', 'consent', 'notes']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $studies = Auth::user()->accessibleStudies()->orderBy('name')->get(['id', 'name']);

        $participants = Participant::query()
            ->whereIn('study_id', $studies->pluck('id'))
            ->when($this->studyFilter, fn ($query) => $query->where('study_id', $this->studyFilter))
            ->when($this->search !== '', fn ($query) => $query->where('code', 'like', '%'.$this->search.'%'))
            ->with('study:id,name')
            ->withCount('quizSessions')
            ->latest()
            ->paginate(20);

        return view('livewire.admin.participants.participant-index', [
            'participants' => $participants,
            'studies' => $studies,
        ]);
    }
}
