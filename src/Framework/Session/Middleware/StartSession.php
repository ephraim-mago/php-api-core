<?php

namespace Framework\Session\Middleware;

use Closure;

class StartSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $next($request);
    }
}
