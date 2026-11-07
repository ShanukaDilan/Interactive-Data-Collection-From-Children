<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and resolves question media (images / audio) on the configured upload disk.
 * Paths from the legacy app ("uploads/...") are served from /public as-is.
 */
class Media
{
    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const AUDIO_MIMES = ['mp3', 'ogg', 'wav', 'm4a'];

    public static function disk(): string
    {
        return config('quiz.uploads.disk', 'public');
    }

    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, 'uploads/')) {
            return asset($path);
        }

        return Storage::disk(self::disk())->url($path);
    }

    /**
     * @param  'image'|'audio'  $kind
     */
    public static function store(UploadedFile $file, string $kind): string
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return $file->storeAs(
            "questions/{$kind}s",
            $kind.'_'.Str::random(20).'.'.$extension,
            self::disk(),
        );
    }

    /**
     * Delete stored files that are no longer referenced. Legacy and remote paths are left alone.
     *
     * @param  iterable<string|null>  $paths
     */
    public static function delete(iterable $paths): void
    {
        foreach ($paths as $path) {
            if ($path && ! Str::startsWith($path, ['uploads/', 'http://', 'https://'])) {
                Storage::disk(self::disk())->delete($path);
            }
        }
    }
}
