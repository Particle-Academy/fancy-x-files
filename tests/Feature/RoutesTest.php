<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ParticleAcademy\XFiles\Files\LlmsTxt;
use ParticleAcademy\XFiles\Files\RobotsTxt;
use ParticleAcademy\XFiles\Files\SecurityTxt;
use ParticleAcademy\XFiles\Files\Sitemap;
use ParticleAcademy\XFiles\Laravel\Http\ServeWellKnownFile;
use ParticleAcademy\XFiles\Registry;

beforeEach(function (): void {
    // Override the default config, then drop the singleton so the next resolve
    // rebuilds the Registry from this test's files. Routes for every well-known
    // path already exist from boot; their closures re-resolve at request time.
    config()->set('x-files.files', function (Registry $registry): void {
        $registry
            ->add(
                RobotsTxt::make()
                    ->userAgent('*')->disallow('/')->allowAll()
                    ->userAgent('GPTBot')->allow('/')
                    ->protect('/admin')
                    ->sitemap('https://example.test/sitemap.xml')
            )
            ->add(SecurityTxt::make()->contact('mailto:sec@example.test')->expires(new DateTimeImmutable('+1 year')))
            ->add(LlmsTxt::make('Example')->summary('hi'))
            ->add(Sitemap::make()->url('https://example.test/'));
    });

    $this->app->forgetInstance(Registry::class);
});

it('serves robots.txt with the right content type and body', function (): void {
    $res = $this->get('/robots.txt');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/plain');
    $res->assertSee('User-agent: *', false);
    $res->assertSee('Sitemap: https://example.test/sitemap.xml', false);
});

it('never leaks /admin as an Allow for the AI bot over the real route', function (): void {
    $body = $this->get('/robots.txt')->getContent();

    expect($body)->not->toMatch('/Allow:\s*\/admin\b/')
        ->and($body)->toContain('Disallow: /admin');
});

it('serves security.txt at the .well-known path', function (): void {
    $res = $this->get('/.well-known/security.txt');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/plain');
    $res->assertSee('Contact: mailto:sec@example.test', false);
});

it('serves sitemap.xml as application/xml', function (): void {
    $res = $this->get('/sitemap.xml');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/xml');
    $res->assertSee('<urlset', false);
});

it('serves llms.txt', function (): void {
    $this->get('/llms.txt')->assertOk()->assertSee('# Example', false);
});

it('registers cacheable class-string route actions (no closures)', function (): void {
    // Closures capturing the container fail route:cache with
    // "Serialization of 'WeakMap' is not allowed". Every x-files route must use
    // a class-string action so `php artisan route:cache` / optimize can serialize it.
    $xfiles = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-files.'));

    expect($xfiles)->not->toBeEmpty();

    $xfiles->each(function ($route): void {
        expect($route->getActionName())->toBe(ServeWellKnownFile::class);
    });
});

it('applies cache headers', function (): void {
    config()->set('x-files.cache', 1200);
    // Rebuild app so the singleton + routes pick up config (handled by fresh test app).
    $res = $this->get('/robots.txt');
    expect($res->headers->get('Cache-Control'))->toContain('max-age=1200');
});
