<?php

namespace App\Services\Analytics;

/**
 * A plain-language observation about behaviour, with the numbers behind it.
 */
final readonly class Insight
{
    public const INFO = 'info';

    public const NOTICE = 'notice';

    public const POSITIVE = 'positive';

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public string $severity = self::INFO,
        public string $icon = '💡',
        public array $evidence = [],
    ) {}
}
