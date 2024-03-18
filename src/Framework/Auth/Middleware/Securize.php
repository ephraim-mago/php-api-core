<?php

namespace Framework\Auth\Middleware;

use Closure;
use App\Models\User;
use Framework\Http\Request;
use Framework\Auth\AuthenticationException;
use Framework\Contracts\Core\Application;

class Securize
{
    /**
     * The application instance.
     *
     * @var \Framework\Contracts\Core\Application
     */
    protected $app;

    /**
     * Create a new middleware instance.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

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
        if ($request->bearerToken() && $this->checkToken($request)) {
            return $next($request);
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Determine if the user is logged in to any of the given token.
     *
     * @param \Framework\Http\Request $request
     * @return bool
     */
    protected function checkToken($request)
    {
        $users = User::all();

        foreach ($users as $user) {
            if ($user->tokenCan($request->bearerToken())) {
                $this->app->singleton(User::class, fn () => $user);
                
                $request->setUserResolver(fn () => $user);

                return true;
            }
        }

        return false;
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
