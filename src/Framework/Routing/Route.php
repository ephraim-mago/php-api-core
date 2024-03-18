<?php

namespace Framework\Routing;

use LogicException;
use Framework\Http\Request;
use UnexpectedValueException;
use Framework\Support\Reflector;
use Framework\Container\Container;
use Framework\Http\Exceptions\HttpResponseException;

class Route
{
    /**
     * The URI pattern the route responds to.
     *
     * @var string
     */
    public $uri;

    /**
     * The HTTP methods the route responds to.
     *
     * @var array
     */
    public $methods;

    /**
     * The route action array.
     *
     * @var array
     */
    public $action;

    /**
     * The controller instance.
     *
     * @var mixed
     */
    public $controller;

    /**
     * The default values for the route.
     *
     * @var array
     */
    public $defaults = [];

    /**
     * The array of matched parameters.
     *
     * @var array|null
     */
    public $parameters;

    /**
     * The parameter names for the route.
     *
     * @var array|null
     */
    public $parameterNames;

    /**
     * The array of the matched parameters' original values.
     *
     * @var array
     */
    protected $originalParameters;

    /**
     * The compiled version of the route path.
     *
     * @var string
     */
    public $compiled;

    /**
     * The computed gathered middleware.
     *
     * @var array|null
     */
    public $computedMiddleware;

    /**
     * The router instance used by the route.
     *
     * @var \Framework\Routing\Router
     */
    protected $router;

    /**
     * The container instance used by the route.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * The fields that implicit binding should use for a given parameter.
     *
     * @var array
     */
    protected $bindingFields = [];

