<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Laravel\Http;

use Illuminate\Http\Request;
use ParticleAcademy\XFiles\Registry;
use Symfony\Component\HttpFoundation\Response;

use function abort;
use function config;
use function ltrim;
use function response;

/**
 * Single-action controller serving one well-known file per request.
 *
 * Routes are registered as a class-string action (not a closure) so that
 * `php artisan route:cache` / `optimize` can serialize them. The file to serve
 * is resolved from the {@see Registry} by the CURRENT request path, so the body
 * always reflects the live Registry rather than a copy snapshotted at boot.
 */
final class ServeWellKnownFile
{
    public function __invoke(Request $request, Registry $registry): Response
    {
        $path = $request->path();

        // Registry keys paths with a leading slash (see Registry::normalize()).
        // Match the normalized path first, then the raw path as a fallback.
        $served = $registry->get('/'.ltrim($path, '/')) ?? $registry->get($path);

        if ($served === null) {
            abort(404);
        }

        $cache = (int) config('x-files.cache', 3600);

        $headers = ['Content-Type' => $served->contentType()];
        if ($cache > 0) {
            $headers['Cache-Control'] = 'public, max-age='.$cache;
        }

        return response($served->render(), 200, $headers);
    }
}
