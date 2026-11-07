<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Serves the offline service worker and the web app manifest.
 *
 * The service worker is generated from resources/sw/service-worker.js with the hashed quiz
 * assets from Vite's build manifest, so every deploy produces a new cache version.
 */
class ServiceWorkerController extends Controller
{
    /** Vite entries the offline quiz needs (fonts are added automatically). */
    private const QUIZ_ENTRIES = ['resources/css/quiz.css', 'resources/js/quiz/main.js'];

    public function worker(): Response
    {
        $manifestPath = public_path('build/manifest.json');
        $manifest = File::exists($manifestPath) ? json_decode(File::get($manifestPath), true) : [];

        $assets = collect($manifest)
            ->filter(fn (array $chunk, string $key) => in_array($key, self::QUIZ_ENTRIES, true)
                || preg_match('/\.(woff2?|css)$/', $chunk['file']) && str_starts_with($key, '_'))
            ->flatMap(fn (array $chunk) => [$chunk['file'], ...($chunk['css'] ?? [])])
            ->unique()
            ->map(fn (string $file) => '/build/'.$file)
            ->values();

        $version = substr(sha1(json_encode($manifest).File::lastModified(resource_path('sw/service-worker.js'))), 0, 12);

        $script = strtr(File::get(resource_path('sw/service-worker.js')), [
            '__VERSION__' => $version,
            '__PRECACHE__' => json_encode($assets->all(), JSON_UNESCAPED_SLASHES),
        ]);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'Learning Fun!',
            'short_name' => 'Learning Fun',
            'description' => 'Interactive learning games for young children.',
            'start_url' => '/play',
            'scope' => '/',
            'display' => 'fullscreen',
            'orientation' => 'any',
            'background_color' => '#FFF5E4',
            'theme_color' => '#FF6B6B',
            'icons' => [
                ['src' => '/icons/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
