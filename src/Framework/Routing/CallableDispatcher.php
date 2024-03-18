<?php

namespace Framework\Routing;

use ReflectionFunction;
use Framework\Container\Container;

class CallableDispatcher
{
    use ResolvesRouteDependencies;
    
    /**
     * The container instance.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * Create a new callable dispatcher instance.
     *
     * @param  \Framework\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given callable.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  callable  $callable
     * @return mixed
     */
    public function dispatch(Route $route, $callable)
    {
        return $callable(...array_values($this->resolveParameters($route, $callable)));
    }

    /**
     * Resolve the parameters for the callable.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  callable  $callable
     * @return array
     */
    protected function resolveParameters(Route $route, $callable)
    {
        return $this->resolveMethodDependencies($route->parametersWithoutNulls(), new ReflectionFunction($callable));
    }
}
