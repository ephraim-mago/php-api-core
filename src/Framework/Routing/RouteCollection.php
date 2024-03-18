<?php

namespace Framework\Routing;

use Framework\Http\Request;
use Framework\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RouteCollection
{
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * A flattened array of all of the routes.
     *
     * @var \Framework\Routing\Route[]
     */
    protected $allRoutes = [];

    /**
     * Add a Route instance to the collection.
     *
     * @param  \Framework\Routing\Route  $route
     * @return \Framework\Routing\Route
     */
    public function add(Route $route)
    {
        $this->addToCollections($route);

        return $route;
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function addToCollections($route)
    {
        $uri = $route->uri();

        foreach ($route->methods() as $method) {
            $this->routes[$method][$uri] = $route;
        }

        $this->allRoutes[$method . $uri] = $route;
    }

    /**
     * Find the first route matching a given request.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Framework\Routing\Route
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function match(Request $request)
    {
        $routes = $this->get($request->getMethod());

        $route = $this->matchAgainstRoutes($routes, $request);

        return $this->handleMatchedRoute($request, $route);
    }

    /**
     * Handle the matched route.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Framework\Routing\Route|null  $route
     * @return \Framework\Routing\Route
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function handleMatchedRoute(Request $request, $route)
    {
        if (!is_null($route)) {
            return $route->bind($request);
        }

        // If no route was found we will now check if a matching route is specified by
        // another HTTP verb. If it is we will need to throw a MethodNotAllowed and
        // inform the user agent of which HTTP verb it should use for this route.
        $others = $this->checkForAlternateVerbs($request);

        if (count($others) > 0) {
            return $this->getRouteForMethods($request, $others);
        }

        throw new NotFoundHttpException("Route no found.");
    }

    /**
     * Determine if any routes match on another HTTP verb.
     *
     * @param  \Framework\Http\Request  $request
     * @return array
     */
    protected function checkForAlternateVerbs($request)
    {
        $methods = array_diff(Router::$verbs, [$request->getMethod()]);

        // Here we will spin through all verbs except for the current request verb and
        // check to see if any routes respond to them. If they do, we will return a
        // proper error response with the correct headers on the response string.
        return array_values(array_filter(
            $methods,
            function ($method) use ($request) {
                return !is_null($this->matchAgainstRoutes($this->get($method), $request, false));
            }
        ));
    }

    /**
     * Determine if a route in the array matches the request.
     *
     * @param  \Framework\Routing\Route[]  $routes
     * @param  \Framework\Http\Request  $request
     * @param  bool  $includingMethod
     * @return \Framework\Routing\Route|null
     */
    protected function matchAgainstRoutes(array $routes, $request, $includingMethod = true)
    {
        foreach ($routes as $route) {
            if ($route->matches($request, $includingMethod)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Get a route (if necessary) that responds when other available methods are present.
     *
     * @param  \Framework\Http\Request  $request
     * @param  string[]  $methods
     * @return \Framework\Routing\Route
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function getRouteForMethods($request, array $methods)
    {
        if ($request->isMethod('OPTIONS')) {
            return (new Route('OPTIONS', $request->getPathInfo(), function () use ($methods) {
                return new Response('', 200, ['Allow' => implode(',', $methods)]);
            }))->bind($request);
        }

        $this->methodNotAllowed($methods, $request->getMethod());
    }

    /**
     * Throw a method not allowed HTTP exception.
     *
     * @param  array  $others
     * @param  string  $method
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function methodNotAllowed(array $others, $method)
    {
        throw new MethodNotAllowedHttpException(
            $others,
            sprintf(
                'The %s method is not supported for this route. Supported methods: %s.',
                $method,
                implode(', ', $others)
            )
        );
    }

    /**
     * Get routes from the collection by method.
     *
     * @param  string|null  $method
     * @return \Framework\Routing\Route[]
     */
    public function get($method = null)
    {
        return is_null($method) ? $this->getRoutes() : $this->routes[$method] ?? [];
    }

    /**
     * Get all of the routes in the collection.
     *
     * @return \Framework\Routing\Route[]
     */
    public function getRoutes()
    {
        return array_values($this->allRoutes);
    }
}
