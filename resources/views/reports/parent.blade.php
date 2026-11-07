<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Learning Fun! · Progress report</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <header class="mb-6 text-center">
            <p class="font-display text-2xl text-coral">🌟 Learning Fun!</p>
            <h1 class="font-display mt-2 text-3xl">{{ $childName ? $childName.'’s' : 'Your child’s' }} progress</h1>
            <p class="mt-1 text-sm text-muted">From the learning games played at school. Shared privately with you — please don’t forward this link.</p>
        </header>

        @include('reports.partials.child-report', ['report' => $report, 'withPeers' => false, 'isParent' => true])

        <section class="card mt-6 p-5 text-sm text-muted">
            <p><strong class="text-ink">How to read this.</strong> “Right first try” is how often your child chose the correct picture on their first tap. Areas are compared with your child’s own average, not with other children. A short game can’t capture everything — talk with the teacher if you have questions.</p>
            <p class="mt-2">This link stops working on {{ $link->expires_at->format('j F Y') }}.</p>
        </section>
    </main>

    @livewireScripts
</body>
</html>
