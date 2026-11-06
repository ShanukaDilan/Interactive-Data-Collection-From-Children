<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
<div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">

    {{-- Mobile top bar --}}
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-line bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
        <a href="{{ route('admin.dashboard') }}" class="font-display text-xl text-coral" wire:navigate>🌟 Learning Fun!</a>
        <button type="button" class="btn btn-secondary btn-sm" @click="sidebarOpen = true" aria-label="Open menu">☰</button>
    </header>

    {{-- Sidebar --}}
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 lg:hidden" @click="sidebarOpen = false"></div>

    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-line bg-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="flex items-center justify-between px-5 py-5">
            <a href="{{ route('admin.dashboard') }}" class="font-display text-2xl text-coral" wire:navigate>🌟 Learning Fun!</a>
            <button type="button" class="text-muted lg:hidden" @click="sidebarOpen = false" aria-label="Close menu">✕</button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
            <x-admin.nav-link route="admin.dashboard" icon="📊">Dashboard</x-admin.nav-link>
            <x-admin.nav-link route="admin.studies.*" :href="route('admin.studies.index')" icon="🧪">Studies</x-admin.nav-link>
            @can('viewAny', App\Models\Question::class)
                <x-admin.nav-link route="admin.questions.*" :href="route('admin.questions.index')" icon="📝">Questions</x-admin.nav-link>
            @endcan
            <x-admin.nav-link route="admin.participants.*" :href="route('admin.participants.index')" icon="🧒">Participants</x-admin.nav-link>

            @can('viewAny', App\Models\User::class)
                <p class="px-3 pt-5 pb-1 text-xs font-extrabold tracking-wide text-muted uppercase">Administration</p>
                <x-admin.nav-link route="admin.users.*" :href="route('admin.users.index')" icon="👥">Users</x-admin.nav-link>
            @endcan
        </nav>

        <div class="border-t border-line p-4">
            <div class="mb-3 flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-full bg-sun font-extrabold text-ink">
                    {{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-muted">{{ Str::ucfirst(auth()->user()->getRoleNames()->first() ?? 'no role') }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.account.password') }}" class="btn btn-secondary btn-sm flex-1" wire:navigate>Password</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm w-full">Sign out</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
        {{ $slot }}
    </main>
</div>

{{-- Toasts: session flash or Livewire `$this->dispatch('toast', message: '...', type: 'success|error')` --}}
<div x-data="{ toasts: [] }"
     x-init="@if (session('status')) toasts.push({ id: Date.now(), message: @js(session('status')), type: 'success' }); setTimeout(() => toasts.shift(), 3500); @endif"
     @toast.window="const t = { id: Date.now() + Math.random(), message: $event.detail.message, type: $event.detail.type ?? 'success' }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 3500)"
     class="pointer-events-none fixed right-4 bottom-4 z-[60] flex flex-col gap-2" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition class="pointer-events-auto rounded-xl px-4 py-3 text-sm font-bold text-white shadow-lg"
             :class="toast.type === 'error' ? 'bg-coral-dark' : 'bg-sky-dark'" x-text="toast.message"></div>
    </template>
</div>
</body>
</html>
