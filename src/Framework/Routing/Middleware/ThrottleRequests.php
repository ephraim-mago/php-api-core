<?php

namespace Framework\Routing\Middleware;

use Closure;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRequests
{
    /**
     * Indicates if the rate limiter keys should be hashed.
     *
     * @var bool
     */
    protected static $shouldHashKeys = true;

    /**
     * Handle an incoming request.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     *
    //  * @throws \Framework\Http\Exceptions\ThrottleRequestsException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        return $this->handleRequest(
            $request,
            $next,
            [
                (object) [
                    'key' => $prefix . $this->resolveRequestSignature($request),
                    'maxAttempts' => $this->resolveMaxAttempts($request, $maxAttempts),
                    'decayMinutes' => $decayMinutes,
                    'responseCallback' => null,
                ],
            ]
        );
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Closure  $next
     * @param  array  $limits
     * @return \Symfony\Component\HttpFoundation\Response
     *
    //  * @throws \Framework\Http\Exceptions\ThrottleRequestsException
     */
    protected function handleRequest($request, Closure $next, array $limits)
    {
        $response = $next($request);

        foreach ($limits as $limit) {
            $response = $this->addHeaders(
                $response,
                $limit->maxAttempts,
                $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts)
            );
        }

        return $response;
    }

    /**
     * Resolve the number of attempts if the user is authenticated or not.
     *
     * @param  \Framework\Http\Request  $request
     * @param  int|string  $maxAttempts
     * @return int
     */
    protected function resolveMaxAttempts($request, $maxAttempts)
    {
        if (str_contains($maxAttempts, '|')) {
            $maxAttempts = explode('|', $maxAttempts, 2)[$request->user() ? 1 : 0];
        }

        if (!is_numeric($maxAttempts) && $request->user()) {
            $maxAttempts = $request->user()->{$maxAttempts};
        }

        return (int) $maxAttempts;
    }

    /**
     * Resolve request signature.
     *
     * @param  \Framework\Http\Request  $request
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return $this->formatIdentifier($user->getAuthIdentifier());
        } elseif ($route = $request->route()) {
            return $this->formatIdentifier($route->getDomain() . '|' . $request->ip());
        }

        throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
    }

    /**
     * Add the limit header information to the given response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $response->headers->add(
            $this->getHeaders($maxAttempts, $remainingAttempts, $retryAfter, $response)
        );

        return $response;
    }

    /**
     * Get the limit headers information.
     *
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @param  \Symfony\Component\HttpFoundation\Response|null  $response
     * @return array
     */
    protected function getHeaders(
        $maxAttempts,
        $remainingAttempts,
        $retryAfter = null,
        ?Response $response = null
    ) {
        if (
            $response &&
            !is_null($response->headers->get('X-RateLimit-Remaining')) &&
            (int) $response->headers->get('X-RateLimit-Remaining') <= (int) $remainingAttempts
        ) {
            return [];
        }

        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if (!is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            // $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
            $headers['X-RateLimit-Reset'] = $retryAfter;
        }

        return $headers;
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int|null  $retryAfter
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        // return is_null($retryAfter) ? $this->limiter->retriesLeft($key, $maxAttempts) : 0;
        return 0;
    }

    /**
     * Format the given identifier based on the configured hashing settings.
     *
     * @param  string  $value
     * @return string
     */
    private function formatIdentifier($value)
    {
        return self::$shouldHashKeys ? sha1($value) : $value;
    }

    /**
     * Specify whether rate limiter keys should be hashed.
     *
     * @param  bool  $shouldHashKeys
     * @return void
     */
    public static function shouldHashKeys(bool $shouldHashKeys = true)
    {
        self::$shouldHashKeys = $shouldHashKeys;
    }
}
