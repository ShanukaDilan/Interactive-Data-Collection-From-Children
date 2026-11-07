@props(['icon' => '🫧', 'title', 'message' => null])

<div class="px-6 py-14 text-center">
    <div class="mb-2 text-4xl" aria-hidden="true">{{ $icon }}</div>
    <p class="font-display text-lg text-ink">{{ $title }}</p>
    @if ($message)
        <p class="mx-auto mt-1 max-w-sm text-sm text-muted">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
