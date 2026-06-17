<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Files;

use ParticleAcademy\XFiles\Contracts\WellKnownFile;
use ParticleAcademy\XFiles\Robots\RobotsPolicy;

/**
 * Fluent, JSON-friendly builder for robots.txt.
 *
 * Output contract: one block per group (its `User-agent:` lines followed by the
 * group's ordered `Allow:` / `Disallow:` rules), then any `Host:` line, then all
 * `Sitemap:` lines at the end.
 *
 * Precedence note for consumers: robots.txt access is decided by the most
 * specific (longest) matching rule, with Allow winning ties of equal length.
 * See {@see RobotsPolicy}. That is why blanket Allows are dangerous — a longer
 * Allow can override a shorter Disallow.
 *
 * Leak guarantee: {@see self::protect()} appends a Disallow to EVERY group
 * (current and future) and refuses to ever emit an Allow for a protected path.
 * So an admin path registered via protect() can never be Allowed for one bot
 * by accident — the motivating robots.txt leak bug becomes structurally
 * impossible.
 */
final class RobotsTxt implements WellKnownFile
{
    /**
     * Groups in insertion order.
     *
     * @var list<array{agents: list<string>, rules: list<array{type: 'allow'|'disallow', path: string}>}>
     */
    private array $groups = [];

    /** @var list<string> */
    private array $sitemaps = [];

    private ?string $host = null;

    /** @var list<string> Paths force-disallowed across every group. */
    private array $protected = [];

    /** Index of the group most recently targeted by userAgent(), for chaining. */
    private ?int $cursor = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Build from a JSON-friendly array. Shape:
     * [
     *   'groups' => [ ['userAgent' => '*'|[...], 'allow' => [...], 'disallow' => [...]], ... ],
     *   'sitemaps' => [...], 'host' => '...', 'protect' => [...],
     * ]
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $robots = new self;

        /** @var list<array<string, mixed>> $groups */
        $groups = $config['groups'] ?? [];
        foreach ($groups as $group) {
            $agents = $group['userAgent'] ?? $group['user_agent'] ?? '*';
            $robots->userAgent($agents);
            foreach ((array) ($group['disallow'] ?? []) as $path) {
                $robots->disallow($path);
            }
            foreach ((array) ($group['allow'] ?? []) as $path) {
                $robots->allow($path);
            }
            if (isset($group['crawlDelay']) || isset($group['crawl_delay'])) {
                $robots->crawlDelay((int) ($group['crawlDelay'] ?? $group['crawl_delay']));
            }
        }

        foreach ((array) ($config['sitemaps'] ?? []) as $url) {
            $robots->sitemap((string) $url);
        }
        if (isset($config['host'])) {
            $robots->host((string) $config['host']);
        }
        foreach ((array) ($config['protect'] ?? []) as $path) {
            $robots->protect((string) $path);
        }

