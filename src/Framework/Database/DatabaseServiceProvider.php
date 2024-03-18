<?php

namespace Framework\Database;

use Framework\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConnectionServices();
    }

    /**
     * Register the primary database bindings.
     *
     * @return void
     */
    protected function registerConnectionServices()
    {
        $this->app->singleton('db', function ($app) {
            return new DatabaseManager($app);
        });

        $this->app->bind('db.connection', function ($app) {
            return $app['db']->connection();
        });
    }
}
