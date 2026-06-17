<?php

declare(strict_types=1);

use ParticleAcademy\XFiles\Files\HumansTxt;
use ParticleAcademy\XFiles\Files\LlmsTxt;
use ParticleAcademy\XFiles\Files\RobotsTxt;
use ParticleAcademy\XFiles\Files\SecurityTxt;
use ParticleAcademy\XFiles\Files\Sitemap;
use ParticleAcademy\XFiles\Registry;

return [

    /*
    |--------------------------------------------------------------------------
    | Enable
    |--------------------------------------------------------------------------
    | Master switch. When false, the package registers no routes — useful to
    | disable in environments that serve these files some other way.
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache (seconds)
    |--------------------------------------------------------------------------
    | Cache-Control max-age applied to every served well-known file. 0 disables.
    */
    'cache' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    | Define your well-known files here. Two options:
    |
    |  1. Set 'files' to a callable (or invokable class string) that receives a
    |     Registry and registers WellKnownFile instances onto it. This is the
    |     most expressive option and gives you the full builder API, including
    |     RobotsTxt::protect() which keeps a path Disallowed for EVERY bot.
    |
    |  2. Leave 'files' null and the provider builds a Registry from the simple
    |     arrays below.
    |
    | Whichever you use, the Registry is bound as a singleton you can resolve.
    */

    'files' => static function (Registry $registry): void {
        $registry->add(
            RobotsTxt::make()
                ->userAgent('*')
                ->disallow('/')
                ->allowAll()
                // protect() Disallows these for EVERY group and bars any Allow.
                ->protect('/admin', '/internal')
                ->sitemap(rtrim((string) config('app.url'), '/').'/sitemap.xml')
        );

        $registry->add(
            SecurityTxt::make()
                ->contact('mailto:security@'.parse_url((string) config('app.url'), PHP_URL_HOST))
                ->expires(new DateTimeImmutable('+1 year'))
        );

        $registry->add(
            LlmsTxt::make((string) config('app.name', 'App'))
                ->summary('Machine-readable index of this site for LLMs.')
        );

        $registry->add(
            HumansTxt::make()->section('TEAM', [
                ['label' => 'Site', 'value' => (string) config('app.url')],
            ])
        );

        $registry->add(
            Sitemap::make()->url(rtrim((string) config('app.url'), '/').'/')
        );
    },

];
