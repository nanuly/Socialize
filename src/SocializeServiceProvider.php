<?php

namespace Nanuly\Socialize;

use Illuminate\Support\ServiceProvider;

class SocializeServiceProvider extends ServiceProvider
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
        $this->app->bindShared('Nanuly\Socialize\Contracts\Factory', function ($app) {
            return new SocializeManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['Nanuly\Socialize\Contracts\Factory'];
    }
}
