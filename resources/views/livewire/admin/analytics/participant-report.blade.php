@php use App\Services\Analytics\Metrics; @endphp

<div>
    <x-admin.page-header :title="'Report · '.$participant->code"
                         :subtitle="collect([$participant->display_name, $participant->age ? 'age '.$participant->age : null, $participant->study->name])->filter()->implode(' · ')">
        <x-slot:actions>
            <a href="{{ route('admin.participants.index') }}" class="btn btn-secondary print:hidden" wire:navigate>← Participants</a>
            <button type="button" onclick="window.print()" class="btn btn-secondary print:hidden">🖨️ Print</button>
        </x-slot:actions>
    </x-admin.page-header>

    @include('reports.partials.child-report', ['report' => $report, 'withPeers' => $withPeers])

    {{-- Sessions --}}
    @if ($report['profile']['sessions'])
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4"><h2 class="font-display text-lg">Sessions</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mode</th>
                        <th class="text-right">Questions</th>
                        <th class="text-right">Right first try</th>
                        <th class="text-right">Typical time</th>
                        <th class="text-right print:hidden"><span class="sr-only">Open</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach (array_reverse($report['profile']['sessions']) as $session)
                        <tr>
                            <td>{{ $session['started_at']?->format('j M Y, H:i') ?? '—' }}</td>
                            <td>{{ $session['mode'] === 'free' ? 'Free Choice' : 'Find the Answer' }}</td>
                            <td class="text-right">{{ $session['answers'] }}</td>
                            <td class="text-right font-bold">{{ Metrics::percent($session['first_try_rate']) }}</td>
                            <td class="text-right">{{ Metrics::seconds($session['median_rt_ms']) }}</td>
                            <td class="text-right print:hidden">
                                @if ($uuid = $report['session_uuids'][$session['session_id']] ?? null)
                                    <a href="{{ route('admin.sessions.show', $uuid) }}" class="btn btn-secondary btn-sm" wire:navigate>Timeline</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Parent share link --}}
    @can('update', $participant)
        <section class="card mt-6 p-5 print:hidden">
            <h2 class="font-display text-lg">Share with parent</h2>
            <p class="mb-4 text-sm text-muted">Creates a private link to a simple version of this report — no account needed. It shows only this child, never other children, and stops working when it expires or you revoke it.</p>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="linkDays" class="field-label">Link works for</label>
                    <select id="linkDays" wire:model="linkDays" class="field-input">
                        <option value="7">7 days</option>
                        <option value="30">30 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                <button type="button" wire:click="createLink" class="btn btn-primary">🔗 Create link</button>
            </div>

            @if ($newLinkUrl)
                <div class="mt-4 rounded-2xl border border-sky bg-sky/10 p-4" x-data="{ copied: false }">
                    <p class="mb-2 text-sm font-bold">Copy this link now — for privacy it is only shown once.</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="text" readonly value="{{ $newLinkUrl }}" class="field-input font-mono text-xs" x-ref="link" @focus="$el.select()" aria-label="Share link">
                        <button type="button" class="btn btn-secondary shrink-0"
                                @click="navigator.clipboard?.writeText($refs.link.value).then(() => copied = true, () => { $refs.link.select(); })">
                            <span x-text="copied ? '✓ Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                </div>
            @endif

            @if ($links->isNotEmpty())
                <ul class="mt-5 divide-y divide-line rounded-xl border border-line">
                    @foreach ($links as $link)
                        <li wire:key="link-{{ $link->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                            <span class="flex-1">
                                Created {{ $link->created_at->diffForHumans() }} by {{ $link->creator?->name ?? 'someone' }} ·
                                expires {{ $link->expires_at->format('j M Y') }} ·
                                viewed {{ $link->view_count }} {{ Str::plural('time', $link->view_count) }}
                            </span>
                            <button type="button" wire:click="revokeLink({{ $link->id }})" wire:confirm="Revoke this link? The parent will no longer be able to open it."
                                    class="btn btn-danger btn-sm">Revoke</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endcan
</div>
