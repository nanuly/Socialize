<?php

namespace Nanuly\socialize;

use Illuminate\Support\ServiceProvider;

class socializeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bindShared('Nanuly\socialize\Contracts\Factory', function ($app) {
            return new socializeManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['Nanuly\socialize\Contracts\Factory'];
    }
}
