<?php

namespace Framework\Core\Http;

use Throwable;
use Framework\Http\Response;
use Framework\Routing\Router;
use Framework\Routing\Pipeline;
use Framework\Contracts\Core\Application;
use Framework\Contracts\Debug\ExceptionHandler;
use Framework\Contracts\Http\Kernel as KernelContract;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var \Framework\Contracts\Core\Application
     */
    protected $app;

    /**
     * The router instance.
     *
     * @var \Framework\Routing\Router
     */
    protected $router;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        // \Framework\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Framework\Core\Bootstrap\LoadConfiguration::class,
        \Framework\Core\Bootstrap\HandleExceptions::class,
        \Framework\Core\Bootstrap\RegisterFacades::class,
        \Framework\Core\Bootstrap\RegisterProviders::class,
        \Framework\Core\Bootstrap\BootProviders::class,
    ];

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        \Framework\Http\Middleware\HandleCors::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        // 'web' => [
        //     \App\Http\Middleware\EncryptCookies::class,
        //     \Framework\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        //     \Framework\Session\Middleware\StartSession::class,
        //     \Framework\View\Middleware\ShareErrorsFromSession::class,
        //     \App\Http\Middleware\VerifyCsrfToken::class,
        //     \Framework\Routing\Middleware\SubstituteBindings::class,
        // ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Framework\Routing\Middleware\ThrottleRequests::class . ':api',
            // \Framework\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * @var array<string, class-string|string>
     *
     * @deprecated
     */
    protected $routeMiddleware = [];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \Framework\Auth\Middleware\Authenticate::class,
        'securize' => \Framework\Auth\Middleware\Securize::class,
        // 'auth.basic' => \Framework\Auth\Middleware\AuthenticateWithBasicAuth::class,
        // 'auth.session' => \Framework\Session\Middleware\AuthenticateSession::class,
        'throttle' => \Framework\Routing\Middleware\ThrottleRequests::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces non-global middleware to always be in the given order.
     *
     * @var string[]
     */
    protected $middlewarePriority = [
        \Framework\Core\Http\Middleware\HandlePrecognitiveRequests::class,
        \Framework\Session\Middleware\StartSession::class,
        \Framework\Routing\Middleware\ThrottleRequests::class,
    ];

    /**
     * Create a new HTTP kernel instance.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @param  \Framework\Routing\Router  $router
     * @return void
     */
    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;

        $this->syncMiddlewareToRouter();
    }

    /**
     * Handle an incoming HTTP request.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Framework\Http\Response
     */
    public function handle($request)
    {
        try {
            $request->enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }

        return $response;
    }

    /**
     * Send the given request through the middleware / router.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Framework\Http\Response
     */
    protected function sendRequestThroughRouter($request)
    {
        $this->app->instance('request', $request);

        $this->bootstrap();

        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->middleware)
            ->then($this->dispatchToRouter());
    }

    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
    }

    /**
     * Get the route dispatcher callback.
     *
     * @return \Closure
     */
    protected function dispatchToRouter()
    {
        return function ($request) {
            $this->app->instance('request', $request);

            return $this->router->dispatch($request);
        };
    }

    /**
     * Sync the current state of the middleware to the router.
     *
     * @return void
     */
    protected function syncMiddlewareToRouter()
    {
        $this->router->middlewarePriority = $this->middlewarePriority;

        foreach ($this->middlewareGroups as $key => $middleware) {
            $this->router->middlewareGroup($key, $middleware);
        }

        foreach (array_merge($this->routeMiddleware, $this->middlewareAliases) as $key => $middleware) {
            $this->router->aliasMiddleware($key, $middleware);
        }
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param  \Throwable  $e
     * @return void
     */
    protected function reportException(Throwable $e)
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the exception to a response.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderException($request, Throwable $e)
    {
        return $this->app[ExceptionHandler::class]->render($request, $e);
    }
}
