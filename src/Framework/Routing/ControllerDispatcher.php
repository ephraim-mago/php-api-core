<?php

namespace Framework\Routing;

use Framework\Container\Container;

class ControllerDispatcher
{
    use ResolvesRouteDependencies;
    
    /**
     * The container instance.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * Create a new controller dispatcher instance.
     *
     * @param  \Framework\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given controller and method.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return mixed
     */
    public function dispatch(Route $route, $controller, $method)
    {
        $parameters = $this->resolveParameters($route, $controller, $method);

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }

        return $controller->{$method}(...array_values($parameters));
    }

    /**
     * Resolve the parameters for the controller.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return array
     */
    protected function resolveParameters(Route $route, $controller, $method)
    {
        return $this->resolveClassMethodDependencies(
            $route->parametersWithoutNulls(), $controller, $method
        );
    }
}