    /**
     * Create a new Route instance.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array  $action
     * @return void
     */
    public function __construct($methods, $uri, $action)
    {
        $this->uri = $uri;
        $this->methods = (array) $methods;
        $this->action = $this->parseAction($action);

        if (in_array('GET', $this->methods) && !in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        if (is_array($action)) {
            $this->prefix(isset($action['prefix']) ? $action['prefix'] : '');
        }
    }

    /**
     * Parse the route action into a standard array.
     *
     * @param  callable|array|null  $action
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    protected function parseAction($action)
    {
        $uri = $this->uri();

        if (is_null($action)) {
            return ['uses' => function () use ($uri) {
                throw new LogicException("Route for [{$uri}] has no action.");
            }];
        }

        if (Reflector::isCallable($action, true)) {
            return !is_array($action) ? ['uses' => $action] : [
                'uses' => $action[0] . '@' . $action[1],
                'controller' => $action[0] . '@' . $action[1],
            ];
        } elseif (is_string($action['uses']) && !str_contains($action['uses'], '@')) {
            if (!method_exists($action['uses'], '__invoke')) {
                throw new UnexpectedValueException("Invalid route action: [{$action['uses']}].");
            }

            $action['uses'] = $action['uses'] . '@__invoke';
        }

        return $action;
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    public function run()
    {
        try {
            if ($this->isControllerAction()) {
                return $this->runController();
            }

            return $this->runCallable();
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Checks whether the route's action is a controller.
     *
     * @return bool
     */
    protected function isControllerAction()
    {
        return is_string($this->action['uses']);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    protected function runCallable()
    {
        $callable = $this->action['uses'];

        return $this->container[CallableDispatcher::class]->dispatch($this, $callable);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function runController()
    {
        return $this->controllerDispatcher()->dispatch(
            $this,
            $this->getController(),
            $this->getControllerMethod()
        );
    }

    /**
     * Get the controller instance for the route.
     *
     * @return mixed
     */
    public function getController()
    {
        if (!$this->controller) {
            $class = $this->getControllerClass();

            $this->controller = $this->container->make(ltrim($class, '\\'));
        }

        return $this->controller;
    }

    /**
     * Get the controller class used for the route.
     *
     * @return string
     */
    public function getControllerClass()
    {
        return $this->parseControllerCallback()[0];
    }

    /**
     * Get the controller method used for the route.
     *
     * @return string
     */
    protected function getControllerMethod()
    {
        return $this->parseControllerCallback()[1];
    }

    /**
     * Parse the controller.
     *
     * @return array
     */
    protected function parseControllerCallback()
    {
        $callback = $this->action['uses'];

        return str_contains($callback, '@') ? explode('@', $callback, 2) : [$callback, null];
    }

    /**
     * Determine if the route matches a given request.
     *
     * @param  \Framework\Http\Request  $request
     * @param  bool  $includingMethod
     * @return bool
     */
    public function matches(Request $request, $includingMethod = true)
    {
        $this->compileRoute();

        $path = rtrim($request->path(), '/') ?: '/';

        return preg_match($this->compiled, $path);
    }

    /**
     * Compile the route into a Symfony CompiledRoute instance.
     *
     * @return string
     */
    protected function compileRoute()
    {
        if (!$this->compiled) {
            $regex = str_replace('/', '\/', $this->uri());
            

            $this->compiled = '/^' . preg_replace('/{(\w+)}/', '([a-zA-Z0-9\-]+)', $regex) . '$/';
        }

        return $this->compiled;
    }

    /**
     * Bind the route to a given request for execution.
     *
     * @param  \Framework\Http\Request  $request
     * @return $this
     */
    public function bind(Request $request)
    {
        $this->parameters = (new RouteParameterBinder($this))
            ->parameters($request);

        $this->originalParameters = $this->parameters;

        return $this;
    }

    /**
     * Get the key / value list of parameters for the route.
     *
     * @return array
     *
     * @throws \LogicException
     */
    public function parameters()
    {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        throw new LogicException('Route is not bound.');
    }

    /**
     * Get the key / value list of parameters without null values.
     *
     * @return array
     */
    public function parametersWithoutNulls()
    {
        return array_filter($this->parameters(), fn ($p) => !is_null($p));
    }

    /**
     * Get all of the parameter names for the route.
     *
     * @return array
     */
    public function parameterNames()
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->getDomain() . $this->uri, $matches);

        return array_map(fn ($m) => trim($m, '?'), $matches[1]);
    }

    /**
     * Get the HTTP verbs the route responds to.
     *
     * @return array
     */
    public function methods()
    {
        return $this->methods;
    }

    /**
     * Get the domain defined for the route.
     *
     * @return string|null
     */
    public function getDomain()
    {
        return isset($this->action['domain'])
            ? str_replace(['http://', 'https://'], '', $this->action['domain']) : null;
    }

    /**
     * Add a prefix to the route URI.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function prefix($prefix)
    {
        $prefix ??= '';

        $this->updatePrefixOnAction($prefix);

        $uri = rtrim($prefix, '/') . '/' . ltrim($this->uri, '/');

        return $this->setUri($uri !== '/' ? trim($uri, '/') : $uri);
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Set the URI that the route responds to.
     *
     * @param  string  $uri
     * @return $this
     */
    public function setUri($uri)
    {
        $this->uri = $this->parseUri($uri);

        return $this;
    }

    /**
     * Parse the route URI and normalize / store any implicit binding fields.
     *
     * @param  string  $uri
     * @return string
     */
    protected function parseUri($uri)
    {
        $this->bindingFields = [];

        return tap(RouteUri::parse($uri), function ($uri) {
            $this->bindingFields = $uri->bindingFields;
        })->uri;
    }

    /**
     * Update the "prefix" attribute on the action array.
     *
     * @param  string  $prefix
     * @return void
     */
    protected function updatePrefixOnAction($prefix)
    {
        if (!empty($newPrefix = trim(rtrim($prefix, '/') . '/' . ltrim($this->action['prefix'] ?? '', '/'), '/'))) {
            $this->action['prefix'] = $newPrefix;
        }
    }

    /**
     * Get all middleware, including the ones from the controller.
     *
     * @return array
     */
    public function gatherMiddleware()
    {
        if (! is_null($this->computedMiddleware)) {
            return $this->computedMiddleware;
        }

        $this->computedMiddleware = [];

        return $this->computedMiddleware = Router::uniqueMiddleware(array_merge(
            $this->middleware(), $this->controllerMiddleware()
        ));
    }

    /**
     * Get or set the middlewares attached to the route.
     *
     * @param  array|string|null  $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if (is_null($middleware)) {
            return (array) ($this->action['middleware'] ?? []);
        }

        if (! is_array($middleware)) {
            $middleware = func_get_args();
        }

        foreach ($middleware as $index => $value) {
            $middleware[$index] = (string) $value;
        }

        $this->action['middleware'] = array_merge(
            (array) ($this->action['middleware'] ?? []), $middleware
        );

        return $this;
    }

    /**
     * Get the middleware for the route's controller.
     *
     * @return array
     */
    public function controllerMiddleware()
    {
        return [];
    }

    /**
     * Get the middleware that should be removed from the route.
     *
     * @return array
     */
    public function excludedMiddleware()
    {
        return (array) ($this->action['excluded_middleware'] ?? []);
    }

    /**
     * Get the dispatcher for the route's controller.
     *
     * @return \Framework\Routing\ControllerDispatcher
     */
    public function controllerDispatcher()
    {
        if ($this->container->bound(ControllerDispatcher::class)) {
            return $this->container->make(ControllerDispatcher::class);
        }

        return new ControllerDispatcher($this->container);
    }

    /**
     * Set the router instance on the route.
     *
     * @param  \Framework\Routing\Router  $router
     * @return $this
     */
    public function setRouter(Router $router)
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Set the container instance on the route.
     *
     * @param  \Framework\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }
}
