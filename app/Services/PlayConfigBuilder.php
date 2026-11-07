<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Study;
use App\Models\User;

/**
 * The setup-screen data for the quiz: the user's active studies with their participants.
 * Used by the web page (embedded) and by registered devices (API) so a tablet can refresh it.
 */
class PlayConfigBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function studies(User $user): array
    {
        return $user->accessibleStudies()
            ->where('is_active', true)
            ->withCount(['questions' => fn ($query) => $query->where('is_archived', false)])
            ->with(['participants' => fn ($query) => $query->orderBy('code')])
            ->orderBy('name')
            ->get()
            ->map(fn (Study $study) => [
                'id' => $study->id,
                'name' => $study->name,
                'age_min' => $study->age_min,
                'age_max' => $study->age_max,
                'default_quiz_mode' => $study->default_quiz_mode->value,
                'question_count' => $study->questions_count,
                'bundle_url' => route('play.bundle', $study),
                'api_bundle_url' => route('api.v1.play.bundle', $study),
                'participants' => $study->participants->map(fn (Participant $participant) => [
                    'id' => $participant->id,
                    'code' => $participant->code,
                    'age' => $participant->age,
                    'name' => $participant->display_name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Full page config for the quiz screen.
     *
     * @return array<string, mixed>
     */
    public function forPage(User $user): array
    {
        return [
            'studies' => $this->studies($user),
            'userId' => $user->id,
            'syncUrl' => route('play.sync'),
            'apiSyncUrl' => route('api.v1.play.sync'),
            'apiConfigUrl' => route('api.v1.play.config'),
            'deviceRegisterUrl' => route('play.devices.store'),
            'generatedAt' => now()->toIso8601String(),
        ];
    }
}
