<?php

namespace App\Livewire\Admin\Studies;

use App\Models\Study;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Studies')]
class StudyIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(Study $study): void
    {
        $this->authorize('update', $study);

        $study->update(['is_active' => ! $study->is_active]);

        $this->dispatch('toast', message: $study->is_active ? 'Study activated.' : 'Study paused.');
    }

    public function delete(Study $study): void
    {
        $this->authorize('delete', $study);

        $name = $study->name;
        $study->delete();

        $this->dispatch('toast', message: "Deleted “{$name}”.");
    }

    public function render(): View
    {
        $studies = Auth::user()->accessibleStudies()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->with('owner:id,name')
            ->withCount(['questions', 'participants', 'quizSessions'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.studies.study-index', ['studies' => $studies]);
    }
}
