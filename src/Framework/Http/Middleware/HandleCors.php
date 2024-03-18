<?php

namespace Framework\Http\Middleware;

use Closure;
use Fruitcake\Cors\CorsService;
use Framework\Container\Container;

class HandleCors
{
    /**
     * The container instance.
     *
     * @var \Framework\Contracts\Container\Container
     */
    protected $container;

    /**
     * The CORS service instance.
     *
     * @var \Fruitcake\Cors\CorsService
     */
    protected $cors;

    /**
     * Create a new middleware instance.
     *
     * @param  \Framework\Contracts\Container\Container  $container
     * @param  \Fruitcake\Cors\CorsService  $cors
     * @return void
     */
    public function __construct(Container $container, CorsService $cors)
    {
        $this->container = $container;
        $this->cors = $cors;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Closure  $next
     * @return \Framework\Http\Response
     */
    public function handle($request, Closure $next)
    {
        $this->cors->setOptions(
            [
                'paths' => ['api/*', 'sanctum/csrf-cookie'],

                'allowed_methods' => ['*'],

                'allowed_origins' => ['*'],

                'allowed_origins_patterns' => [],

                'allowed_headers' => ['*'],

                'exposed_headers' => [],

                'max_age' => 0,

                'supports_credentials' => false,
            ]
        );

        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);

            $this->cors->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        $response = $next($request);

        if ($request->getMethod() === 'OPTIONS') {
            $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $this->cors->addActualRequestHeaders($response, $request);
    }
}
