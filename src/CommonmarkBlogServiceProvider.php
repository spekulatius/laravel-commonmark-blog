<?php

namespace Spekulatius\LaravelCommonmarkBlog;

use Spekulatius\LaravelCommonmarkBlog\Commands\BuildBlog;
use Illuminate\Support\ServiceProvider;

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
                BuildBlog::class,
            ]);
        }
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
