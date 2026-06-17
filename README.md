# fancy-x-files

Headless manager for the **well-known files** every modern web app owes both
bots *and* agents — `robots.txt`, `.well-known/security.txt`, `llms.txt`,
`humans.txt`, `sitemap.xml`, and an agents/AI manifest. Define them **once** in
code or config, serve them consistently, and validate them in CI.

- **Zero runtime dependencies.** The core (`ParticleAcademy\XFiles\`) is plain
  PHP 8.2+. The Laravel adapter is optional and auto-discovered.
- **A default-open robots evaluator** (`RobotsPolicy`) so your scraper can
  *honor* a target site's robots.txt with correct precedence — the same honest
  implementation everywhere.
- **A leak-proof `robots.txt` builder.** `protect('/admin')` makes it
  *structurally impossible* to accidentally `Allow:` a protected path for one
  bot. (See the guarantee below — it's the bug this package was born from.)

## Install

```bash
composer require particle-academy/fancy-x-files
```

## Core: build the files

Each file is a small fluent builder implementing `WellKnownFile`
(`path()`, `contentType()`, `render()`, `validate()`). Collect them in a
`Registry` — the single source of truth.

```php
use ParticleAcademy\XFiles\Registry;
use ParticleAcademy\XFiles\Files\{RobotsTxt, SecurityTxt, LlmsTxt};

$registry = new Registry;

$registry->add(
    RobotsTxt::make()
        ->userAgent('*')->disallow('/')->allowAll()
        ->userAgent('GPTBot')->allow('/')        // be generous to the AI bot…
        ->protect('/admin', '/internal')         // …but /admin can NEVER leak
        ->sitemap('https://example.com/sitemap.xml')
);

$registry->add(
    SecurityTxt::make()
        ->contact('mailto:security@example.com')
        ->expires(new DateTimeImmutable('+1 year'))   // required, must be future
        ->policy('https://example.com/security-policy')
);

$registry->add(
    LlmsTxt::make('Example')
        ->summary('Machine-readable index of this site for LLMs.')
        ->section('Docs', [
            ['title' => 'Getting started', 'url' => 'https://example.com/docs', 'notes' => 'start here'],
            ['title' => 'API reference',   'url' => 'https://example.com/api'],
        ])
);

echo $registry->render('/robots.txt');
```

Renders:

```
User-agent: *
Disallow: /internal
Disallow: /admin
Disallow: /
Disallow:

User-agent: GPTBot
Disallow: /internal
Disallow: /admin
Allow: /

Sitemap: https://example.com/sitemap.xml
```

Every group — including the permissive `GPTBot` one — carries a `Disallow:` for
each protected path, and `/admin` never appears as an `Allow`.

Other builders: `HumansTxt`, `Sitemap` (valid `<urlset>` XML), `AgentsTxt`
(served at `/ai.txt`, or `->at('/.well-known/agents.md')`).

## The `/admin` can't leak — guaranteed

The motivating bug: a hand-rolled `robots.txt` listed permissive per-AI-bot
`Allow:` blocks and `/admin` slipped into one of them, exposing it to a crawler.

`RobotsTxt::protect(...$paths)` makes that impossible:

- It adds a `Disallow:` for each protected path to **every** group — those
  already defined **and** any group added later.
- It **drops** any existing `Allow:` for a protected path and **refuses** to add
  new ones (`->allow('/admin')` after `->protect('/admin')` is a silent no-op).
- `validate()` flags any protected path that somehow surfaced as an `Allow`.

So even a copy-pasted "allow everything" block for `GPTBot` can't reopen
`/admin`. The robots **precedence rule** (longest match wins; `Allow` beats
`Disallow` at equal length) means a blanket `Allow: /` would otherwise override
a shorter `Disallow` — `protect()` adds an equally-or-more specific `Disallow`
to neutralize that, and the evaluator agrees.

## Scraper side: honor a target's robots.txt

Use `RobotsPolicy` (or the `HonorsRobots` guard) so your crawler obeys robots
correctly and **default-open** (no matching rule ⇒ allowed):

```php
use ParticleAcademy\XFiles\Robots\RobotsPolicy;
use ParticleAcademy\XFiles\Laravel\HonorsRobots;

$policy = RobotsPolicy::parse($targetRobotsTxt);
if ($policy->allowed('/some/page', 'MyCrawler')) {
    // fetch it
}

// one-shot guard, framework-free:
HonorsRobots::allows($targetRobotsTxt, '/some/page', 'MyCrawler'); // bool
```

Precedence implemented: most-specific (longest) matching rule wins; `Allow`
beats `Disallow` at equal specificity (Google's rule); a UA-specific group
overrides `*`; `*` and trailing `$` wildcards are honored. A built `RobotsTxt`
round-trips via `$robots->policy()`.

## Laravel quickstart

The service provider auto-discovers. Publish the config:

```bash
php artisan vendor:publish --tag=x-files-config
```

Define your files in `config/x-files.php` (a callback receiving a `Registry`):

```php
'files' => static function (Registry $registry): void {
    $registry->add(
        RobotsTxt::make()->userAgent('*')->disallow('/')->allowAll()
            ->protect('/admin')
            ->sitemap(config('app.url').'/sitemap.xml')
    );
    // …security.txt, llms.txt, sitemap.xml, etc.
},
```

The provider binds `Registry::class` as a singleton and registers a `GET` route
for every file at its `path()`, returning `render()` with the right
`Content-Type` and `Cache-Control` headers. So `GET /robots.txt`,
`GET /.well-known/security.txt`, `GET /sitemap.xml`, `GET /llms.txt`, … all work
with no controllers.

Validate everything in CI:

```bash
php artisan x-files:check   # prints a table, non-zero exit on any error
```

## Validation

- `Registry::validate()` → `array<path, list<issue>>` (empty ⇒ all valid).
- `SecurityTxt`: requires ≥1 `Contact` and a future `Expires`.
- `Sitemap`: every URL needs a `<loc>`.
- `RobotsTxt`: needs at least one group; flags a leaked protected `Allow`.
- `LlmsTxt` / `AgentsTxt`: require a title.

## Testing & formatting

```bash
composer test    # pest
composer lint    # pint --test
```

## License

MIT.
