<?php

namespace App\Http\Requests;

use App\Enums\QuizMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of quiz sessions with their answers, sent by the quiz page (online) or
 * a registered device (offline sync). Re-sending the same batch is safe.
 */
class SyncQuizDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sessions' => ['required', 'array', 'min:1', 'max:50'],
            'sessions.*.uuid' => ['required', 'uuid'],
            'sessions.*.study_id' => ['required', 'integer'],
            'sessions.*.participant_id' => ['nullable', 'integer'],
            'sessions.*.child_age' => ['nullable', 'integer', 'between:2,12'],
            'sessions.*.quiz_mode' => ['required', Rule::enum(QuizMode::class)],
            'sessions.*.started_at' => ['nullable', 'date'],
            'sessions.*.completed_at' => ['nullable', 'date'],
            'sessions.*.responses' => ['present', 'array', 'max:500'],
            'sessions.*.responses.*.client_uuid' => ['required', 'uuid'],
            'sessions.*.responses.*.question_id' => ['nullable', 'integer'],
            'sessions.*.responses.*.question_ref' => ['nullable', 'string', 'max:40'],
            'sessions.*.responses.*.question_version' => ['nullable', 'integer', 'min:1'],
            'sessions.*.responses.*.question_text' => ['nullable', 'string', 'max:255'],
            'sessions.*.responses.*.category' => ['nullable', 'string', 'max:80'],
            'sessions.*.responses.*.correct_label' => ['nullable', 'string', 'max:100'],
            'sessions.*.responses.*.selected_label' => ['nullable', 'string', 'max:100'],
            'sessions.*.responses.*.selected_number' => ['nullable', 'integer', 'between:0,999'],
            'sessions.*.responses.*.is_correct' => ['required', 'boolean'],
            'sessions.*.responses.*.attempts' => ['required', 'integer', 'between:1,100'],
            'sessions.*.responses.*.response_time_ms' => ['required', 'integer', 'between:0,3600000'],
            'sessions.*.responses.*.position' => ['nullable', 'integer', 'between:0,1000'],
            'sessions.*.responses.*.answered_at' => ['nullable', 'date'],
            'sessions.*.responses.*.first_tap_ms' => ['nullable', 'integer', 'between:0,3600000'],
            'sessions.*.responses.*.prompt_ended_ms' => ['nullable', 'integer', 'between:0,3600000'],
            'sessions.*.responses.*.replay_count' => ['nullable', 'integer', 'between:0,1000'],
            'sessions.*.responses.*.answered_during_prompt' => ['nullable', 'boolean'],
            'sessions.*.responses.*.option_order' => ['nullable', 'array', 'max:12'],
            'sessions.*.responses.*.option_order.*' => ['nullable', 'string', 'max:100'],
            'sessions.*.responses.*.selected_position' => ['nullable', 'integer', 'between:0,11'],
            'sessions.*.responses.*.taps' => ['nullable', 'array', 'max:50'],
            'sessions.*.responses.*.taps.*.selected_label' => ['nullable', 'string', 'max:100'],
            'sessions.*.responses.*.taps.*.selected_number' => ['nullable', 'integer', 'between:0,999'],
            'sessions.*.responses.*.taps.*.screen_position' => ['nullable', 'integer', 'between:0,11'],
            'sessions.*.responses.*.taps.*.is_correct' => ['required', 'boolean'],
            'sessions.*.responses.*.taps.*.elapsed_ms' => ['required', 'integer', 'between:0,3600000'],
            'sessions.*.responses.*.taps.*.during_prompt' => ['nullable', 'boolean'],
        ];
    }
}
