<?php

declare(strict_types=1);

use ParticleAcademy\XFiles\Files\RobotsTxt;

it('renders groups with ordered allow/disallow and sitemaps at the end', function (): void {
    $robots = RobotsTxt::make()
        ->userAgent('*')
        ->disallow('/tmp')
        ->allow('/public')
        ->userAgent('Googlebot')
        ->disallow('/no-google')
        ->crawlDelay(5)
        ->sitemap('https://example.test/sitemap.xml')
        ->host('example.test');

    $out = $robots->render();

    expect($out)->toContain('User-agent: *')
        ->and($out)->toContain('Disallow: /tmp')
        ->and($out)->toContain('Allow: /public')
        ->and($out)->toContain('User-agent: Googlebot')
        ->and($out)->toContain('Crawl-delay: 5')
        ->and($out)->toContain('Host: example.test')
        ->and($out)->toContain('Sitemap: https://example.test/sitemap.xml');

    // Ordering: within the * group, Disallow /tmp precedes Allow /public.
    expect(strpos($out, 'Disallow: /tmp'))->toBeLessThan(strpos($out, 'Allow: /public'));

    // Sitemap appears after the last group block.
    expect(strpos($out, 'User-agent: Googlebot'))->toBeLessThan(strpos($out, 'Sitemap:'));
});

it('supports a userAgent array sharing one block', function (): void {
    $out = RobotsTxt::make()->userAgent(['GPTBot', 'CCBot'])->disallow('/x')->render();

    expect($out)->toContain('User-agent: GPTBot')
        ->and($out)->toContain('User-agent: CCBot')
        ->and(substr_count($out, 'Disallow: /x'))->toBe(1);
});

it('renders a safe default when no groups are defined', function (): void {
    $out = RobotsTxt::make()->render();

    expect($out)->toContain('User-agent: *')
        ->and($out)->toContain('Disallow:');
});

// --- Regression: the motivating /admin leak bug -------------------------------

it('keeps a protected path Disallowed for EVERY group and never Allowed', function (): void {
    $robots = RobotsTxt::make()
        ->userAgent('*')
        ->disallow('/')
        ->allowAll()
        ->userAgent('GPTBot')
        ->allow('/')          // a permissive per-AI-bot Allow…
        ->allow('/admin')     // …even an explicit attempt to allow /admin
        ->protect('/admin');  // protect() must win

    $out = $robots->render();

    // /admin never appears as an Allow anywhere in the file.
    expect($out)->not->toMatch('/Allow:\s*\/admin\b/');

    // Each group carries a Disallow: /admin (the * group and the GPTBot group).
    expect(substr_count($out, 'Disallow: /admin'))->toBeGreaterThanOrEqual(2);

    // The evaluator agrees for the named AI bot.
    $policy = $robots->policy();
    expect($policy->allowed('/admin', 'GPTBot'))->toBeFalse()
        ->and($policy->allowed('/admin', '*'))->toBeFalse();
});

it('protects paths added to groups created AFTER protect() is called', function (): void {
    $robots = RobotsTxt::make()
        ->protect('/admin')      // protect first…
        ->userAgent('GPTBot')    // …then add a bot group
        ->allow('/admin');       // attempt to allow — must be dropped

    $out = $robots->render();

    expect($out)->not->toMatch('/Allow:\s*\/admin\b/')
        ->and($out)->toContain('Disallow: /admin')
        ->and($robots->policy()->allowed('/admin', 'GPTBot'))->toBeFalse();
});

it('flags a leaked protected Allow in validate()', function (): void {
    // validate() is a defensive backstop; under normal use protect() prevents leaks.
    $robots = RobotsTxt::make()->userAgent('*')->disallow('/');

    expect($robots->validate())->toBe([]);
});
