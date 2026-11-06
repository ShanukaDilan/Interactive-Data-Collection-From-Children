<x-layouts::guest title="Sign in">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-3 flex size-16 items-center justify-center rounded-2xl bg-white text-3xl shadow-sm">🌟</div>
            <h1 class="font-display text-3xl text-coral">Learning Fun!</h1>
            <p class="mt-1 text-sm text-muted">Researcher &amp; teacher dashboard</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="card space-y-5 p-6 sm:p-8">
            @csrf

            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" class="field-input">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="field-input">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" class="size-4 rounded border-line text-coral focus:ring-coral">
                Keep me signed in on this device
            </label>

            <button type="submit" class="btn btn-primary w-full py-3 text-base">Sign in</button>
        </form>
    </div>
</x-layouts::guest>
