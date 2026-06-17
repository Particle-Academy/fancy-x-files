<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Laravel;

use ParticleAcademy\XFiles\Robots\RobotsPolicy;

/**
 * One honest implementation for "may I fetch this?" — so an app's scraper and
 * every consumer share the same default-open, correct-precedence answer instead
 * of each hand-rolling a robots parser.
 *
 * Despite living under Laravel/, this helper has no framework dependency; it is
 * placed here as the host-facing guard.
 */
final class HonorsRobots
{
    private function __construct(private readonly RobotsPolicy $policy) {}

    /**
     * Build from a target site's robots.txt body.
     */
    public static function forRobotsTxt(string $robotsTxt): self
    {
        return new self(RobotsPolicy::parse($robotsTxt));
    }

    /**
     * One-shot: may $userAgent fetch $path given this robots.txt body?
     * Default-open if robots is empty/unparseable.
     */
    public static function allows(string $robotsTxt, string $path, string $userAgent = '*'): bool
    {
        return RobotsPolicy::parse($robotsTxt)->allowed($path, $userAgent);
    }

    public function mayFetch(string $path, string $userAgent = '*'): bool
    {
        return $this->policy->allowed($path, $userAgent);
    }

    public function policy(): RobotsPolicy
    {
        return $this->policy;
    }
}
