@props(['insights' => [], 'empty' => 'No notable patterns yet. Patterns appear once there are enough answers to be confident.'])

<div class="space-y-3">
    @forelse ($insights as $insight)
        <div @class([
            'flex gap-3 rounded-2xl border p-4',
            'border-peach/50 bg-peach/10' => $insight->severity === App\Services\Analytics\Insight::NOTICE,
            'border-mint bg-mint/15' => $insight->severity === App\Services\Analytics\Insight::POSITIVE,
            'border-line bg-white' => $insight->severity === App\Services\Analytics\Insight::INFO,
        ])>
            <div class="text-2xl leading-none" aria-hidden="true">{{ $insight->icon }}</div>
            <div class="min-w-0">
                <p class="text-sm font-extrabold text-ink">{{ $insight->title }}</p>
                <p class="mt-0.5 text-sm text-ink/80">{{ $insight->message }}</p>
            </div>
        </div>
    @empty
        <p class="rounded-2xl bg-canvas px-4 py-6 text-center text-sm text-muted">{{ $empty }}</p>
    @endforelse

    <p class="pt-1 text-xs text-muted">ℹ️ These are indicators from a short game, not a diagnosis. Look at them alongside what you know about the child.</p>
</div>
