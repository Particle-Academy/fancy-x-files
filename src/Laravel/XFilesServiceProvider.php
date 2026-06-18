<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ParticleAcademy\XFiles\Laravel\Console\CheckCommand;
use ParticleAcademy\XFiles\Laravel\Http\ServeWellKnownFile;
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

        foreach ($registry->all() as $file) {
            $path = ltrim($file->path(), '/');

            // A class-string action (not a closure) so `route:cache` / `optimize`
            // can serialize it. The controller re-resolves the file + cache config
            // at REQUEST time from the live Registry, matching the request path.
            Route::get($path, ServeWellKnownFile::class)
                ->name('x-files.'.str_replace(['/', '.'], ['-', '-'], $path));
        }
    }
}
