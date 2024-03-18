<?php

namespace Framework\Routing;

use Framework\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRouter();
        $this->registerCallableDispatcher();
        $this->registerControllerDispatcher();
    }

    /**
     * Register the router instance.
     *
     * @return void
     */
    protected function registerRouter()
    {
        $this->app->singleton('router', function ($app) {
            return new Router($app);
        });
    }

    /**
     * Register the callable dispatcher.
     *
     * @return void
     */
    protected function registerCallableDispatcher()
    {
        $this->app->singleton(CallableDispatcher::class, function ($app) {
            return new CallableDispatcher($app);
        });
    }

    /**
     * Register the controller dispatcher.
     *
     * @return void
     */
    protected function registerControllerDispatcher()
    {
        $this->app->singleton(ControllerDispatcher::class, function ($app) {
            return new ControllerDispatcher($app);
        });
    }
}
