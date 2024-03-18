<?php

namespace Framework\Core\Bootstrap;

use Framework\Contracts\Core\Application;

class RegisterProviders
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $app->registerConfiguredProviders();
    }
}
