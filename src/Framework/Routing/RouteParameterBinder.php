<?php

namespace Framework\Routing;

class RouteParameterBinder
{
    /**
     * The route instance.
     *
     * @var \Framework\Routing\Route
     */
    protected $route;

    /**
     * Create a new Route parameter binder instance.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    public function __construct($route)
    {
        $this->route = $route;
    }

    /**
     * Get the parameters for the route.
     *
     * @param  \Framework\Http\Request  $request
     * @return array
     */
    public function parameters($request)
    {
        $parameters = $this->bindPathParameters($request);

        return $this->replaceDefaults($parameters);
    }

    /**
     * Get the parameter matches for the path portion of the URI.
     *
     * @param  \Framework\Http\Request  $request
     * @return array
     */
    protected function bindPathParameters($request)
    {
        $path = rtrim($request->path(), '/') ?: '/';

        preg_match($this->route->compiled, $path, $matches);

        return $this->matchToKeys(array_slice($matches, 1));
    }

    /**
     * Combine a set of parameter matches with the route's keys.
     *
     * @param  array  $matches
     * @return array
     */
    protected function matchToKeys(array $matches)
    {
        if (empty($parameterNames = $this->route->parameterNames())) {
            return [];
        }

        $parameters = [];

        foreach ($parameterNames as $key => $value) {
            $parameters[$value] = $matches[$key];
        }

        return array_filter($parameters, function ($value) {
            return is_string($value) && strlen($value) > 0;
        });
    }

    /**
     * Replace null parameters with their defaults.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function replaceDefaults(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            $parameters[$key] = $value ?? $this->route->defaults[$key] ?? null;
        }

        foreach ($this->route->defaults as $key => $value) {
            if (! isset($parameters[$key])) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }
}
