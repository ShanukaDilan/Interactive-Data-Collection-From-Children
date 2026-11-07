@props(['label', 'value', 'icon' => null, 'tone' => 'coral', 'hint' => null])

@php
    $tones = [
        'coral' => 'bg-coral/15',
        'sky' => 'bg-sky/20',
        'sun' => 'bg-sun/40',
        'mint' => 'bg-mint/40',
        'lav' => 'bg-lav/30',
        'peach' => 'bg-peach/25',
    ];
@endphp

<div class="card flex items-center gap-4 p-5">
    @if ($icon)
        <div @class(['flex size-12 shrink-0 items-center justify-center rounded-2xl text-2xl', $tones[$tone] ?? $tones['coral']]) aria-hidden="true">{{ $icon }}</div>
    @endif
    <div class="min-w-0">
        <p class="text-xs font-extrabold tracking-wide text-muted uppercase">{{ $label }}</p>
        <p class="font-display text-2xl text-ink">{{ $value }}</p>
        @if ($hint)
            <p class="text-xs text-muted">{{ $hint }}</p>
        @endif
    </div>
</div>
