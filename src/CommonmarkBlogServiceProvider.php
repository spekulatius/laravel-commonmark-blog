<?php

namespace Spekulatius\LaravelCommonmarkBlog;

use Spekulatius\LaravelCommonmarkBlog\Commands\BuildSite;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class CommonmarkBlogServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();

        // Register the command if we are using the application via the CLI
        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildSite::class,
            ]);
        }

        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Load the configuration
        $this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');
    }

    /**
     * Publishes the config
     *
     * @return void
     */
    public function publishConfig()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/blog.php' => config_path('blog.php'),
            ], 'blog-config');
        }
    }

    /**
     * The blog requires certain service providers to be loaded.
     *
     * @return array
     */
    public function provides()
    {
        return [
            self::class,
            \romanzipp\Seo\Providers\SeoServiceProvider::class,
        ];
    }
}
