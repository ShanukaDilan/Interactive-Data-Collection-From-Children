<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

/**
 * Filters applied to every analytics view. All optional.
 */
final readonly class AnalyticsFilters
{
    public function __construct(
        public ?int $studyId = null,
        public ?int $participantId = null,
        public ?string $sessionUuid = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $age = null,
        public ?string $mode = null,
        public ?string $category = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $int = fn (string $key): ?int => filled($input[$key] ?? null) ? (int) $input[$key] : null;
        $string = fn (string $key): ?string => filled($input[$key] ?? null) ? (string) $input[$key] : null;
        $date = fn (string $key): ?CarbonImmutable => filled($input[$key] ?? null) ? CarbonImmutable::parse($input[$key]) : null;

        return new self(
            studyId: $int('study'),
            participantId: $int('participant'),
            sessionUuid: $string('session'),
            from: $date('from')?->startOfDay(),
            to: $date('to')?->endOfDay(),
            age: $int('age'),
            mode: in_array($input['mode'] ?? null, ['correct', 'free'], true) ? $input['mode'] : null,
            category: $string('category'),
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
