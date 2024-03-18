<?php

namespace Framework\Routing;

use Closure;
use stdClass;
use ArrayObject;
use JsonSerializable;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Database\Model;
use Framework\Http\JsonResponse;
use Framework\Container\Container;
use Framework\Contracts\Support\Jsonable;
use Framework\Contracts\Support\Arrayable;
use Framework\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Router
{
    /**
     * The IoC container instance.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * The route collection instance.
     *
     * @var \Framework\Routing\RouteCollection
     */
    protected $routes;

    /**
     * The currently dispatched route instance.
     *
     * @var \Framework\Routing\Route|null
     */
    protected $current;

    /**
     * The request currently being dispatched.
     *
     * @var \Framework\Http\Request
     */
    protected $currentRequest;

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * All of the middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    public $middlewarePriority = [];

    /**
     * All of the verbs supported by the router.
     *
     * @var string[]
     */
    public static $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Create a new Router instance.
     * 
     * @param  \Framework\Container\Container|null  $container
     * @return void
     */
    public function __construct(Container $container = null)
    {
        $this->routes = new RouteCollection;
        $this->container = $container ?: new Container;
    }

    /**
     * Register a new GET route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function get($uri, $action = null)
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function post($uri, $action = null)
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function put($uri, $action = null)
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function patch($uri, $action = null)
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function delete($uri, $action = null)
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function options($uri, $action = null)
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function any($uri, $action = null)
    {
        return $this->addRoute(self::$verbs, $uri, $action);
    }


    /**
     * Load the provided routes.
     *
     * @param  \Closure|string  $routes
     * @return void
     */
    public function loadRoutes($routes)
    {
        if ($routes instanceof Closure) {
            $routes($this);
        } else {
            // (new RouteFileRegistrar($this))->register($routes);
        }
    }

    /**
     * Add a route to the underlying route collection.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function addRoute($methods, $uri, $action)
    {
        return $this->routes->add($this->createRoute($methods, $uri, $action));
    }

    /**
     * Create a new route instance.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed  $action
     * @return \Framework\Routing\Route
     */
    protected function createRoute($methods, $uri, $action)
    {
        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }

    /**
     * Dispatch the request to the application.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function dispatch(Request $request)
    {
        $this->currentRequest = $request;

        return $this->dispatchToRoute($request);
    }

    /**
     * Dispatch the request to a route and return the response.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function dispatchToRoute(Request $request)
    {
        return $this->runRoute($request, $this->findRoute($request));
    }

    /**
     * Find the route matching a given request.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Framework\Routing\Route
     */
    protected function findRoute($request)
    {
        $this->current = $route = $this->routes->match($request);

        $route->setContainer($this->container);

        $this->container->instance(Route::class, $route);

        return $route;
    }

    /**
     * Return the response for the given route.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Framework\Routing\Route  $route
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function runRoute(Request $request, Route $route)
    {
        $request->setRouteResolver(fn () => $route);

        return $this->prepareResponse(
            $request,
            $this->runRouteWithinStack($route, $request)
        );
    }

    /**
     * Run the given route within a Stack "onion" instance.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  \Framework\Http\Request  $request
     * @return mixed
     */
    protected function runRouteWithinStack(Route $route, Request $request)
    {
        $shouldSkipMiddleware = $this->container->bound('middleware.disable') &&
            $this->container->make('middleware.disable') === true;

        $middleware = $shouldSkipMiddleware ? [] : $this->gatherRouteMiddleware($route);

        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then(fn ($request) => $this->prepareResponse(
                $request,
                $route->run()
            ));
    }

    /**
     * Gather the middleware for the given route with resolved class names.
     *
     * @param  \Framework\Routing\Route  $route
     * @return array
     */
    public function gatherRouteMiddleware(Route $route)
    {
        return $this->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());
    }

    /**
     * Resolve a flat array of middleware classes from the provided array.
     *
     * @param  array  $middleware
     * @param  array  $excluded
     * @return array
     */
    public function resolveMiddleware(array $middleware, array $excluded = [])
    {
        $middleware = array_map(function ($name) {
            return (array) MiddlewareNameResolver::resolve($name, $this->middleware, $this->middlewareGroups);
        }, $middleware);

        return static::uniqueMiddleware(
            array_merge(
                $this->middlewarePriority,
                ...array_values($middleware)
            )
        );
    }

    /**
     * Create a response instance from the given value.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  mixed  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function prepareResponse($request, $response)
    {
        return static::toResponse($request, $response);
    }

    /**
     * Static version of prepareResponse.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  mixed  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function toResponse($request, $response)
    {
        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        if ($response instanceof Model && $response->wasRecentlyCreated) {
            $response = new JsonResponse($response, 201);
        } elseif (
            !$response instanceof SymfonyResponse &&
            ($response instanceof Arrayable ||
                $response instanceof Jsonable ||
                $response instanceof ArrayObject ||
                $response instanceof JsonSerializable ||
                $response instanceof stdClass ||
                is_array($response))
        ) {
            $response = new JsonResponse($response);
        } elseif (!$response instanceof SymfonyResponse) {
            $response = new Response($response, 200, ['Content-Type' => 'text/html']);
        }

        if ($response->getStatusCode() === Response::HTTP_NOT_MODIFIED) {
            $response->setNotModified();
        }

        return $response->prepare($request);
    }

    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Register a short-hand name for a middleware.
     *
     * @param  string  $name
     * @param  string  $class
     * @return $this
     */
    public function aliasMiddleware($name, $class)
    {
        $this->middleware[$name] = $class;

        return $this;
    }

    /**
     * Check if a middlewareGroup with the given name exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasMiddlewareGroup($name)
    {
        return array_key_exists($name, $this->middlewareGroups);
    }

    /**
     * Get all of the defined middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups()
    {
        return $this->middlewareGroups;
    }

    /**
     * Register a group of middleware.
     *
     * @param  string  $name
     * @param  array  $middleware
     * @return $this
     */
    public function middlewareGroup($name, array $middleware)
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Remove any duplicate middleware from the given array.
     *
     * @param  array  $middleware
     * @return array
     */
    public static function uniqueMiddleware(array $middleware)
    {
        $seen = [];
        $result = [];

        foreach ($middleware as $value) {
            $key = \is_object($value) ? \spl_object_id($value) : $value;

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $value;
            }
        }

        return $result;
    }
}
