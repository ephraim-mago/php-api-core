<?php

namespace Framework\Auth\Middleware;

use Closure;
use Framework\Http\Request;
use Framework\Auth\AuthenticationException;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws \Framework\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        return $next($request);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Framework\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Framework\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Framework\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        //
    }
}
