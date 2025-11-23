<?php
namespace BugFinder\VersionUpgrade;

use Illuminate\Support\ServiceProvider;
use BugFinder\VersionUpgrade\Console\InjectConfigCommand;

class VersionUpgradeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/version-upgrade.php', 'version-upgrade');
        // Bind service
        $this->app->singleton(\BugFinder\VersionUpgrade\Services\UpdateService::class, function($app){
            return new \BugFinder\VersionUpgrade\Services\UpdateService();
        });
    }

    public function boot()
    {
		// Load migration
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load routes & views
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'version-upgrade');

        // Publish resources
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/version-upgrade'),
        ], 'version-upgrade-views');

        $this->publishes([
            __DIR__.'/../config/version-upgrade.php' => config_path('version-upgrade.php'),
        ], 'version-upgrade-config');

        // Merge menu into runtime config so package works out-of-the-box
        $this->appendMenuToRuntimeConfig();

        // Register artisan command for permanent injection
        if ($this->app->runningInConsole()) {
            $this->commands([InjectConfigCommand::class]);
        }
    }

    protected function appendMenuToRuntimeConfig()
    {
        $menu = config('version-upgrade.menu', []);
        $settings = config('generalsettings.settings', []);
        if (is_array($menu) && is_array($settings)) {
            $merged = array_merge($settings, $menu);
            config(['generalsettings.settings' => $merged]);
        }
    }
}
