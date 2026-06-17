<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ParticleAcademy\XFiles\Laravel\Console\CheckCommand;
use ParticleAcademy\XFiles\Registry;

/**
 * Laravel adapter: builds a {@see Registry} from config and serves every
 * registered well-known file at its own path with the right content type and
 * cache headers.
 */
final class XFilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/x-files.php', 'x-files');

        $this->app->singleton(Registry::class, function ($app): Registry {
            $registry = new Registry;

            $files = $app['config']->get('x-files.files');

            if (is_callable($files)) {
                $files($registry);
            } elseif (is_string($files) && class_exists($files)) {
                $instance = $app->make($files);
                if (is_callable($instance)) {
                    $instance($registry);
                }
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/x-files.php' => $this->app->configPath('x-files.php'),
            ], 'x-files-config');

            $this->commands([CheckCommand::class]);
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        if (! $this->app['config']->get('x-files.enabled', true)) {
            return;
        }

        /** @var Registry $registry */
        $registry = $this->app->make(Registry::class);
        $app = $this->app;

        foreach ($registry->all() as $file) {
            $path = ltrim($file->path(), '/');

            // The closure resolves the file + cache config at REQUEST time, so the
            // served body always reflects the current Registry rather than a copy
            // snapshotted at boot.
            Route::get($path, function () use ($app, $file): mixed {
                /** @var Registry $registry */
                $registry = $app->make(Registry::class);
                $served = $registry->get($file->path()) ?? $file;
                $cache = (int) $app['config']->get('x-files.cache', 3600);

                $headers = ['Content-Type' => $served->contentType()];
                if ($cache > 0) {
                    $headers['Cache-Control'] = 'public, max-age='.$cache;
                }

                return response($served->render(), 200, $headers);
            })->name('x-files.'.str_replace(['/', '.'], ['-', '-'], $path));
        }
    }
}
