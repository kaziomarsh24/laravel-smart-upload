<?php

namespace KaziOmar\SmartUpload;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use KaziOmar\SmartUpload\Commands\PruneUploadsCommand;

/**
 * Class SmartUploadServiceProvider
 *
 * Bootstraps config publishing, bindings, console commands, and auto-scheduling.
 */
class SmartUploadServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/smart-upload.php', 'smart-upload');

        $this->app->bind('smart-upload', function () {
            return new SmartUploader();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/smart-upload.php' => config_path('smart-upload.php'),
            ], 'smart-upload-config');

            $this->commands([
                PruneUploadsCommand::class,
            ]);

            // Auto-schedule daily pruning if enabled in configuration
            if (config('smart-upload.auto_schedule_prune', true)) {
                $this->app->booted(function () {
                    $schedule = $this->app->make(Schedule::class);
                    $schedule->command('smart-upload:prune')->daily();
                });
            }
        }
    }
}
