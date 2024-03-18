<?php

namespace Framework\Core\Bootstrap;

use Framework\Support\Facades\Facade;
use Framework\Contracts\Core\Application;

class RegisterFacades
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        Facade::clearResolvedInstances();

        Facade::setFacadeApplication($app);
    }
}
