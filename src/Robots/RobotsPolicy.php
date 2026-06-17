<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Robots;

/**
 * A default-open robots.txt evaluator — the honest implementation a scraper
 * uses to decide whether it may fetch a path.
 *
 * Precedence (matching Google's documented rules):
 *  - The most specific (longest) matching rule wins.
 *  - On a tie between an Allow and a Disallow of equal length, Allow wins.
 *  - Rules from the user-agent-specific group are used if one matches the UA;
 *    otherwise the "*" group applies.
 *  - No matching rule => allowed (default-open). An empty "Disallow:" allows all.
 *
 * Wildcards "*" (any run of characters) and "$" (end-of-path anchor) are honored.
 */
final class RobotsPolicy
{
    /**
     * @param  array<string, list<array{type: 'allow'|'disallow', pattern: string}>>  $groups
     *                                                                                         Lowercased user-agent token => ordered rules.
     */
    private function __construct(private readonly array $groups) {}

    public static function parse(string $robotsTxt): self
    {
        /** @var array<string, list<array{type: 'allow'|'disallow', pattern: string}>> $groups */
        $groups = [];

        // user-agents collecting rules for the current block
        $currentAgents = [];
        // whether we just saw a rule line (so a new User-agent starts a fresh block)
        $sawRule = false;

        foreach (preg_split('/\r\n|\r|\n/', $robotsTxt) ?: [] as $rawLine) {
            $line = trim(preg_replace('/#.*$/', '', $rawLine) ?? '');
            if ($line === '') {
                continue;
            }

            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }

            $field = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));

            if ($field === 'user-agent') {
                if ($sawRule) {
                    // A User-agent line after rules begins a new group block.
                    $currentAgents = [];
                    $sawRule = false;
                }
                $agent = strtolower($value);
                $currentAgents[] = $agent;
                $groups[$agent] ??= [];

                continue;
            }

            if ($field === 'allow' || $field === 'disallow') {
                $sawRule = true;
                if ($currentAgents === []) {
                    // Rule before any User-agent — attribute to "*".
                    $currentAgents = ['*'];
                    $groups['*'] ??= [];
                }
                foreach ($currentAgents as $agent) {
                    $groups[$agent][] = ['type' => $field, 'pattern' => $value];
                }
            }
            // Other fields (Sitemap, Host, Crawl-delay) are irrelevant to access decisions.
        }

        return new self($groups);
    }

    /**
     * Is $path fetchable by $userAgent? Default-open.
     */
    public function allowed(string $path, string $userAgent = '*'): bool
    {
        $rules = $this->rulesFor($userAgent);

        $bestLen = -1;
        $bestAllow = true; // default-open

        foreach ($rules as $rule) {
            $pattern = $rule['pattern'];

            // An empty Disallow value means "allow everything" — never matches a path.
            if ($pattern === '') {
                continue;
            }

            if (! $this->matches($pattern, $path)) {
                continue;
            }

            $len = $this->specificity($pattern);

            if ($len > $bestLen) {
                $bestLen = $len;
                $bestAllow = $rule['type'] === 'allow';
            } elseif ($len === $bestLen && $rule['type'] === 'allow') {
                // Equal specificity: Allow wins.
                $bestAllow = true;
            }
        }

        return $bestAllow;
    }

    public function disallowed(string $path, string $userAgent = '*'): bool
    {
        return ! $this->allowed($path, $userAgent);
    }

    /**
     * Resolve the rule set for a user-agent: exact match, else longest matching
     * prefix token, else the "*" group, else none.
     *
     * @return list<array{type: 'allow'|'disallow', pattern: string}>
     */
    private function rulesFor(string $userAgent): array
    {
        $ua = strtolower($userAgent);

        if (isset($this->groups[$ua])) {
            return $this->groups[$ua];
        }

        // robots.txt UA matching is by substring/prefix of the product token.
        $bestToken = null;
        $bestLen = -1;
        foreach ($this->groups as $token => $rules) {
            if ($token === '*') {
                continue;
            }
            if ($token !== '' && str_contains($ua, $token) && strlen($token) > $bestLen) {
                $bestToken = $token;
                $bestLen = strlen($token);
            }
        }

        if ($bestToken !== null) {
            return $this->groups[$bestToken];
        }

        return $this->groups['*'] ?? [];
    }

    /**
     * Specificity = length of the literal pattern (wildcards excluded from the
     * count beyond their single char), matching Google's "longest match wins".
     */
    private function specificity(string $pattern): int
    {
        return strlen($pattern);
    }

    /**
     * Glob-style match: "*" = any chars, "$" (trailing) = end anchor.
     * A pattern is a prefix match unless anchored with "$".
     */
    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $core = $anchored ? substr($pattern, 0, -1) : $pattern;

        // Build a regex from the pattern, treating "*" as ".*".
        $parts = explode('*', $core);
        $regex = implode('.*', array_map('preg_quote', $parts, array_fill(0, count($parts), '#')));

        $regex = '#^'.$regex.($anchored ? '$' : '').'#';

        return preg_match($regex, $path) === 1;
    }
}
