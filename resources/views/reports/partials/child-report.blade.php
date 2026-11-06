{{--
    Shared body of a child's report (staff report page + parent share link).
    $report from App\Services\Analytics\ChildReport::build(); $withPeers adds anonymous same-age averages.
--}}
@php
    use App\Services\Analytics\ChildProfile;
    use App\Services\Analytics\Metrics;
    $overall = $report['profile']['overall'];
    $categories = collect($report['profile']['categories']);
    $labelStyles = [
        ChildProfile::STRENGTH => 'bg-mint/50 text-ink',
        ChildProfile::DEVELOPING => 'bg-sky/20 text-ink',
        ChildProfile::PRACTICE => 'bg-peach/30 text-ink',
        ChildProfile::NOT_ENOUGH => 'bg-line text-muted',
    ];
@endphp

@if ($overall['answers'] === 0)
    <div class="card"><x-admin.empty-state icon="🎈" title="No answers yet" message="The report fills in once this child has played a quiz." /></div>
@else
    {{-- Headline --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-admin.stat label="Sessions played" :value="$overall['sessions']" icon="🎮" tone="sky" />
        <x-admin.stat label="Questions answered" :value="$overall['answers']" icon="✋" tone="peach" />
        <x-admin.stat label="Right first try" :value="Metrics::percent($overall['first_try_rate'])" icon="🎯" tone="mint"
                      :hint="$withPeers && $report['profile']['peer_overall'] ? 'age '.$report['peer_age'].' average: '.Metrics::percent($report['profile']['peer_overall']['first_try_rate']) : null" />
        <x-admin.stat label="Typical time to answer" :value="Metrics::seconds($overall['median_rt_ms'])" icon="⏱️" tone="lav"
                      :hint="$withPeers && $report['profile']['peer_overall'] ? 'age '.$report['peer_age'].' average: '.Metrics::seconds($report['profile']['peer_overall']['median_rt_ms']) : null" />
    </div>

    @if ($report['strengths'] || $report['practice'])
        <div class="card mt-6 grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div>
                <p class="text-xs font-extrabold tracking-wide text-muted uppercase">🌟 Doing especially well</p>
                <p class="mt-1 font-display text-lg">{{ $report['strengths'] ? implode(', ', $report['strengths']) : 'Keep playing to find out!' }}</p>
            </div>
            <div>
                <p class="text-xs font-extrabold tracking-wide text-muted uppercase">🌱 Worth practising</p>
                <p class="mt-1 font-display text-lg">{{ $report['practice'] ? implode(', ', $report['practice']) : 'Nothing stands out' }}</p>
            </div>
            <p class="text-xs text-muted sm:col-span-2">Compared with {{ $withPeers ? 'this child’s own average (and, where shown, the anonymous average of other children the same age in this study)' : 'this child’s own average across all areas' }}.</p>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="card p-5">
            <h2 class="font-display mb-3 text-lg">Areas</h2>
            @php
                $datasets = [['label' => 'This child', 'data' => $report['radar']['child'], 'color' => '#FF6B6B']];
                if ($withPeers && $report['radar']['peers']) {
                    $datasets[] = ['label' => 'Age '.$report['peer_age'].' average', 'data' => $report['radar']['peers'], 'color' => '#4ECDC4'];
                }
            @endphp
            {{-- Radar needs room for its labels; phones get a horizontal bar chart instead. --}}
            @if (count($report['radar']['labels']) >= 3)
                <x-admin.chart type="radar" format="percent" label="Right first try by area" height="20rem" class="hidden md:block"
                               :labels="$report['radar']['labels']" :datasets="$datasets" />
            @endif
            <x-admin.chart type="bar" horizontal format="percent" :max="100" label="Right first try by area"
                           :height="max(10, count($report['radar']['labels']) * ($withPeers ? 3.2 : 2.4)).'rem'"
                           :class="count($report['radar']['labels']) >= 3 ? 'md:hidden' : ''"
                           :labels="$report['radar']['labels']" :datasets="$datasets" />
        </section>

        <section class="card overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Area</th>
                    <th class="text-right">Right first try</th>
                    @if ($withPeers && $report['profile']['peers_available']) <th class="text-right">Age avg</th> @endif
                    <th class="text-right">Typical time</th>
                    <th class="hidden sm:table-cell"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($categories as $row)
                    <tr>
                        <td>
                            <p class="font-bold">{{ $row['category'] }}</p>
                            <p class="text-xs text-muted">{{ $row['answers'] }} {{ Str::plural('answer', $row['answers']) }}</p>
                            <span class="badge mt-1 whitespace-nowrap sm:hidden {{ $labelStyles[$row['label']] }}">{{ ChildProfile::labelText($row['label']) }}</span>
                        </td>
                        <td class="text-right font-bold">{{ Metrics::percent($row['first_try_rate']) }}</td>
                        @if ($withPeers && $report['profile']['peers_available'])
                            <td class="text-right text-muted">{{ Metrics::percent($row['peer_rate']) }}</td>
                        @endif
                        <td class="text-right">{{ Metrics::seconds($row['median_rt_ms']) }}</td>
                        <td class="hidden text-right sm:table-cell"><span class="badge whitespace-nowrap {{ $labelStyles[$row['label']] }}">{{ ChildProfile::labelText($row['label']) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    </div>

    @if (count($report['trend']['labels']) >= 2)
        <section class="card mt-6 p-5">
            <h2 class="font-display mb-3 text-lg">Progress over sessions</h2>
            <x-admin.chart type="line" format="percent" :max="100" label="Right first try per session"
                           :labels="$report['trend']['labels']"
                           :datasets="[['label' => 'Right first try', 'data' => $report['trend']['values'], 'color' => '#A68BFF']]" />
        </section>
    @endif

    <section class="card mt-6 p-5">
        <h2 class="font-display mb-3 text-lg">How {{ $isParent ?? false ? 'your child' : 'the child' }} plays</h2>
        <x-admin.insight-list :insights="$report['insights']" empty="No particular patterns yet — they appear after more questions have been played." />
    </section>
@endif