        return $robots;
    }

    public function path(): string
    {
        return '/robots.txt';
    }

    public function contentType(): string
    {
        return 'text/plain';
    }

    /**
     * Start (or re-target) a group for one or more user-agents.
     *
     * @param  string|list<string>  $agents
     */
    public function userAgent(string|array $agents): self
    {
        $agents = array_values(array_map('strval', (array) $agents));
        if ($agents === []) {
            $agents = ['*'];
        }

        $rules = [];
        foreach ($this->protected as $protectedPath) {
            $rules[] = ['type' => 'disallow', 'path' => $protectedPath];
        }

        $this->groups[] = ['agents' => $agents, 'rules' => $rules];
        $this->cursor = array_key_last($this->groups);

        return $this;
    }

    /** Alias reading naturally as `->forAgent(...)`. */
    public function forAgent(string|array $agents): self
    {
        return $this->userAgent($agents);
    }

    public function disallow(string ...$paths): self
    {
        $group = $this->ensureGroup();
        foreach ($paths as $path) {
            $this->groups[$group]['rules'][] = ['type' => 'disallow', 'path' => $path];
        }

        return $this;
    }

    public function allow(string ...$paths): self
    {
        $group = $this->ensureGroup();
        foreach ($paths as $path) {
            // A protected path can never be Allowed — silently dropped so a
            // copy-pasted allow list can't reopen /admin for one bot.
            if ($this->isProtected($path)) {
                continue;
            }
            $this->groups[$group]['rules'][] = ['type' => 'allow', 'path' => $path];
        }

        return $this;
    }

    public function crawlDelay(int $seconds): self
    {
        $group = $this->ensureGroup();
        $this->groups[$group]['crawlDelay'] = $seconds;

        return $this;
    }

    public function disallowAll(): self
    {
        return $this->disallow('/');
    }

    public function allowAll(): self
    {
        // An empty Disallow value is the canonical "allow everything".
        $group = $this->ensureGroup();
        $this->groups[$group]['rules'][] = ['type' => 'disallow', 'path' => ''];

        return $this;
    }

    /**
     * Protect one or more paths: add a Disallow to EVERY group — those already
     * defined and any added later — and bar them from ever being Allowed.
     * This is how an admin path is kept out of every bot's Allow list.
     */
    public function protect(string ...$paths): self
    {
        foreach ($paths as $path) {
            if ($path === '' || in_array($path, $this->protected, true)) {
                continue;
            }
            $this->protected[] = $path;

            foreach ($this->groups as $i => $group) {
                // Drop any existing Allow for this path…
                $this->groups[$i]['rules'] = array_values(array_filter(
                    $group['rules'],
                    fn (array $rule): bool => ! ($rule['type'] === 'allow' && $rule['path'] === $path),
                ));
                // …and ensure a Disallow exists.
                $hasDisallow = false;
                foreach ($this->groups[$i]['rules'] as $rule) {
                    if ($rule['type'] === 'disallow' && $rule['path'] === $path) {
                        $hasDisallow = true;
                        break;
                    }
                }
                if (! $hasDisallow) {
                    array_unshift($this->groups[$i]['rules'], ['type' => 'disallow', 'path' => $path]);
                }
            }
        }

        return $this;
    }

    public function sitemap(string $url): self
    {
        if (! in_array($url, $this->sitemaps, true)) {
            $this->sitemaps[] = $url;
        }

        return $this;
    }

    public function host(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function render(): string
    {
        $lines = [];

        if ($this->groups === []) {
            // A useful, safe default: allow everyone everything.
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow:';
        }

        foreach ($this->groups as $index => $group) {
            if ($index > 0) {
                $lines[] = '';
            }
            foreach ($group['agents'] as $agent) {
                $lines[] = 'User-agent: '.$agent;
            }
            foreach ($group['rules'] as $rule) {
                $label = $rule['type'] === 'allow' ? 'Allow' : 'Disallow';
                $lines[] = $label.': '.$rule['path'];
            }
            if (isset($group['crawlDelay'])) {
                $lines[] = 'Crawl-delay: '.$group['crawlDelay'];
            }
        }

        if ($this->host !== null) {
            $lines[] = '';
            $lines[] = 'Host: '.$this->host;
        }

        if ($this->sitemaps !== []) {
            $lines[] = '';
            foreach ($this->sitemaps as $url) {
                $lines[] = 'Sitemap: '.$url;
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Round-trip: get a {@see RobotsPolicy} evaluator for the rendered file, so
     * the same source of truth answers "may a scraper fetch this?".
     */
    public function policy(): RobotsPolicy
    {
        return RobotsPolicy::parse($this->render());
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $issues = [];

        if ($this->groups === []) {
            $issues[] = 'robots.txt has no user-agent groups';
        }

        foreach ($this->groups as $group) {
            if ($group['agents'] === []) {
                $issues[] = 'a robots.txt group has no User-agent';
            }
        }

        // Defensive: a protected path must never surface as an Allow.
        foreach ($this->groups as $group) {
            foreach ($group['rules'] as $rule) {
                if ($rule['type'] === 'allow' && $this->isProtected($rule['path'])) {
                    $issues[] = sprintf('protected path "%s" leaked into an Allow rule', $rule['path']);
                }
            }
        }

        return $issues;
    }

    private function ensureGroup(): int
    {
        if ($this->cursor === null) {
            $this->userAgent('*');
        }

        return $this->cursor ?? array_key_last($this->groups);
    }

    private function isProtected(string $path): bool
    {
        return in_array($path, $this->protected, true);
    }
}
