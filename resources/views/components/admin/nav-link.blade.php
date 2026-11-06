@props(['route', 'href' => null, 'icon' => null])

@php
    $isActive = request()->routeIs($route);
    $href ??= route($route);
@endphp

<a href="{{ $href }}" wire:navigate
   @class([
       'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition',
       'bg-coral-soft text-coral' => $isActive,
       'text-muted hover:bg-canvas hover:text-ink' => ! $isActive,
   ])
   @if ($isActive) aria-current="page" @endif>
    @if ($icon)<span class="text-base" aria-hidden="true">{{ $icon }}</span>@endif
    <span>{{ $slot }}</span>
</a>
