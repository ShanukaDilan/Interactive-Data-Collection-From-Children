<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-24 -left-16 size-72 rounded-full bg-sun/40 blur-3xl"></div>
        <div class="absolute top-1/3 -right-20 size-80 rounded-full bg-sky/25 blur-3xl"></div>
        <div class="absolute -bottom-24 left-1/4 size-72 rounded-full bg-lav/30 blur-3xl"></div>
    </div>

    <main class="relative flex min-h-screen items-center justify-center px-4 py-12">
        {{ $slot }}
    </main>
</body>
</html>
